# S8: alerts and the readiness web service

Stage S8 of the evidence hub plan
(`moodle-local_dimensions/docs/proposals/2026-10-29-evidence-hub/README.md`, row S8, ideas 5 and 8,
decision D11):

> Message providers (due, expired, verification requested, dossier ready), weekly digest to
> mentors, `get_readiness` web service. Done when the messages are asserted with the message sink
> and the web service returns its allowlist.

D11 (approved): the worker and the holders of `local/evidencehub:receivealerts` in the worker's
user context are alerted; mentors get a weekly digest. The contracts of S2–S7 (`docs/stages/`) still
hold — in particular the effective status (S4) and the rule that what a third party may learn about a
sensitive competency is "Restricted" (S7's snapshot). `dossierready` exists since S7. This note
settles what the plan leaves open; each choice is a contract the tests hold. No schema change unless
proven necessary (then an upgrade step).

## Message providers (`db/messages.php`, lang `messageprovider:<name>`)

| Provider | To | When | Defaults |
|---|---|---|---|
| `validitydue` | the worker | their pair's status changes **into** due or critical | popup + email on |
| `validityexpired` | the worker | their pair's status changes **into** expired | popup + email on |
| `verificationrequested` | verifiers (below) | an evidence's details enter the pending state (created, details changed, reset) on a competency whose policy requires verification | popup on, email off |
| `weeklydigest` | mentors (below) | the weekly task | popup + email on |
| `dossierready` | (S7) | unchanged | unchanged |

- Immediate alerts are sent **after** the status row commits, from an observer of
  `validity_status_changed` (`other.from`, `other.to`), never from inside the recompute transaction;
  only on a real change into the alerting state (a recompute that leaves the status as it was sends
  nothing; due → critical sends `validitydue` again, as a new threshold).
- Never to a deleted, suspended or not-yet-confirmed user; never to the user who caused the change
  when that user is the recipient? No: the worker is always told about their own pair.
- Each message is composed **in the recipient's language** (`force_current_language($user->lang)`
  around the strings, restored after), carries a `contexturl` to the worker's evidence page
  (`evidence.php?userid=`), and the competency's name as a plain spelling.

### Who verifies (`verificationrequested`)

Holders of `local/evidencehub:verify` whose capability comes from a role assigned **in the worker's
user context** (the mentor pattern, `tool_cohortroles`), never from a system or category assignment —
otherwise every site manager would get a message per upload from every worker. One message per
recipient per evidence state change; the worker never receives it for their own evidence. Sites that
verify centrally use the hub (S4/S5); the README says so.

### Who is a mentor (`weeklydigest`)

A user holding `receivealerts` **and** `moodle/competency:userevidenceview` through roles assigned in
the worker's user context (same rule, same reason). The digest lists, per worker the mentor follows:
pairs currently not compliant (due, critical, expired, awaiting verification, missing for a template
the worker has an active plan from), with the competency, the effective status **for that mentor**
(a sensitive deciding evidence reads "Restricted" unless the mentor holds `viewsensitive` in that
worker's user context) and the expiry where shown; and the count of compliant pairs. A mentor with
nothing to report gets no message. One message per mentor per week.

## The digest task (`classes/task/send_weekly_digest.php`, scheduled weekly, Monday 07:00)

- Finds mentors with one query over `{role_assignments}` in user contexts joined to
  `{role_capabilities}` (allow, not prohibited) for both capabilities, then each mentor's workers.
- For each mentor, reads the pairs through the hub's base set (`hub_query::base_sql()` with a scope
  built for that mentor — `hub_scope::for_user($mentorid)` or equivalent, the effective status computed
  for the mentor), not with the cron user's rights.
- Composes in the mentor's language; chunks of mentors per run with a cap and a resume point (a config
  value with the last mentor id handled this week) so a large site spreads over runs and never sends
  twice in a week.

## The web service `local_evidencehub_get_readiness` (`classes/external/get_readiness.php`)

- Parameters: `templateid` (int, required); exactly one of `userid` (int) or `useridnumber`
  (string, the user's `idnumber`); refusal when both or neither.
- Access, in order: `validate_parameters()`; `require_login()` semantics of `external_api` (the
  token's user), guests refused; the template's context validated (`validate_context()` on it);
  `require_capability('local/evidencehub:view', <template context>)`; the worker resolved (deleted
  refused; an unknown idnumber and an unreadable user give the same error — no existence oracle);
  `moodle/competency:userevidenceview` in the worker's user context; the worker must have an
  **active** plan from the template, else the same "not found".
- Returns (an allowlist): `ready` (bool), `computed` (timestamp of the latest status row read), and
  `competencies`: for every competency of the template, `id`, `shortname` (plain), `idnumber`,
  `status` (the **effective** code for the token's user, S4), `expiry` (ISO day or null; null when
  restricted), `blocking` (bool: not ready under a regulatory policy).
- `ready` is the worker's **real** readiness (the gate system's whole purpose), as S7's snapshot; a
  restricted competency's own row shows only "restricted". Document the trade-off (as S7).
- `db/services.php` with `ajax => false` (a gate system calls it with a token; not used by the hub's
  pages), a pre-built service "Evidence hub readiness" containing the function, disabled by default.
- `use core_external\...` (fleet rule). No event (read-only).

## Privacy

Messages are core's (the message subsystem's provider covers them). The digest's resume point is a
plugin config value, not personal data. No change to the provider unless a new user-keyed value is
stored.

## Tests

- Message sink (`redirectMessages()`): due, critical, expired each send one message to the worker on
  the change and none when the status stays; none for a suspended worker; the recipient's language is
  used (a pt_br worker's subject is the pt_br string); `contexturl` points at the evidence page.
- `verificationrequested`: a verifier assigned in the worker's user context gets it; a system-level
  manager with `verify` does not (control); the worker does not; only on entering pending under a
  policy that requires verification (control: a technical policy without `requireverified` sends none).
- Digest: a mentor through `tool_cohortroles` gets one message listing the not-compliant pairs and the
  compliant count; a system-level holder of `receivealerts` gets none; a mentor without
  `userevidenceview` gets none; a sensitive pair reads "Restricted" for a mentor without
  `viewsensitive` (control: with it); a mentor with nothing to report gets none; the resume point
  prevents a second message the same week; the cap spreads mentors over runs.
- Web service through `call_external_function()` (fleet rule, `$_POST['sesskey']` where needed): the
  allowlist of returns (exact keys); access both directions for each check; `userid` and `useridnumber`
  agree; both/neither refused; unknown idnumber and unreadable user give the same error; no active
  plan gives the same error; restricted competency shows no status or expiry for a caller without
  `viewsensitive` (control) while `ready` is the real one.
- Behat: none needed (no UI); the README documents the service.

Mutation spec: `G335`+ for every guard listed.

## Versioning

`version.php` 2026100508 (providers, task, service), `CHANGELOG.md`, lang en + pt_br sorted in
lockstep, README (alerts, who receives them and why only user-context roles count, the digest, the web
service and its pre-built service), the plugin `CLAUDE.md`.

## Review rounds (2026-10-31), folded into S8

The `security-diff-read` pass found no leak in the main paths and eight points, all fixed: the digest
now confirms `userevidenceview` per worker with `has_capability()` (an override in the user context is
honoured); the digest's resume point is reset before its round; the web service answers an unknown
template like one out of reach (no existence oracle) and looks a worker up by ID number only when the
caller is shown ID numbers; one failing verification recipient no longer re-messages the others; an
immediate alert re-reads the pair and is dropped when the status or the expiry moved since; the digest
skips templates the mentor cannot read; and the task's comment no longer overclaims. Left to the owner,
as in S7: `ready` and `computed` reflect the real state of a restricted pair.

The sweep then found G373 (the status re-read) held by no test, because the expiry check behind it
also stopped the test's renewed case; `test_a_status_overtaken_with_the_same_expiry_alerts_only_its_last`
moves a pair from due to expired with the same expiry. S8 also moved the reset in
`observer::user_evidence_updated()`, so S2's G46 was rewritten.
