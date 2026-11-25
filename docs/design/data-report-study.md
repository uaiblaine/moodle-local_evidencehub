# The hub as a data report

Study for moving the evidence hub's list onto the fleet's data report mould
(`moodle-dev/docs/design-system/components/data-report.md`, D122 to D128). The owner confirmed D128 on
2026-11-23: local_evidencehub is the mould's second client. The reference is enrol_apply's course report
(`moodle-enrol_apply`, branch `MOODLE_503_STABLE`); its pattern is copied, never depended on (AR-D10).
Mockup: `data-report-mockup.html` beside this file. Built in stages E1 to E5 (pull requests 18, 19, 23, 24 and
25, versions 2026100514 to 2026100519): section 4 says what each delivered, section 5 holds the measurements.

This reopens one approved rule of this repository, on the owner's instruction: "the hub grid is two queries,
not a report builder system report" (`CLAUDE.md`, S0: one statement took 0.5 to 0.8 s on MariaDB over 300,000
pairs). The mould keeps what that rule protected: a page is still read in two statements (D122), now over
the report's own SQL.

## 1. The hub before this work

As it stood at stage E0 (version 2026100514). Stage E4 removed the old list: its page, reads, download, figures
cache and filter preference.

| Part | Where | What it did |
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

The line numbers are those of version 2026100514. The restricted rule was written in three SQL places and one PHP
place that concern the list: `hub_query::base_sql()` (which the grid, the status filter, the sort, the figures,
the matrix, the digest, the dossier selection and the readiness web service all read), `hub_download::row()` (the
cells' masking), `datasource/validity.php:100-109` with the entities' `masked()`, and `details::is_restricted()`
for the drawer and the badges. The mutation spec held it once per surface (`docs/stages/s4-hub-page.md:196-198`:
grid, filter, sort, KPI, download, datasource).

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
  them. So the pair set's joins and the restricted join move into one small builder, `local\pairset`, that the
  report assembles itself from and the others wrap as a subquery. The rule is then written once; the report is
  its only reader for everything the list shows. The datasource takes its restricted join from the same
  builder.

### 2.2 A row that is not one table's

enrol_apply pages by the main table's id. A pair has no id: its key is (plan, template competency). The
headless table (`headless_table`, core's `system_report_table` plus the two-statement `query_db()`) therefore
reads the page's two-part keys and the total first, ordered by the reader's sort and then by both parts,
then reads those pairs whole and puts them back in order. Of the two shapes for the second statement, the old
list's (the page's workers crossed with the page's competencies, trimmed in PHP) and an exact list of pairs, the
exact list was built: one `(userid AND competencyid)` term per pair, with the page's workers and competencies also as
two IN lists so that each database has a condition on one table to read an index by
(`headless_table::read_pairs()`). This is the part of the contract the second client proves.

### 2.3 Three web services

Copied from the reference under this plugin's names, each `ajax => true`, each opening with
`validate_parameters()`, `validate_context()` on the context after `hub_access` accepted it and the
template, guests refused, `require_capability('local/evidencehub:view')`; then the report is built, so its
own rules run.

| Service | Takes | Returns |
|---|---|---|
| `local_evidencehub_report_rows` | context, template, scope, search, page, page size, sort, direction, whether to count the facets | the request as applied; `total`; `hidden` (workers the reader may not view); `columns` (the catalogue); `rows` (the pair's key, the typed values the client needs, the inspect and evidence urls, `cells` from `format_row()`, `selectable`); `filters` with options and counts; `presets`; the `preference`; `candecide` (whether the reader may export dossiers, decision 10) |
| `local_evidencehub_report_summary` | context, template, scope, search | the band's figures (section 2.6), cached in `reportsummary` unless searched |
| `local_evidencehub_set_report_preference` | columns, widths or "use the default" | the preference as stored, cleaned |

Core's `core_reportbuilder_set_filters` and `_filters_reset` write the filters. The existing
`local_evidencehub_get_readiness` is untouched.

**Scale is the difference from the reference.** enrol_apply's seed is 10,000 rows; this plugin's is
10,000 workers by 30 competencies, 300,000 rows. The reference tallies its facet counts in PHP over one read
of every record; here that is 300,000 rows a request, so the facets are counted in SQL, grouped (the contract's
"one query per facet, never one per option"; since pull request 24 one grouped statement for every facet whose
own filter is not in force, section 5.1). The summary is grouped SQL too.

### 2.4 Filters move to core's store

The filters of one value from a list become the report's own filter instances, stored by core
(`reportbuilder_user_filter`), offered in the panel with counts: status, cohort (the context's cohorts as
`hub_access` offers them; a searched list above eight), competency, type of evidence, verification and "valid
until" (decision 12). The search and the scope are request parameters, never stored. The sort, the page and the
page size are the request's too.

What that removes: `hub_filter` as the list's filter object, its preference, the GET form, and "every view
is a URL" for the list. What it costs: a filtered view can no longer be sent as a link, except for the
template, which stays in the URL (decision 2).

### 2.5 Columns

The preference `local_evidencehub_report` (one user preference for the site: catalogue keys and widths, beside
the suspended choice of decision 5 and the last template per hub context of decision 2) is read in the report's
`initialise()`, so the page, the web services and the download agree. The fixed columns are the worker (sticky,
with the identity fields the reader may see as its sub-line or as columns, as core lists them) and the
competency: a pair is named by both. Catalogue and sets: decision 6.

### 2.6 The band

Tiles over the reader's rows: workers, ready workers with the rate, pairs needing attention, and the
workers hidden by permissions as the first tile's sub-line. One panel: the pairs by status, each line a
link that sets the status filter (the old tiles, as a distribution). A second panel is proposed (decision
7). A worker's readiness is a fact about all of their pairs, so the workers and ready figures ignore the
scope and the status filter and follow every other criterion; the distribution is the status facet's
counts. The figures start from `hub_kpis`'s statements; the summary is cached in `reportsummary` under the
report's own generation (`reportgeneration`), and the old `hubkpis` cache went with the old list (decision 8).

### 2.7 Narrow screens, no script, "Table view"

Cards under a 576 px container, the same rows and order: the worker, then the competency with the status
label, then "Valid until", then the type of evidence; every other chosen column behind "More". The page's
`<noscript>` holds core's rendering of the report without its filter form, and `?view=table` renders
core's table whole, with core's filter form: the place for the filter kinds the panel does not draw. Core's
download is refused there (the report is not downloadable; the bar's Download is the list's one door, decision 9).
The inspection drawer needs JavaScript; core's table links the worker to their evidence page.

### 2.8 What the mould did not cover here

- **Row selection.** The old list had a checkbox per row for two things: downloading the ticked rows and
  exporting the dossiers of the ticked rows' workers. The mould had no selection when this study was written.
  The download no longer takes ticked rows (decision 9). For the dossiers the Design System gained a selection
  variant and a bulk dock (D129 to D135) on 2026-11-23, and the list follows it (decision 10).
- **A second view of the same data.** The matrix (decision 11).
- **A default that hides rows.** "Include suspended" is off by default; core's filter store has no default
  value (decision 5).
- **The row action is a drawer**, not a page: an AMD `core/modal` subclass opened from React. Stage E3
  proved that call; the fallback would have been the action as a link to the worker's evidence page.

## 3. Decisions

Each with a recommendation. The four the owner named come first.

**The owner accepted every recommendation below on 2026-11-23** (EH-D1 to EH-D15, by these numbers), and
then the three additions decisions 7 and 12 list as "proposed": the tile "Expiring in 30 days", the second
panel "The competencies with most open pairs" and the "Valid until" facet. All three are new figures: stage
E2 added them to the summary and the facet counts, and E5 measured them. Decision 10 was replaced the same
evening; its text below is the decision in force.

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
   filters; E5 measures it both ways. As built: the summary has a cache of its own, `reportsummary`, keyed by
   the reader's criteria and today and retired by `reportgeneration`; the old `hubkpis` went with the old list.
9. **The download.** The mould uses core's `reportbuilder/download.php`. That loses the `hub_downloaded`
   event (a download of personal data, logged today) and the ticked rows. Recommended: the plugin's own
   short entry that builds the same report, checks `can_view() && is_downloadable()` as core's does, fires
   the event and lets the report's table write the file; the ticked-rows download goes (D114: the file
   holds what the list shows). The Design System records the variant. As built (E4): the bar's Download menu
   links to `index.php?download=<format>` (`local\report_download`), which logs `hub_downloaded` with the
   number of rows; core's download of the report stays refused.
10. **Dossiers: row selection and a bulk dock (form A).** Decided by the owner on 2026-11-23, replacing the
    recommendation accepted earlier that day (a More menu on the bar exporting the dossiers of the listed
    workers): the Design System had gained a selection variant and a bulk dock (D129 to D135), and the owner
    chose them over a More menu after the two were drawn side by side. A reader holding `exportdossier` gets a
    box on each row and one on the page's heading. While anything is ticked, a dock under the rows says
    "{n} selected on this page" and how many workers those rows name (a dossier is per worker; the owner kept
    this second figure on 2026-11-24, the Design System's D155), with one
    action, "Export dossiers": the ZIP of readiness dossiers, one per worker of the ticked rows, downloaded at
    once when it is small and prepared in the background when it is larger, as before. The selection belongs
    to the page on screen: another page, page size, scope, filter, search, sort or accounts choice empties
    it. The server takes the posted pairs' workers and keeps only those of the template's pair set the reader
    may view (`dossier_export::selection()`). There is no More menu on the bar. The bar's Download stays the
    table's data in a data format, what the list shows (D114), never the ticked rows. The matrix keeps its
    own box per worker.
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
    designed for. The finding on MariaDB is in section 5.1.
15. **The status labels' tones.** Today's badges are Bootstrap's: compliant success, due soon warning,
    critical danger, expired dark, the two pending ones and restricted secondary
    (`status::badge_class()`). The Design System's status label has no dark tone. Recommended: compliant
    success, due soon info, critical warning, expired danger, pending and restricted neutral, restricted
    with a lock icon; the mockup draws these.

## 4. Stages

One pull request each, every commit and the merge with `[skip ci]`; the gate is the fleet's tier for the
diff (`~/dev/CLAUDE.md`, section 4). A stage that adds a guard adds its mutation gates in the same change,
swept on both database families before the commit (`mdl mutate ... --fast --changed <base>`).

| Stage | Holds | Tier and gate | Delivered |
|---|---|---|---|
| E0 | this study and the mockup | 0: `mdl ci moodle-local_evidencehub --only leftover` | pull request 17, documentation only (2026100514) |
| E1 | the pair-set builder; the system report `hub_pairs` over it; the entities' effective status; `?view=table` rendering core's table for it; `base_sql()` and the datasource reading the builder. The hub's default page is unchanged. A test that the report and `hub_query` list the same pairs, values and order, for a manager, a cohort-role reader and a reader without `viewsensitive` | 3 (SQL, capabilities): every leg, `--behat` | pull request 18, no version change (2026100514) |
| E2 | the headless table and engine (two-statement read on the two-part key, facets grouped in SQL, the summary); the three web services; the column catalogue and preference with its privacy export; scope, search and "include suspended" as base conditions from parameters | 3 (web services, privacy, SQL): every leg | pull request 19, 2026100515 |
| E3 | the React view on the list: shell and bundle, bar, panel, chips, band, grid, cards, pager, the drawer from the row action, the selection variant and its dock (decision 10), the noscript, "Table view"; Behat smoke tests; the browser pass in light, dark, RTL and at phone width | 2, or 3 if a service changes: one full leg with `--behat`, or every leg | pull request 23, 2026100518 (2026100516 and 2026100517 are pull requests 20 to 22, outside this work) |
| E4 | the old list retired: `hub_filter`'s preference deleted by an upgrade step and dropped from the privacy provider, `hub_query::page*()`, the list's template and AMD removed, the download through the report with its event, the dossier export taking the posted selection only, the matrix reading the report's criteria; `db/uninstall.php`; the reader's stored report filters under the Privacy API; the mutation spec retargeted | 3 (upgrade, privacy): every leg, `--behat`; `security-diff-read` first, then an MDL Shield review if the owner asks | pull request 25, 2026100519 |
| E5 | measurement, and the fixes it finds (section 5) | the tier of each fix; the figures in the pull request | measured (section 5.1: the services on 2026-11-23 and 24, the browser and the quiet reruns on 2026-11-25); its fix is pull request 24, no version change (2026100518) |
| E6 | README and its screenshots, `CLAUDE.md` (the "two queries" rule rewritten), `docs/cluster-validation.md`; in moodle-dev, `data-report.md`'s table and what the second client taught | 0 in both repositories | this repository's documentation and screenshots in one pull request; the moodle-dev side in its own |

Cluster-first: every store the report writes is shared (core's filter rows, a user preference, the three MUC
caches `hubgeneration`, `reportgeneration` and `reportsummary`, core's revisioned JS). A cold node rebuilds
nothing; cron does nothing for the report. E4's upgrade changes no table this code reads, so no version gate is
needed. E5 checked the cache on the mini cluster (section 5.1, `docs/cluster-validation.md`).

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

### 5.1 Results

Measured on 2026-11-23 and 2026-11-24 (the browser and two reruns on 2026-11-25) on the seed above: 10,000
workers by 30 competencies, of which 294,000 pairs
are listed (the suspended accounts left out, the default). The three readers: one who reads every worker; a
category manager who reads about 1,200 workers (35,940 pairs; 8,791 workers hidden by permissions) without
`viewsensitive`; the same reader with it. Each cell is p50 / p95 in milliseconds, inside PHP (the web service's
`execute()` and `clean_returnvalue()`); a p95 over its budget is in bold (rows 300 ms, summary 500 ms). Statements
are per call, the range over the readers and the calls. "Warm" repeats a call, so after the first the rows'
total and facets come from the `reportsummary` cache; "cold" replaces the report generation before every call,
so each call computes them, as after a reader changes a criterion. A selection of the cases; each file holds every
case, with the maximum and the answer's size. The raw files are kept with the measuring scripts, outside the
repository; each table names its file.

Statement counts compare only within one database family: on PostgreSQL the count includes a cursor's `FETCH` and
`CLOSE` for a read without a limit, as the scripts' notes say. Within a case they vary by one or two, from core:
`require_login()` updates the user's last access once a minute and a long-lived process reloads the user's
preferences every two minutes; the report's own statements are constant (`CHANGELOG.md`, pull request 24).

#### PostgreSQL 18 on m503

Warm, `bench-m503-round2.md`:

| Case | Every worker | About 1,200 workers | The same, sensitive shown | Statements |
|---|---:|---:|---:|---:|
| Rows, first page of 50, with facets | 94.6 / 152.3 | 135.2 / 177.3 | 137.4 / 150.0 | 27 |
| Rows, first page of 50 | 83.4 / 130.2 | 126.9 / 151.4 | 137.3 / 151.9 | 27 |
| Rows, first page of 100 | 100.0 / 129.8 | 144.1 / 212.5 | 162.2 / 178.6 | 27 |
| Rows, last page | 88.4 / 114.4 | 116.9 / 139.6 | 127.8 / 151.5 | 27 |
| Rows, sort by worker | 48.9 / 61.0 | 98.9 / 112.9 | 112.7 / 121.2 | 27 |
| Rows, sort by status | 79.0 / 135.6 | 120.3 / 129.6 | 141.1 / 180.4 | 27 |
| Rows, scope "All", with facets | 77.5 / 89.1 | 117.6 / 128.4 | **144.2 / 452.1** | 27 |
| Rows, status filter "Expired", with facets | 80.0 / 98.1 | 125.2 / 166.0 | **138.9 / 306.5** | 27-28 |
| Rows, cohort filter, with facets | 74.5 / 82.2 | 121.0 / 142.8 | 138.5 / 149.3 | 27-28 |
| Rows, "Valid until" within 30 days, with facets | 74.6 / 99.8 | 123.0 / 142.7 | 136.2 / 151.4 | 27 |
| Rows, a name search, with facets | 177.3 / 203.2 | 198.2 / 220.4 | 213.8 / 223.7 | 29-30 |
| Summary, uncached | 169.7 / 221.4 | 168.0 / 190.4 | 183.9 / 210.7 | 29-30 |
| Summary, cached | 6.3 / 7.7 | 59.9 / 67.2 | 72.6 / 79.9 | 22-23 |

Cold, `bench-m503-round2-cold.md`:

| Case | Every worker | About 1,200 workers | The same, sensitive shown | Statements |
|---|---:|---:|---:|---:|
| Rows, first page of 50, with facets | 231.1 / 251.2 | 220.3 / 227.5 | 240.7 / 265.9 | 30 |
| Rows, first page of 50 | 114.1 / 123.9 | 169.5 / 176.1 | 188.0 / 209.6 | 29 |
| Rows, first page of 100 | 131.6 / 167.5 | 186.3 / 190.1 | 204.2 / 224.3 | 29 |
| Rows, last page | 122.7 / 152.8 | 158.5 / 166.2 | 174.6 / 188.5 | 29 |
| Rows, sort by worker | 82.4 / 101.2 | 149.2 / 151.9 | 165.2 / 180.2 | 29 |
| Rows, sort by status | 107.0 / 115.2 | 170.5 / 177.7 | 187.5 / 196.5 | 29 |
| Rows, scope "All", with facets | 241.9 / 250.8 | 206.8 / 232.6 | 221.5 / 255.2 | 30 |
| Rows, status filter "Expired", with facets | 204.8 / 232.6 | 245.5 / 286.9 | **271.6 / 313.9** | 31-32 |
| Rows, cohort filter, with facets | 176.0 / 184.3 | 216.0 / 219.8 | **239.2 / 456.0** | 30-31 |
| Rows, "Valid until" within 30 days, with facets | 205.7 / 235.5 | 245.3 / 264.7 | 265.8 / 274.7 | 31 |
| Rows, a name search, with facets | 161.0 / 188.9 | 190.9 / 194.8 | 212.4 / 233.3 | 29-30 |

Within budget at p50 in every case, and at p95 for the reader of every worker and the manager without
`viewsensitive`. The manager with it went over at p95 in two warm cases (scope "All" 452.1, the status filter
306.5) and two cold ones (the status filter 313.9, the cohort filter 456.0), with p50 between 138.9 and 271.6. The
runs shared the machine with other sessions (a load average of 4 to 5.6 in the run notes), and the same cases of
the same reader stayed within budget on the mini cluster below (99.7 to 231.3 at p95). A warm rerun of that reader
alone with the machine quiet (a load average of 2.4, `bench-m503-round3-manager2.md`) put every case within budget:
scope "All" 120.6 / 139.1, the status filter 121.3 / 137.0, the cohort filter 121.3 / 126.0, the name search the
worst rows case at 189.8 p95, the worst summary (a cohort, uncached) 171.0. A cold rerun the same way (a load
average of 2.8 at its start and 4.1 at its end, `bench-m503-round3-manager2-cold-d.md`, on 2026100520) did too: the
status filter 250.1 / 262.4, the cohort filter 219.6 / 227.9, the worst rows case "Valid until" within 30 days at
250.2 / 268.2, the worst summary 181.0. So the four overruns were the machine's load, not the report's. The cold
filtered reads of this reader have the least room of any case on PostgreSQL (a p50 of 220 to 250 ms against 300):
two cold reruns under load each put one or two of them over at p95, a different case each time (`-cold.md` at a
load average of 9.4, `-cold-c.md` with the load rising from 3.5 to 8). The cached summary costs a
manager 60 to 73 ms at p50 against 6 for the reader of every worker: the run notes put it on working out the
manager's reach from their user-context grants on every call, which the summary's cache does not hold.

#### What changed between round one and round two (pull request 24)

Round one ran on the code of E3 (`bench-m503-round1.md`, load average about 8.6 in the run notes): the rows call
was over budget in 21 cases, worst 1608.6 ms at p95 (scope "All" with facets, the reader of every worker).
The plans (`explain-m503-admin-round1.md`, that reader, the first page with facets) showed why: the page's key
statement took 152.6 ms, because it joined every pair to its status row and counted them with a window on every
page turn and sort, and six facet statements each read the whole pair set, 53.2 to 92.4 ms each.

Pull request 24 changed the engine, with no version change (`CHANGELOG.md` has the whole list): the facets whose
own filter is not in force are counted in one grouped statement; the rows' total, the hidden workers and the facet
counts are cached in `reportsummary` under the report generation, so a page turn or a sort sends the page's two
statements and no window count; the summary reads its distribution, expiring pairs and open pairs per competency
in one grouped statement; the competency and its framework are LEFT joins on primary keys, which a database may
drop from a statement that reads neither.

| Case, the reader of every worker, p50 / p95 ms | Round one | Round two, warm | Round two, cold |
|---|---:|---:|---:|
| Rows, first page of 50, with facets | 908.7 / 1182.7 | 94.6 / 152.3 | 231.1 / 251.2 |
| Rows, first page of 50 | 232.4 / 284.4 | 83.4 / 130.2 | 114.1 / 123.9 |
| Rows, sort by status | 305.3 / 593.6 | 79.0 / 135.6 | 107.0 / 115.2 |
| Rows, scope "All", with facets | 1094.9 / 1608.6 | 77.5 / 89.1 | 241.9 / 250.8 |
| Summary, uncached | 253.9 / 393.2 | 169.7 / 221.4 | not run cold |
| Rows cases over budget, every reader | 21 | 2 | 2 |

Round one's rows calls sent 9 to 17 statements and round two's 27 to 32; they do not compare: round one's script reused one report
instance per reader (core's report cache reset does nothing outside tests), so it never counted the statements
that build the report; round two's builds it in every call, as each web service request does. The plans of
round two were not recorded.

#### MariaDB 12.3

A site kept from a CI run of the plugin on MariaDB, with the seed. Warm, `bench-mariadb.md`:

| Case | Every worker | About 1,200 workers | The same, sensitive shown | Statements |
|---|---:|---:|---:|---:|
| Rows, first page of 50, with facets | **467.7 / 532.9** | 126.1 / 150.7 | 137.9 / 143.6 | 19 |
| Rows, first page of 50 | **450.4 / 466.5** | 125.5 / 130.8 | 137.3 / 141.0 | 19 |
| Rows, first page of 100 | **299.4 / 305.3** | 129.0 / 140.0 | 142.7 / 151.3 | 19-20 |
| Rows, last page | **308.9 / 337.3** | 123.4 / 131.7 | 134.3 / 139.7 | 19 |
| Rows, sort by worker | **357.4 / 389.8** | 132.3 / 135.7 | 142.4 / 145.7 | 19 |
| Rows, sort by status | **297.6 / 311.8** | 136.1 / 158.7 | 138.1 / 143.0 | 19-21 |
| Rows, scope "All", with facets | **423.7 / 461.8** | 133.5 / 176.5 | 141.6 / 149.3 | 19 |
| Rows, status filter "Expired", with facets | 244.9 / 257.7 | 120.8 / 131.2 | 132.4 / 140.0 | 19-20 |
| Rows, cohort filter, with facets | 27.1 / 29.9 | 84.6 / 95.3 | 96.7 / 100.5 | 19 |
| Rows, "Valid until" within 30 days, with facets | 240.3 / 242.3 | 122.0 / 128.4 | 132.6 / 137.9 | 19-20 |
| Rows, a name search, with facets | **294.0 / 311.1** | 139.2 / 158.4 | 149.4 / 151.2 | 21 |
| Summary, uncached | **867.9 / 889.0** | 202.5 / 212.3 | 210.6 / 214.1 | 21-22 |
| Summary, cached | 2.0 / 2.2 | 36.5 / 52.3 | 41.4 / 43.0 | 14 |

Cold, `bench-mariadb-cold.md`:

| Case | Every worker | About 1,200 workers | The same, sensitive shown | Statements |
|---|---:|---:|---:|---:|
| Rows, first page of 50, with facets | **983.4 / 1059.8** | **304.5 / 323.3** | **316.1 / 322.4** | 22 |
| Rows, first page of 50 | **542.5 / 550.3** | 214.7 / 231.9 | 225.0 / 234.8 | 21 |
| Rows, first page of 100 | **550.9 / 565.8** | 219.7 / 231.2 | 231.1 / 237.0 | 21-22 |
| Rows, last page | **559.6 / 589.6** | 213.8 / 222.1 | 224.5 / 228.4 | 21 |
| Rows, sort by worker | **606.1 / 616.2** | 221.2 / 226.7 | 233.6 / 249.2 | 21 |
| Rows, sort by status | **550.4 / 570.2** | 217.1 / 232.1 | 226.5 / 230.7 | 21 |
| Rows, scope "All", with facets | **1595.7 / 1618.9** | **337.1 / 349.0** | **345.5 / 356.4** | 22-23 |
| Rows, status filter "Expired", with facets | **1000.5 / 1036.3** | **363.4 / 383.0** | **374.9 / 381.5** | 23 |
| Rows, cohort filter, with facets | **416.2 / 423.5** | 217.7 / 222.3 | 232.2 / 238.0 | 22 |
| Rows, "Valid until" within 30 days, with facets | **994.3 / 1016.2** | **361.3 / 371.7** | **379.9 / 398.9** | 23 |
| Rows, a name search, with facets | **282.9 / 304.1** | 135.2 / 139.7 | 149.2 / 153.8 | 21 |

**Finding, recorded under EH-D14 by the owner's decision of 2026-11-24: no query is rewritten for MariaDB.**
Decision 14 holds the mould's budgets at 300,000 pairs and records what misses as a finding; on MariaDB the reader
of every worker misses them:

- warm, nine rows cases over at p95, from 303.7 (the sort by "valid until", descending) to 532.9 (the first page
  with facets), and
  the uncached summary at 889.0 (896.5 with the scope "All");
- cold, thirteen of the fourteen rows cases over: the first page with facets 983.4 / 1059.8, the scope "All" with
  facets 1595.7 / 1618.9, the status filter with facets 1000.5 / 1036.3;
- the managers of about 1,200 workers stay within budget warm, and go over cold in four of the cases that count
  facets (the first page, the scope "All", the status filter, "valid until"), 322.4 to 398.9 at p95.

The cause is in the plans (`explain-mariadb-admin.md`, the reader of every worker): MariaDB joins by nested loops.
It reads the template's 30 competencies, joins the plans by a block join, then looks up each pair one by one in
the user table (300,000 loops), the status table (294,000) and, for the pairs that need attention, the details and
the competency (111,173 each): the page's key statement took 285.7 ms. Each of the summary's two statements reads
the pair set the same way (418.3 and 429.2 ms in the plans), and its readiness statement still joins the details and the
competency for all 294,000 pairs, though it reads neither: MariaDB did not eliminate those LEFT joins.
PostgreSQL's plan of the readiness statement in round one (`explain-m503-admin-round1.md`, when it still read the
status table twice for the restricted rule) has no join to the details and hash-joins the rest, 65.8 ms in the
call. The old list's own key read measured 321 ms at the median on MariaDB at
this size (`docs/stages/s4-hub-page.md`, "Measurement"), so this is what the pair set costs on MariaDB, not a
regression of the report. PostgreSQL (above) and the mini cluster (below) hold the budgets.

#### The mini cluster

PostgreSQL behind two web nodes, MUC and sessions in Redis; the seed and the script on the first node, 30 calls
per case warm and 20 cold. Warm, `bench-cluster.md`:

| Case | Every worker | About 1,200 workers | The same, sensitive shown | Statements |
|---|---:|---:|---:|---:|
| Rows, first page of 50, with facets | 65.3 / 74.1 | 84.0 / 86.6 | 96.1 / 98.4 | 27 |
| Rows, first page of 50 | 63.4 / 81.4 | 84.1 / 87.9 | 95.4 / 99.0 | 27 |
| Rows, first page of 100 | 68.9 / 72.0 | 89.2 / 92.8 | 102.3 / 107.3 | 27 |
| Rows, last page | 77.4 / 80.4 | 89.7 / 105.1 | 94.3 / 99.2 | 27 |
| Rows, sort by worker | 21.0 / 22.6 | 57.8 / 59.1 | 69.8 / 76.8 | 27 |
| Rows, sort by status | 62.9 / 71.3 | 87.5 / 101.4 | 96.0 / 99.3 | 27 |
| Rows, scope "All", with facets | 62.6 / 69.6 | 87.5 / 100.4 | 94.9 / 105.7 | 27 |
| Rows, status filter "Expired", with facets | 60.4 / 64.5 | 83.8 / 91.0 | 96.9 / 99.7 | 27 |
| Rows, cohort filter, with facets | 63.3 / 68.0 | 83.4 / 85.5 | 96.6 / 98.8 | 27 |
| Rows, "Valid until" within 30 days, with facets | 61.7 / 71.0 | 82.8 / 85.5 | 96.1 / 99.3 | 27 |
| Rows, a name search, with facets | 91.6 / 98.3 | 117.7 / 122.6 | 133.2 / 135.3 | 29 |
| Summary, uncached | 151.4 / 173.5 | 124.2 / 127.0 | 137.6 / 143.2 | 29 |
| Summary, cached | 2.5 / 2.7 | 34.5 / 35.8 | 46.0 / 48.2 | 22 |

Cold, `bench-cluster-cold.md`:

| Case | Every worker | About 1,200 workers | The same, sensitive shown | Statements |
|---|---:|---:|---:|---:|
| Rows, first page of 50, with facets | 226.1 / 247.6 | 181.3 / 191.1 | 194.5 / 201.9 | 30 |
| Rows, first page of 50 | 111.8 / 120.6 | 131.7 / 144.3 | 144.7 / 151.4 | 29 |
| Rows, first page of 100 | 116.6 / 122.4 | 136.7 / 139.9 | 150.4 / 154.5 | 29 |
| Rows, last page | 125.1 / 129.5 | 130.4 / 141.2 | 142.4 / 147.2 | 29 |
| Rows, sort by worker | 72.6 / 74.8 | 106.7 / 120.6 | 117.9 / 138.5 | 29 |
| Rows, sort by status | 111.6 / 113.1 | 134.1 / 142.7 | 140.9 / 143.6 | 29 |
| Rows, scope "All", with facets | 249.7 / 275.4 | 158.8 / 172.8 | 167.9 / 180.6 | 30 |
| Rows, status filter "Expired", with facets | 227.9 / 249.8 | 216.3 / 226.9 | 227.9 / 231.3 | 31 |
| Rows, cohort filter, with facets | 198.1 / 210.5 | 183.3 / 195.7 | 191.0 / 195.4 | 30 |
| Rows, "Valid until" within 30 days, with facets | 229.8 / 239.7 | 219.3 / 226.9 | 230.8 / 240.7 | 31 |
| Rows, a name search, with facets | 90.9 / 107.5 | 126.6 / 134.1 | 134.6 / 143.5 | 29 |

Every case within budget, warm and cold: the worst rows p95 is 275.4 ms (cold, the scope "All" with facets, the
reader of every worker), the worst summary p95 179.1 ms (warm, uncached, the scope "All").

The cache is shared between the nodes (`cluster-cache-check.txt`, five steps, each a process on one node, the
reader of every worker): the first node computes the rows (316 ms, 40 statements) and the summary (148 ms, 29);
the second is served both from the cache (rows 103 ms, 36 statements; summary 3 ms, 22); the first replaces the
generation; the second computes again (rows 316 ms, 39; summary 159 ms, 29); the first is then served the
second's figures (rows 101 ms, 36; summary 3 ms, 22). `docs/cluster-validation.md` has what was not checked.

#### The browser, on m503

Headless Chrome over CDP (`perf_ehr6.mjs`), the hub's list on the seed, the median of five loads per reader at 1x
and at 4x CPU throttling, in milliseconds. The first load counts from navigation start; every other time counts
from the input (the search's from its last key). "Answer" is the first thing the reader sees: the rows dimmed
(`aria-busy`), the panel or the dock shown. `perf-m503-<reader>-pass3.log`, 2026-11-25, on 2026100520 (the list's
code is 2026100519's), the machine at a load average of 4.2 to 6.4.

| Step | Every worker, 1x / 4x | About 1,200 workers, 1x / 4x | The same, sensitive shown, 1x / 4x |
|---|---:|---:|---:|
| First load: HTML | 389 / 752 | 239 / 241 | 263 / 333 |
| First load: skeleton | 438 / 912 | 283 / 351 | 307 / 447 |
| First load: first rows | 659 / 1,729 | 602 / 627 | 621 / 884 |
| First load: band | 492 / 1,330 | 411 / 492 | 648 / 634 |
| Sort: answer, then new rows | 21, 130 / 25, 262 | 19, 187 / 23, 197 | 6, 207 / 23, 249 |
| Next page: answer, then new rows | 20, 119 / 26, 414 | 27, 197 / 38, 213 | 10, 206 / 39, 250 |
| Filters: panel shown | 25 / 35 | 31 / 44 | 24 / 44 |
| Status filter: answer, then new rows | 55, 320 / **214**, 559 | **120**, 421 / 117, 360 | **148**, 449 / 162, 425 |
| Search: slowest keystroke (Event Timing) | 32 / 72 | 32 / 48 | 32 / 56 |
| Search: answer, then new rows, from the last key | 293, 497 / 265, 301 | 289, 757 / 272, 547 | 292, 609 / 273, 794 |
| Row box: dock shown | 32 / 59 | 35 / 49 | 16 / 50 |

Against the budgets:

- **First rows under 1 s**: 602 to 659 ms at 1x, and 468 to 667 ms in an earlier pass on 2026100519 with the
  machine at a load average of 3 to 4 (`-pass1.log`). At 4x the reader of every worker read 1,729 ms in a run
  whose HTML alone took 752 ms as the machine's load rose (771 ms in the earlier pass); the budget is stated at 1x.
- **An answer within 100 ms, 200 ms at 4x**: a sort, a page change, the panel, the dock and a keystroke hold it
  with room (6 to 35 ms at 1x, 23 to 72 ms at 4x). A resize drag commits nothing in React (one commit for one reader at 1x)
  and its slowest input event is 32 ms; no long animation frame at 1x, the longest at 4x 82 ms with 29 ms blocking
  (the status filter). The search answers at its debounce, by design.
- **A stored filter change is over**: the rows dim only when core's `core_reportbuilder_set_filters` has answered
  (`HubView.writeFilters()` writes, then reads), so the first answer is the write's round trip: 55 ms for the
  reader of every worker, 120 and 148 ms for the two managers at 1x, and 117 to 214 ms at 4x. Under a loaded
  machine it reached 175 to 346 ms (`-pass2.log`, a load average of 10). The reference client writes its filters
  the same way (enrol_apply's `ReportView.writeFilters()`), and its profile did not time a filter change apart.
  A finding of this stage, which is prose. The owner had it fixed on 2026-11-25, in 2026100521 here and
  2026112500 in enrol_apply: both regions dim at the press and stay dimmed through the write and the read that
  follows it (the Design System's D156, which has the figure measured afterwards).
- **Not measured**: React's commit times. m503 serves react-dom's production build (`cachejs` on, and the site is
  shared), where the profiler reads no duration; the commits are counted (two for a sort or a page, five for a
  filter change, seven or eight for three keystrokes, three for a row box, ten or eleven for the first load).

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
