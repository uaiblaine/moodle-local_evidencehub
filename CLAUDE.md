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
mdl ci moodle-local_evidencehub --matrix --behat   # every leg with Behat: the merge gate of a tier-3 change
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
version.php, db/access.php, db/install.php (seeds types), db/install.xml (7 tables), db/upgrade.php,
                  db/uninstall.php (core's report persistents and stored filters, the plugin's preferences)
db/hooks.php, db/events.php   tool_lp prompt (footer hook) and the local_dimensions pills
                  (competency_status_badges -> local/validity_badges); observers (classes/observer.php)
db/tasks.php      evaluate_validity (hourly), reconcile_validity (weekly), cleanup_dossier_exports (daily),
                  send_weekly_digest (hourly; the round opens Monday 07:00)
db/messages.php   dossierready, validitydue, validityexpired, verificationrequested, weeklydigest
db/services.php   local_evidencehub_get_readiness (service disabled); report_rows, report_summary,
                  set_report_preference (AJAX, classes/external/)
db/caches.php     hubgeneration, reportgeneration, reportsummary (keys and generations: local/hub_kpis)
evidence.php      the worker's evidence page (renderable output/evidence_page, amd/src/evidence_page)
policies.php      framework list / one framework's policies (output/policy_frameworks,
                  output/framework_policies, amd/src/policies); settings.php adds its admin entry
index.php         the hub: the list (output/hub_report_view, the React view's shell, core's table in its
                  noscript), the choice of a template (output/hub_choose_page); entries: settings.php (system)
                  and lib.php's category More menu
                  view=table: core's table of the pairs report; view=matrix: output/hub_matrix_page,
                  templates/hub_matrix, amd/src/hub_page
                  download=<format>: local/report_download (the report's table, the hub_downloaded event)
                  action=exportdossiers: local/dossier_export (selection, stream or task)
                  drawer: amd/src/drawer (core/modal subclass, templates/drawer), body from lib.php's
                  output_fragment_pair (output/pair_drawer, templates/pair_drawer)
js/esm/src/       the list in React: datareport/ (the mould's parts), hub/ (HubView, Band, Bar, Grid, Cards,
                  Dock, export.ts); js/esm/build holds the bundle and its manifest
verify.php        the public verification page of a dossier (output/verify_page); gated by verifydossier
                  (dossier::can_verify(), the guest role for a visitor not logged in), as tool_certificate
settings.php      the admin entries, and the dossier settings page (local/dossier_config reads them)
classes/local/    status, meta, audit, export: stored constants and their labels
                  meta also holds the YYYYMMDD date helpers; types: the seeded default types
                  details: THE details API (save, verify, reject, the reset rule); all callers use it
                  policy: constants + THE policy API (resolve, save, delete, require_manage)
                  validity_calculator: the rule, pure (candidate, validity value objects)
                  status_repository: THE writer of local_evidencehub_status (recompute, scopes)
                  pairset: THE pair set's joins and restricted rule (the report, base_sql, the datasource)
                  hub_access (context, templates, cohorts), hub_scope (the two capability fragments),
                  hub_filter (the pair set's cuts), hub_query (base_sql), hub_kpis (cache keys, generations),
                  hub_matrix (the report's workers, cells over base_sql)
                  reportengine (the headless read: rows, facets, summary), reportpreference (the reader's
                  preference), report_download, reportpersistents (WHICH of core's report persistents are the
                  plugin's: by source, never by component; the provider, the observer and the uninstall read it)
                  dossier (codes, masked name, readiness, list, revoke), dossier_config (settings),
                  dossier_builder (prepare as the requester, build into any archive_writer),
                  dossier_plan (value object), dossier_pdf (TCPDF page; barcode() is the test seam),
                  dossier_export (selection, thresholds, queue/stream, store, notify, servable_file, cleanup)
                  alerts (immediate alerts, user-context holders, recipient's language), digest (mentors, their
                  workers, the report over base_sql with the mentor's scope)
classes/reportbuilder/ local/systemreports: hub_pairs (the list), headless_table (the two-statement read);
                  local/filters: cohort, reader_select, validuntil; local/entities: validity,
                  evidence_details; datasource/validity (custom reports)
classes/form/     evidence (core evidence + details in one submit), verify, policy (dynamic forms)
classes/event/    evidence_details_updated, evidence_verified, evidence_rejected, validity_status_changed,
                  validity_policy_updated, dossier_exported, dossier_revoked, hub_downloaded
classes/task/     evaluate_validity, reconcile_validity, recompute_scope (ad hoc),
                  build_dossier_export (ad hoc, as the requester), cleanup_dossier_exports,
                  send_weekly_digest, send_verification_request (ad hoc, one queued per evidence)
classes/privacy/  provider: worker rows in the worker's user context; actor columns kept; a reader's
                  stored report filters in the reader's user context
tests/            local/, output/, external/, reportbuilder/, privacy/, form/, task/, observer_test,
                  hook_callbacks_test, schema_test, upgrade_test, uninstall_test, bundle_test,
                  marketplace_readiness_test, behat/ (hub_report.feature drives the React list)
tests/classes/    scenario trait (users with chosen capabilities, evidence, details); hub_scenario, dossier_scenario
                  (requesters, decided evidence with files, a ZIP read back), dossier_pdf_spy (the QR URLs),
                  held_lock_factory (refuses chosen user_<id> locks via $CFG->lock_factory)
tests/generator/  writes rows directly (create_status() stays direct for the task tests)
```

## Versions

5.3 only, one branch (`main`), numbered in the 5.3 namespace `20261005XX`. `db/upgrade.php` starts at
2026100504 (the status row's `issensitive`), then 2026100507 (the export's `contextid`) and 2026100519 (the old
list's preference deleted); every later step ends in a savepoint no higher than `version.php`.

## Owner decisions (2026-11-23)

- **Maturity is BETA** for the first public release (v5.3-r1); STABLE after time in production.
- **A pair valid with no expiry sorts as the least urgent** by "valid until" (after every dated pair,
  ascending); a restricted pair still sorts as urgent as a missing one. The data report stages apply it to
  `hub_query` and the pairs report's key.
- **The drawer's timeline judges a non-deciding evidence by its printed expiry**: it reads "Expired" the day
  after that date even within a technical policy's grace, because grace belongs to the pair's status, which
  the deciding evidence (shown in force) already carries.

## Architecture gotchas

The long form of a rule marked "notes" is in `docs/architecture-notes.md`, under the rule's name.

- **The family bridge is a listener, not a dependency**: the hook class in `db/hooks.php` may not exist, so
  `validity_badges` references no local_dimensions class; a pair with no status row gets a pill only under an
  explicit policy (notes).
- **Dates are calendar days stored as `YYYYMMDD` integers** (`issuedate`, `expirydate`, `effectiveexpiry`): a
  timestamp would move a certificate's date across midnight with the timezone; compare as integers (notes).
- **"Missing" is never stored**: a pair the template requires and no status row covers is computed at query
  time (`local\status::label(null)`), so the status table never holds a row per required pair.
- **No table or column is named after a reserved word** of any supported database (`tests/schema_test.php`):
  `validate` and PostgreSQL accept `sensitive`, which only MySQL reserves, and the install fails on MariaDB alone.
- **The evidence is core's**: create and update it through `core_competency\api`, never by writing
  `competency_userevidence`; core's privacy provider owns it and its files (notes).
- **Row access always also needs core's `moodle/competency:userevidenceview` in the worker's user
  context**, filtered in SQL (`api::filter_users_with_capability_on_user_context_sql`).
- **A page of the hub's list is two statements**, over the pairs report's SQL (`headless_table`; S0: one
  statement took 0.5–0.8 s on MariaDB over 300k pairs; the two-step deferred join stays under 300 ms).
- **The status row carries its deciding evidence's type flag** (`issensitive`, read instead of joining meta and
  type per pair): a change to a type's `issensitive` must recompute that type's pairs (notes).
- **The hub reads the effective status, never the stored one** (`hub_query::base_sql()`, the report's entities):
  restricted (-1) and missing (0) are computed per viewer; rows, filters, sort, figures and download all read it,
  worked out once in the innermost table, because each capability fragment's placeholders may appear once.
- **The matrix reads the list's criteria from the report** (`reportengine::workers_sql()`: stored filters, scope,
  suspended choice) and every cell from `hub_query::base_sql()`, so a worker kept by one pair shows them all (notes).
- **The matrix renders without layout shift**: server-rendered cells, a fixed table layout, text or pix icons
  only; `tests/behat/matrix.feature` measures it (notes).
- **The report's cached figures never rest on a TTL**: generations retire them (`hubgeneration`,
  `reportgeneration`), replaced by the status, plan, cohort, user and details events (the definition's one-day
  TTL only bounds dead keys); core fires no event for a template's competencies, so the key reads
  `hub_kpis::template_signature()`; the cache holds codes and ids only (notes).
- **The hub's cohort check includes the page context itself**, unlike `cohort_get_cohort()`, whose
  callers are courses (`hub_access::cohorts()` says why).
- **`archive_writer::get_file_writer()` takes a file NAME**: a path makes ZipStream write the ZIP to stdout without
  a word. Its request directory goes with the task: `dossier_export::store()` copies the ZIP before the task ends.
- **A dossier is built as the requester, wherever it is built.** `dossier_builder::prepare()` checks
  with `$USER`; the ad hoc task is queued with `set_userid()` (core's cron calls `setup_user()` with it)
  and refuses to run as anyone else. The request-time selection is cut by `hub_query::base_sql()`.
- **A prepared ZIP is served by `dossier_export::servable_file()` only**, which checks the requester, the
  export's state, the recorded hub context and the reading of every worker (notes).
- **The dossier snapshot is what the public page may show**, and `verify.php` reads nothing else: never the
  identifier, the full name or a file; a revoked dossier's page states the revocation alone (notes).
- **Dossier files are read in one array query** (`dossier_builder::files()`): a recordset costs three
  reads on PostgreSQL, and the test bounds the statement count.
- **TCPDF traps**: every cell carries its width; `dossier_pdf::font()` picks the font; no em dash or arrow in the
  PDF's own strings (notes).
- **Privacy:** deleting an actor (verifier, issuer, requester of others' dossiers) leaves their columns in other
  workers' rows, as core competencies do. A new user column goes into `get_metadata()`; `provider_test` checks it.
- **The status is recomputed, never patched**: every writer goes through `status_repository::recompute()`;
  observers only pick pairs, and skip its own core history entry, or each recomputation would start another (notes).
- **A status change and its core log entry commit together**; `validity_status_changed` fires after the commit,
  because internal observers run at once even inside a transaction; a pair whose user lock times out is queued
  (`recompute_or_queue()`) (notes).
- **The pill of a competency follows its deciding evidence**: a sensitive deciding evidence shows
  "Restricted" on every linked item to a viewer without `viewsensitive`.
- **Alerts go to roles assigned in the worker's user context only** (`alerts::grants_sql()`): a system or category
  holder would be alerted about every worker; validity alerts are an external observer (notes).
- **Messages are composed in the recipient's language** (`alerts::in_language()`); a test fakes the
  pt_br pack with a `langconfig.php` in `$CFG->dataroot/lang/pt_br`.
- **PHPUnit on PostgreSQL wraps each test in a transaction**, so the external observer never runs there
  (`preventResetByRollback()`); on MariaDB it does, so message tests redirect them (notes).
- **Every readiness verdict follows the `readinessbasis` setting** (`dossier_config::readiness_basis()`): the PDF,
  the public page and the web service; every worker refusal of the service is one error (notes).
- **Templates print plain spellings**: `format_string(..., ['escape' => false])` into double stashes.
- **"Today" is the site's calendar day** (`core_date::get_server_timezone_object()`), read from
  `\core\clock`, so tests freeze it with `mock_clock_with_frozen()`.
- **The drawer's fragment checks everything itself** (`local_evidencehub_output_fragment_pair()`): core's
  `core_get_fragment` validates only the context, so view, read access, competency and template are re-checked.
- **The drawer's side-sheet class is in its template** and it slides in with a CSS animation: core's `.modal`
  cannot be transitioned from `display: none` (notes).
- **`settings.php` names every setting `local_evidencehub/<name>`** with `<name>` + `<name>_desc` strings, places
  its pages beside local_dimensions' best effort, and never queries `user_info_field` when loaded (notes).
- **The list's rows reach core's own routes without the hub's event or file**: core's table services return
  them masked and under the same `can_view()`, as for every core system report (notes).
- **The download's event fires before the first byte**, so a send that fails after it leaves an entry without a file,
  and its row count is a separate statement from the file's read.
- **Deleting a hub's category leaves the report persistent and its readers' stored filters as orphans** (core cascades
  nothing); they stay reachable to the Privacy API through each reader's user context.
- **A report persistent is the plugin's by its source, never by its component** (`reportpersistents::is_plugins()`):
  core's services create a persistent under any component a client names, before `can_view()`; a new system report
  class joins `SOURCES` (a test lists the tree).

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
