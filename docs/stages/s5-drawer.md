# S5: the inspection drawer

Stage S5 of the evidence hub plan
(`moodle-local_dimensions/docs/proposals/2026-10-29-evidence-hub/README.md`, row S5, idea 6
"Register recycling", the mockup's screen 3 "Inspection drawer", and the S0 side-sheet finding in
`spike/README.md`):

> Side sheet: current evidence, files, timeline of history, audit trail, actions (verify, reject,
> register recycling, download). Done when Behat opens it, verifies, and checks the timeline order.

D1–D12 are approved. The contracts of S2, S3 and S4 (`docs/stages/`) still hold, in particular S2's
sensitive rules, S2's verification rules (no self-verification, reject needs a note, the reset rule)
and S4's effective status. This note settles what the plan leaves open; each choice is a contract
the tests hold.

## Scope

In: the drawer for one (worker, competency) pair opened from the hub grid, its server-rendered
content, its actions (verify and reject through S2's verify form, edit details and register
recycling through S2's evidence form, file download links, open in the learning plan), and the
recycling extension of S2's evidence form. Out: the matrix view (S6), dossiers (S7), alerts (S8).
No schema change.

## Opening and access

- A button per hub grid row ("Inspect", `aria-label` naming the worker and competency) opens the
  drawer. The worker's name link to `evidence.php` stays.
- The drawer is a `core/modal` subclass (`amd/src/drawer.js`) with its own `TEMPLATE` that carries
  the side-sheet class from creation, so the first slide-in animates (the spike added the class
  after `create()` and lost it). Plugin-scoped CSS: right edge, full height, body scrolls, a
  `transform` slide of at least 100 ms, none under `prefers-reduced-motion`. Focus trap, Escape and
  focus return are core's.
- Its body comes from a **fragment** (`local_evidencehub_output_fragment_pair($args)` in `lib.php`,
  loaded with `core/fragment` in the hub page context). The callback checks everything itself; it
  never trusts the hub page to have checked:
  - the context is a system or category context and the viewer holds `local/evidencehub:view` in
    it (`hub_access::require_view`);
  - the viewer may read the worker's evidence: `user_evidence::can_read_user($userid)` (the same
    fact the hub's SQL cut reads);
  - the competency exists; `templateid`, when given, must be a template the hub offers in that
    context (`hub_access::require_template`), and is used only to find the worker's plan for the
    "Open in learning plan" link.
- Everything after an action (verify, reject, edit, recycling) reloads the hub page with a
  `core/pending` marker left unresolved until the reload (S3's lesson), so Behat waits.

## Content (renderable `output\pair_drawer`, template `pair_drawer.mustache`)

Header: the worker's name and the identity fields the page context shows; the competency's name
and idnumber; the **effective** status pill (S4: restricted, missing, or the stored status) with
days left when it covers today; core's proficiency ("Proficient", "Not proficient", "Not rated")
only when `user_competency::can_read_user($userid)` holds, else nothing.

Evidence in force (the status row's deciding evidence, `status.metaid`), when there is one: type,
issue date, expiry (or "Never expires"), issuer, hours with the policy's minimum when set, the
verification (state, who, when), and the core evidence's files as download links
(`moodle_url::make_pluginfile_url(<user context>, 'core_competency', 'userevidence', <id>, ...)`:
core serves them with its own checks). The authenticity code only to who may manage or verify
(S2's page rule).

Policy: the resolved policy in one sentence (kind, verified only or not, alerts, grace, cycle,
blocking), and its source (competency, framework, default).

History: **every** core evidence of the worker linked to the competency
(`competency_userevidencecomp`), newest issue first (ties: highest id), each with its interval
(issue → expiry or never), its state ("in force", "expired", "not yet valid", "awaiting
verification", "rejected", "details missing"), issuer and hours, and the gap in days to the next
newer interval when they do not touch. Never rewritten: recycling adds a row.

Audit trail: the audit rows of those evidences, newest first: date, action label, actor's full
name, and the note. Notes only to the worker and to holders of `local/evidencehub:verify` in the
worker's context (S2's rule for the reject note).

Footer: when the status was computed (`status.timemodified`) and its next change
(`nexttransition`, as a day), or "No status yet".

### Sensitive evidence (S2 + S4 rules, applied per item)

For a viewer for whom an item is restricted (sensitive type, not the worker, no `viewsensitive` in
the worker's context — `details::is_restricted()`):
- the header pill reads "Restricted" when the deciding evidence is restricted (S4's effective
  status) and shows no days left;
- that item (in force or in the history) shows its type name and "Restricted details" only: no
  dates, issuer, code, hours, verification, files or audit notes; its audit rows show date and
  action only;
- the gap computation never uses a restricted item's dates (no gap shown next to it).

### Actions

Each shown only when its rule passes, and each re-checked by the form or API it opens:
- **Verify / reject** per item in the history (in force included) whose state allows it:
  `details::can_verify()` (verify in the worker's context, read access, not the owner) and not
  restricted. Opens S2's verify form.
- **Edit details** per item: `user_evidence::can_manage()` and not restricted. Opens S2's evidence
  form.
- **Register recycling** once, under the header: `user_evidence::can_manage_user($userid)`. Opens
  S2's evidence form **for a new evidence** with:
  - `competencyid` (and optional `recyclefrom`, a meta id) as form args;
  - prefilled type and issuer copied from `recyclefrom` when that item belongs to this worker, is
    linked to this competency and is not restricted for the viewer (else nothing is copied);
  - on save, after the core evidence and its details, the new evidence is linked to the competency
    through `api::create_user_evidence_competency()` (core checks its own rights) in the same
    transaction; S3's observers then recompute the pair.
- **Download**: the file links above.
- **Open in learning plan**: tool_lp's page for the competency in the worker's plan from the
  template (`/admin/tool/lp/user_competency_in_plan.php?userid=&competencyid=&planid=`), only when
  `templateid` was given and the worker has a plan from it.

## Tests

- `tests/output/pair_drawer_test.php` and a fragment test through
  `local_evidencehub_output_fragment_pair()`: access both directions for each check (no `view` in
  the context; a worker the viewer cannot read; a template outside the context; control: allowed);
  the history order and its ties; the gap computation (touching intervals, a gap of N days, a
  restricted neighbour); each state label; the evidence in force matches `status.metaid`; the
  sensitive rule per item both directions (restricted viewer, viewer with `viewsensitive`, the
  worker); audit notes both directions; proficiency shown only with `can_read_user`; each action's
  visibility both directions; the plan link only with a template and a plan.
- `tests/form/evidence_test.php` (extend): recycling prefills type and issuer from a valid
  `recyclefrom`, copies nothing from another worker's, another competency's or a restricted item;
  saving links the new evidence to the competency (core row exists) and the pair is recomputed;
  without `can_manage_user` the form is refused.
- Behat `tests/behat/drawer.feature`: a manager opens the drawer from the hub, sees the timeline in
  order (newest first), verifies the pending evidence, and the drawer shows it verified after the
  reload; the drawer closes with Escape and focus returns to its button.

Mutation spec: `G210`+ for every guard (each fragment check, each sensitive rule in the drawer,
the note rule, each action's visibility, recyclefrom's three checks, the link on save, the history
order and gap rule).

## Versioning

`version.php` 2026100505 (new fragment callback, form behaviour), `CHANGELOG.md`, lang en + pt_br
sorted in lockstep, README (the drawer, recycling), the plugin `CLAUDE.md`.

## Review rounds (2026-10-30), folded into S5

The `security-diff-read` pass found three defects; all are fixed: the section heading ("Evidence in
force" or "Deciding evidence") told a viewer without `viewsensitive` whether a restricted deciding
evidence covers today (a restricted pill now always shows the neutral heading); the fragment printed a
competency's name, idnumber and policy without `api::read_competency()`; and the plan link was built
from any plan of the template without `plan::can_read()` or a check that the template requires the
competency.

The Behat smoke then found that "Inspect" did nothing: `amd/src/drawer.js` exported its modal class as
the default beside the named `open()`, and Moodle's AMD build makes a module with a default export
resolve to that default alone, so `open` was undefined for `hub_page`. The class is no longer
exported. Nothing short of a browser shows this: eslint, the build check and PHPUnit all pass.
