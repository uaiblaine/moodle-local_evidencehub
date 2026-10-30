# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## Unreleased

### Added

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
