# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## Unreleased

### Added

- Stage S6: the readiness matrix. The hub has a list and a matrix tab (`view=list|matrix`, part of the
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
  Each row's checkbox posts the worker's id as `workers[]` (one dossier per worker); the server-side
  handling of `workers[]`, and the matrix form's action, come with S7.
- Stage S5: the inspection drawer. Each row of the hub's grid opens a side sheet (a `core/modal`
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
- Stage S4: the hub page (`/local/evidencehub/index.php`), opened from Site administration >
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
- Stage S3: validity engine. A pure validity calculator works out each (worker, competency) status
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
- Stage S2: evidence details. A worker's evidence page (`/local/evidencehub/evidence.php`) lists
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
- Stage S1: the data model (seven tables), five seeded evidence types, seven capabilities, English
  and Brazilian Portuguese strings, and a privacy provider covering every table with personal data.
- The export request keeps a count of its workers instead of the proposal's list of ids: the
  dossier rows of the export already say who it covered, and a list inside a text column could not
  be cleaned by a deletion request.
