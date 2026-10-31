# S7: readiness dossiers

Stage S7 of the evidence hub plan
(`moodle-local_dimensions/docs/proposals/2026-10-29-evidence-hub/README.md`, row S7, section 4 "File
API, PDF and ZIP", the privacy and LGPD notes in section 5, decisions D7–D10, the mockup's screens
"Dossier export", "Dossier PDF" and "Public verification page", and the S0 PDF measurements and traps
in `spike/README.md`):

> `dossier_builder` (archive_writer), PDF per worker with QR, manifest, naming, stream vs ad hoc
> task, `dossier` + `export` rows, pluginfile, cleanup task, public `verify.php`, message
> providers. Done when the ZIP contents are asserted in PHPUnit, a sensitive file is excluded, the
> verify page shows no identifier, and the task runs as the requester.

D1–D12 are approved: D7 (sensitive files excluded from dossiers by default), D8 (idnumber by default,
a CPF profile field only by setting, never on the verification page), D9 (stream up to 10 workers /
100 MB, an ad hoc task above, ZIPs kept 7 days), D10 (the verification page shows the snapshot at
issuance and a "revoked" flag; live status off by default). The contracts of S2–S6 (`docs/stages/`)
still hold. This note settles what the plan leaves open; each choice is a contract the tests hold.
Schema: the S1 tables `local_evidencehub_dossier` and `local_evidencehub_export` already hold what
S7 writes; no new column unless the implementation proves one is needed (then an upgrade step).

## Who may export, and whom

- An "Export dossiers" action on the hub (list and matrix) for holders of
  `local/evidencehub:exportdossier` **and** `view` in the hub context. It posts the selection:
  `pairs[]` from the list (their workers are taken) or `workers[]` from the matrix, plus the current
  validated filter (template, context). One dossier per worker.
- The selection is validated server-side against the filter's visible base set (S4's access cut):
  a worker outside it is dropped, never trusted from the post; at most a configured maximum of
  workers per export (refuse above it with a clear message).
- Everything is re-checked **as the requester** where the dossier is built (the request or the ad
  hoc task): `exportdossier` and `view` in the context, the template offered there, and per worker
  `moodle/competency:userevidenceview` in the worker's user context. A worker who fails is left out
  and listed in the manifest as omitted (no reason that reveals more than "not available").

## What one worker's dossier holds

Folder `<identifier>_<lastname>-<firstname>/` (`clean_param(..., PARAM_FILE)`, de-duplicated):

- `<identifier>_dossier.pdf`: the PDF page (below).
- The core evidence files of every deciding evidence of the template's competencies (the status
  row's `metaid`), named `<identifier>_<competency idnumber>_<expiry YYYY-MM-DD or "never">_<original
  name>`, de-duplicated with a counter. Files read in **one** SQL over `{files}` for all evidence ids
  of the export (component `core_competency`, filearea `userevidence`, the workers' user contexts),
  then `get_file_instance()` per record — never `get_area_files()` in a loop.
- **Sensitive evidence (D7)**: its files are never included unless the site setting
  `includesensitivefiles` is on (default off) **and** the requester holds
  `local/evidencehub:viewsensitive` in that worker's user context. Its PDF row shows type and
  validity ("ASO: valid until …") only when the requester may see it; otherwise "Restricted".

Plus one `manifest.csv` at the root: path, size, SHA-256 of every file in the ZIP, and the omitted
workers.

### The identifier (D8)

A setting `identifierfield`: `idnumber` (default) or a custom profile field's shortname (a select of
the site's text profile fields). Read through `core_user\fields` / `profile_user_record()`. A worker
without a value falls back to the user id prefixed `u`. The identifier is printed in the ZIP names and
the PDF, **never** on the verification page.

## The PDF (one page per worker, TCPDF, in memory)

`dossier_pdf::render(stdClass $snapshot): string` (`Output('', 'S')`): the site name and logo (core's
`logo` setting through the file API, when set), "Readiness dossier", the worker's full name and
identifier, the template's name, the issue date, a table of the template's competencies with
effective status (S4's rule computed for the requester), deciding evidence type, issue and expiry
days, issuer and verification, the overall readiness (ready / not ready / at risk by policy kind),
and a QR code (`write2DBarcode(..., 'QRCODE,M', ...)`) to `verify.php?code=<code>` with the code
printed beside it. Font: `helvetica` when every string fits Latin-1, else `freesans` (S0). Every
table cell has its `width` (S0 trap). The PDF's SHA-256 is stored on the dossier row (`pdfhash`).

## Rows and the snapshot

Per worker a `local_evidencehub_dossier` row: `code` (32 hex chars from `random_bytes(16)`, UNIQUE),
`userid`, `templateid`, `exportid` (null for a streamed export? — no: every export has an export
row, streamed ones are created and finished in the same request), `createdby` (the requester),
`snapshot` (JSON: masked name, template name, issue time, each competency's name, effective status,
expiry, readiness — **no identifier, no full name, no files**), `pdfhash`, `revoked` 0, `timecreated`.
The export row: `requestedby`, `templateid`, `subjectcount`, `status` (queued/running/done/failed),
`timecreated`, `timecompleted`, `timeexpires` (async only). Event `dossier_exported` (other:
templateid, subjectcount) when an export finishes.

## Two drivers, one builder

`dossier_builder::build(\core_files\archive_writer $zip, ...)`:

- **Stream** (`archive_writer::get_stream_writer(<name>.zip, ZIP_WRITER)`) when the export has at
  most `syncmaxworkers` (default 10) workers and its files total at most `syncmaxbytes` (default 100
  MB): the download starts in the request; nothing is kept.
- **Ad hoc task** `build_dossier_export` otherwise: queued with `set_userid(<requester>)` so every
  check runs as the requester; it writes through `get_file_writer(<bare file name>)` (**a name, not a
  path**: S0 found a path makes ZipStream write to stdout), copies `get_path_to_zip()` into the file
  area `local_evidencehub/export` of the **requester's user context**, itemid = export id, before the
  task ends; marks the export done with `timeexpires` = now + `exportretentiondays` (default 7); sends
  the message below. A failure marks it failed and messages the failure.
- **Message provider** `dossierready` (`db/messages.php`, lang `messageprovider:dossierready`) to the
  requester, with a link to the file.
- **pluginfile** `local_evidencehub_pluginfile()` serves the `export` area only to its owner (the
  requester's user context and `$USER` is the requester, the export not expired, `exportdossier`
  still held in a context... at least `require_login()`), through `send_stored_file()`.
- **Cleanup** scheduled task `cleanup_dossier_exports` (daily): deletes expired exports' files and
  marks them expired; never deletes dossier rows (they back the verification page).

## The public verification page `verify.php`

- No `require_login()` (it must work under `forcelogin` and for a contractor without an account);
  `$PAGE` in the system context, a minimal layout, `noindex`.
- `code` is `PARAM_ALPHANUM`, exactly 32 hex characters, else the same "not found" as an unknown code
  (no oracle). Looked up by the UNIQUE index.
- Shows the snapshot only: the masked name ("J*** S***": first letter of each name part), the template,
  the issue date, each competency's status and expiry **as at issuance**, overall readiness, "Revoked
  on …" when revoked, and the PDF's SHA-256 so a holder can compare. **Never** the identifier, the full
  name, files, or anything not in the snapshot.
- D10: a setting `verifylive` (default off) adds the current effective status of each competency
  computed with no viewer rights (a sensitive deciding evidence reads "Restricted").
- An event `dossier_verified`? No: the page is anonymous; no event, no log row with the code.

## Transparency and revocation

- The worker's evidence page (S2's `evidence.php`) lists "Shared dossiers" about them: when, by whom,
  for which template, revoked or not (LGPD art. 18) — visible to the worker and to readers of their
  evidence.
- Revoke: a POST with sesskey from that list, for the dossier's creator or a holder of
  `exportdossier` in the system context; sets `revoked`, `timerevoked`; the verification page then says
  so. Event `dossier_revoked`.

## Privacy

The provider already declares both tables (S1). Add the `export` file area: exported and deleted with
the requester's data (`writer::with_context()->export_area_files()`, `delete_area_files`), and
`get_contexts_for_userid` / `get_users_in_context` cover it through the export rows. The snapshot
holds the masked name only; the dossier row stays the worker's under the provider (S1 rules).

## Settings

`includesensitivefiles` (checkbox, default 0), `identifierfield` (select, default idnumber),
`syncmaxworkers` (10), `syncmaxbytes` (100 MB), `maxexportworkers` (e.g. 500), `exportretentiondays`
(7), `verifylive` (0). `settings.php` already adds admin pages; add an `admin_settingpage` (strings
`<key>` + `<key>_desc`). `marketplace_readiness_test` checks setting names (`local_evidencehub/<name>`)
and the README names every setting.

## Tests

- `tests/local/dossier_builder_test.php`: build into a file writer and open the ZIP
  (`ZipArchive`): folders and names per worker (identifier, idnumber, expiry, counter on clash), the
  PDF present, `manifest.csv` with correct SHA-256s and the omitted worker; **a sensitive file excluded**
  by default, excluded without `viewsensitive` even with the setting on, included with both (control);
  a worker outside the requester's scope omitted; the snapshot JSON has no identifier and no full name;
  `pdfhash` equals the stored PDF's hash; the files read in one query (`perf_get_reads` bound).
- `tests/local/dossier_pdf_test.php`: the URL handed to `write2DBarcode()` (a seam), the font choice
  (Latin-1 → helvetica, other → freesans), every row's cells sized.
- `tests/task/build_dossier_export_test.php`: **the task runs as the requester** (a check that only the
  requester passes, and a control where the queued userid is another user and the export fails or omits);
  the file stored in the requester's user context; the message sent (message sink); failure path.
- `tests/task/cleanup_dossier_exports_test.php`: expired files deleted, rows kept, unexpired kept.
- `tests/verify_page_test.php`: `require()` of `verify.php` with a valid, an unknown, a malformed and a
  revoked code; the page shows **no identifier** (control: the identifier is in the PDF) and no full
  name; `verifylive` off/on.
- pluginfile tests: owner served; another user refused; expired refused.
- Hub tests: the action only with `exportdossier`; the selection validated against the visible set;
  the threshold picks stream vs task.
- Behat `tests/behat/dossier.feature` (thin): a manager selects two workers in the matrix, exports;
  with `syncmaxworkers` = 1 the export is queued, the ad hoc task runs (`I run all adhoc tasks`), and the
  "dossier ready" notification appears; the verification page opened by its code shows the masked name.

Mutation spec: `G269`+ for every guard listed above.

## Versioning

`version.php` 2026100507 (messages, tasks, settings, pluginfile), `CHANGELOG.md`, lang en + pt_br
sorted in lockstep, README (dossiers, settings, the verification page, the retention, what a dossier
holds and that sensitive files stay out by default), the plugin `CLAUDE.md`.

## Amendments after the security read (2026-10-30)

- **The public snapshot never carries a sensitive-decided status.** A competency whose deciding
  evidence is sensitive (the status row's `issensitive` or its type's flag) is stored in the snapshot as
  restricted (code -1, no expiry), whoever builds the dossier, because `verify.php` is anonymous. The
  PDF, which goes to the requester, keeps the requester-relative rule. **Privacy note:** the overall
  readiness in the snapshot (and the PDF) is the worker's real one, computed from the stored statuses:
  certifying it is the dossier's purpose, and it reveals no status or date of its own; it can reveal,
  for a worker whose only unmet competency would be a sensitive one, that it is met.
- **The export row records the hub's context** (`local_evidencehub_export.contextid`, nullable for
  older rows; upgrade step 2026100507). A download requires `exportdossier` and `view` there (the
  template's context for a row without it) and `moodle/competency:userevidenceview` over every worker
  the ZIP holds a dossier about; one worker no longer readable refuses the ZIP.
- **Manifest cells** starting with `=`, `+`, `-`, `@`, a tab or a carriage return get a leading single
  quote (CSV formula injection).
- **The ad hoc task marks the export done inside its transaction**; the event and the message follow
  the commit and can never turn it failed. The cleanup also expires exports left queued, running or
  failed past the retention period, with any file they own.
- **Each dossier row is written before its PDF enters the archive**, so a streamed PDF's QR code always
  resolves; a later failure marks the export failed and keeps the rows.
- **Export and revocation are POST only**, with sesskey; revocation is one conditional UPDATE.
- **`verify.php` reads the code raw**, lowercases it and accepts exactly 32 hex characters.

## Review rounds (2026-10-30), folded into S7

The `security-diff-read` pass's findings are recorded under "Amendments" above; all are fixed, with the
upgrade step 2026100507 (`local_evidencehub_export.contextid`). The sweep then found two test gaps:
`includesensitivefiles` and `verifylive` are installed as '0', so no test told "only '1' is on" apart
from "anything but '0' is on" until `dossier_config_test` cleared the settings; and G325 named a test
that builds its export through `queue()` while the mutation touched `create()` only. The final gate:
`--matrix --behat` 4/4 legs (372 tests and 10 scenarios each; the later config test runs in the
sweep's baseline), and 334/334 mutations on both database families.
