# S6: the readiness matrix

Stage S6 of the evidence hub plan
(`moodle-local_dimensions/docs/proposals/2026-10-29-evidence-hub/README.md`, row S6, idea 1, the
mockup's screen 2 "Readiness matrix", and the S0 measurement Q1w in `spike/README.md`: paging by
worker costs 0.5–297 ms in the worst case):

> Workers × competencies grid, sticky first column, legend, same filters. Done when a Behat smoke
> passes and 200 × 25 renders without layout shift.

D1–D12 are approved. The contracts of S2–S5 (`docs/stages/`) still hold, in particular S4's base
set, row-level access and effective status, and S5's drawer. This note settles what the plan leaves
open; each choice is a contract the tests hold.

## Scope

In: a second view of the hub (`index.php?...&view=matrix`) with tabs between the list and the
matrix, the worker-paged query, the matrix renderable and template, the legend, the cells opening
S5's drawer, the selection checkboxes per worker (one `workers[]` checkbox per row, value the user id:
S7's dossiers are one per worker, and posting every pair of a page of 100 workers by more than 10
competencies would pass `hub_download::MAX_PAIRS` and PHP's default `max_input_vars`; the server-side
handling of `workers[]` and the form's action come with S7), and a layout-shift measurement. Out: column sorting by risk
(a later refinement), a matrix download (the list's download covers the same rows), dossiers (S7).
No schema change.

## The view

- Same `hub_filter` (template, cohort, search, include suspended, status chips) and the same access
  rules as the list (S4): the context's `view`, the offered template and cohort, the
  `userevidenceview` cut, the effective status. The view name is part of the validated filter
  (`list` default, `matrix`), stored in the same preference.
- **Rows are workers**, a page of them (25/50/100, default 50), sorted by name (lastname, firstname,
  id) or by "most urgent" (the worker's lowest effective expiry among pairs not valid, then name).
  The status chips keep the workers who have **at least one pair** in the selected statuses
  (default "needs attention", as the list); the row still shows every competency of the template.
- **Columns are the template's competencies**, in the template's own order
  (`competency_templatecomp.sortorder`), each header the competency's shortname (plain spelling,
  `format_string` in its framework context) with its policy kind as a small tag (REG/TEC/BEH, from
  `policy::resolve`), resolved once per column, not per cell.
- **Cells**: the effective status (S4's codes) as a coloured square with its label in
  `visually-hidden` text and a short visible text: days left for due/critical, days since for
  expired, nothing for valid, "—" for missing, a lock glyph for restricted (no number: S4's rule).
  Each cell is a button opening S5's drawer for that pair (`aria-label` "<worker>: <competency>,
  <status>"). Colours from S3's `status::effective_badge_class()`, text colour stated.
- **The first column** (the worker, with the selection checkbox and the identity fields the page
  shows) is sticky (`position: sticky; inset-inline-start: 0`), with an opaque background from the
  family tokens so scrolled cells do not show through. The table scrolls horizontally inside its own
  wrapper; the page never does.
- **Legend** above the table: one entry per effective status with its colour and label, and the
  policy tags explained (REG blocks readiness; TEC and BEH flag it at risk).
- **The worker count** of the pager: `hub_kpis`' readiness worker count when no status chip
  narrows the rows; otherwise one count query over the same worker filter (say which, and measure
  it on the S0 seed shape in the report, or leave it to the main session).
- **No layout shift**: the table is rendered by the server in full (no client-side fill), with
  `table-layout: fixed`, an explicit width for the worker column and a fixed width for the cell
  columns, so nothing reflows after load. Fonts and icons used in cells are already loaded by the
  theme (no late icon font swap: use a text glyph or core's pix icon, not an external font).

## `classes/local/hub_matrix.php`

- `workers(hub_filter $f): array` — step 1, the page of worker ids in order (one statement over
  S4's `base_sql()` grouped by worker, the status filter as a `HAVING` or `EXISTS`, the sort, the
  page through `$DB` limit args). Portable SQL, a named placeholder once per statement.
- `cells(array $userids, hub_filter $f): array` — step 2, every pair of those workers from the same
  base set (effective status and expiry), keyed `userid:competencyid`, plus each worker's name and
  identity fields through `core_user\fields` (one statement).
- `worker_count(hub_filter $f): int` — as above.
- `columns(hub_filter $f): array` — the template's competencies in order with the resolved policy
  kind; it takes the validated filter, so no caller passes a raw template id.

## Tests

- `tests/local/hub_matrix_test.php`: rows are exactly the visible workers (category manager with and
  without `tool_cohortroles`, as S4); the status filter keeps a worker with one matching pair and
  drops one with none (control); each sort, ties by id; paging stable across pages; columns in the
  template's order with the right kind; a restricted pair's cell carries the restricted code and no
  number (control: with `viewsensitive`); cells equal the list's rows for the same filter (the
  matrix and the list never disagree on a pair).
- `tests/output/hub_matrix_page_test.php` + a page `require()` test with `view=matrix`: the tabs,
  the legend, a cell button's `aria-label` and drawer data attributes, names escaped once.
- Behat `tests/behat/matrix.feature`: open the hub, switch to the matrix tab, see a worker row and a
  coloured cell, open the drawer from a cell (S5), and **measure layout shift**: a step in the
  plugin's Behat context that reads the page's `layout-shift` entries
  (`performance.getEntriesByType` / a `PerformanceObserver` with `buffered: true`, ignoring entries
  with `hadRecentInput`) after load and asserts the sum stays at or under 0.01, on a template of
  **200 workers × 25 competencies** seeded through the plugin's Behat generator (extend it with a
  bulk entity if row-by-row is too slow: say what you added).

Mutation spec: `G246`+ for every guard (the access cut and restriction in both statements, the status
filter, the sorts and tie-break, the column order, the restricted cell's number, the count choice,
the view value's validation).

## Versioning

`version.php` 2026100506, `CHANGELOG.md`, lang en + pt_br sorted in lockstep, README (the matrix),
the plugin `CLAUDE.md`.

## Review rounds (2026-10-30), folded into S6

The `security-diff-read` pass found no access or leak defect, and three points, all fixed: the
selection posted one hidden `pairs[]` per cell, which a full page of a template with more than ten
competencies would push past `hub_download::MAX_PAIRS` and PHP's `max_input_vars` (the matrix now
posts `workers[]`, one per row; S7 handles them); the urgency order had no restricted fixture (a
restricted pair now provably sorts as a missing one); and `columns()` took a raw template id (it
takes the validated filter).

The mutation sweep then found the two tie-break gates (G258, G259) held by no test: both database
families returned tied workers in id order without them. `test_every_sort_ends_in_the_worker_id`
reads the ORDER BY of `workers_sql()` for every sort, and `test_paging_is_stable` moves the lowest
ids to the end of PostgreSQL's heap so a missing tie-break reorders the page there too.

Behat measured the matrix on a 200 × 25 seed: 9 scenarios, 141 steps, none skipped; the layout-shift
sum stayed at or under 0.01.
