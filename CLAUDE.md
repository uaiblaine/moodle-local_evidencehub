# Claude instructions for `local_evidencehub`

This file is auto-loaded as context whenever Claude works in this plugin's
directory tree. **Fleet-wide standards live in `~/dev/CLAUDE.md`** (coding
style, CI gates, lang-string rules, the `mdl` environment, git rules) — do not
repeat them here. This file keeps only what is true for this plugin, as rules
with a one-line reason each; keep it under ~250 lines, because every session
and every subagent pays for it on every cache write. Measurements, incidents
and the long version of a gotcha go to `docs/` (export-ignored) with a pointer
from the rule.

Plugin context: a Moodle **local** plugin ("Evidence hub") that adds validity details (type, issue
and expiry dates, issuer, authenticity code, hours, verification) to core competencies' user
evidence, materialises the validity of each (worker, competency) pair, and exports verifiable
readiness dossiers. It owns seven tables (`db/install.xml`) and leaves core's evidence and files
to core. Companion of `local_dimensions` with **no dependency** on it (the bridge is a Hooks API
hook that `local_dimensions` declares, stage S9). Supports Moodle **5.3 only**
(`$plugin->requires = 2026100500`, `$plugin->supported = [503, 503]`). CI is the
moodle-an-hochschulen reusable workflow, one job in `.github/workflows/ci.yml` — **update it when
`supported` changes**. Development happens on m503; this repo is mounted into m503 and m503b at
`local/evidencehub` (see `~/dev/moodle-dev/plugins.conf`).

Design, decisions D1–D12 (approved by the owner 2026-10-29) and the stage plan S0–S9:
`~/dev/moodle-local_dimensions/docs/proposals/2026-10-29-evidence-hub/README.md`, with the S0
measurements in its `spike/`. Do not reopen an approved decision without the owner.

## Agent orchestration budget (fleet rule, repeated here on purpose)

Section 6 of `~/dev/CLAUDE.md` (`moodle-dev/CLAUDE.fleet.md`) is the authority and says why.
This short copy reaches sessions that do not load that file: cloud sessions and checkouts
outside `~/dev`. Every subagent gets the model and effort of its role from its agent
definition, and none runs on the session model.

| Role | model | effort | agent |
|---|---|---|---|
| Mechanical sweeps, greps, renames, counts, log reading | `haiku` | `medium` | `fleet-sweeper` |
| Checklists against evidence (handoff counts, spec lines against a sweep log, lang lockstep) | `haiku` | `high` | `fleet-checker` |
| Readers, measurers, graders | `sonnet` | `medium` | `fleet-reader` |
| Refuters and verifiers of a blocking finding | `sonnet` | `high` | `fleet-verifier` |
| Well-scoped implementation (established cause, settled design, written recipe) | `sonnet` | `medium` | `fleet-fixer` |
| Non-trivial implementation (open design, several files, long tasks) | `opus` | `high` | `fleet-implementer` |
| Consolidators, critics, estimators, ADR and documentation drafters | `opus` | `high` | `fleet-synthesist` |

- Launch the `Agent` tool with `subagent_type: "fleet-*"`; it has no `effort` parameter, so
  the role's effort comes from that definition (`mdl claude-setup` installs them). Where they
  are not installed, pass `model`. Aliases only; never `fable`; `xhigh` only for a long-horizon
  implementer whose prompt says why; never `xhigh`/`max` on Sonnet or Haiku.
- No long command inside a subagent: `mdl ci --matrix`, `mdl mutate` and Behat run from the
  main session in a background Bash command; a subagent runs the fast gate its prompt names and
  reports the command with its counts.
- Workflows only on the user's opt-in, every `agent()` with `agentType: 'fleet-*'`, under 10
  agents. Advisor off by default.

## Commands

```sh
mdl ci moodle-local_evidencehub --matrix    # every leg: the merge gate (add --behat once features exist)
mdl ci moodle-local_evidencehub --only phpcs,phpdoc,validate,phpunit   # fast iteration, one leg
mdl phpunit m503 local_evidencehub          # targeted tests on the stack
mdl purge m503                              # after PHP changes that affect output
```

In a Claude Code cloud session none of `mdl`, the stacks or `~/dev/CLAUDE.md` exist. The cloud
environment's setup script (`moodle-dev/cloud/setup.sh`) installs `moodle-plugin-ci` and the `mpci`
runner instead: `mpci` runs every gate on the highest branch in `ci.yml`, `mpci --branch <branch>`
another one, `mpci --reuse --only phpunit --filter <test>` iterates. It cannot run Behat, PHP 8.4 or
MariaDB, so the owner runs `mdl ci moodle-local_evidencehub --matrix --behat` before merging: push a
branch, open the pull request, leave the merge to them. If `which mpci` prints nothing, the session
was resumed on a container that skipped the setup script: attach `moodle-dev` and run
`bash cloud/setup.sh` from it. See `moodle-dev/cloud/README.md`.

## Code layout

```
version.php, db/access.php, db/install.php (seeds types), db/install.xml (7 tables)
db/hooks.php, db/events.php   tool_lp prompt (footer hook); observers (classes/observer.php)
db/tasks.php      evaluate_validity (hourly), reconcile_validity (weekly)
evidence.php      the worker's evidence page (renderable output/evidence_page, amd/src/evidence_page)
policies.php      framework list / one framework's policies (output/policy_frameworks,
                  output/framework_policies, amd/src/policies); settings.php adds its admin entry
index.php         the hub (output/hub_page, amd/src/hub_page); entries: settings.php (system) and
                  lib.php's category More menu; db/caches.php hubkpis + hubgeneration
                  drawer: amd/src/drawer (core/modal subclass, templates/drawer), body from lib.php's
                  output_fragment_pair (output/pair_drawer, templates/pair_drawer)
classes/local/    status, meta, audit, export: stored constants and their labels
                  meta also holds the YYYYMMDD date helpers; types: the seeded default types
                  details: THE details API (save, verify, reject, the reset rule); all callers use it
                  policy: constants + THE policy API (resolve, save, delete, require_manage)
                  validity_calculator: the rule, pure (candidate, validity value objects)
                  status_repository: THE writer of local_evidencehub_status (recompute, scopes)
                  hub_access (context, templates, cohorts), hub_scope (the two capability
                  fragments), hub_filter, hub_query (base_sql, page_keys, page_details, rows),
                  hub_kpis (one statement per method, cached by generation), hub_download
classes/reportbuilder/ datasource validity, entities validity + evidence_details
classes/form/     evidence (core evidence + details in one submit), verify, policy (dynamic forms)
classes/event/    evidence_details_updated, evidence_verified, evidence_rejected,
                  validity_status_changed, validity_policy_updated
classes/task/     evaluate_validity, reconcile_validity, recompute_scope (ad hoc)
classes/privacy/  provider: worker rows in the worker's user context; actor columns kept
tests/            privacy/provider_test, local/{types,constants,meta,details,policy,
                  validity_calculator,status_repository}_test, form/*, output/*, task/*,
                  observer_test, hook_callbacks_test, marketplace_readiness_test, behat/
tests/classes/    scenario trait: users holding chosen capabilities, evidence, details;
                  held_lock_factory: refuses chosen user_<id> locks (via $CFG->lock_factory)
tests/generator/  writes rows directly (create_status() stays direct for the task tests)
```

## Versions

5.3 only, one branch (`main`), numbered in the 5.3 namespace `20261005XX`. `db/upgrade.php` starts at
2026100504 (the status row's `issensitive`); every later step ends in a savepoint no higher than
`version.php`.

## Architecture gotchas

- **Dates are calendar days stored as `YYYYMMDD` integers** (`issuedate`, `expirydate`,
  `effectiveexpiry`): a certificate's date has no time of day, and a timestamp would move it
  across midnight with the timezone. Format with `local\meta::iso_date()`; compare as integers.
- **"Missing" is never stored**: a pair the plan template requires and no status row covers is
  computed at query time (`local\status::label(null)`), so the status table never holds a row per
  required pair.
- **No table or column is named after a reserved word** of any supported database
  (`tests/schema_test.php` reads core's list): `validate` and a PostgreSQL install accept
  `sensitive`, which only MySQL reserves, and the install then fails on MariaDB alone.
- **The evidence is core's.** Create and update it through `core_competency\api`
  (`create_user_evidence()`, `update_user_evidence()`), never by writing
  `competency_userevidence`; core's privacy provider owns it and its files, this plugin's only the
  details beside it.
- **Row access always also needs core's `moodle/competency:userevidenceview` in the worker's user
  context**, filtered in SQL (`api::filter_users_with_capability_on_user_context_sql`).
- **The hub grid is two queries, not a report builder system report** (S0: one statement took
  0.5–0.8 s on MariaDB over 300k pairs; the two-step deferred join stays under 300 ms).
- **The status row carries its deciding evidence's type flag** (`local_evidencehub_status.issensitive`,
  written by `status_repository` on every write): the hub's base set reads it instead of joining meta
  and type for every pair. Any future change to a type's `issensitive` must recompute that type's
  pairs (no type management UI exists yet).
- **The hub reads the effective status, never the stored one** (`hub_query::base_sql()`): restricted
  (-1) and missing (0) are computed per viewer; grid, filters, sort, KPIs and download all read it.
  The restriction is worked out once in the innermost table, because each capability fragment's
  placeholders may appear once per statement.
- **The KPI cache has no TTL**: every status write or delete and the plan, cohort and user events
  in `db/events.php` replace the generation; core fires no event for a template's competencies, so
  the key reads `hub_kpis::template_signature()` (the one statement a cached call sends).
- **The hub's cohort check includes the page context itself**, unlike `cohort_get_cohort()`, whose
  callers are courses (`hub_access::cohorts()` says why).
- **`archive_writer::get_file_writer()` takes a file NAME**: a path makes ZipStream write the ZIP
  to stdout without a word.
- **Privacy:** deleting an actor (verifier, issuer, requester of others' dossiers) leaves their
  columns in other workers' rows, mirroring `core_competency\privacy\provider`. A new table with a
  user column goes into `get_metadata()`; `provider_test` checks every declared column exists.

- **The status is recomputed, never patched.** Every writer of a status row goes through
  `status_repository::recompute()` (lock `user_<id>`, transaction, upsert or delete); observers only
  pick pairs. Its own log entry in core's history (`desccomponent` local_evidencehub) is skipped by
  the `competency_evidence_created` observer, or each recomputation would start another.
- **A status change and its core log entry commit together** (`add_evidence()` runs inside the
  row's transaction); `validity_status_changed` fires after the commit, because internal observers
  run at once even inside a transaction. Observers queue a pair whose user lock times out
  (`recompute_or_queue()`); the tasks trace and skip a failing pair.
- **The pill of a competency follows its deciding evidence**: a sensitive deciding evidence shows
  "Restricted" on every linked item to a viewer without `viewsensitive`.
- **Templates print plain spellings**: `format_string(..., ['escape' => false])` into double stashes.
- **"Today" is the site's calendar day** (`core_date::get_server_timezone_object()`), read from
  `\core\clock`, so tests freeze it with `mock_clock_with_frozen()`.
- **The drawer's fragment checks everything itself** (`local_evidencehub_output_fragment_pair()`):
  core's `core_get_fragment` validates only the context, so view, read access, competency and
  template are re-checked there, never trusted to the hub page.
- **The drawer's side-sheet class is in its template** (`templates/drawer.mustache`), and it slides
  in with a CSS animation: core's `.modal` goes from `display: none` to block in one step, which a
  transition cannot animate (S0 spike: a class added after `create()` lost the first slide).
- **`settings.php` only adds an admin page**, so `marketplace_readiness_test` carries the
  local_dictionaries variant of the settings check (an external page and no setting is accepted).

## Testing notes

- `tests/generator/lib.php` writes rows directly (`create_meta()` creates the core evidence too);
  move each method onto the real API when its stage lands. `create_meta()` stays direct on
  purpose: the privacy scenario seeds verified rows, which `details::save()` cannot write.
- The core evidence observer runs inside the form's transaction (internal observer): the form's
  own core update resets a decided row first, so `details::save()` finds it pending.
- PHP 8.3 constructs are fine here (5.3 only): `#[\Override]`, typed class constants, attributes
  for test metadata.

## When in doubt

Follow the patterns in existing files. The codebase is internally
consistent — if a new file feels like it matches no existing shape,
re-examine the approach.
