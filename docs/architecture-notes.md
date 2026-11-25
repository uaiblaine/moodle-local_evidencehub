# Architecture notes

The long form of the gotchas in this repository's `CLAUDE.md`, which keeps each as a rule of a line or two with a
pointer here. A section is named as its rule is.

## The family bridge

`db/hooks.php` names `\local_dimensions\hook\competency_status_badges`, a class that may not exist: core records
the callback unloaded when `local_dimensions` is absent. So nothing in `local\validity_badges` may reference a
local_dimensions class, and the tones it answers are plain strings. A pair with no status row gets a pill only
under an explicit policy, never under the built-in default, so a site that has not set validity up sees no pill
on every competency.

## The matrix renders without layout shift

The server renders every cell; the table has a fixed layout with explicit widths, the column count passed as an
inline custom property; cells hold text or core's pix icons only. `tests/behat/matrix.feature` measures the shift
on a bulk seed: the generator entity `matrix seeds`, 200 workers by 25 competencies.

## The report's cached figures never rest on a TTL

Every figure is retired by a generation, never by a time to live (the one-day `ttl` of `reportsummary` in
`db/caches.php` only bounds how long a dead key lives). `hubgeneration` is replaced when a status row is
inserted, deleted or changed in its status or its `issensitive` flag (a rewrite that changes neither keeps it),
and by the plan, cohort and user events in `db/events.php`. Core fires no event for a template's competencies, so
the cache key also reads `hub_kpis::template_signature()`, the one statement a cached call sends.

The pairs report's summary, and its rows' total, hidden workers and facet counts, have their own cache and
generation (`reportsummary`, `reportgeneration`): every replacement of `hubgeneration` replaces
`reportgeneration` too, and so does a status row's change of expiry, deciding evidence or evidence count, and the
details' events. The cache holds codes and ids, never a label or a name. A generation replaced inside a transaction is replaced once more when
the request ends, so a reader arriving before the commit cannot leave pre-commit figures under the new
generation.

## A prepared ZIP is served by `dossier_export::servable_file()` only

It serves a file only when all of these hold: the file is in the requester's own user context; the export is
done and not expired; the requester holds `exportdossier` and `view` in the export's recorded hub context
(`contextid`; the template's context for a row older than that column); and the requester holds
`moodle/competency:userevidenceview` over every worker of its dossiers.

## The dossier snapshot is what the public page may show

The snapshot keeps: the masked name, the template, the issue time, each competency's code and expiry (a
competency decided by sensitive evidence always restricted, whoever issued the dossier; the PDF keeps the
requester's own view), and both verdicts (`readiness`, the real one; `readinessshown`, over the public statuses).
Never the identifier, the full name or a file. `verify.php` reads nothing else; under `verifylive` it reads the
status rows with no rights. A revoked dossier's page reads neither: it states the revocation alone.

## TCPDF traps

Found in S0: every table cell carries its width; helvetica serves Latin-1 only and freesans everything else
(`dossier_pdf::font()`); an em dash or an arrow in the PDF's own strings sets every page in freesans.

## A status change and its core log entry commit together

`add_evidence()` runs inside the status row's transaction. `validity_status_changed` fires after the commit,
because internal observers run at once even inside a transaction. Observers queue a pair whose user lock times out
(`recompute_or_queue()`); the tasks trace and skip a failing pair.

## Alerts go to roles assigned in the worker's user context only

`alerts::grants_sql()` finds the holders, then `has_capability()` honours prohibitions. A system or category
holder would be alerted about every worker. The validity alerts run in an external observer (`internal => false`),
so never for a change that rolls back. Verification requests go through an ad hoc task queued with
`checkforexisting`, because the evidence form resets and saves in one transaction before it links the competency.

## PHPUnit and the external observer

PHPUnit on PostgreSQL wraps each test in a transaction, so an external observer (the validity alerts) never runs
there: a test of them calls `preventResetByRollback()`. On MariaDB the observer runs in every test that moves a
pair into due, critical or expired, so a test asserting messages redirects them and runs on both families.

## Every readiness verdict follows `readinessbasis`

`dossier_config::readiness_basis()`: `real` (the default) or as the reader is shown it, where a restricted
competency counts as not met. It governs the PDF, the public page (read at view time; a snapshot without
`readinessshown` states no verdict under "shown") and the web service. The web service shows the effective
status, and every refusal about a worker is one error (`errorreadinessnotfound`).

## The drawer's side sheet

The side-sheet class is in `templates/drawer.mustache`, and the sheet slides in with a CSS animation: core's
`.modal` goes from `display: none` to block in one step, which a transition cannot animate. The S0 spike found
that a class added after `create()` lost the first slide.

## `settings.php`

It adds two admin pages and the dossier settings page, inside local_dimensions' admin category when that category
is already in the tree (`local\admin_tree`). Plugins load by their localised display name, so this is best effort
and falls back to `competencies`. Every setting is named `local_evidencehub/<name>`, with `<name>` and
`<name>_desc` strings. The identifier select loads its choices lazily through a closure
(`identifier_choices(...)`): core's configselect takes an array callable for the choices themselves, so loading
`settings.php` never queries `user_info_field`.

## The list's rows reach core's own routes without the hub's event or file

The `hub_downloaded` event records a file written by `index.php?download=`. Core's table routes return the list's
rows too: `core_table_get_dynamic_table_content` with a page size the client chooses, and
`core_reportbuilder_retrieve_system_report` on the mobile service. They return them masked and under the same
`can_view()`, with no event and no file, as for every core system report.

## The evidence is core's

Create and update it through `core_competency\api` (`create_user_evidence()`, `update_user_evidence()`), never by
writing `competency_userevidence`; core's privacy provider owns it and its files, this plugin's only the details
beside it.

## The status row carries its deciding evidence's type flag

`local_evidencehub_status.issensitive` is written by `status_repository` on every write: the hub's base set reads
it instead of joining meta and type for every pair. Any future change to a type's `issensitive` must recompute
that type's pairs (no type management UI exists yet).

## The status is recomputed, never patched

Every writer of a status row goes through `status_repository::recompute()` (lock `user_<id>`, transaction, upsert
or delete); observers only pick pairs. Its own log entry in core's history (`desccomponent` local_evidencehub) is
skipped by the `competency_evidence_created` observer, or each recomputation would start another.

## Dates are calendar days stored as `YYYYMMDD` integers

`issuedate`, `expirydate` and `effectiveexpiry`: a certificate's date has no time of day, and a timestamp would
move it across midnight with the timezone. Format with `local\meta::iso_date()`; compare as integers.

## The matrix reads the list's criteria from the report

`reportengine::workers_sql()` gives the workers of the report's rows under the reader's stored filters, the scope
and the suspended choice; every cell comes from `hub_query::base_sql()`, so a worker kept by one pair shows all of
their pairs. The matrix's pager counts its own workers, not the list's pairs.
