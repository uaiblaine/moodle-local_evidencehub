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

use core_files\archive_writer;
use local_evidencehub\event\dossier_exported;
use local_evidencehub\task\build_dossier_export;

/**
 * The dossier export requests (local_evidencehub_export): the hub's selection, the choice between the two
 * drivers, the stored ZIP, its message, who may download it, and its cleanup.
 *
 * Every export has a row: a streamed one is created and finished in the same request; a queued one waits
 * for the ad hoc task {@see build_dossier_export}, which runs as the requester.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class dossier_export {
    /** @var string The table. */
    public const string TABLE = 'local_evidencehub_export';

    /** @var string The file area of the prepared ZIPs, in the requester's user context, itemid the export id. */
    public const string FILEAREA = 'export';

    /** @var int The most pairs one export may post; the selection of one page of the list names at most 100. */
    public const int MAX_PAIRS = 1000;

    /** @var int Seconds a request waits for another request of the same requester to finish queueing. */
    private const int QUEUE_LOCK_TIMEOUT = 5;

    /**
     * @var int How far back a queued or running export counts against the requester's limit. A stream that died
     *     (the client went away, PHP timed out) leaves its row running until cleanup() expires it after the retention
     *     days; a day is far longer than any real build, so such a row stops blocking the requester after it.
     */
    private const int QUEUE_WINDOW = DAYSECS;

    /**
     * Refuse anything but a POST: an export, a revocation or a policy removal changes state, so a link must
     * never trigger it.
     *
     * @return void
     * @throws \moodle_exception On another method.
     */
    public static function require_post(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            throw new \moodle_exception('errorpostonly', 'local_evidencehub');
        }
    }

    /**
     * The posted "userid:competencyid" pairs, as keys: the rows a reader selected in the hub's list.
     *
     * @param array $raw The posted values.
     * @return array "userid:competencyid" => true.
     * @throws \moodle_exception On a value of another shape or more than MAX_PAIRS pairs.
     */
    public static function parse_pairs(array $raw): array {
        if (count($raw) > self::MAX_PAIRS) {
            throw new \moodle_exception('errorhubpairs', 'local_evidencehub');
        }
        $pairs = [];
        foreach ($raw as $value) {
            if (!is_string($value) || !preg_match('/^([1-9]\d*):([1-9]\d*)$/', $value, $matches)) {
                throw new \moodle_exception('errorhubpairs', 'local_evidencehub');
            }
            $pairs[(int) $matches[1] . ':' . (int) $matches[2]] = true;
        }
        return $pairs;
    }

    /**
     * The workers the hub posted: the matrix's workers[], or the workers of the list's pairs[].
     *
     * @return array User ids, without repeats.
     * @throws \moodle_exception On a malformed value, or more workers than one export may name.
     */
    public static function posted_workers(): array {
        $workers = optional_param_array('workers', [], PARAM_INT);
        $pairs = optional_param_array('pairs', [], PARAM_RAW_TRIMMED);
        foreach (array_keys($pairs ? self::parse_pairs($pairs) : []) as $pair) {
            $workers[] = (int) explode(':', $pair)[0];
        }
        $workers = array_values(array_unique(array_filter(array_map('intval', $workers), fn(int $id): bool => $id > 0)));
        if (count($workers) > dossier_config::max_export_workers()) {
            throw new \moodle_exception('errordossiertoomany', 'local_evidencehub', '', dossier_config::max_export_workers());
        }
        return $workers;
    }

    /**
     * The posted workers that belong to the template's pair set as this reader may view it, by id.
     *
     * The reader selected rows they were shown; the server's job is that they may see those workers. So the cut is
     * the pair set of the template for the reader ({@see hub_query::base_sql()}: an active plan from the template,
     * userevidenceview over the worker) with suspended accounts as the reader's preference lists them
     * ({@see reportpreference}), and nothing else: no status, cohort or search, which only chose what was on screen.
     * A worker outside it (another template's, one the reader may not read, a suspended one the reader does not
     * list) is dropped: the post only narrows the set, it never widens it.
     *
     * @param \context $context The hub's context.
     * @param int $templateid The template, offered in the context.
     * @param hub_scope $scope The reader's capability cuts.
     * @param array $userids The posted workers.
     * @return array User ids, ascending.
     * @throws \moodle_exception When none is left, or more than one export may name, or the template is not offered.
     */
    public static function selection(\context $context, int $templateid, hub_scope $scope, array $userids): array {
        global $DB;
        $userids = array_values(array_unique(array_filter(array_map('intval', $userids), fn(int $id): bool => $id > 0)));
        if (!$userids) {
            throw new \moodle_exception('errordossiernoselection', 'local_evidencehub');
        }
        $max = dossier_config::max_export_workers();
        if (count($userids) > $max) {
            throw new \moodle_exception('errordossiertoomany', 'local_evidencehub', '', $max);
        }
        $filter = hub_filter::from_values(
            ['templateid' => $templateid, 'suspended' => reportpreference::read($scope->viewerid)['suspended']],
            $context
        );
        [$basesql, $params] = hub_query::base_sql($filter, $scope, $context);
        [$userin, $userparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'dsel');
        $sql = "SELECT DISTINCT b.userid
                  FROM ($basesql) b
                 WHERE b.userid $userin
              ORDER BY b.userid";
        $kept = array_map('intval', $DB->get_fieldset_sql($sql, $params + $userparams));
        if (!$kept) {
            throw new \moodle_exception('errordossiernoselection', 'local_evidencehub');
        }
        return $kept;
    }

    /**
     * Whether an export streams in the request: at most syncmaxworkers workers and syncmaxbytes of files.
     *
     * @param int $workers How many workers it names.
     * @param int $bytes The size of its evidence files.
     * @return bool
     */
    public static function streams(int $workers, int $bytes): bool {
        return $workers <= dossier_config::sync_max_workers() && $bytes <= dossier_config::sync_max_bytes();
    }

    /**
     * Start an export: the plan to stream now, or null once the ad hoc task is queued.
     *
     * A selection above syncmaxworkers is queued without reading its files; one below it is prepared, and
     * queued only when its files weigh more than syncmaxbytes.
     *
     * @param \context $context The hub's context.
     * @param int $templateid The plan template, from the validated filter.
     * @param array $userids The validated selection.
     * @return dossier_plan|null
     * @throws \moodle_exception When the template is not offered in the context.
     * @throws \required_capability_exception When the user may not export there.
     */
    public static function start(\context $context, int $templateid, array $userids): ?dossier_plan {
        dossier_builder::require_export($context);
        if (count($userids) <= dossier_config::sync_max_workers()) {
            $plan = (new dossier_builder($context, $templateid))->prepare($userids);
            if (self::streams(count($userids), $plan->bytes)) {
                return $plan;
            }
        }
        self::queue($context, $templateid, $userids);
        return null;
    }

    /**
     * Create an export row for the current user.
     *
     * @param \context $context The hub's context it is authorised in; a download re-checks the rights there.
     * @param int $templateid The plan template.
     * @param int $count How many workers it names.
     * @param int $status An export STATUS_* constant.
     * @return \stdClass The row.
     */
    public static function create(\context $context, int $templateid, int $count, int $status): \stdClass {
        global $DB, $USER;
        $export = (object) [
            'requestedby' => (int) $USER->id,
            'templateid' => $templateid,
            'contextid' => (int) $context->id,
            'subjectcount' => $count,
            'status' => $status,
            'timecreated' => \core\di::get(\core\clock::class)->time(),
        ];
        $export->id = (int) $DB->insert_record(self::TABLE, $export);
        return $export;
    }

    /**
     * Queue the ad hoc task of an export, to run as the current user.
     *
     * A requester has at most maxqueuedexports exports queued or running ({@see dossier_config::max_queued_exports()})
     * created within the last QUEUE_WINDOW: each one builds a whole ZIP on the cron, so one more is refused until one
     * finishes. The count and the new row
     * are decided under the requester's queue lock, so two requests at once cannot both pass the count. A streamed
     * export is not counted: it runs in its own request.
     *
     * @param \context $context The hub's context.
     * @param int $templateid The plan template.
     * @param array $userids The validated selection.
     * @return \stdClass The export row, queued.
     * @throws \moodle_exception When the requester's queue is full, or another of their requests holds it.
     */
    public static function queue(\context $context, int $templateid, array $userids): \stdClass {
        global $DB, $USER;
        $requesterid = (int) $USER->id;
        $lock = \core\lock\lock_config::get_lock_factory('local_evidencehub')
            ->get_lock('dossierqueue_' . $requesterid, self::QUEUE_LOCK_TIMEOUT);
        if (!$lock) {
            throw new \moodle_exception('errordossierqueuebusy', 'local_evidencehub');
        }
        try {
            [$waitingsql, $params] = $DB->get_in_or_equal(
                [export::STATUS_QUEUED, export::STATUS_RUNNING],
                SQL_PARAMS_NAMED,
                'dxq'
            );
            $waiting = $DB->count_records_select(
                self::TABLE,
                "requestedby = :dxqrequester AND status $waitingsql AND timecreated > :dxqsince",
                $params + [
                    'dxqrequester' => $requesterid,
                    'dxqsince' => \core\di::get(\core\clock::class)->time() - self::QUEUE_WINDOW,
                ]
            );
            $max = dossier_config::max_queued_exports();
            if ($waiting >= $max) {
                throw new \moodle_exception('errordossierqueuefull', 'local_evidencehub', '', $max);
            }
            $export = self::create($context, $templateid, count($userids), export::STATUS_QUEUED);
            $task = new build_dossier_export();
            // Core runs the task as this user, so every check of dossier_builder::prepare() is the requester's.
            $task->set_userid($requesterid);
            $task->set_custom_data([
                'exportid' => $export->id,
                'contextid' => $context->id,
                'userids' => array_values(array_map('intval', $userids)),
            ]);
            \core\task\manager::queue_adhoc_task($task);
        } finally {
            $lock->release();
        }
        return $export;
    }

    /**
     * Stream a plan's ZIP to the browser: nothing is kept but the export and dossier rows.
     *
     * A failure half-way marks the export failed but keeps the dossier rows written so far: the builder
     * writes each row before its PDF enters the stream, and that PDF may already have reached the client,
     * so its QR code must keep resolving.
     *
     * @param \context $context The hub's context.
     * @param int $templateid The plan template.
     * @param dossier_plan $plan The plan, from {@see self::start()}.
     * @return void
     */
    public static function stream(\context $context, int $templateid, dossier_plan $plan): void {
        $export = self::create($context, $templateid, count($plan->workers), export::STATUS_RUNNING);
        \core\session\manager::write_close();
        $zip = archive_writer::get_stream_writer(self::filename($export), archive_writer::ZIP_WRITER);
        try {
            (new dossier_builder($context, $templateid))->build($zip, $plan, $export);
        } catch (\Throwable $e) {
            self::fail($export);
            throw $e;
        }
        self::mark_done($export, count($plan->workers), null);
        dossier_exported::create_from_export($context, $export)->trigger();
    }

    /**
     * The ZIP's file name.
     *
     * @param \stdClass $export The export row.
     * @return string
     */
    public static function filename(\stdClass $export): string {
        return 'dossiers-' . $export->id . '.zip';
    }

    /**
     * Copy a prepared ZIP into the requester's file area, before the task's request directory goes away.
     *
     * @param \stdClass $export The export row.
     * @param string $path The ZIP, from the file writer's get_path_to_zip().
     * @return \stored_file
     */
    public static function store(\stdClass $export, string $path): \stored_file {
        return get_file_storage()->create_file_from_pathname([
            'contextid' => \context_user::instance((int) $export->requestedby)->id,
            'component' => 'local_evidencehub',
            'filearea' => self::FILEAREA,
            'itemid' => (int) $export->id,
            'filepath' => '/',
            'filename' => self::filename($export),
            'userid' => (int) $export->requestedby,
        ], $path);
    }

    /**
     * Mark an export done; the caller announces it once the row is committed.
     *
     * @param \stdClass $export The export row; updated.
     * @param int $issued How many dossiers it holds.
     * @param int|null $timeexpires When its stored ZIP is deleted; null for a streamed one.
     * @return void
     */
    public static function mark_done(\stdClass $export, int $issued, ?int $timeexpires): void {
        global $DB;
        $export->status = export::STATUS_DONE;
        $export->subjectcount = $issued;
        $export->timecompleted = \core\di::get(\core\clock::class)->time();
        $export->timeexpires = $timeexpires;
        $DB->update_record(self::TABLE, (object) [
            'id' => $export->id,
            'status' => $export->status,
            'subjectcount' => $export->subjectcount,
            'timecompleted' => $export->timecompleted,
            'timeexpires' => $export->timeexpires,
        ]);
    }

    /**
     * Mark an export failed.
     *
     * @param \stdClass $export The export row; updated.
     * @return void
     */
    public static function fail(\stdClass $export): void {
        global $DB;
        $export->status = export::STATUS_FAILED;
        $export->timecompleted = \core\di::get(\core\clock::class)->time();
        $DB->update_record(self::TABLE, (object) [
            'id' => $export->id,
            'status' => $export->status,
            'timecompleted' => $export->timecompleted,
        ]);
    }

    /**
     * Tell the requester that their export is ready, with the link to the ZIP, or that it failed.
     *
     * @param \stdClass $export The export row.
     * @param \stored_file|null $file The stored ZIP; null when the export failed.
     * @return void
     */
    public static function notify(\stdClass $export, ?\stored_file $file): void {
        $requester = \core_user::get_user((int) $export->requestedby);
        if (!$requester || $requester->deleted) {
            return;
        }
        $message = new \core\message\message();
        $message->courseid = SITEID;
        $message->component = 'local_evidencehub';
        $message->name = 'dossierready';
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = $requester;
        $message->notification = 1;
        if ($file === null) {
            $message->subject = get_string('dossierfailedsubject', 'local_evidencehub');
            $message->fullmessage = get_string('dossierfailedbody', 'local_evidencehub');
            $message->fullmessagehtml = '<p>' . s($message->fullmessage) . '</p>';
        } else {
            $url = \moodle_url::make_pluginfile_url(
                $file->get_contextid(),
                'local_evidencehub',
                self::FILEAREA,
                $file->get_itemid(),
                $file->get_filepath(),
                $file->get_filename(),
                true
            );
            $a = (object) [
                'count' => (int) $export->subjectcount,
                'url' => $url->out(false),
                'expires' => userdate((int) $export->timeexpires, get_string('strftimedatetime', 'core_langconfig')),
            ];
            $message->subject = get_string('dossierreadysubject', 'local_evidencehub');
            $message->fullmessage = get_string('dossierreadybody', 'local_evidencehub', $a);
            $message->fullmessagehtml = '<p>' . s(get_string('dossierreadybody', 'local_evidencehub', $a)) . '</p>'
                . '<p><a href="' . s($a->url) . '">' . s($file->get_filename()) . '</a></p>';
            $message->contexturl = $url;
            $message->contexturlname = $file->get_filename();
        }
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->smallmessage = $message->subject;
        message_send($message);
    }

    /**
     * The stored ZIP a pluginfile request names, when the current user may download it.
     *
     * Only while competencies are enabled; only the requester, in their own user context, while the export is
     * done and not expired, while they still hold exportdossier and view in the hub's context the export was
     * authorised in (the template's context for a row from before that context was recorded), and while they
     * still read the evidence of every worker the ZIP holds a dossier about: one worker no longer readable refuses
     * the whole ZIP. What the builder recorded on the export decides the rest: a ZIP holding a worker's unmasked
     * sensitive rows needs, for every worker, the right to read them ({@see details::can_see_sensitive()}: the
     * worker themself, or viewsensitive in their user context); one holding files of a sensitive evidence also
     * needs the site to include such files still ({@see dossier_config::include_sensitive_files()}).
     *
     * @param \context $context The file's context.
     * @param string $filearea The file area.
     * @param array $args itemid, then the path and file name.
     * @return \stored_file|null
     */
    public static function servable_file(\context $context, string $filearea, array $args): ?\stored_file {
        global $DB, $USER;
        if ((int) $context->contextlevel !== CONTEXT_USER || $filearea !== self::FILEAREA) {
            return null;
        }
        if (!\core_competency\api::is_enabled()) {
            return null;
        }
        if ((int) $context->instanceid !== (int) $USER->id) {
            return null;
        }
        $itemid = (int) array_shift($args);
        $export = $DB->get_record(self::TABLE, ['id' => $itemid]);
        if (!$export || (int) $export->requestedby !== (int) $USER->id || (int) $export->status !== export::STATUS_DONE) {
            return null;
        }
        if ((int) $export->timeexpires <= \core\di::get(\core\clock::class)->time()) {
            return null;
        }
        $contextid = $export->contextid ?? $DB->get_field('competency_template', 'contextid', ['id' => $export->templateid]);
        $hubcontext = $contextid ? \context::instance_by_id((int) $contextid, IGNORE_MISSING) : null;
        if (
            !$hubcontext
            || !has_capability('local/evidencehub:exportdossier', $hubcontext)
            || !has_capability('local/evidencehub:view', $hubcontext)
        ) {
            return null;
        }
        if ((int) $export->hassensitivefiles === 1 && !dossier_config::include_sensitive_files()) {
            return null;
        }
        $sensitive = (int) $export->hassensitive === 1;
        foreach ($DB->get_fieldset_select(dossier::TABLE, 'DISTINCT userid', 'exportid = ?', [$export->id]) as $workerid) {
            $workercontext = \context_user::instance((int) $workerid, IGNORE_MISSING);
            if (!$workercontext || !has_capability('moodle/competency:userevidenceview', $workercontext)) {
                return null;
            }
            if ($sensitive && !details::can_see_sensitive((int) $workerid)) {
                return null;
            }
        }
        $filename = (string) array_pop($args);
        $filepath = $args ? '/' . implode('/', $args) . '/' : '/';
        $file = get_file_storage()->get_file($context->id, 'local_evidencehub', self::FILEAREA, $itemid, $filepath, $filename);
        return $file && !$file->is_directory() ? $file : null;
    }

    /**
     * Delete the ZIPs of the exports whose time has come and mark them expired; the dossier rows stay.
     *
     * A done export expires at its timeexpires. One still queued, running or failed after the retention
     * period was left behind (a cancelled task, a killed run): it expires too, and any file it owns goes.
     *
     * @param int $now The current time.
     * @return int How many exports expired.
     */
    public static function cleanup(int $now): int {
        global $DB;
        $fs = get_file_storage();
        $count = 0;
        [$stalesql, $staleparams] = $DB->get_in_or_equal(
            [export::STATUS_QUEUED, export::STATUS_RUNNING, export::STATUS_FAILED],
            SQL_PARAMS_NAMED,
            'dxstale'
        );
        $rs = $DB->get_recordset_select(
            self::TABLE,
            "(status = :done AND timeexpires <= :now) OR (status $stalesql AND timecreated <= :stale)",
            ['done' => export::STATUS_DONE, 'now' => $now, 'stale' => $now - dossier_config::retention_days() * DAYSECS]
                + $staleparams,
            'id',
            'id, requestedby'
        );
        foreach ($rs as $export) {
            self::retire_one($fs, $export);
            $count++;
        }
        $rs->close();
        return $count;
    }

    /**
     * Retire exports now, whatever their state: their stored ZIPs are deleted and they are marked expired, as the
     * cleanup task does; the dossier rows stay.
     *
     * The privacy provider's door, for the exports holding a dossier about a worker whose data is deleted.
     *
     * @param array $exportids The export ids; an id with no row is skipped.
     * @return void
     */
    public static function retire(array $exportids): void {
        global $DB;
        if (!$exportids) {
            return;
        }
        $fs = get_file_storage();
        foreach ($DB->get_records_list(self::TABLE, 'id', $exportids, 'id', 'id, requestedby') as $export) {
            self::retire_one($fs, $export);
        }
    }

    /**
     * Delete one export's stored ZIP, in its requester's user context, and mark it expired.
     *
     * @param \file_storage $fs The file storage.
     * @param \stdClass $export The export's id and requestedby.
     * @return void
     */
    private static function retire_one(\file_storage $fs, \stdClass $export): void {
        global $DB;
        $context = \context_user::instance((int) $export->requestedby, IGNORE_MISSING);
        if ($context) {
            $fs->delete_area_files($context->id, 'local_evidencehub', self::FILEAREA, (int) $export->id);
        }
        $DB->set_field(self::TABLE, 'status', export::STATUS_EXPIRED, ['id' => $export->id]);
    }
}
