# S9: the family bridge and the release

Stage S9 of the evidence hub plan
(`moodle-local_dimensions/docs/proposals/2026-10-29-evidence-hub/README.md`, row S9, decision D1):

> `local_dimensions` hook `competency_status_badges` (its own small PR on `main`), pills in the plan
> overview and tracker; docs, screenshots, `security-diff-read`, MDL Shield review, release. Done when
> the hook is tested in both plugins, the matrix is green and the owner has reviewed it.

D1 (approved): neither plugin requires the other; the hook is the whole contract. The contracts of
S2–S8 (`docs/stages/`) still hold; in particular a pair decided by a sensitive evidence reads
"Restricted", without its date, to a viewer without `local/evidencehub:viewsensitive` over the worker,
and the worker always sees their own (S4, `hub_scope`; S2, `details::is_restricted()`).

## Part A: the hook, in `local_dimensions` (its own pull request on `main`, 5.3 only)

New features land on `main` only (local_dimensions `CLAUDE.md`); the 4.5/5.1/5.2 branches do not get
the hook, and `local_evidencehub` is 5.3 only anyway.

### `classes/hook/competency_status_badges.php`

A final class, `#[\core\attribute\label(...)]` and `#[\core\attribute\tags('local_dimensions')]`, the
shape of core's own hooks (`core\hook\output\before_footer_html_generation`). Constructed by
local_dimensions only; a listener reads and adds.

- `__construct(int $userid, array $competencyids)`: the learner whose competencies are shown (the plan
  owner, never the viewer) and the competency ids on the page, as ints.
- `get_userid(): int`, `get_competencyids(): array`.
- `add_badge(int $competencyid, string $label, string $tone): void`.
  - `$label` is **plain** text (the template's double stash escapes it); the listener composes it in
    the viewer's language.
  - `$tone` is one of the class constants `TONE_SUCCESS`, `TONE_WARNING`, `TONE_DANGER`, `TONE_INFO`,
    `TONE_NEUTRAL` (`success`, `warning`, `danger`, `info`, `neutral`), the family's tone tokens; any
    other value is a `coding_exception` (it becomes a CSS class).
  - A competency id not on the page is ignored (no exception: a listener answering from a cached list
    must not break the page), as is an empty label.
  - The component adding the badge is not recorded: the badge carries no link and no HTML, so there is
    nothing to attribute.
- `get_badges(int $competencyid): array`: the list of `['label' => ..., 'tone' => ...]` in the order
  added; `[]` when none.

No URL and no HTML on purpose: the plan overview's header is a `<button>` (the accordion toggle), where
a link is invalid, and a plain label keeps the listener from injecting markup into a page it does not
own.

### Dispatch

`\core\di::get(\core\hook\manager::class)->dispatch($hook)`, once per page, after the page's own data is
built:

- **Plan overview** (`view_plan_summary_page::export_for_template()`): one dispatch with every
  competency id of `$data['competencies']`, after the loop, before the sort; each row gains
  `statusbadges` (the list above) and `hasstatusbadges`.
- **Tracker** (`view_competency_page::export_for_template()`): one dispatch with the hero's competency
  id when there is a competency; `$data['hero']` gains `statusbadges` and `hasstatusbadges`.
- The owner passed is the page's learner (`$plan->get('userid')`, `$this->userid`); the listener decides
  what the current user may see. The tracker dispatches nothing when the page has no competency.

### Templates and CSS

- A partial `templates/status_badges.mustache`: `{{#statusbadges}}<span class="local-dimensions-grade-badge
  local-dimensions-status-badge local-dimensions-status-badge-{{tone}}">{{label}}</span>{{/statusbadges}}`,
  with its example context.
- Included in `view_plan_summary.mustache` inside the header content, after the existing badges; in
  `hero_header.mustache` after the title (the partial is shared, so the plan overview's hero, which has
  no badges, renders nothing).
- `styles.css`: one rule per tone on `.local-dimensions-status-badge-<tone>`, edge/tint/ink from the
  tone's tokens, qualified by the plugin class (fleet rule: plugin CSS loses to a bare core class).

### Tests (local_dimensions)

- `tests/hook/competency_status_badges_test.php`: the accessors; badges in order per competency; an id
  not on the page and an empty label ignored; an unknown tone refused.
- `tests/output/view_plan_summary_page_test.php` and `view_competency_page_test.php`: with a test
  listener registered through `\core\di` / `hook\manager::phpunit_get_instance()`, the exported rows carry
  the badges for the right competency and the dispatched hook carries the owner (not the viewer) and
  exactly the page's ids; without a listener, `hasstatusbadges` is false (control).
- A scan in the existing template tests (or the mustache lint) that the partial renders with its example.

Version `2026100516`, `CHANGELOG.md`, README section "For developers: competency status badges".

## Part B: the bridge, in `local_evidencehub`

### `classes/local/validity_badges.php` (testable without local_dimensions)

`validity_badges::for_user(int $userid, array $competencyids): array` → competency id ⇒
`['label' => string, 'tone' => string]`, for the current user:

1. Nothing when competencies are disabled or the current user cannot read the worker's evidence
   (`user_evidence::can_read_user($userid)`, the rule of `evidence.php`). A guest gets nothing.
2. One query: the worker's status rows for those competencies (`status`, `effectiveexpiry`,
   `issensitive`), plus the explicit policy rows (own or framework) that apply to them.
3. A pair with a status row: when `issensitive` and the viewer may not see the worker's sensitive
   evidence (`details::can_see_sensitive($userid)`; the worker always may), the label is "Validity:
   Restricted", tone neutral, no date. Otherwise "Validity: <status label>", followed by the effective
   expiry for compliant, due, critical and expired ("Validity: Due soon, 2027-03-01").
4. A pair with no status row gets "Validity: Pending — no evidence" only when an **explicit** policy
   (competency or framework row) applies; under the built-in default nothing is shown, so a site that has
   not set validity up sees no pill on every competency.
5. Tones: compliant → success; due → warning; critical and expired → danger; awaiting verification →
   info; missing and restricted → neutral.

### `hook_callbacks::competency_status_badges()`

Registered in `db/hooks.php` for `\local_dimensions\hook\competency_status_badges::class`; core records
a callback for a hook class that does not exist without loading it, so the registration is harmless
without local_dimensions. The callback passes `for_user()`'s answer to `add_badge()`. `version.php`
`2026100509` (a `db/hooks.php` change).

### CI

`plugin-dependencies: uaiblaine/moodle-local_dimensions,main` in `ci.yml` (local_dimensions is public),
so the callback's tests run in CI; they skip with a reason when the hook class is absent. Part A merges
first.

### Tests (local_evidencehub)

- `tests/local/validity_badges_test.php`: each status → label and tone; the expiry shown for the four
  dated states and not for awaiting verification; sensitive pair restricted for a viewer without
  `viewsensitive` (control: with it, and the worker themself); a viewer who cannot read the worker gets
  nothing (control: a mentor through a user-context role gets the pills); missing pair with an explicit
  competency policy and with a framework policy shows "no evidence" (control: default policy shows
  nothing); ids not asked for are not answered; guest gets nothing.
- `tests/hook_callbacks_test.php`: with local_dimensions present, dispatching the hook through the
  manager yields the badges; skipped otherwise.
- Behat (`tests/behat/bridge.feature`; CI installs local_dimensions, so no skip tag): the worker opens
  the plan overview and sees "Validity: Compliant, ..." beside a competency, and a manager without
  `viewsensitive` sees "Validity: Restricted" on a sensitive one.

Mutation spec: `G377`+ for every guard listed.

## Part C: docs and release

- README of both plugins (the hook; the pills, who sees them, the restricted rule, why the default
  policy shows none); screenshots of the hub, the drawer, the matrix, the dossier PDF and the pills, on
  the demo data, under `docs/screenshots/`.
- `security-diff-read` over both diffs. MDL Shield review only if the owner posts it (credits).
- Release: `v5.3-r1` of local_evidencehub after the owner's review, from the merged `main`, with the
  GitHub pipeline green on the tagged commit; not part of the pull request.

Left out of S9: the link from the Competency hub's context bar to the evidence hub (proposal, "What it
reuses"). It would make local_dimensions name another plugin and its capability; if wanted, it is a second
hook of the same shape.

## Review rounds (2026-10-31), folded into S9

The `security-diff-read` pass over both diffs found no leak: the hook carries the page's learner, set on the
server, and `validity_badges` checks guest, competencies and read access itself, so another plugin dispatching
the hook with any user id learns nothing the viewer could not read on the evidence page. Two points were fixed:
the pill read only the stored `issensitive`, while the drawer and the dossier also read the deciding evidence's
type, so a type made sensitive after the last recompute showed its pair's date (now stored OR live, test
`test_a_pair_whose_type_became_sensitive_is_restricted`, gate G390); and an unknown or deleted worker made
`can_read_user()` throw (now no pills, G391). Left as noted: the plan overview dispatches for any viewer who
may read the plan, so every listener must check access itself (the hook's docblock and README say so).

The sweep found G377 (the guest check) held by no test, since a guest cannot read evidence by default; its test
now gives the guest role `userevidenceview`. Deviations from the text above: the missing pair reads "Validity:
Pending, no evidence" (the hub's own `status_missing`), and the lookups are two or three statements, not one.
