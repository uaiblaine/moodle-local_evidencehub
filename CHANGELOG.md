# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## Unreleased

### Added

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
