# S2: evidence details

Stage S2 of the evidence hub plan
(`moodle-local_dimensions/docs/proposals/2026-10-29-evidence-hub/README.md`, row S2):

> Own `dynamic_form` creating/updating core evidence through `api::create_user_evidence()` +
> metadata in one save; "Complete details" for tool_lp evidence (footer hook); worker
> self-service page; verify / reject actions; audit rows; events. Done when worker and manager
> paths are tested in both directions of every capability, and edit-after-verify resets.

The decisions D1–D12 are approved and not reopened here. This note settles what they leave
open for S2; each choice below is a contract the tests hold.

## Scope

In: the details API, the evidence form, the verification form, the worker's evidence page, the
tool_lp prompt, two core observers, three events, the Behat smoke for the two forms.

Out (later stages): validity status and its pill (S3), the hub and its drawer (S4/S5), linking
evidence to competencies from this plugin (the page links to tool_lp's own page for that),
dossiers, alerts, web services.

## Pieces

### `classes/local/details.php` (the API; everything else calls it)

- `details::get(int $userevidenceid): ?\stdClass`, the meta row or null.
- `details::save(\core_competency\user_evidence $ue, \stdClass $data): \stdClass` writes the
  meta row (insert or update) for that evidence and returns it.
  - Caller rights: `$ue->can_manage()` (core's own check: `userevidencemanage`, or
    `userevidencemanageown` for the owner), else `required_capability_exception`.
  - Validation (also in the form, but the API refuses on its own, because it is the reusable
    entry point): the type exists; `issuedate` is a valid `YYYYMMDD`; an `expirydate`, when
    given, is not before `issuedate`; a type with `requireexpiry` refuses `noexpiry`; when no
    expiry is given and `noexpiry` is off, the type's `defaultvalidity` (months) fills it from
    the issue date with month clamping (`meta::add_months()`: 2028-02-29 + 12 = 2029-02-28),
    and a type with `defaultvalidity = 0` then refuses the save; a type with `requireauthcode`
    refuses an empty `authcode`; `hours`, when given, is ≥ 0. Errors are
    `\moodle_exception` with lang strings.
  - Writes an audit row: `created` on insert, `details` on update.
  - Applies the **reset rule** (below) on update.
  - Fires `evidence_details_updated` (on insert and on update).
- `details::verify(int $metaid, string $note)` / `details::reject(int $metaid, string $note)`:
  - `require_capability('local/evidencehub:verify', context_user::instance($meta->userid))`.
  - The actor must also be able to read the evidence (`user_evidence::can_read_user()`).
  - **No self-verification**: the evidence's owner can never verify or reject their own
    evidence, whatever their capabilities (a compliance check that a worker can approve their
    own card is worthless). `moodle_exception('cannotverifyown')`.
  - Reject needs a non-empty note; verify accepts an empty one.
  - Sets `verifystatus`, `verifiedby`, `timeverified`, `verifynote`; audit row
    `verified`/`rejected` with the note; fires `evidence_verified`/`evidence_rejected`.
  - Verifying an already verified evidence again is refused (`alreadyverified`); rejecting a
    rejected one likewise. Verify after reject and reject after verify are allowed (a second
    look), each with its own audit row.
- **Reset rule** (`details::apply_edit_rule(\stdClass $meta, int $actorid): bool`): when the
  details of a VERIFIED or REJECTED evidence change, the state returns to PENDING and an audit row
  `reset` is written, **unless the actor holds `local/evidencehub:verify` in the worker's
  context AND is not the worker**. The owner holding `verify` still resets (otherwise the
  no-self-verification rule could be walked around: get verified once, then edit freely).
  Returns whether it reset. Called from `save()` and from the core observer.

`meta::add_months(int $date, int $months): int` and `meta::from_timestamp(int $ts, ?string
$tz = null): int` / `meta::to_timestamp(int $date, ?string $tz = null): int` (calendar day in
the user's timezone, via `core_date::get_user_timezone_object()`) are pure helpers on the
existing `local\meta` class, with data-provider tests (leap days, month ends, a UTC-3 and a
UTC+9 timezone around midnight).

### `classes/form/evidence.php` (`core_form\dynamic_form`)

One form creates or edits the core evidence AND its details, in one submit.

- Fields: core's (name, description editor, url, files filemanager), mirroring
  `admin/tool/lp/classes/form/user_evidence.php` (same options: core's `userevidence` file area,
  `subdirs`, its accepted types and limits), then a header "Validity details": type (select
  of `local_evidencehub_type` by sortorder), issue date (date_selector), never expires
  (advcheckbox), expiry date (date_selector, optional, `hideIf` never expires), issuer, authenticity
  code, hours (float).
- Custom data / ajax args: `userid` (the worker), and `id` (the core user evidence id) when
  editing. A "complete details" call is the same form with `id` of an evidence that has no
  meta row yet: the core fields come prefilled, the details empty.
- `get_context_for_dynamic_submission()`: the worker's user context.
- `check_access_for_dynamic_submission()`: new → `user_evidence::can_manage_user($userid)`;
  edit → `(new user_evidence($id))->can_manage()`, and the evidence's `userid` must equal the
  `userid` argument (no evidence of user A edited through user B's page).
- `process_dynamic_submission()`: in one `$DB->start_delegated_transaction()`: core create or
  update through `api::create_user_evidence($data, $draftitemid)` /
  `api::update_user_evidence(...)`, never a direct write, then `details::save()`. Returns the
  user evidence id.
- `validation()` repeats the API's rules so the user sees them on the field, not as an
  exception.
- `set_data_for_dynamic_submission()` loads core's record (`file_prepare_draft_area` for the
  files, the editor format) and the meta row (dates through `meta::to_timestamp()`).

### `classes/form/verify.php` (`core_form\dynamic_form`)

Decision (radio: verify / reject) and note (textarea, required when rejecting: `validation()`).
Context: the worker's user context; access: `local/evidencehub:verify` there and not the
owner (the same rule as the API, which it calls).

### `evidence.php` (the worker's page)

`/local/evidencehub/evidence.php?userid=<id>` (default `$USER->id`).

- `require_login(null, false)`, guests refused, competencies enabled
  (`\core_competency\api::is_enabled()`, else the core `competenciesarenotenabled` notice).
- Access: `user_evidence::can_read_user($userid)`, else `required_capability_exception` on
  `moodle/competency:userevidenceview`.
- Page: user context, layout `standard`, title "Evidence and validity", heading the user's
  full name when it is not `$USER`.
- Lists the worker's core evidence (`api::list_user_evidence()`), each with: name (link to the
  tool_lp view), its details when present (type, issue/expiry date via `meta::iso_date()` →
  `userdate`-free plain ISO day, issuer, hours; authenticity code only to who can manage or
  verify), the verification state as a badge (`text-*` colour stated per the fleet rule) with
  who and when, the reject note to the worker and verifiers, or "Details missing".
- **Sensitive types**: a viewer who is not the worker and lacks
  `local/evidencehub:viewsensitive` in the worker's context sees the type name and "Restricted
  details" instead of dates, issuer, code, hours and note.
- Actions (buttons opening the dynamic forms in `core_form/modalform`, reloading the list on
  submit): "Add evidence" and "Edit" when `can_manage`; "Complete details" when no meta row and
  `can_manage`; "Verify / reject" when the verify form's access rule passes; "Link competencies"
  to tool_lp's page.
- Template `templates/evidence_page.mustache` (+ a partial per item), renderable
  `classes/output/evidence_page.php`; one small AMD module `amd/src/evidence_page.js` binding the
  buttons to `ModalForm`. Bootstrap 5 only (5.3-only plugin): `data-bs-*`, no `data-toggle`.
- No new capability: reading follows core's, so a mentor given roles through
  `tool_cohortroles` reaches exactly their workers.

### tool_lp prompt (`db/hooks.php` → `classes/hook_callbacks.php`)

`core\hook\output\before_footer_html_generation`, guarded by pagetype first (tool_lp's evidence
view and evidence list pages; read the exact pagetype strings from a real request or from
`tool_lp\page_helper`, never guess them), then by context (a user context), then by
`can_manage` for that user. It calls `js_call_amd('local_evidencehub/lp_prompt', 'init', [...])`
with a URL to `evidence.php` and a count of the user's evidence without details (list page)
or a flag for the one evidence (view page); the module inserts a `alert alert-info` with the
link at the top of `#region-main`. Nothing when everything has details.

### Observers (`db/events.php` → `classes/observer.php`)

- `\core\event\competency_user_evidence_updated`: load the meta row; when present, apply the
  reset rule with `$event->userid` as the actor. Our own form triggers this event too (core's
  update fires it); the rule is idempotent (only a verified/rejected row resets), so the form's
  own `details::save()` afterwards finds it already pending and writes no second `reset` row.
- `\core\event\competency_user_evidence_deleted`: delete the meta row (core already unlinked
  the competencies). Audit rows stay: the trail is append-only. S3 adds the status recompute.

### Events (`classes/event/`)

`evidence_details_updated`, `evidence_verified`, `evidence_rejected`: crud `c`/`u`, edulevel
`LEVEL_OTHER`, context the worker's user context, `relateduserid` the worker, `objectid` the
meta id + `objecttable` `local_evidencehub_meta`, `other['userevidenceid']`; a
`get_objectid_mapping()` returning `base::NOT_MAPPED`; lang strings `eventevidencedetailsupdated`
etc.

## Tests

PHPUnit (most of it), every capability in both directions, every "did not happen" with its
control:

- `tests/local/details_test.php`: save by owner (manageown) and by a manager (manage), refused
  without either; every validation rule refused and its passing neighbour accepted; default
  expiry and the clamp; audit rows and events (sink) on insert and update; verify and reject
  rights both directions; self-verification refused even with `verify` (control: another
  holder verifies the same row); reject without note refused; double verify refused; the reset
  rule's four cases (owner without verify → reset; owner WITH verify → reset; another verifier
  → no reset; a manager without verify → reset), each asserting the audit row.
- `tests/local/meta_test.php` (or extend `constants_test`): the date helpers.
- `tests/form/evidence_test.php`, `tests/form/verify_test.php`: `mock_ajax_submission()` +
  `process_dynamic_submission()`; core evidence created through the API (core's event fired,
  files saved), meta row beside it; editing another user's evidence through a mismatched
  `userid` refused; the transaction rolls the core row back when `details::save()` refuses.
- `tests/observer_test.php`: tool_lp-style update through `api::update_user_evidence()` resets
  a verified row (control: a verifier's update does not); delete drops the meta row and keeps
  the audit.
- `tests/hook_callbacks_test.php`: the prompt is added on the tool_lp pages and not on another
  pagetype, not without `can_manage`, not when every evidence has details (read the AMD call
  through the requirements manager's `amdjscode`, fleet rule).
- `tests/evidence_page_test.php`: the renderable's export for the owner, for a reader without
  `viewsensitive` (sensitive details hidden; control: with it, shown), for a reader without
  verify (no verify action; control: with it, shown, and never on own evidence).
- Behat `tests/behat/evidence_details.feature` (thin): a worker adds an evidence with details
  through the modal and sees it listed; a manager verifies it; a reject without a note shows the
  field error.

Mutation spec: every guard above gets a `G<n>` line in `mutations/gates.conf` with the test that
must redden.

## Versioning

`version.php` 2026100501, `CHANGELOG.md` (Unreleased/Added), lang en + pt_br in lockstep and
sorted, README (the page, the prompt, the no-self-verification rule, the reset rule).

## Security read (2026-10-30), folded into S2

The `security-diff-read` pass over the S2 diff found four defects, all fixed before the first
commit:

1. Sensitive details reached a manager without `viewsensitive` through the edit form (the page
   hid them; `set_data_for_dynamic_submission()` loaded them). `details::is_restricted()` now
   refuses an update of a restricted row in `save()`, in the form's access check and on the
   page; creating a new sensitive evidence stays allowed.
2. A verifier without `viewsensitive` could decide on a sensitive row they could not see.
   `decide()`, the verify form and the page refuse it the same way.
3. `decide()` read, checked and wrote the whole loaded row outside its transaction. Every writer
   of a meta row now takes the core lock `meta_<id>` once at its entry point, re-reads the row
   inside the transaction and writes only its own columns. Two requests cannot interleave in one
   PHPUnit process, so this guard has pinning tests and no mutation gate.
4. `evidence.php` set the heading with the target user's name before the access check.
