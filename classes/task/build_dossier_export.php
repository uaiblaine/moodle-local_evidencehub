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

namespace local_evidencehub\task;

use core_files\archive_writer;
use local_evidencehub\event\dossier_exported;
use local_evidencehub\local\dossier_builder;
use local_evidencehub\local\dossier_config;
use local_evidencehub\local\dossier_export;
use local_evidencehub\local\export;

/**
 * Prepare a dossier export too large to stream, store its ZIP for the requester and tell them.
 *
 * Queued with set_userid() to the requester, so core's cron runs it as them (core\cron::run_inner_adhoc_task()
 * calls setup_user() with the task's user): every check of {@see dossier_builder::prepare()} is the requester's,
 * as in the request. A task found running as anyone else fails the export rather than build it with
 * someone else's rights.
 *
 * Custom data: exportid, contextid (the hub's context), userids (the validated selection).
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class build_dossier_export extends \core\task\adhoc_task {
    /**
     * The task's name.
     *
     * @return string
     */
    #[\Override]
    public function get_name(): string {
        return get_string('taskbuilddossierexport', 'local_evidencehub');
    }

    /**
     * Build the export, or mark it failed and tell the requester; once built, announce it.
     *
     * A failure is not retried: the requester was told and can ask again. Once the export is committed
     * done, nothing that follows (the event, the message) can turn it failed: such a failure is traced.
     *
     * @return void
     */
    #[\Override]
    public function execute(): void {
        global $DB;
        $data = (object) $this->get_custom_data();
        $export = $DB->get_record(dossier_export::TABLE, ['id' => (int) ($data->exportid ?? 0)]);
        if (!$export || (int) $export->status !== export::STATUS_QUEUED) {
            mtrace('Dossier export ' . ($data->exportid ?? '?') . ' is not waiting for this task.');
            return;
        }
        try {
            [$context, $file] = $this->build($export, $data);
        } catch (\Throwable $e) {
            dossier_export::fail($export);
            mtrace('Dossier export ' . $export->id . ' failed: ' . $e->getMessage());
            try {
                dossier_export::notify($export, null);
            } catch (\Throwable $notice) {
                mtrace('Its requester could not be told: ' . $notice->getMessage());
            }
            return;
        }
        try {
            $this->announce($context, $export, $file);
            mtrace('Dossier export ' . $export->id . ' is ready: ' . $export->subjectcount . ' dossiers.');
        } catch (\Throwable $e) {
            mtrace('Dossier export ' . $export->id . ' is ready but could not be announced: ' . $e->getMessage());
        }
    }

    /**
     * Build, store and mark the export done in one transaction: a failure leaves no dossier row behind,
     * and a committed export is done.
     *
     * @param \stdClass $export The export row, queued; updated.
     * @param \stdClass $data The custom data.
     * @return array [the hub's context, the stored ZIP].
     * @throws \moodle_exception When the task runs as another user than the requester, or a check refuses.
     */
    private function build(\stdClass $export, \stdClass $data): array {
        global $DB, $USER;
        if ((int) $USER->id !== (int) $export->requestedby) {
            throw new \moodle_exception('errordossiertaskuser', 'local_evidencehub');
        }
        $DB->set_field(dossier_export::TABLE, 'status', export::STATUS_RUNNING, ['id' => $export->id]);
        $context = \context::instance_by_id((int) $data->contextid);
        $transaction = $DB->start_delegated_transaction();
        try {
            $builder = new dossier_builder($context, (int) $export->templateid);
            $plan = $builder->prepare(array_map('intval', (array) $data->userids));
            // A file NAME: given a path, ZipStream writes the archive to stdout and no file is made.
            $zip = archive_writer::get_file_writer(dossier_export::filename($export), archive_writer::ZIP_WRITER);
            $builder->build($zip, $plan, $export);
            // The writer's request directory goes away with the task: the ZIP is copied into file storage now.
            $file = dossier_export::store($export, $zip->get_path_to_zip());
            $timeexpires = \core\di::get(\core\clock::class)->time() + dossier_config::retention_days() * DAYSECS;
            dossier_export::mark_done($export, count($plan->workers), $timeexpires);
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            $transaction->rollback($e);
        }
        return [$context, $file];
    }

    /**
     * Announce a committed export: the event, then the message with the link.
     *
     * @param \context $context The hub's context.
     * @param \stdClass $export The export row, done.
     * @param \stored_file $file The stored ZIP.
     * @return void
     */
    protected function announce(\context $context, \stdClass $export, \stored_file $file): void {
        dossier_exported::create_from_export($context, $export)->trigger();
        dossier_export::notify($export, $file);
    }
}
