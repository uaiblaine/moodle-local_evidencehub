# S4: the hub page

Stage S4 of the evidence hub plan
(`moodle-local_dimensions/docs/proposals/2026-10-29-evidence-hub/README.md`, row S4, its sections
"Data model and performance" and "Gaps and edge cases", and the S0 measurements in its
`spike/README.md` and `spike/queries.sql`):

> `index.php` (site + category entry, locked context bar), KPI bar (one grouped query), filters
> (template, cohort, status chips, search), `hub_query` two-step grid (own pager, checkboxes,
> default view "needs attention" by expiry), download through `\core\dataformat::download_data()`,
> KPI cache keyed by status generation; report builder datasource. Done when row-level access is
> tested with a category manager with and without `tool_cohortroles`, KPI = grid counts, and the
> spike queries are re-measured on the plugin's real SQL.

D1–D12 are approved (D5: the plan filter is a plan TEMPLATE; its users are the active plans
created from it; individual plans are out of scope; D7: sensitive types hidden without
`viewsensitive`). The contracts of S2 and S3 (`docs/stages/`) still hold. This note settles what
the plan leaves open; each choice is a contract the tests hold.

## Scope

In: `index.php` and its renderables, `local\hub_query`, `local\hub_kpis` with its MUC cache and
generation counter, the category and site entries, the filter preference, the download, the report
builder datasource. Out: the drawer (S5), the matrix view (S6), dossier export (S7; the grid's
checkboxes prepare it), alerts (S8), the Competency hub link (S9). No schema change.

## The base set and row-level access

A **pair** is (worker, competency) where the worker has an ACTIVE plan (`competency_plan.status =
1`, `templateid = T`) from the chosen template T and the competency is one of T's
(`competency_templatecomp`). The worker is a real user (`deleted = 0`); suspended workers are out
by default, a "Include suspended" filter brings them back.

Row-level access, in SQL, never per row in PHP:

- the worker set is cut by
  `api::filter_users_with_capability_on_user_context_sql('moodle/competency:userevidenceview',
  $USER->id, SQL_PARAMS_NAMED, <unique prefix>)` — one `IN` fragment (a site manager gets the
  "> 0" form); a category manager reaches exactly the workers whose user contexts grant it to them
  (the `tool_cohortroles` route);
- the page shows how many of the template's workers are hidden by permissions ("N workers are not
  shown: you cannot view their evidence"), and "0 workers you can view" instead of an empty grid.
  The hidden count is every worker with an active plan from the template outside the cut, with no
  cohort, suspended or search cut: a filter must not tell the viewer anything about hidden workers.

### The effective status (the sensitive rule, in SQL)

A pair whose deciding evidence (`status.metaid` → meta → type `issensitive = 1`) is restricted for
the viewer — the viewer lacks `local/evidencehub:viewsensitive` in that worker's user context (a
second `filter_users_with_capability_on_user_context_sql()` fragment) and is not the worker — has
the **effective status "restricted"** (a code of its own, e.g. `-1`, never stored). Everything the
hub does reads the effective status, never the stored one: the grid shows "Restricted" with no
date, type or issuer; status filters, sorting by expiry and the KPI buckets treat it as its own
value. Otherwise a filter on "Expired" or a KPI over one worker would reveal what the cell hides.
Missing (no status row) has its own code too (0).

## Pieces

### `classes/local/hub_filter.php` (readonly value object, built only through validation)

`templateid` (required), `cohortid` (0 = none), `statuses` (array of effective codes; the default
"needs attention" = every code except valid), `search` (worker name, trimmed, `sql_like` on
firstname and lastname, and on idnumber only when it is among the identity fields the viewer is shown
in the page context, `\core_user\fields::get_identity_fields($context, false)`; case-insensitive,
accent-sensitive as core),
`suspended` (bool), `sort` (`expiry` default, `worker`, `competency`, `status`), `dir`, `page`,
`perpage` (50; allowed 25/50/100). Built from request params by one function that validates every
field (`PARAM_*`, allowlists), and from the stored user preference. The constructor is private, so
a template or cohort can only enter through `from_values()` (which runs `require_template()` /
`require_cohort()`); `with()` changes only statuses, sort, dir, page and perpage, validated again,
and refuses any other key.

### Context and templates

- `index.php?contextid=<id>`: a system context or a course category context, else refused.
  `require_login()`, guests refused, competencies enabled,
  `require_capability('local/evidencehub:view', $context)`.
- **Locked context bar**: entered from a category, the page says so and offers only templates whose
  context is that category or a descendant; from the site, every template. In both cases a template
  is offered and accepted only when the viewer can read it
  (`template::can_read_context($template->get_context())` or core's equivalent) — a `templateid`
  outside the offered set is refused, not silently shown.
- A cohort is accepted only through `cohort_get_cohort($cohortid, $context)` (fleet rule) and
  visible to the viewer; else refused.
- Entries: a "Evidence hub" node in the category's More menu
  (`local_evidencehub_extend_navigation_category_settings()` in `lib.php`, shown when `view` holds
  there) and an `admin_externalpage` under Site administration > Competencies next to S3's policies
  (capability `local/evidencehub:view`). A category manager uses the More menu; the README says so.

### `classes/local/hub_query.php` — the two-step grid

- `page(hub_filter $f): array` → rows of one page. Step 1 sorts only the narrow key (`userid`,
  `competencyid`, sort value) over the base set with the access cut, the cohort, the search, the
  suspended flag and the effective-status filter, and keeps one page (`LIMIT` via `$DB` paging
  args, never interpolated). Step 2 reads that page's details by primary and unique keys: the
  user's name fields and identity fields through ONE `\core_user\fields::for_identity($context,
  false)->with_name()->get_sql()`, the competency, the status row, the deciding meta and its type.
  Rows come back in step 1's order.
- Sort values: expiry → `COALESCE(effectiveexpiry, 0)` with restricted and missing first, then
  userid, competencyid (a total order, so paging is stable); worker → lastname, firstname, userid;
  competency → shortname, competencyid; status → effective code. Every sort ends in the unique key.
- `rows(hub_filter $f): \moodle_recordset` — the same filter and order without a limit, one
  statement with the details joined (the download path; a download may be slow, a page may not).
- Portable SQL only: no `NULLS FIRST`, a named placeholder once per statement (the two capability
  fragments need distinct prefixes), ids cast.

### `classes/local/hub_kpis.php` — the KPI bar and the pager count

- One grouped query over the same base set and cuts (template, cohort, search, suspended — NOT the
  status filter): count per effective code. The pager count of the grid is the sum of the
  selected codes' counts; the page never runs a separate count.
- Readiness: workers whose every pair is valid/due/critical over workers, in the same query or one
  more (S0 Q5 shape).
- Cached in an application MUC cache (`db/caches.php`, `cachedef_hubkpis` lang string), except for a
  filter with a search (free text would grow the cache without bound: counted every time). Key:
  sha1 of templateid, cohortid, suspended, the page context, the viewer's two capability fragments
  (their SQL without placeholder names and params, i.e. the scope, and the viewer id), a signature of
  the template's competencies (core fires no event for them), and the **generation**. The generation is a value in its own small
  application cache (`cachedef_hubgeneration`), replaced by a new random value whenever:
  `status_repository` writes or deletes a status row (one call after its commit), and on core
  events that change the base set (plan created/updated/completed/reopened/deleted, a template's
  competency added/removed, cohort member added/removed, user updated/deleted — verify each event
  class exists in core 5.3 and list the ones used). Never a TTL. A lost generation (cache purge)
  just starts a new one.

### The page

- Renderable `output\hub_page`, templates, one ESM module. Bootstrap 5 only; plugin-scoped CSS in
  `styles.css` with the family tokens `--local-evidencehub-x: var(--dimensions-x, <own chain>)`
  where colour is needed (status pills reuse S3's `status::badge_class()`).
- KPI bar: one tile per effective status (count, share), readiness rate, "workers hidden by
  permissions"; each tile toggles its status chip.
- Filters: template select (required; the page with no template asks for one), cohort
  autocomplete or select, status chips (default "needs attention"), search box, include suspended,
  per-page. A GET form: every view is a URL, shareable and back-button safe. The last filter that
  validated and rendered the grid (never on the download branch) is stored as a user preference (`local_evidencehub_hubfilter`, JSON of the validated filter, never
  raw input) and restored when the page is opened with no filter params.
- Grid: checkbox per row and a "select all on this page", worker (name + identity fields), the
  competency, effective status pill, effective expiry (ISO day; none for restricted, missing or
  never), evidence count, the deciding evidence's type and issuer (count, type and issuer hidden
  when restricted). The
  worker name links to S2's `evidence.php?userid=`. Own pager (core `paging_bar`).
- Download: `index.php?...&download=<format>` with `sesskey`, through
  `\core\dataformat::download_data()` over `hub_query::rows()`, every allowed format, the same
  columns and the same restriction; selected rows only when the checkboxes posted some
  (`userid:competencyid` pairs validated against the filter's set). An event `hub_downloaded`
  (other: templateid, rows) before the stream.

### Privacy

The filter preference is personal data: `get_metadata()` declares it
(`add_user_preference`), `export_user_preferences()` exports it; the provider becomes a
`\core_privacy\local\request\user_preference_provider` too. `provider_test` and the compliance
check stay green.

### Report builder datasource

`classes/reportbuilder/datasource/validity.php` with entities for the status row, the deciding
evidence's details and type, joined to core's user and competency entities. Rules:
- available only when the user holds `local/evidencehub:view` in the system context
  (`datasource::is_available()`, which core checks per user when it loads a report);
- core builds a datasource per user, schedules included (`send_schedule` switches to the creator,
  `schedule\message` to the chosen user or each recipient through `\core\cron::setup_user()`), so
  the hub's rules apply per worker: a base condition with the user's userevidenceview fragment
  (rows only for workers whose user context grants it), and the restriction from the viewsensitive
  fragment, the worker never restricted from their own pair, worked out once in a report-level join;
- a restricted pair reads "Restricted" in status, expiry, evidence count, when it changed, type,
  issuer, dates and verification, in SQL, so the status and type filters cannot pick it out.
Lang strings for every column and filter (`entity:`, `datasource:`).

## Tests

- `tests/local/hub_query_test.php`: the base set (only active plans of T, only T's competencies,
  deleted out, suspended out unless asked); the access cut with a **category manager with and
  without `tool_cohortroles`** (assign through `tool_cohortroles\api` and run its sync): without,
  0 rows and the hidden count = all; with, exactly the cohort's workers; a site manager sees all;
  the effective restricted status in the grid, in the status filter and in the sort (a restricted
  pair never appears under "Expired"; control: with `viewsensitive` it does); paging stable across
  pages (no pair twice, none lost); each sort; search; cohort; template outside the context refused;
  a cohort from another context refused.
- `tests/local/hub_kpis_test.php`: **KPI = grid counts** for every effective code and for "needs
  attention" (sum of tiles = pager count = number of rows across all pages); readiness; the cache
  serves a second call without a query (`perf_get_reads()` difference, fleet note: it counts
  statements) and a status write, a plan created and a cohort member added each change the
  generation and so the numbers.
- `tests/output/hub_page_test.php` + a page `require()` test of `index.php` (as S3 did) for a
  system manager and a category manager; access both directions (`view` in the category: allowed;
  in another category: refused).
- Download: the rows the dataformat receives (test the row builder, not the HTTP stream), selected
  pairs only when posted, restricted cells blank.
- Preference round trip; privacy export of it.
- Datasource: available/unavailable both directions; a restricted pair reads "Restricted".
- Behat `tests/behat/hub.feature` (thin): a manager opens the hub from a category's More menu,
  picks a template, sees the KPI tiles and a row, clicks a status chip.

Mutation spec: `G149`+ for every guard (context level, view capability, template and cohort
acceptance, the access cut, the restricted rule in each of grid / filter / sort / KPI / download /
datasource, the generation bumps, the base-set conditions, the stable tie-break).

## Measurement (main session, after the gate)

Re-measured 2026-10-30 on the S0 seed (10,000 workers × 30 competencies), site manager, template 1,
median ms PostgreSQL/MariaDB. The first S4 SQL joined meta and type for every pair to read
`issensitive`: step 1 needs-attention 411/653, all by expiry 340/632, KPI distribution 336/610,
readiness 366/654 (S0 spike: 134/256, 155/267, 151/260, 179/279). With the flag stored on the status
row (`local_evidencehub_status.issensitive`, written by `status_repository` on every write, filled
by the 2026100504 upgrade step): 172/321, 158/301, 173/269, 160/310. The worker sort orders by
`COALESCE(lastname, '')` and `COALESCE(firstname, '')`: on the bare columns PostgreSQL walked the
lastname index under LIMIT for a viewer with 500 user-context grants (841 ms; 60 ms after), site
manager by worker 175/469. The datasource's restriction reads the same stored flag.


Re-run the S0 seed (`spike/`) with the plugin's real SQL from `hub_query` and `hub_kpis` on both
database families and record the medians beside the spike's in `docs/stages/s4-hub-page.md`.

## Versioning

`version.php` 2026100503, `CHANGELOG.md`, lang en + pt_br sorted in lockstep, README (the hub, its
two entries, what a category manager needs: `view` in the category and `tool_cohortroles` for the
user-context capability; the datasource's audience), the plugin `CLAUDE.md`.

## Review rounds (2026-10-30), folded into S4

The `security-diff-read` pass found nine defects; all are fixed: the report builder showed a
restricted pair's type (column and filter); the evidence count was not masked on a restricted pair
(grid, download, report); the search matched ID numbers the viewer may not see; the datasource had
no per-worker `userevidenceview` cut (it now has one as a base condition, which also holds for
scheduled reports, built as the user they run as); the filter preference was saved before any check
and on the download branch; the hidden-workers count applied the cohort and suspended cuts; the
query classes trusted any filter (the constructor is private now); a searched view grew the KPI cache
(searches are never cached); and no test held the promise that two scopes never share a cached KPI.

The S0 re-measurement (above) then found the plugin's base set two to three times slower than the
spike's, because it joined each pair's deciding evidence and type to read the sensitive flag. The flag
is now stored on the status row (`issensitive`, the plugin's first upgrade step, 2026100504), and the
worker sort reads `COALESCE()` of the names so PostgreSQL stops walking the lastname index under
`LIMIT`.
