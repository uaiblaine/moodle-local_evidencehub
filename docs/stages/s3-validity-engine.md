# S3: validity engine

Stage S3 of the evidence hub plan
(`moodle-local_dimensions/docs/proposals/2026-10-29-evidence-hub/README.md`, row S3, and its
sections "Data model and performance", "Domain model" and "Gaps and edge cases"):

> `validity_calculator` (pure, no DB) with a data provider over the rule; `status_repository`
> upsert under a per-user lock; observers; `evaluate_validity` scheduled task over
> `nexttransition`; weekly reconciliation; policy admin UI (framework + competency override);
> `ACTION_LOG` write. Done when the boundary tests (expiry today, alert days exact, leap day,
> timezone, future issue, multi-evidence ties) pass and the mutation sweep holds.

Decisions D1–D12 are approved and not reopened (D4: unverified evidence counts per policy,
regulatory default no; D6: on expiry log plus an optional review request, never an automatic
proficiency demotion; D12: the last proficient rating is the first source beyond uploads). S2's
contract (`docs/stages/s2-evidence-details.md`) still holds. This note settles what the plan
leaves open for S3; each choice is a contract the tests hold.

## Scope

In: the calculator, the policy resolver, the status repository, observers that recompute, two
scheduled tasks and one ad hoc task, the policy pages, the core `ACTION_LOG` write and the
`validity_status_changed` / `validity_policy_updated` events, the validity pill on S2's evidence
page.

Out: the hub, KPIs and the grid (S4), the drawer (S5), alerts and messages (S8). No schema
change: every column S3 writes exists since S1 (`local_evidencehub_status.nexttransition` holds a
timestamp, `timemodified` is when the row was last computed).

## Pieces

### `classes/local/policy.php` (extend the S1 constants class) — policy resolution

- `policy::resolve(int $competencyid, int $frameworkid): \stdClass` returns the effective policy:
  the competency's own row (`competencyid = c`), else the framework's row (`competencyid = 0`),
  else the **default**: `kind` technical, `requireverified` 0, `requireproficiency` 0, `minhours`
  null, `alert1` 60, `alert2` 30, `gracedays` 0, `cyclemonths` 0, `onexpiry` log. A resolved
  policy says which row it came from (`source`: competency, framework, default).
- Kind defaults applied when an administrator creates a row (the form's defaults, not the
  resolver): regulatory → `requireverified` 1; technical and behavioural → 0 (D4).
- Validation (form and `policy::save()`): `alert1 ≥ alert2 ≥ 0`; `gracedays`, `cyclemonths`,
  `minhours` ≥ 0; a behavioural policy needs `cyclemonths > 0` or says "never expires"
  explicitly (`cyclemonths = 0` accepted, documented as never).

### `classes/local/validity_calculator.php` — the rule, pure

No DB, no globals, no clock: everything comes in.

`calculate(array $candidates, \stdClass $policy, int $today): result`

- `$today` is a calendar day `YYYYMMDD` (the caller computes it in the **site** timezone,
  `core_date::get_server_timezone_object()`, never the user's).
- A candidate is a value object `local\candidate` (readonly): `source` (`evidence` | `rating`),
  `metaid` (null for a rating), `userevidenceid`, `issue` (`YYYYMMDD`), `expiry` (`YYYYMMDD` or
  null = never), `complete` (details present), `verified`, `hours` (float|null).
  - Evidence candidates come from every core evidence linked to the competency and owned by the
    user, with or without a meta row (without: `complete` false).
  - A rating candidate exists when the policy kind is technical or behavioural and the user
    competency is proficient: `issue` = the day of the latest rating (`competency_evidence` row
    with a grade) in site timezone, `expiry` = `issue + cyclemonths` (month clamp,
    `meta::add_months()`) when `cyclemonths > 0`, else null. Behavioural policies count ONLY the
    rating candidate; regulatory policies NEVER count a rating.
- Counted set `E`: complete, verified if `requireverified`, `hours ≥ minhours` if set; for a
  technical policy with `requireproficiency`, nothing counts unless the user competency is
  proficient (passed in the result's input as `proficient` on the policy call:
  `calculate(..., bool $proficient)`).
- Effective expiry of a counted candidate: `expiry + gracedays` days for a technical policy,
  else `expiry`; never-expiring stays null.
- `covers` = counted with `issue ≤ today ≤ effective expiry` (the expiry day itself is valid).
- `current` = the covering candidate with the latest effective expiry (null = never beats any
  date); ties: latest issue, then highest `metaid` (a rating loses a tie to an evidence).
- Status: covers non-empty → days left = effective expiry − today (calendar days; never = no
  alert): `valid` when days left > `alert1`, `due` when ≤ `alert1`, `critical` when ≤ `alert2`;
  else counted non-empty → `expired`, effective expiry = the latest effective expiry in E; else
  any candidate → `unverified`; else no status (the caller deletes any row: "missing" is never
  stored).
- A counted candidate with a future issue date does not cover yet; on its own it yields
  `unverified`, with `nexttransition` at its issue date.
- `nexttransition` = the earliest day strictly after today among: current's effective expiry −
  `alert1`, − `alert2` (each only if still ahead), effective expiry + 1, and the issue day of
  every counted candidate not yet valid; the rating expiry likewise. Null when nothing will
  change on its own.
- Result (readonly value object `local\validity`): `status`, `metaid` of current (null for a
  rating or none), `effectiveexpiry` (`YYYYMMDD` or null), `evidencecount` (evidence candidates,
  counted or not), `nexttransition` (`YYYYMMDD` or null), `ready` (bool: valid/due/critical; an
  expired technical or behavioural policy is "at risk", not blocking — `blocking` bool on the
  result, true only for regulatory).

### `classes/local/status_repository.php` — reading candidates and writing rows

- `recompute(int $userid, int $competencyid): ?validity` under the core lock
  `user_<userid>` (`lock_config::get_lock_factory('local_evidencehub')`, short timeout, a clear
  exception), inside a transaction: reads the candidates (one query for the user's evidence
  linked to the competency with their meta rows; one for the user competency's proficiency and
  latest rating), resolves the policy, calls the calculator with today in site timezone, then
  upserts the status row by its UNIQUE (userid, competencyid) — or deletes it when the result is
  "no status". `nexttransition` is stored as the timestamp of 00:00 of that day in site timezone.
  `timechanged` is written only when the status changes; `timemodified` on every write.
- On a status change (including a first row; not on a delete) and **after** the row is written:
  the event `validity_status_changed` (other: `from` (null on a first row), `to`,
  `competencyid`), then the core log: `api::add_evidence($userid, $competencyid,
  context_user::instance($userid), evidence::ACTION_LOG, 'evidence_status_<to>',
  'local_evidencehub', $a, $recommend)` with `$recommend` true only when `to` is expired and the
  policy's `onexpiry` is review (D6). Never a proficiency or grade change.
- `recompute_for_userevidence(int $userevidenceid)`: every competency linked to it.
- `recompute_competency(int $competencyid)` and `recompute_framework(int $frameworkid)` queue
  the ad hoc task, never inline (a policy change may touch thousands of pairs).

### Observers (`db/events.php` → `classes/observer.php`, extending S2's)

Every observer only decides WHAT to recompute and calls the repository.

- `\core\event\competency_evidence_created`: read the evidence row; when its `desccomponent` is
  `local_evidencehub` → do nothing (our own log write, or the loop never ends); when its
  `descidentifier` is `evidence_evidenceofpriorlearninglinked` / `...unlinked` → recompute that
  (user, competency); when it carries a grade (a rating) → recompute that pair. Any other row:
  nothing.
- `\core\event\competency_user_competency_rated`, `_rated_in_plan`, `_rated_in_course`:
  recompute the pair (ratings also land as evidence rows; recompute is idempotent, so both paths
  are fine).
- S2's `competency_user_evidence_updated` / `_deleted` observers: add a recompute of the linked
  competencies after their S2 work (on delete core has already unlinked; the unlink events
  recompute, so delete only cleans up).
- The plugin's own `evidence_details_updated`, `evidence_verified`, `evidence_rejected`:
  `recompute_for_userevidence()`.
- `\core\event\competency_deleted`: delete its status rows and its policy row.
  `\core\event\competency_framework_deleted`: delete the framework's policy rows (its
  competencies' own deleted events clean the rest). `\core\event\user_deleted`: delete the
  user's status rows.

### Tasks (`db/tasks.php`, `classes/task/`)

- `evaluate_validity` (scheduled, hourly at a fixed minute): status rows with
  `nexttransition <= time()`, oldest first, in chunks of 500 per run (a cap so one run stays
  short), each through `recompute()`.
- `reconcile_validity` (scheduled, weekly, Sunday 03:xx): every pair that has a linked evidence,
  a status row, or (for competencies whose resolved policy counts ratings) a proficient user
  competency, recomputed in chunks; it heals a missed event. It reports counts through `mtrace`.
- `recompute_scope` (ad hoc, custom data `competencyid` or `frameworkid`): queued by a policy
  change; recomputes the scope's pairs (the same pair query as reconcile, filtered).
- One lang string per task.

### Policy pages

- `policies.php?frameworkid=<id>`: the framework's own policy and the list of its competencies
  with their effective policy (and its source), each with an Edit / Override / Remove override
  action. `require_capability('local/evidencehub:managepolicies', <the framework's context>)`
  (a framework's context is its own `contextid`: system or a category); competencies enabled.
- The edit form: a `dynamic_form` in a modal (`classes/form/policy.php`), custom data
  `frameworkid` and `competencyid` (0 = the framework row). Access in the framework's context,
  and the competency must belong to that framework (no competency of framework A edited under
  framework B's id). Save through `policy::save()`; removing an override through
  `policy::delete()`. Both fire `validity_policy_updated` (other: frameworkid, competencyid)
  and queue `recompute_scope`.
- Entry: core's framework pages have no extension point, so `policies.php` without
  `frameworkid` lists the frameworks whose context grants `managepolicies` to the user, and
  `settings.php` adds one `admin_externalpage` "Validity policies" under Site administration >
  Competencies pointing at it (`moodle/site:config` is not required: the page does its own
  checks, so the external page names `local/evidencehub:managepolicies`). A category manager
  opens the same list and sees only their frameworks. The list query stays portable (no
  family-specific SQL).

### Evidence page pill (S2's `evidence.php`)

Each evidence item shows, per linked competency, its current validity status pill (status
label, effective expiry as an ISO day), read from the status rows of that user — one query for
the page, not one per item. Sensitive-row rules from S2 apply to dates.

## Tests

PHPUnit carries the rule; Behat stays a smoke.

- `tests/local/validity_calculator_test.php` (pure, `\basic_testcase`, data providers): expiry
  today is valid; tomorrow expired; alert1 and alert2 exactly on the day (and one day before
  and after); leap day issue + 12 months; never-expiring evidence (no alert, no transition);
  future issue alone → unverified with transition at the issue day; future issue beside a valid
  one; ties (same expiry: latest issue; same issue: highest metaid; rating loses); unverified
  not counted under requireverified (control: counted without); minhours below / equal;
  incomplete evidence → unverified; gracedays only for technical; regulatory ignores ratings;
  behavioural counts only the rating, cyclic expiry; requireproficiency blocks a covering
  evidence (control: proficient); `blocking` per kind; `nexttransition` for each shape.
- `tests/local/policy_test.php`: resolution order (competency over framework over default) and
  `source`; validation rules both directions; kind defaults.
- `tests/local/status_repository_test.php`: upsert vs insert; delete when no status; today in
  site timezone (a site timezone where it is already tomorrow in UTC, and one where it is still
  yesterday); `timechanged` only on change; event and core `ACTION_LOG` row only on change and
  after the write (control: a recompute with no change writes neither); recommend only on expired
  with review (control: log); the log row carries `desccomponent` local_evidencehub.
- `tests/observer_test.php` (extend): link/unlink recompute; a rating recomputes; our own log
  row does NOT recompute (control: a foreign log row with a grade does); details events
  recompute; deletions clean up.
- `tests/task/*_test.php`: evaluate picks only due rows and caps the chunk; reconcile heals a
  hand-deleted row; recompute_scope covers exactly its scope.
- `tests/form/policy_test.php` and a page test: managepolicies both directions in the
  framework's context (a category manager on a category framework: allowed; on a system
  framework: refused); the competency/framework mismatch refused.
- Behat `tests/behat/validity_policies.feature`: an admin sets a framework policy and overrides
  one competency; the list shows the source.

Mutation spec: a `G<n>` line in `mutations/gates.conf` for every guard and every branch of the
rule above (each with the test that must redden), numbered from G59.

## Versioning

`version.php` 2026100502 (new tasks, observers, events, admin page), `CHANGELOG.md`, lang en +
pt_br sorted in lockstep (one string per task, event, status log descidentifier, admin page),
README (policies, statuses, tasks, the core log rows the plugin writes into competency
histories), the plugin `CLAUDE.md` layout.

## Review rounds (2026-10-30), folded into S3

The `security-diff-read` pass over the S3 diff found five defects; all are fixed:

1. The validity pill showed the pair's status and date even when a restricted (sensitive)
   evidence decided it: such a pill now reads "Restricted" on every item of that competency.
2. The status row committed before its core log entry: the log is now written in the same
   transaction, so a failing log rolls the row back and the next recompute retries both.
3. An observer's lock timeout lost the recompute: it now queues the pair for `recompute_scope`;
   a failing pair in a task run is traced and skipped, and a failing due row is put off an hour.
4. Names were escaped twice in the double-stash templates (`format_string(..., 'escape' => false)`).
5. Competency names on the evidence page were formatted in the system context, not the
   framework's.

The README now tells category managers to open `/local/evidencehub/policies.php`: a plugin's
settings load for site administrators only.

The Behat smoke then found two more: `policies.php` exported its renderable before
`$OUTPUT->header()` (a TypeError on every framework page), and the policy form refused every save
of a regulatory policy. A field hidden by `hideIf` is also disabled, so the browser never submits
it, and grace days and cycle months carried a server-checked `required` rule; the form tests had
always submitted every field, so they encoded the convenient fact.
`test_a_regulatory_policy_saves_without_its_hidden_fields` and gate G148 hold the fix. The page
reload after a save also opens a `core/pending` marker it never resolves, so a test waits for the
new page instead of reading the old rows.
