<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_evidencehub\local;

use core_competency\plan;
use core_files\archive_writer;

/**
 * One builder of readiness dossiers for two drivers: the request's streamed ZIP and the ad hoc task's file.
 *
 * {@see self::prepare()} checks everything as the current user, who is the requester in the request and in
 * the ad hoc task (core runs a task queued with set_userid() as that user): exportdossier and view in the
 * hub's context, the template offered there, and per worker core's userevidenceview in the worker's user
 * context. {@see self::build()} then writes one folder per worker (the PDF page and the files of each
 * deciding evidence) and manifest.csv into any archive_writer, and stores a dossier row per worker.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class dossier_builder {
    /** @var string The manifest's name at the ZIP's root. */
    public const string MANIFEST = 'manifest.csv';

    /**
     * The builder of one template's dossiers in a hub context.
     *
     * @param \context $context The hub's context: the system or a course category.
     * @param int $templateid The plan template.
     * @param dossier_pdf|null $pdf The PDF renderer; core's TCPDF one when null.
     */
    public function __construct(
        /** @var \context The hub's context. */
        private readonly \context $context,
        /** @var int The plan template. */
        private readonly int $templateid,
        /** @var dossier_pdf|null The PDF renderer. */
        private readonly ?dossier_pdf $pdf = null,
    ) {
    }

    /**
     * Refuse a context the hub does not open in, and a user without view and exportdossier there.
     *
     * @param \context $context The hub's context.
     * @return void
     * @throws \moodle_exception When the context is neither the system nor a course category.
     * @throws \required_capability_exception Without local/evidencehub:view or local/evidencehub:exportdossier.
     */
    public static function require_export(\context $context): void {
        hub_access::require_view($context);
        require_capability('local/evidencehub:exportdossier', $context);
    }

    /**
     * Read and check, as the current user, what the dossiers of some workers will hold.
     *
     * A worker who is gone, has no active plan from the template any more, or whose evidence the user
     * may not read is left out and listed as omitted, with no reason.
     *
     * @param array $userids The workers asked for.
     * @return dossier_plan
     * @throws \moodle_exception When the template is not offered in the context.
     * @throws \required_capability_exception When the user may not export there.
     */
    public function prepare(array $userids): dossier_plan {
        global $DB;
        self::require_export($this->context);
        // The one check of the template: the filter accepts only a template the context offers to the user.
        $filter = hub_filter::from_values(['templateid' => $this->templateid], $this->context);
        $record = $DB->get_record('competency_template', ['id' => $this->templateid], 'id, shortname, contextid', MUST_EXIST);
        $template = (object) [
            'id' => $this->templateid,
            'name' => format_string(
                $record->shortname,
                true,
                ['context' => \context::instance_by_id((int) $record->contextid), 'escape' => false]
            ),
            'contextid' => (int) $record->contextid,
        ];
        $columns = $this->columns($filter);

        $asked = array_values(array_unique(array_filter(array_map('intval', $userids), fn(int $id): bool => $id > 0)));
        $workers = [];
        foreach ($this->workers($asked) as $worker) {
            if (has_capability('moodle/competency:userevidenceview', \context_user::instance($worker->userid))) {
                $workers[$worker->userid] = $worker;
            }
        }
        $omitted = array_values(array_diff($asked, array_keys($workers)));

        $statuses = $this->statuses(array_keys($workers), array_column($columns, 'id'));
        $wanted = [];
        foreach ($workers as $worker) {
            $worker->rows = $this->rows($worker, $columns, $statuses[$worker->userid] ?? []);
            // The worker's real readiness, whatever the requester may see of each competency: it feeds the
            // snapshot, which is issued once and read by everyone.
            $worker->readiness = dossier::readiness(array_map(
                fn(\stdClass $row): array => [$row->realcode, $row->kind],
                $worker->rows
            ));
            // The readiness of what this requester is shown, for the PDF under the "shown" basis.
            $worker->readinessshown = dossier::readiness(array_map(
                fn(\stdClass $row): array => [$row->code, $row->kind],
                $worker->rows
            ));
            foreach ($worker->rows as $row) {
                if ($row->userevidenceid !== null && $row->withfiles) {
                    $wanted[$row->userevidenceid] = true;
                }
            }
        }
        $contextids = array_map(fn(int $userid): int => \context_user::instance($userid)->id, array_keys($workers));
        $files = self::files(array_keys($wanted), $contextids);

        $bytes = 0;
        foreach ($workers as $worker) {
            $worker->files = [];
            foreach ($worker->rows as $row) {
                if ($row->userevidenceid === null || !$row->withfiles) {
                    continue;
                }
                foreach ($files[$row->userevidenceid] ?? [] as $file) {
                    $name = clean_param(
                        implode('_', [$worker->fileid, $row->idnumber, $row->expiryname, $file->get_filename()]),
                        PARAM_FILE
                    );
                    $worker->files[] = [$name, $file];
                    $bytes += (int) $file->get_filesize();
                }
            }
        }
        return new dossier_plan($template, array_values($workers), $omitted, $bytes);
    }

    /**
     * The template's competencies in its order, with their policy kind, plain name and ID number.
     *
     * @param hub_filter $filter The validated filter of the template.
     * @return array List of records: id, name, idnumber (as in file names), kind.
     */
    private function columns(hub_filter $filter): array {
        global $DB;
        $columns = hub_matrix::columns($filter);
        if (!$columns) {
            return [];
        }
        $idnumbers = $DB->get_records_list('competency', 'id', array_column($columns, 'id'), '', 'id, idnumber');
        return array_map(fn(\stdClass $column): \stdClass => (object) [
            'id' => $column->id,
            'name' => format_string(
                $column->shortname,
                true,
                ['context' => \context::instance_by_id($column->frameworkcontextid), 'escape' => false]
            ),
            'idnumber' => clean_param((string) ($idnumbers[$column->id]->idnumber ?? ''), PARAM_FILE) ?: 'c' . $column->id,
            'kind' => $column->kind,
        ], $columns);
    }

    /**
     * The workers asked for who exist and have an active plan from the template, by name, with their identifier.
     *
     * @param array $userids The workers asked for.
     * @return array List of records: userid, firstname, lastname, fullname, identifier, fileid.
     */
    private function workers(array $userids): array {
        global $DB;
        if (!$userids) {
            return [];
        }
        $field = dossier_config::identifier_field();
        $fields = \core_user\fields::for_name()->including('idnumber', $field)->get_sql('u', true);
        [$userin, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'dbw');
        $sql = "SELECT u.id{$fields->selects}
                  FROM {user} u
                       {$fields->joins}
                 WHERE u.id $userin AND u.deleted = 0
                       AND EXISTS (SELECT 1
                                     FROM {" . plan::TABLE . "} p
                                    WHERE p.userid = u.id AND p.templateid = :dbtemplateid AND p.status = :dbactive)
              ORDER BY u.lastname, u.firstname, u.id";
        $params += $fields->params + ['dbtemplateid' => $this->templateid, 'dbactive' => plan::STATUS_ACTIVE];
        $workers = [];
        $rs = $DB->get_recordset_sql($sql, $params);
        foreach ($rs as $user) {
            $userid = (int) $user->id;
            $value = trim((string) ($user->$field ?? ''));
            $fileid = clean_param($value, PARAM_FILE);
            if ($fileid === '') {
                // No value, or none that survives as a file name: the user id stands in for both.
                $value = $fileid = 'u' . $userid;
            }
            $workers[] = (object) [
                'userid' => $userid,
                'firstname' => (string) $user->firstname,
                'lastname' => (string) $user->lastname,
                'fullname' => fullname($user, true),
                'identifier' => $value,
                'fileid' => $fileid,
            ];
        }
        $rs->close();
        return $workers;
    }

    /**
     * The status rows of the workers in the template's competencies, with their deciding evidence and type.
     *
     * @param array $userids The workers kept.
     * @param array $competencyids The template's competencies.
     * @return array User id => competency id => record.
     */
    private function statuses(array $userids, array $competencyids): array {
        global $DB;
        if (!$userids || !$competencyids) {
            return [];
        }
        [$userin, $userparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'dbsu');
        [$compin, $compparams] = $DB->get_in_or_equal($competencyids, SQL_PARAMS_NAMED, 'dbsc');
        $sql = "SELECT s.id, s.userid, s.competencyid, s.status, s.effectiveexpiry, s.issensitive, s.metaid,
                       m.userevidenceid, m.issuedate, m.expirydate, m.issuer, m.verifystatus,
                       t.name AS typename, t.issensitive AS typesensitive
                  FROM {" . status_repository::TABLE . "} s
             LEFT JOIN {" . details::TABLE . "} m ON m.id = s.metaid
             LEFT JOIN {local_evidencehub_type} t ON t.id = m.typeid
                 WHERE s.userid $userin AND s.competencyid $compin";
        $statuses = [];
        $rs = $DB->get_recordset_sql($sql, $userparams + $compparams);
        foreach ($rs as $record) {
            $statuses[(int) $record->userid][(int) $record->competencyid] = $record;
        }
        $rs->close();
        return $statuses;
    }

    /**
     * One worker's row per competency, as the requester may see it.
     *
     * The effective status follows the hub ({@see hub_query::base_sql()}): no status row is missing, and a pair
     * decided by a sensitive evidence is restricted to a requester who is not the worker and lacks viewsensitive.
     * A restricted row keeps its status only. The deciding evidence's files go in when its type is not sensitive,
     * or when the site includes sensitive files and the requester holds viewsensitive in the worker's user context.
     *
     * @param \stdClass $worker The worker.
     * @param array $columns The template's competencies.
     * @param array $statuses Competency id => status record of the worker.
     * @return array List of records: competencyid, name, idnumber, kind, code (as the requester may see it),
     *     realcode (the stored status, or missing), sensitive (decided by a sensitive evidence), restricted,
     *     typename, issuedate, expiry (effective, YYYYMMDD or null), expiryname, issuer, verifystatus,
     *     userevidenceid, withfiles.
     */
    private function rows(\stdClass $worker, array $columns, array $statuses): array {
        $cansee = details::can_see_sensitive($worker->userid);
        $usercontext = \context_user::instance($worker->userid);
        $sensitivefiles = dossier_config::include_sensitive_files()
            && has_capability('local/evidencehub:viewsensitive', $usercontext);
        $rows = [];
        foreach ($columns as $column) {
            $status = $statuses[$column->id] ?? null;
            $row = (object) [
                'competencyid' => $column->id,
                'name' => $column->name,
                'idnumber' => $column->idnumber,
                'kind' => $column->kind,
                'code' => status::EFFECTIVE_MISSING,
                'realcode' => status::EFFECTIVE_MISSING,
                'sensitive' => false,
                'restricted' => false,
                'typename' => null,
                'issuedate' => null,
                'expiry' => null,
                'expiryname' => 'never',
                'issuer' => null,
                'verifystatus' => null,
                'userevidenceid' => null,
                'withfiles' => false,
            ];
            $rows[] = $row;
            if ($status === null) {
                continue;
            }
            $sensitive = (int) $status->issensitive === 1 || (int) $status->typesensitive === 1;
            $row->sensitive = $sensitive;
            $row->realcode = (int) $status->status;
            if ((int) $status->issensitive === 1 && !$cansee) {
                $row->code = status::EFFECTIVE_RESTRICTED;
                $row->restricted = true;
                continue;
            }
            $row->code = (int) $status->status;
            $row->expiry = $status->effectiveexpiry === null ? null : (int) $status->effectiveexpiry;
            if ($status->userevidenceid === null) {
                continue;
            }
            $row->typename = $status->typename === null ? null : format_string(
                $status->typename,
                true,
                ['context' => \context_system::instance(), 'escape' => false]
            );
            $row->issuedate = (int) $status->issuedate;
            $row->expiryname = $status->expirydate === null ? 'never' : meta::iso_date((int) $status->expirydate);
            $row->issuer = $status->issuer;
            $row->verifystatus = (int) $status->verifystatus;
            $row->userevidenceid = (int) $status->userevidenceid;
            $row->withfiles = !$sensitive || $sensitivefiles;
        }
        return $rows;
    }

    /**
     * The files of some core evidence records, in one statement over {files}, never get_area_files() in a loop.
     *
     * @param array $userevidenceids The core evidence ids (the files' itemid).
     * @param array $contextids The workers' user contexts, where core stores those files.
     * @return array Evidence id => list of stored_file, by path and name.
     */
    public static function files(array $userevidenceids, array $contextids): array {
        global $DB;
        if (!$userevidenceids || !$contextids) {
            return [];
        }
        [$itemin, $itemparams] = $DB->get_in_or_equal($userevidenceids, SQL_PARAMS_NAMED, 'dbfi');
        [$ctxin, $ctxparams] = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED, 'dbfc');
        $sql = "SELECT f.*
                  FROM {files} f
                 WHERE f.component = :dbcomponent AND f.filearea = :dbfilearea
                       AND f.itemid $itemin AND f.contextid $ctxin AND f.filename <> :dbdot
              ORDER BY f.itemid, f.filepath, f.filename, f.id";
        $params = $itemparams + $ctxparams + ['dbcomponent' => 'core_competency', 'dbfilearea' => 'userevidence', 'dbdot' => '.'];
        $fs = get_file_storage();
        $files = [];
        // An array, not a recordset: one read on every database (a recordset is three on PostgreSQL).
        foreach ($DB->get_records_sql($sql, $params) as $record) {
            $files[(int) $record->itemid][] = $fs->get_file_instance($record);
        }
        return $files;
    }

    /**
     * Write a plan's dossiers into an archive and finish it: a folder per worker with the PDF and the files,
     * and manifest.csv; store a dossier row per worker.
     *
     * @param archive_writer $zip The archive: a stream in the request, a file in the ad hoc task.
     * @param dossier_plan $plan The plan, from {@see self::prepare()}.
     * @param \stdClass $export The export row the dossiers belong to.
     * @return array The dossier rows stored, in the plan's order.
     */
    public function build(archive_writer $zip, dossier_plan $plan, \stdClass $export): array {
        global $DB, $SITE, $USER;
        $now = \core\di::get(\core\clock::class)->time();
        $issued = meta::iso_date(status_repository::site_day($now));
        $renderer = $this->pdf ?? new dossier_pdf();
        $sitename = format_string($SITE->fullname, true, ['context' => \context_system::instance(), 'escape' => false]);
        $identifierlabel = \core_user\fields::get_display_name(dossier_config::identifier_field());
        $manifest = [];
        $folders = [];
        $dossiers = [];
        // The PDF's verdict follows the site's readiness basis setting.
        $shown = dossier_config::readiness_basis() === dossier_config::READINESS_SHOWN;
        foreach ($plan->workers as $worker) {
            $pdfreadiness = $shown ? $worker->readinessshown : $worker->readiness;
            $folder = clean_param($worker->fileid . '_' . $worker->lastname . '-' . $worker->firstname, PARAM_FILE);
            $folder = self::unique($folder, $folders);
            $names = [];
            $code = dossier::new_code();
            $pdf = $renderer->render((object) [
                'sitename' => $sitename,
                'fullname' => $worker->fullname,
                'identifierlabel' => $identifierlabel,
                'identifier' => $worker->identifier,
                'template' => $plan->template->name,
                'issued' => $issued,
                'rows' => array_map([self::class, 'pdf_row'], $worker->rows),
                'readiness' => dossier::readiness_label($pdfreadiness),
                'code' => $code,
                'verifyurl' => (new \moodle_url('/local/evidencehub/verify.php', ['code' => $code]))->out(false),
            ]);
            // The row first: once the PDF enters a stream it may reach the client, and its QR code must resolve.
            $dossier = (object) [
                'code' => $code,
                'userid' => $worker->userid,
                'templateid' => $plan->template->id,
                'exportid' => (int) $export->id,
                'createdby' => (int) $USER->id,
                'snapshot' => json_encode(self::snapshot($worker, $plan->template->name, $now)),
                'pdfhash' => hash('sha256', $pdf),
                'revoked' => 0,
                'timecreated' => $now,
            ];
            $dossier->id = $DB->insert_record(dossier::TABLE, $dossier);
            $dossiers[] = $dossier;
            $path = $folder . '/' . self::unique($worker->fileid . '_dossier.pdf', $names);
            $zip->add_file_from_string($path, $pdf);
            $manifest[] = [$path, strlen($pdf), hash('sha256', $pdf), ''];

            foreach ($worker->files as [$name, $file]) {
                $path = $folder . '/' . self::unique($name, $names);
                $zip->add_file_from_stored_file($path, $file);
                $manifest[] = [$path, (int) $file->get_filesize(), self::sha256($file), ''];
            }
        }
        $notavailable = get_string('dossiernotavailable', 'local_evidencehub');
        foreach ($plan->omitted as $userid) {
            $manifest[] = ['u' . $userid . '/', '', '', $notavailable];
        }
        $zip->add_file_from_string(self::MANIFEST, self::csv($manifest));
        $zip->finish();
        return $dossiers;
    }

    /**
     * What the public verification page shows: no identifier, no full name, no files.
     *
     * A competency decided by a sensitive evidence is always stored restricted, without its expiry, whoever
     * builds the dossier: the page is anonymous, while the requester's own right to see it covers the PDF
     * only. It states two verdicts, so the verification page can follow the site's basis at view time
     * ({@see dossier_config::readiness_basis()}): readiness is the worker's real one ({@see self::prepare()}),
     * readinessshown the one over the snapshot's own statuses, where a sensitive row is restricted and so
     * counts as not met.
     *
     * @param \stdClass $worker A worker of the plan.
     * @param string $template The template's plain name.
     * @param int $now The issue time.
     * @return array name (masked), template, issued, competencies (id, name, status, expiry), readiness,
     *     readinessshown.
     */
    public static function snapshot(\stdClass $worker, string $template, int $now): array {
        return [
            'name' => dossier::mask_name($worker->firstname, $worker->lastname),
            'template' => $template,
            'issued' => $now,
            'competencies' => array_map(fn(\stdClass $row): array => [
                'id' => $row->competencyid,
                'name' => $row->name,
                'status' => $row->sensitive ? status::EFFECTIVE_RESTRICTED : $row->realcode,
                'expiry' => $row->sensitive ? null : $row->expiry,
            ], $worker->rows),
            'readiness' => $worker->readiness,
            'readinessshown' => dossier::readiness(array_map(
                fn(\stdClass $row): array => [$row->sensitive ? status::EFFECTIVE_RESTRICTED : $row->realcode, $row->kind],
                $worker->rows
            )),
        ];
    }

    /**
     * One row of the PDF's table, plain text: a restricted row states its status only.
     *
     * @param \stdClass $row A worker's row.
     * @return array competency, status, type, issued, expiry, issuer, verification.
     */
    public static function pdf_row(\stdClass $row): array {
        $cells = [
            'competency' => $row->name,
            'status' => status::effective_label($row->code),
            'type' => '',
            'issued' => '',
            'expiry' => '',
            'issuer' => '',
            'verification' => '',
        ];
        if ($row->restricted || $row->code === status::EFFECTIVE_MISSING) {
            return $cells;
        }
        $cells['expiry'] = $row->expiry === null
            ? ($row->userevidenceid === null ? '' : get_string('noexpiry', 'local_evidencehub'))
            : meta::iso_date($row->expiry);
        if ($row->userevidenceid !== null) {
            $cells['type'] = (string) $row->typename;
            $cells['issued'] = meta::iso_date($row->issuedate);
            $cells['issuer'] = (string) $row->issuer;
            $cells['verification'] = meta::verify_label($row->verifystatus);
        }
        return $cells;
    }

    /**
     * A name not yet taken in a set, with a counter before the extension on a clash; the set records it.
     *
     * @param string $name The wanted name.
     * @param array $taken Names taken so far => true; the name returned is added.
     * @return string
     */
    public static function unique(string $name, array &$taken): string {
        $candidate = $name;
        $dot = strrpos($name, '.');
        [$stem, $extension] = $dot === false || $dot === 0 ? [$name, ''] : [substr($name, 0, $dot), substr($name, $dot)];
        for ($counter = 2; isset($taken[$candidate]); $counter++) {
            $candidate = $stem . '_' . $counter . $extension;
        }
        $taken[$candidate] = true;
        return $candidate;
    }

    /**
     * The SHA-256 of a stored file, read as a stream so a large file is never held in memory.
     *
     * @param \stored_file $file The file.
     * @return string
     */
    private static function sha256(\stored_file $file): string {
        $handle = $file->get_content_file_handle();
        $hash = hash_init('sha256');
        hash_update_stream($hash, $handle);
        fclose($handle);
        return hash_final($hash);
    }

    /**
     * The manifest as CSV: a heading, then one line per file and per omitted worker.
     *
     * A cell starting with =, +, -, @, a tab or a carriage return gets a single quote in front, so a
     * spreadsheet opening the manifest reads it as text, never as a formula (an identifier or a name is
     * the worker's own text).
     *
     * @param array $lines List of [path, bytes, sha256, note].
     * @return string
     */
    private static function csv(array $lines): string {
        $handle = fopen('php://temp', 'w+');
        fputcsv($handle, ['path', 'bytes', 'sha256', 'note'], ',', '"', '');
        foreach ($lines as $line) {
            $cells = array_map(
                fn($cell): string => preg_match('/^[=+\-@\t\r]/', (string) $cell) ? "'" . $cell : (string) $cell,
                $line
            );
            fputcsv($handle, $cells, ',', '"', '');
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);
        return $csv;
    }
}
