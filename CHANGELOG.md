# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## Unreleased

### Added

- Version 2026100520: the validity policy in `local_dimensions`' Competency hub. A listener of
  `\local_dimensions\hook\competency_panel_sections` (`local\policy_section`) adds a "Validity policy" section to
  the panel of the selected competency: the rows of the Validity policies page and who sets the policy (the
  competency, the framework or the default), to a holder of `view` or `managepolicies` in the framework's context;
  "Edit policy" to `policies.php` for `managepolicies` only. The competency's own framework is read and a
  hook naming another gives nothing. Three statements a panel; no dependency on `local_dimensions`, whose hook
  class is named in `db/hooks.php` only. Gates G550 to G556.
- The hub's pairs of a template as core's system report, `hub_pairs`: the hub's own rows, values, restriction
  and default order (restricted and missing first, then the earliest day), with filters by status ("missing"
  included), cohort, type of evidence and the worker's name. `index.php?view=table` renders it with a link back to
  the list, and the hub's tabs gain a "Table view" link to it; the hub's list, matrix, download and dossier export
  are unchanged. The pair set and its restricted rule are now written once (`local\pairset`), read by the hub's
  base set, the custom-report datasource and the new report.
  The report refuses a guest account and a report built with a `download` parameter, and its identity columns are
  the hub's own: the user table's fields the reader may see, never a custom profile field named in `showuseridentity`.
- Version 2026100515: the pairs report read headlessly, for the hub's coming React view. Three AJAX web services,
  each behind the hub's own door (the context and the template both checked, guests refused, `view` required):
  `local_evidencehub_report_rows` (a page of pairs in two statements on the pair's two-part key, the columns, the
  filters with their counts grouped in SQL, the presets, the reader's grid, and whether the reader may select rows
  for the dossier export, `candecide` and each row's `selectable`: the export's own rule, `exportdossier`), `local_evidencehub_report_summary`
  (workers, ready workers and the rate, pairs, pairs needing attention, pairs expiring in 30 days, the pairs per
  status, the five competencies with most open pairs, and the workers hidden by permissions; cached in the new
  application caches `reportsummary` and `reportgeneration`, unless searched) and `local_evidencehub_set_report_preference`. The report gains a column catalogue
  (the reader's choice, in the new user preference `local_evidencehub_report`, declared and exported by the
  privacy provider), the presets "validity" and "evidence", the filters competency, verification and "valid until"
  (expired, or within 30, 60 or 90 days), page sizes 30, 50 and 100, and three request parameters that can only
  narrow it: the scope (needing attention or all), a search by the worker's name, and whether suspended accounts
  are listed. The hub's page is unchanged.
- Version 2026100518: the hub's list of a template is the fleet's data report, drawn in React (Moodle 5.3; the
  bundle `js/esm/build/bundle.js` with its source manifest, built from `js/esm/src`). A band (workers, with those
  hidden by permissions; ready workers and the rate; pairs needing attention; pairs expiring in 30 days; the pairs
  by status and the competencies with most open pairs, each line setting its filter); a bar with the scope
  ("Needs attention" or "All", each with its count), the count, Filters (status, cohort, competency, type of
  evidence, verification and valid until, with their counts, stored in core's report filters, and "Accounts",
  active only or including suspended accounts, kept in the reader's preference), Columns (the catalogue, the sets
  "Validity" and "Deciding evidence", the default columns, the column widths), View (automatic, table or cards,
  then the matrix and core's table view) and a search by the worker's name; a grid with sortable and resizable
  columns or cards; a pager of 30, 50 or 100 pairs. A reader who may export dossiers selects pairs on the page
  and exports their workers' dossiers from a dock under the rows, through the hub's existing export; "Inspect"
  opens the existing drawer. Without JavaScript the page holds core's table. The hub opens on the reader's last
  template in that place while it is still offered. The matrix, the table view, the download and the dossier
  export's server side are unchanged; the old list's code stays until the next stage removes it.
- Version 2026100519: the list's bar gains Download, a menu of the site's enabled formats, each a link to the hub's
  own download (`index.php?download=<format>`, GET with the sesskey): the pairs report's own table under the
  criteria in force (the reader's stored filters, the scope, the search, the sort, the suspended choice, the chosen
  columns), a restricted pair masked as in the list, logged with `hub_downloaded` once, with the number of rows the
  file holds. Core's report download stays refused: the report is not downloadable, and refuses a `download`
  parameter. Offered once the rows have arrived and while there are any.
- The plugin's uninstall (`db/uninstall.php`) removes what core's leaves: the pairs report's persistents with every
  reader's stored filter values, and the plugin's user preferences.
- The privacy provider declares, finds, exports and deletes a reader's stored filter values of the pairs report
  (`reportbuilder_user_filter` rows of this plugin's reports, which core's provider leaves out for a system report),
  in the reader's own user context, with the template and the hub context named.
- Four fixes to the list's download and the matrix: a request that names core's dynamic table service
  (`info=core_table_get_dynamic_table_content`) no longer leaves the table without its report, which ended the
  download and the matrix in a fatal error; a statement's parameter arrays that share a name throw instead of one
  silently keeping the other's value; `index.php` logs the visitor in before it looks the context up, so a visitor not
  logged in cannot tell an existing context id from a missing one; and deleting an account removes the reader's stored
  filter values of the plugin's reports, which the privacy finder could no longer reach once the user context is gone.

### Changed

- The matrix reads the list's criteria (decision 11): its rows are the workers of the pairs report's rows under the
  reader's stored filters, the scope (`scope`, "Needs attention" by default; the list's View link passes its own)
  and the suspended choice, each showing every competency of the template. Its own filter form, status chips, tiles
  and tabs are gone; the page has the list's template picker, a line saying which criteria are in force with a link
  back to the list, the legend, the matrix, its pager and its dossier export, which returns to it.
- The dossier export takes the selection only: the posted workers are kept when they belong to the template's pair
  set as the reader may view it, suspended accounts as the reader's preference lists them, and nothing else (no
  status, cohort or search). The limits and refusals are unchanged; after the export the hub returns to the list or
  the matrix the post came from.
- A pair with a status and no expiry (valid with no end) sorts as the least urgent by "valid until": after every
  dated pair ascending, first descending, in the list's sort and the hub's pair set (the matrix's urgency); a
  restricted or a missing pair still sorts as the most urgent. The "valid until" filter, its counts and "Expiring in
  30 days" still read no day for it.
- At the narrow step the scope's cells keep their words on one line; "Pairs by status" leaves out a status with no
  pair unless it is the status filter in force.

### Removed

- The old list of the hub: its page (`output\hub_page`, `templates/hub_page.mustache`), its reads
  (`hub_query::page()`, `page_keys()`, `page_details()`, `rows()`), its download of ticked rows (`hub_download`; the
  posted pairs' parser moved to `dossier_export::parse_pairs()`), its figures (`hub_kpis::get()`, `compute()`,
  `pager_count()` and the application cache `hubkpis`), and its filter's request and preference parsing
  (`hub_filter` keeps the pair set's cuts: template, cohort, search, suspended). The user preference
  `local_evidencehub_hubfilter` is deleted by the upgrade (decision 1: no migration) and no longer declared.

- The pairs report's headless rows and summary read the pairs fewer times. Measured on the EHR6 seed (10,000 workers
  by 30 competencies, 294,000 pairs, PostgreSQL 18), a rows call with facets took 909/1183 ms at p50/p95 for a reader
  of every worker, in up to 17 statements that varied between calls: six facet statements each scanned the whole pair
  set (53 to 92 ms each), and the page's key statement joined every pair to its status row and counted them with a
  window spilled to disk (150 to 250 ms) on every page turn and sort.
  - The facets whose own filter is not in force (status, competency, type, verification, valid until) are counted
    in one statement grouped by their masked fields and added up in PHP; the valid-until windows are grouped by the
    narrowest window a pair falls in and summed up. A facet whose filter is in force, and the cohort, keep their own.
  - The rows' total (the shared facet read's sum, or a count of its own with no column and no window), the hidden
    workers and the facet counts are kept in the `reportsummary` cache under the reader's scope and every criterion
    that cuts the rows (stored filters, status included, the scope, suspended accounts, today, the cohorts offered),
    retired by the report generation; neither a search nor a stored name filter with a value is kept (each value typed
    would add an entry). A page turn or a sort sends the page's two statements.
  - The summary reads its distribution, expiring pairs and open pairs per competency in one grouped statement.
  - The report joins the competency and its framework as LEFT joins on their primary keys, which list the same pairs
    (core refuses to delete a competency or framework a template uses) and let each database drop them from
    statements that read neither; for a reader who holds viewsensitive over every user, the report no longer reads
    the status table a second time for the restricted rule, which is false for every pair.
  - The summary's cache key also holds the cohorts offered, so hiding a stored cohort retires its figures.
  - A generation replaced inside a transaction (an observer, or a recomputation nested in a caller's) is replaced
    once more when the request ends, so a reader arriving before the commit cannot leave pre-commit figures under
    the new generation.
  - A page that comes back empty and counts again now answers the fresh facet counts, not the cached ones. A pair
    whose competency or framework row is missing in a damaged database keeps its row, with no name, instead of
    aborting the page.
  - The statement counts that varied between calls of one case came from core, not the report: `require_login()`
    updates the user's last access once a minute, and a long-lived process reloads `$USER`'s preferences every two
    minutes. The report's own statements are constant.
  - Statements per call for a manager, measured in the PHPUnit suite on PostgreSQL and MariaDB alike: the first page
    with facets 19 to 16 (of which three read the pairs, from eight), the same call again 19 to 13 (one reads the
    pairs, from eight), a page turn or a sort 13 (one reads the pairs, with no window), a search with facets 19 to
    15, the summary 17 to 15 (two read the pairs, from four), the cached summary 8.
- The plugin's maturity is BETA (was ALPHA).
- Version 2026100512: the code comments were audited against Moodle's guidance and the code they describe: 30
  that contradicted their code are corrected, and planning and environment references are gone, from the
  comments and from this changelog. No code changed; `amd/build/drawer.min.js.map` is rebuilt for its source's
  JSDoc.

- Version 2026100511: a new capability, `local/evidencehub:verifydossier` (system; guest and authenticated user
  by default), decides who may open a dossier's public verification page, as `tool/certificate:verify` does for
  certificates. A visitor who is not logged in is checked as the guest role and, refused, is asked to log in; a
  logged-in user without it is refused. The default keeps the page open to anyone holding the code.
- Version 2026100511: the verification page of a revoked dossier states the revocation and its date only: the
  snapshot (masked name, template, verdict, competencies, code, PDF fingerprint) and, under `verifylive`, today's
  statuses are no longer shown.
- Version 2026100510: the site setting `local_evidencehub/readinessbasis` chooses which readiness verdict
  readers get from the dossier PDF, the public verification page and the web service. `real` (default, the
  previous behaviour) states the worker's real readiness; `shown` states the readiness over what the reader is
  shown, so a competency restricted to that reader counts as not met. A dossier's snapshot now keeps both
  verdicts (`readiness` and `readinessshown`); under `shown` a snapshot issued before this version has no
  `readinessshown` and its page states no verdict.
- Version 2026100510: the plugin's three admin entries (Validity policies, Evidence hub, Readiness dossiers)
  are added to the `local_dimensions` category when it is already in the admin tree, and to `competencies`
  otherwise (`local\admin_tree::parent_category()`). Plugins load their settings in order of their localised
  names, so the category is found where "Competency Dimensions" sorts before "Evidence hub".

### Fixed

- Version 2026100517: a visitor whom an authentication plugin logs in on the way to the login page (single
  sign-on through its `pre_loginpage_hook`) reads the dossier when the account may verify; the page used to
  refuse it without reading the right again. The page's whole decision is now `dossier::require_verify()`.
- Version 2026100516: the public verification page sends the guest user, logged in as guest, to the login
  page when the guest role may not verify, as it does a visitor; and with `autologinguests` on, a visitor the
  guest role may not verify is no longer logged in as guest and then refused. Either way the login brings them
  back to the dossier they opened: the return carries its code, which the page's own URL leaves out.
- Version 2026100516: a framework's own policy on its policy page shows minimum hours, grace days and cycle
  months, as each competency's row does.
- Version 2026100516: removing a competency's policy override from the framework's policy page takes a POST;
  a GET with the session key is refused.
- Version 2026100516: the privacy metadata declares every column of the plugin's tables, including the links
  between them (`status.metaid`, `audit.metaid`, `dossier.exportid`, `export.contextid`) and the times
  (`status.nexttransition`, `status.timemodified`, `export.timecompleted`), and links to `core_message` for the
  alerts, digests and export notices the plugin sends.
- Version 2026100516: the evidence prompt on core's evidence pages no longer passes its unused count to the
  browser.
- Version 2026100514: a recomputation retired the hub's cached KPIs whenever it rewrote a status row, even when
  the row's status and sensitivity stayed the same, so the weekly reconcile emptied every cached count once per
  pair. They are now retired only when a row appears, goes, or changes its status or its sensitive flag.
- Version 2026100513: the dossier identifier setting (`local_evidencehub/identifierfield`) offered a class name and a
  method name instead of the ID number and the text profile fields, and its default was never stored, so every
  upgrade listed it as a new setting. Its choices are now a closure: core's select setting takes an array callable
  for the choices themselves. Dossiers kept printing the ID number throughout.
- The hub and the validity policies list named themselves twice in the breadcrumb: core already adds the active
  admin entry or category More menu node, so the page adds its own node only when none is active.
- The public verification page had no side gutter in the embedded layout: its content now sits in a centred
  container with padding (`.local-evidencehub-verify-page`).

### Added

- The family bridge (version 2026100509): a listener for `local_dimensions`' hook
  `competency_status_badges` (`db/hooks.php`; neither plugin requires the other, and core records a callback for
  an absent hook class without loading it). `local\validity_badges::for_user()` answers, for the current user,
  the "Validity: ..." pill of each asked competency of a worker: nothing for a guest, with competencies disabled
  or when the user cannot read the worker's evidence; "Restricted", without a date, for a pair decided by a
  sensitive evidence the viewer may not see; the status and, for compliant, due, critical and expired, its
  effective expiry; "Pending, no evidence" for a pair with no status row only under an explicit competency or
  framework policy. CI installs `local_dimensions` (`plugin-dependencies`).
- Alerts and the readiness web service (version 2026100508). Four message providers:
  `validitydue` and `validityexpired` tell the worker when a pair's status enters due or critical, or
  expired, from an external observer of `validity_status_changed` (so only after every open transaction
  commits, and only on a real change); `verificationrequested` asks the holders of `verify` assigned in the
  worker's user context (never the worker; for a sensitive type only those with `viewsensitive`) when an
  evidence's details enter pending under a policy requiring verification, through the ad hoc task
  `send_verification_request` (queued once per evidence, after the commit, so the form's reset and save
  make one request); `weeklydigest` sends each mentor (`receivealerts` and `userevidenceview` through
  roles in a worker's user context, e.g. `tool_cohortroles`) the pairs not compliant as the hub shows them
  to that mentor, with the compliant count. The scheduled task `send_weekly_digest` runs hourly; a round
  opens on Monday 07:00 site time, 200 mentors a run, resuming from `digestafter`, one digest per mentor
  per round. Every message is composed in the recipient's language and skips deleted, suspended and
  unconfirmed users. The web service `local_evidencehub_get_readiness` (template and exactly one of
  `userid` or `useridnumber`) returns the worker's real readiness and each competency's effective status
  and expiry for the caller, with a blocking flag; every worker refusal is one error. Pre-built service
  "Evidence hub readiness", disabled by default. Security read: a mentor's workers are confirmed with
  `has_capability()` for both capabilities and only templates the mentor reads are reported; the round's
  resume point is reset before the round is written; an immediate alert goes out only while the stored
  status (and, for due and critical, the expiry) is still the event's; one failing verification recipient
  no longer stops the others; an unknown template and an ID number the caller is not shown read like a
  template or worker out of reach.
- Readiness dossiers (version 2026100507). "Export dossiers" on the hub's list and matrix (for holders of
  `exportdossier` and `view`) posts the ticked workers with the filter; the selection is cut by the
  filter's visible rows and `maxexportworkers`. `local\dossier_builder` checks everything as the
  requester and writes, into any `archive_writer`, a folder per worker (a TCPDF page with a QR code to the
  verification page, and the deciding evidence's files, read in one statement over `{files}`) and
  `manifest.csv` with every file's SHA-256 and the workers left out; sensitive files only with
  `includesensitivefiles` and `viewsensitive`; the identifier is the ID number or a text profile
  field. Up to `syncmaxworkers` workers and `syncmaxbytes` the ZIP streams; above, the ad hoc task
  `build_dossier_export` runs as the requester, stores the ZIP in their user context for
  `exportretentiondays` days and sends the `dossierready` notification; `cleanup_dossier_exports`
  (daily) deletes expired ZIPs. Each dossier stores a row with its code, PDF hash and a snapshot
  without identifier or full name; the public `verify.php` shows that snapshot, the revocation and,
  with `verifylive`, today's status. The worker's evidence page lists the shared dossiers, which
  their creator or a site exporter can revoke (POST only, one conditional update). The snapshot always
  masks a competency decided by sensitive evidence; the export row records its hub context
  (`contextid`, upgrade step 2026100507), where a download re-checks the rights, with every worker's
  read access; manifest cells never start a formula; the task marks the export done inside its
  transaction and the cleanup also expires exports left behind. Events `dossier_exported` and `dossier_revoked`; the
  privacy provider exports and deletes the `export` file area. Seven settings on a new admin page.
- The readiness matrix (version 2026100506). The hub has a list and a matrix tab (`view=list|matrix`, part of the
  validated filter and its stored preference). The matrix shows one row per worker and one column per
  competency of the template, in the template's order, each heading with its policy kind's tag (REG,
  TEC, BEH), a legend of the statuses, and a cell per pair: the effective status's colour, the days left
  or since, a dash when missing and a lock when restricted, opening the pair's inspection drawer. It
  reads the list's base set in two statements (`local\hub_matrix`: a page of workers grouped, filtered
  by status with a `HAVING`, sorted by name or urgency; then every pair of those workers with their
  names), so the matrix and the list never disagree on a pair. The pager counts the KPIs' workers when
  no status chip narrows the rows, and runs one count statement otherwise. The table has a fixed layout
  and a sticky worker column, and is rendered in full by the server; a Behat step measures the page's
  layout shift on 200 workers by 25 competencies, seeded by a bulk generator entity (`matrix seeds`).
  Each row's checkbox posts the worker's id as `workers[]` (one dossier per worker).
- The inspection drawer (version 2026100505). Each row of the hub's grid opens a side sheet (a `core/modal`
  with its own template) for one worker and competency, its body a fragment
  (`local_evidencehub_output_fragment_pair()`) that checks the context, `local/evidencehub:view`,
  the right to read the worker's evidence and the competency (core's `read_competency()`), and the
  template itself; the learning plan link needs a template requiring the competency and a plan the
  viewer may read. It shows the
  effective status with the days left, core's proficiency to who may read it, the evidence in force
  with its files, the resolved policy in one sentence, every linked evidence as a timeline (newest
  issue first, its state, and the gap to the next record), the audit trail and the next change, each
  item following the sensitive rule on its own; and offers verify or reject, edit details, open in
  learning plan and register recycling. The evidence form takes a `competencyid` (and a
  `recyclefrom` whose type and issuer it copies when that evidence is the worker's, linked to the
  competency and readable) and links a new evidence to the competency through core on save.
- The hub page (`/local/evidencehub/index.php`), opened from Site administration >
  Competencies > Evidence hub or from a course category's More menu, locked there to that
  category's learning plan templates. For one template it lists every (worker, competency) pair of
  the workers with an active plan from it: KPI tiles per status with the readiness rate and how many
  workers the viewer's permissions hide, status chips (default "needs attention"), cohort, search,
  suspended and page size filters in a GET form, and a two-step grid (the page's keys, then their
  details) with its own pager, checkboxes and a download of every row or the ticked ones through
  core's dataformats (`hub_downloaded` event). Rows need core's `moodle/competency:userevidenceview`
  in the worker's user context, cut in SQL; a pair decided by a sensitive evidence the viewer may not
  see reads "Restricted" in the grid, the filters, the sort, the counts and the download. The KPIs
  are cached in an application cache retired by a generation that status writes and plan, cohort
  and user events replace. The last filter is kept as a user preference (exported by the privacy
  provider). A report builder datasource "Competency validity" serves site-level compliance staff.
  The status row stores whether its deciding evidence's type is sensitive (`issensitive`, filled by
  the first upgrade step, 2026100504), so the hub reads it without joining meta and type per pair.
- The validity engine. A pure validity calculator works out each (worker, competency) status
  (compliant, due, critical, expired, pending) from the linked evidence, the latest proficient
  rating and the competency's policy, with calendar days in the site's timezone; the status
  repository stores it under a per-user lock and, on a change, fires `validity_status_changed` and
  writes a log entry into core's competency history (a review request on expiry when the policy
  says so; never a rating change). Observers recompute on links, unlinks, ratings and details
  changes and clean up on competency, framework and user deletion. Tasks: hourly
  `evaluate_validity` over the next transitions, weekly `reconcile_validity`, ad hoc
  `recompute_scope` after a policy change. Policies page (`/local/evidencehub/policies.php`, under
  Site administration > Competencies) to set a framework's policy and override it per competency,
  in the framework's context; `validity_policy_updated` event. The evidence page shows the status
  of each linked competency, or only "Restricted" when a sensitive evidence the viewer may not see
  decides it. A status change and its log entry commit together; a pair whose user is locked is
  queued, and a failing pair never stops a task run.
- Evidence details. A worker's evidence page (`/local/evidencehub/evidence.php`) lists
  core's evidence of prior learning with its validity details and verification state, and opens
  one modal form that creates or edits the core evidence and its details in one save, plus a modal
  to verify or reject them. tool_lp's evidence pages prompt whoever may manage the evidence to
  complete missing details. The details API refuses details that break their type's rules, never
  lets a worker verify or reject their own evidence, and sends verified or rejected details back to
  pending when they change, unless a verifier other than the owner changed them. Each change writes
  the audit trail; three events (`evidence_details_updated`, `evidence_verified`,
  `evidence_rejected`); core's evidence update and delete events are observed. Details of a
  sensitive type are neither shown, edited nor decided on by a viewer without
  `local/evidencehub:viewsensitive`, and the writers of one evidence's details serialise under a
  lock and write only their own columns.
- The data model (seven tables), five seeded evidence types, seven capabilities, English
  and Brazilian Portuguese strings, and a privacy provider covering every table with personal data.
- The export request keeps a count of its workers instead of the proposal's list of ids: the
  dossier rows of the export already say who it covered, and a list inside a text column could not
  be cleaned by a deletion request.
