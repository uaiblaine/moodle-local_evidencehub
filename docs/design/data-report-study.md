# The hub as a data report

Study for moving the evidence hub's list onto the fleet's data report mould
(`moodle-dev/docs/design-system/components/data-report.md`, D122 to D128). The owner confirmed D128 on
2026-11-23: local_evidencehub is the mould's second client. The reference is enrol_apply's course report
(`moodle-enrol_apply`, branch `MOODLE_503_STABLE`); its pattern is copied, never depended on (AR-D10).
Mockup: `data-report-mockup.html` beside this file. Nothing here is implemented.

This reopens one approved rule of this repository, on the owner's instruction: "the hub grid is two queries,
not a report builder system report" (`CLAUDE.md`, S0: one statement took 0.5 to 0.8 s on MariaDB over 300,000
pairs). The mould keeps what that rule protected: a page is still read in two statements (D122), now over
the report's own SQL.

## 1. The hub today

| Part | Where | What it does |
|---|---|---|
| Page | `index.php`, `classes/output/hub_page.php`, `templates/hub_page.mustache` | server-rendered: a GET filter form, status chips, one tile per status, a table with a checkbox per row, core's paging bar, a POST form for the download and the dossiers; 80 lines of AMD (`amd/src/hub_page.js`) for select-all and the drawer |
| Row | `hub_query::base_sql()` (`hub_query.php:81-125`) | a pair (worker, competency): active plans of one template by the template's competencies, LEFT JOIN the status row. A pair with no status row is "missing"; a pair decided by a sensitive evidence is "restricted" for a viewer without `viewsensitive`. Both are computed, never stored |
| Page read | `page_keys()` `:180`, `page_details()` `:209` | two statements: the page's keys in order, then the details of the page's workers by the page's competencies, filtered to the pairs in PHP |
| Access | `hub_access` (context is system or a category, `local/evidencehub:view`, templates and cohorts on offer), `hub_scope` (two SQL fragments from user-context grants: who is listed, whose sensitive evidence is shown) | applied in SQL, never per row |
| Filters | `hub_filter` (template, cohort, search, suspended, statuses, sort, direction, page, page size, view) | every view is a URL; the last one is kept in the user preference `local_evidencehub_hubfilter` (`:66`, `:232`, `:251`) and restored when the hub opens without parameters |
| Figures | `hub_kpis` (distribution by status, workers, ready workers, workers hidden by permissions) | three grouped statements, in the MUC application cache `hubkpis`, retired by the generation in `hubgeneration`; a search skips the cache. The pager's total is the sum of the cached counts: the page runs no count |
| Download | `hub_download` | the filter's rows, or the ticked ones (at most 1,000), in any data format, nine fixed columns, masked for a restricted pair; fires `hub_downloaded` |
| Other views | `hub_matrix` (workers by competencies), `dossier_export` (the ticked rows' workers), `pair_drawer` (the inspection drawer) | the matrix and the dossier selection read `base_sql()` under the same `hub_filter` |
| Report Builder | `classes/reportbuilder/datasource/validity.php` and two entities | a custom-report datasource over the status table (so no missing pairs), with its own copy of the restricted join |

The restricted rule is written, today, in three SQL places and one PHP place that concern the list:
`hub_query::base_sql()` (which the grid, the status filter, the sort, the figures, the matrix, the digest, the
dossier selection and the readiness web service all read), `hub_download::row()` (the cells' masking),
`datasource/validity.php:100-109` with the entities' `masked()`, and `details::is_restricted()` for the drawer
and the badges. The mutation spec holds it once per surface (`docs/stages/s4-hub-page.md:196-198`: grid,
filter, sort, KPI, download, datasource).

## 2. What changes

### 2.1 One report class

A system report, `\local_evidencehub\reportbuilder\local\systemreports\hub_pairs`, becomes the single
definition of the list's rows, columns, access, restriction, formatting and filters.

- **The object** is a context and a template. The report is created for the hub's context (system or
  category) with the template's id as its item, so core keeps one set of stored filters per reader, context
  and template. Unlike enrol_apply, the context cannot be derived from the item: every web service takes
  both, and `hub_access` validates the pair (context level, `view`, the template on offer there).
- **`can_view()`** is `hub_access`'s rule, as a static `may_view()` the preference writer can call without
  building the report.
- **Main table** `competency_plan`, joined to the user (not deleted), to `competency_templatecomp` and, LEFT,
  to the status row, its deciding evidence and type. Base conditions: the template, an active plan, and
  `hub_scope`'s view fragment. These are `base_sql()`'s joins and conditions, moved.
- **The restriction** stays one SQL condition, the datasource's shape: the status row joined a second time
  only when it is restricted for the reader, so each capability fragment's placeholders appear once however
  many columns read it. The entities already take that condition (`validity`, `evidence_details`:
  `masked()`), so columns, filters, sorting, the facet counts, the band and the download all read the masked
  value.
- **The entities grow**: the status column and filter must read the effective status with "missing"
  (`COALESCE(status, 0)`), which the datasource never needed because its main table is the status row.
- **What stays outside the report.** The matrix, the dossier selection, the weekly digest and the readiness
  web service read the same pair set under other shapes or other readers, and a system report cannot serve
  them. So the pair set's joins and the restricted join move into one small builder
  (`local\pairset`, say) that the report assembles itself from and the others wrap as a subquery. The rule is
  then written once; the report is its only reader for everything the list shows. The datasource takes its
  restricted join from the same builder.

### 2.2 A row that is not one table's

enrol_apply pages by the main table's id. A pair has no id: its key is (plan, template competency). The
headless table (`headless_table`, core's `system_report_table` plus the two-statement `query_db()`) therefore
reads the page's two-part keys and the total first, ordered by the reader's sort and then by both parts,
then reads those pairs whole and puts them back in order. How the second statement names the pairs is the
first thing stage E2 measures: today's shape (the page's workers crossed with the page's competencies,
trimmed in PHP, measured under 300 ms) or an exact list of pairs. This is the part of the contract the
second client proves.

### 2.3 Three web services

Copied from the reference under this plugin's names, each `ajax => true`, each opening with
`validate_parameters()`, `validate_context()` on the context after `hub_access` accepted it and the
template, guests refused, `require_capability('local/evidencehub:view')`; then the report is built, so its
own rules run.

| Service | Takes | Returns |
|---|---|---|
| `local_evidencehub_report_rows` | context, template, scope, search, page, page size, sort, direction, whether to count the facets | the request as applied; `total`; `hidden` (workers the reader may not view); `columns` (the catalogue); `rows` (the pair's key, the typed values the client needs, the inspect and evidence urls, `cells` from `format_row()`); `filters` with options and counts; `presets`; the `preference` |
| `local_evidencehub_report_summary` | context, template, scope, search | the band's figures (section 2.6) |
| `local_evidencehub_set_report_preference` | columns, widths or "use the default" | the preference as stored, cleaned |

Core's `core_reportbuilder_set_filters` and `_filters_reset` write the filters. The existing
`local_evidencehub_get_readiness` is untouched.

**Scale is the difference from the reference.** enrol_apply's seed is 10,000 rows; this plugin's is
10,000 workers by 30 competencies, 300,000 rows. The reference tallies its facet counts in PHP over one read
of every record; here that is 300,000 rows a request, so the facets are counted in SQL (one grouped
statement per distinct WHERE, the contract's "one query per facet, never one per option"). The summary is
already grouped SQL (`hub_kpis`).

### 2.4 Filters move to core's store

The filters of one value from a list become the report's own filter instances, stored by core
(`reportbuilder_user_filter`), offered in the panel with counts: status, cohort (the context's cohorts as
`hub_access` offers them; a searched list above eight), type of evidence, and others the owner picks
(decision 12). The search and the scope are request parameters, never stored. The sort, the page and the
page size are the request's too.

What that removes: `hub_filter` as the list's filter object, its preference, the GET form, and "every view
is a URL" for the list. What it costs: a filtered view can no longer be sent as a link, except for the
template, which stays in the URL (decision 2).

### 2.5 Columns

The preference `local_evidencehub_report_columns` (one user preference for the site: catalogue keys and
widths) is read in the report's `initialise()`, so the page, the web services and the download agree. The
fixed columns are the worker (sticky, with the identity fields the reader may see as its sub-line or as
columns, as core lists them) and the competency: a pair is named by both. Catalogue and sets: decision 6.

### 2.6 The band

Tiles over the reader's rows: workers, ready workers with the rate, pairs needing attention, and the
workers hidden by permissions as the first tile's sub-line. One panel: the pairs by status, each line a
link that sets the status filter (today's tiles, as a distribution). A second panel is proposed (decision
7). A worker's readiness is a fact about all of their pairs, so the workers and ready figures ignore the
scope and the status filter and follow every other criterion; the distribution is the status facet's
counts. `hub_kpis`'s statements and its cache are the starting point (decision 8).

### 2.7 Narrow screens, no script, "Table view"

Cards under a 576 px container, the same rows and order: the worker, then the competency with the status
label, then "Valid until", then the type of evidence; every other chosen column behind "More". The page's
`<noscript>` holds core's rendering of the report without its filter form, and `?view=table` renders
core's table whole, with core's filter form and download: the place for the filter kinds the panel does
not draw. The inspection drawer needs JavaScript; core's table links the worker to their evidence page.

### 2.8 What the mould does not cover here

- **Row selection.** The hub's list has a checkbox per row for two things: downloading the ticked rows and
  exporting the dossiers of the ticked rows' workers. The mould has no selection (decisions 9 and 10).
- **A second view of the same data.** The matrix (decision 11).
- **A default that hides rows.** "Include suspended" is off by default; core's filter store has no default
  value (decision 5).
- **The row action is a drawer**, not a page: an AMD `core/modal` subclass opened from React. Stage E3
  starts by proving that call; the fallback is the action as a link to the worker's evidence page.

## 3. Open decisions

Each with a recommendation. The four the owner named come first.

**The owner accepted every recommendation below on 2026-11-23** (EH-D1 to EH-D15, by these numbers), and
then the three additions decisions 7 and 12 list as "proposed": the tile "Expiring in 30 days", the second
panel "The competencies with most open pairs" and the "Valid until" facet. All three are new figures: stage
E2 adds them to the summary and the facet counts, and E5 measures them.

1. **The filters stored as a preference today.** Recommended: no migration. The upgrade deletes every
   `local_evidencehub_hubfilter` row and the privacy provider stops declaring it; readers start on the
   default view. The plugin is at `v5.3-r0`, alpha, with no release. Alternative: an upgrade step that
   writes each reader's cohort and status into core's store, which needs the report's persistent rows to
   exist first.
2. **The template and the URL.** Recommended: the template stays a URL parameter (`?contextid=&templateid=`),
   chosen from a select beside the page's heading; the last template a reader opened in a context is
   remembered in the new preference, so the hub opens on it. Filters are stored per template. Alternative:
   one set of filters for every template (the report's item 0), so a cohort carries over when the template
   changes.
3. **The scope.** Recommended: a segmented control "Needs attention" (today's default: every status but
   compliant) and "All", a request parameter, with the count in each cell.
4. **The status filter: one value or several.** Today several statuses can be ticked. Core's select filter
   holds one. Recommended: one value, as the mould's facets; with the scope it covers the common views.
   Alternative: a filter class of this plugin's that holds a set (enrol_apply wrote one for groups).
5. **"Include suspended".** Recommended: a request parameter like the scope, drawn as a two-option group in
   the panel ("Accounts": "Active only", "Include suspended"), kept in the column preference so the
   download follows it. Alternative: drop the option and always exclude suspended accounts.
6. **Columns and sets.** Recommended catalogue, the default set marked: competency (fixed), status,
   valid until, evidence count, type of evidence, issuer (these five are today's and the default),
   then competency ID number, framework, issue date, the evidence's own expiry date, verification, last
   change. Sets (D127): "Validity" (status, valid until, evidence count, last change), "Deciding evidence"
   (status, type, issuer, issue date, verification). Page sizes 30, 50 and 100 as the mould (today 25, 50,
   100), default 50.
7. **The band.** Recommended: the three tiles and the status distribution of section 2.6; no compact band
   (AR-D17 hid course-wide figures from a narrowed reader, and here every figure is already over the
   reader's own workers). Proposed, the owner's call: a tile "Expiring in 30 days" and a second panel "The
   competencies with most open pairs" (the five worst), both new figures.
8. **The figures' cache.** The mould computes the summary per request and builds a cache only when a
   profile asks. This plugin already has one, validated on the mini cluster, built because the distribution
   took 173 to 269 ms uncached at 300,000 pairs. Recommended: keep it, its key gaining a hash of the stored
   filters; E5 measures it both ways.
9. **The download.** The mould uses core's `reportbuilder/download.php`. That loses the `hub_downloaded`
   event (a download of personal data, logged today) and the ticked rows. Recommended: the plugin's own
   short entry that builds the same report, checks `can_view() && is_downloadable()` as core's does, fires
   the event and lets the report's table write the file; the ticked-rows download goes (D114: the file
   holds what the list shows). The Design System records the variant.
10. **Dossiers without ticked rows.** Recommended: a More menu on the bar (the kebab, only with
    `exportdossier`) with "Export dossiers of the listed workers": the workers of the rows under the
    criteria in force, refused above the configured maximum with a sentence saying so; the matrix keeps its
    own checkboxes. Alternative: a selection column in the grid, which the Design System would have to
    decide first.
11. **The matrix.** Recommended: it stays a server-rendered page (`?view=matrix`), reached from the View
    menu as a link after the separator, beside "Table view"; it reads the template from the URL and the
    reader's stored filters through the report, so the list and the matrix agree. Its redesign is not part
    of this work.
12. **More facets.** Recommended now: competency (a searched list), type of evidence, verification.
    Proposed: "Valid until" as one choice (expired; within 30, 60 or 90 days), in core's number filter's
    format. Cohort options carry counts only if E5 shows the grouped count is cheap; otherwise the cohort
    list is drawn without counts, as the mould allows.
13. **The search.** Today it also matches the ID number when the reader is shown it. The mould searches
    fields every reader may see (AR-D15) and lists identity fields as open. Recommended: the worker's name
    only, until the mould decides.
14. **The budgets at this size.** The mould's budgets are stated at 10,000 rows. Recommended: hold the same
    figures at 300,000 pairs and record what misses as a finding, since that is the size this plugin was
    designed for.
15. **The status labels' tones.** Today's badges are Bootstrap's: compliant success, due soon warning,
    critical danger, expired dark, the two pending ones and restricted secondary
    (`status::badge_class()`). The Design System's status label has no dark tone. Recommended: compliant
    success, due soon info, critical warning, expired danger, pending and restricted neutral, restricted
    with a lock icon; the mockup draws these.

## 4. Stages

One pull request each, every commit and the merge with `[skip ci]`; the gate is the fleet's tier for the
diff (`~/dev/CLAUDE.md`, section 4). A stage that adds a guard adds its mutation gates in the same change,
swept on both database families before the commit (`mdl mutate ... --fast --changed <base>`).

| Stage | Holds | Tier and gate |
|---|---|---|
| E0 | this study and the mockup | 0: `mdl ci moodle-local_evidencehub --only leftover` |
| E1 | the pair-set builder; the system report `hub_pairs` over it; the entities' effective status; `?view=table` rendering core's table for it; `base_sql()` and the datasource reading the builder. The hub's default page is unchanged. A test that the report and `hub_query` list the same pairs, values and order, for a manager, a cohort-role reader and a reader without `viewsensitive` | 3 (SQL, capabilities): every leg, `--behat` |
| E2 | the headless table and engine (two-statement read on the two-part key, facets grouped in SQL, the summary); the three web services; the column catalogue and preference with its privacy export; scope, search and "include suspended" as base conditions from parameters | 3 (web services, privacy, SQL): every leg |
| E3 | the React view on the list: shell and bundle, bar, panel, chips, band, grid, cards, pager, the drawer from the row action, the More menu, the noscript, "Table view"; Behat smoke tests; the browser pass in light, dark, RTL and at phone width | 2, or 3 if a service changes: one full leg with `--behat`, or every leg |
| E4 | the old list retired: `hub_filter`'s preference deleted by an upgrade step and dropped from the privacy provider, `hub_query::page*()`, the list's template and AMD removed, the download through the report with its event, the dossier action without ticked rows, the matrix reading the report's filters; the mutation spec retargeted | 3 (upgrade, privacy): every leg, `--behat`; `security-diff-read` first, then an MDL Shield review if the owner asks |
| E5 | measurement, and the fixes it finds (section 5) | the tier of each fix; the figures in the pull request |
| E6 | README and its screenshots, `CLAUDE.md` (the "two queries" rule rewritten), `docs/cluster-validation.md`; in moodle-dev, `data-report.md`'s table and what the second client taught | 0 in both repositories |

Cluster-first: every store the report writes is shared (core's filter rows, a user preference, the two MUC
caches, core's revisioned JS). A cold node rebuilds nothing; cron does nothing for the report. E4's upgrade
changes no table this code reads, so no version gate is needed. E5 checks all of it on the mini cluster.

The stacks m503 and m503b and the mini cluster mount this checkout: before any `mdl upgrade`, `mdl purge` or
cluster command, the other sessions are asked.

## 5. Measurement (E5)

After the code, on a seed of the plugin's real shape, as enrol_apply's R6. Scripts in
`moodle-dev/data/evidencehub-demo/` (not versioned), written from `enrol-apply-demo/seed_r6.php`,
`bench_r6.php` and `perf_r6.mjs`.

- **Seed**: one template of 30 competencies in one framework; 10,000 workers with an active plan, so
  300,000 pairs; status rows for about 85 % of them in a realistic spread (compliant the most, then due,
  critical, expired, awaiting verification), the rest missing; twelve evidence types, two sensitive; 40
  cohorts in a category tree; 2 % of the accounts suspended. Readers: an administrator; a category manager
  who sees about 1,200 workers through `tool_cohortroles` and lacks `viewsensitive`; the same with it.
- **Each web service**, 50 calls after two warm-ups, core's report cache reset between calls, for each
  reader: p50 and p95 wall time and the statements per call. Rows: the first page of 50 and of 100, the
  last page, a sort by worker, by valid until and by status, the scope "All", a status, a cohort, a name
  search, with and without the facet counts. Summary: cached and uncached. On PostgreSQL 18 and MariaDB
  12.3 on m503, then on the mini cluster.
- **Plans**: the page's key read, the page's detail read and the summary under `EXPLAIN ANALYZE` on both
  families, PostgreSQL's JIT counted apart; the two shapes of section 2.2 side by side.
- **Browser**, headless Chrome over CDP, median of five, at 1x and 4x CPU: the first load (skeleton, band,
  first rows), a filter, a sort, a page change (time to dim, time to paint), a keystroke in the search, a
  resize drag, React's commit times for a page of 100.
- **Budgets** (AR-D16, `data-report.md`): the rows call under 300 ms at p95 and the summary under 500 ms; a
  constant number of statements per call; first rows painted under 1 s after navigation; an interaction
  answered within 100 ms and under 200 ms at 4x. Each figure against its budget in the pull request; one
  over budget is a finding, fixed in this stage or recorded with the owner's decision.

## 6. What the reference already learned

Carried over so this client does not find them again (enrol_apply's study, "R6 status", and its
`CLAUDE.md`):

- Core keeps one report instance per reader per request: every call is its own request (`fetchOne`), the
  preference writer never builds the report, and no alias is carried between instances.
- `core_reportbuilder_set_filters` replaces the reader's whole set: a change rewrites every stored value.
- A sort or filter needs SQL: a column drawn by a callback alone is neither. The masked values here are SQL
  already.
- Parameter names for conditions added to the report come from `database::generate_param_name()`.
- The noscript holds core's table without its filter form.
- A page's last sort key must be unique, or tied rows land on two pages or on none: here both parts of the
  pair's key.
- Core's single read computes every column of every row before the page; "Table view" keeps that cost.
- The bundle is built by the fleet's step after core's `grunt esm`; a test compares it with its sources.
