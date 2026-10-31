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

use local_evidencehub\event\dossier_exported;
use local_evidencehub\local\dossier;
use local_evidencehub\local\dossier_builder;
use local_evidencehub\local\dossier_export;
use local_evidencehub\local\export;
use local_evidencehub\tests\dossier_scenario;
use local_evidencehub\tests\hub_scenario;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The ad hoc task of a large dossier export: it runs as the requester, stores the ZIP in their user
 * context, tells them, and fails whole.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(build_dossier_export::class)]
#[CoversClass(dossier_export::class)]
final class build_dossier_export_test extends \advanced_testcase {
    use hub_scenario;
    use dossier_scenario;

    /** @var int The template. */
    private int $templateid;

    /** @var array The workers a and b. */
    private array $workers = [];

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('enabled', 1, 'core_competency');
        [$c1] = $this->competencies(1);
        $this->templateid = $this->template([$c1]);
        foreach (['a', 'b'] as $name) {
            $this->workers[$name] = $this->worker($this->templateid, ['idnumber' => strtoupper($name) . '1']);
            $this->decided($this->workers[$name], $c1, 'nrcertificate', ['/cert.pdf' => 'certificate ' . $name]);
        }
    }

    /**
     * Queue an export of the workers as the current user, then run the tasks as the admin would from cron.
     *
     * @param array $userids The workers.
     * @param int|null $taskuserid The user the task is queued to run as; the requester when null.
     * @return \stdClass The export row after the run.
     */
    private function queue_and_run(array $userids, ?int $taskuserid = null): \stdClass {
        global $DB;
        $export = dossier_export::queue(\context_system::instance(), $this->templateid, $userids);
        if ($taskuserid !== null) {
            $DB->set_field('task_adhoc', 'userid', $taskuserid, ['classname' => '\\' . build_dossier_export::class]);
        }
        $this->setAdminUser();
        ob_start();
        try {
            $this->runAdhocTasks(build_dossier_export::class);
        } finally {
            ob_end_clean();
        }
        return $DB->get_record(dossier_export::TABLE, ['id' => $export->id], '*', MUST_EXIST);
    }

    /**
     * The stored ZIPs of an export.
     *
     * @param \stdClass $export The export row.
     * @return array List of stored_file.
     */
    private function stored(\stdClass $export): array {
        return array_values(get_file_storage()->get_area_files(
            \context_user::instance((int) $export->requestedby)->id,
            'local_evidencehub',
            dossier_export::FILEAREA,
            (int) $export->id,
            'id',
            false
        ));
    }

    /**
     * The export is done, kept for the retention days, stored in the requester's user context, announced by an
     * event and a message with the link.
     *
     * @return void
     */
    public function test_the_zip_is_stored_for_the_requester_and_announced(): void {
        global $DB;
        $clock = $this->mock_clock_with_frozen();
        set_config('exportretentiondays', 3, 'local_evidencehub');
        $requester = $this->requester(array_values($this->workers));
        $this->setUser($requester);
        $messages = $this->redirectMessages();
        $events = $this->redirectEvents();

        $export = $this->queue_and_run([(int) $this->workers['a']->id, (int) $this->workers['b']->id]);

        $this->assertSame(export::STATUS_DONE, (int) $export->status);
        $this->assertSame(2, (int) $export->subjectcount);
        $this->assertSame($clock->time() + 3 * DAYSECS, (int) $export->timeexpires);
        $files = $this->stored($export);
        $this->assertCount(1, $files);
        $this->assertSame(dossier_export::filename($export), $files[0]->get_filename());
        $this->assertSame((int) $requester->id, (int) $files[0]->get_userid());
        $path = make_request_directory() . '/stored.zip';
        $files[0]->copy_content_to($path);
        $zip = $this->read_zip($path);
        $names = array_map(
            fn(string $name): string => preg_replace('/_C1-\d+_2028-01-15/', '', basename($name)),
            array_diff(array_keys($zip['files']), [dossier_builder::MANIFEST])
        );
        $this->assertEqualsCanonicalizing(['A1_dossier.pdf', 'A1_cert.pdf', 'B1_dossier.pdf', 'B1_cert.pdf'], array_values($names));
        $this->assertSame(2, $DB->count_records(dossier::TABLE, ['exportid' => $export->id, 'createdby' => $requester->id]));

        $sent = $messages->get_messages();
        $this->assertCount(1, $sent);
        $this->assertSame('local_evidencehub', $sent[0]->component);
        $this->assertSame('dossierready', $sent[0]->eventtype);
        $this->assertSame((int) $requester->id, (int) $sent[0]->useridto);
        $this->assertSame(get_string('dossierreadysubject', 'local_evidencehub'), $sent[0]->subject);
        $this->assertStringContainsString('/local_evidencehub/export/' . $export->id . '/', (string) $sent[0]->contexturl);

        $exported = array_values(array_filter($events->get_events(), fn($e) => $e instanceof dossier_exported));
        $this->assertCount(1, $exported);
        $this->assertSame((int) $requester->id, (int) $exported[0]->userid);
        $this->assertSame(2, $exported[0]->other['subjectcount']);
    }

    /**
     * The task's checks are the requester's: a worker the requester may not read is left out although the
     * cron's admin could read them (the control).
     *
     * @return void
     */
    public function test_the_task_runs_as_the_requester(): void {
        global $DB;
        $requester = $this->requester([$this->workers['a']]);
        $both = [(int) $this->workers['a']->id, (int) $this->workers['b']->id];
        // The control: the admin, as whom cron runs a task without a user, would export both.
        $this->setAdminUser();
        $this->assertCount(2, (new dossier_builder(\context_system::instance(), $this->templateid))->prepare($both)->workers);

        $this->setUser($requester);
        $export = $this->queue_and_run($both);

        $this->assertSame(export::STATUS_DONE, (int) $export->status);
        $this->assertSame(1, (int) $export->subjectcount);
        $this->assertSame(
            [(int) $this->workers['a']->id],
            array_map('intval', $DB->get_fieldset_select(dossier::TABLE, 'userid', 'exportid = ?', [$export->id]))
        );
    }

    /**
     * A task queued to run as anyone but the requester fails the export, builds nothing and tells the
     * requester.
     *
     * @return void
     */
    public function test_a_task_running_as_another_user_fails(): void {
        global $DB;
        $requester = $this->requester(array_values($this->workers));
        $other = $this->requester(array_values($this->workers));
        $this->setUser($requester);
        $messages = $this->redirectMessages();

        $export = $this->queue_and_run([(int) $this->workers['a']->id], (int) $other->id);

        $this->assertSame(export::STATUS_FAILED, (int) $export->status);
        $this->assertSame(0, $DB->count_records(dossier::TABLE, ['exportid' => $export->id]));
        $this->assertSame([], $this->stored($export));
        $sent = $messages->get_messages();
        $this->assertCount(1, $sent);
        $this->assertSame((int) $requester->id, (int) $sent[0]->useridto);
        $this->assertSame(get_string('dossierfailedsubject', 'local_evidencehub'), $sent[0]->subject);
    }

    /**
     * A requester who lost the right to export before the task ran gets a failed export, no dossier row
     * and a message saying so.
     *
     * @return void
     */
    public function test_a_failing_check_fails_the_export_whole(): void {
        global $DB;
        $requester = $this->requester(array_values($this->workers));
        $this->setUser($requester);
        $messages = $this->redirectMessages();
        $export = dossier_export::queue(\context_system::instance(), $this->templateid, [(int) $this->workers['a']->id]);
        $prohibit = $this->getDataGenerator()->create_role();
        assign_capability('local/evidencehub:exportdossier', CAP_PROHIBIT, $prohibit, \context_system::instance()->id, true);
        role_assign($prohibit, $requester->id, \context_system::instance()->id);
        accesslib_clear_all_caches_for_unit_testing();

        $this->setAdminUser();
        ob_start();
        try {
            $this->runAdhocTasks(build_dossier_export::class);
        } finally {
            ob_end_clean();
        }

        $export = $DB->get_record(dossier_export::TABLE, ['id' => $export->id]);
        $this->assertSame(export::STATUS_FAILED, (int) $export->status);
        $this->assertSame(0, $DB->count_records(dossier::TABLE));
        $this->assertSame([], $this->stored($export));
        $this->assertSame(get_string('dossierfailedsubject', 'local_evidencehub'), $messages->get_messages()[0]->subject);
    }

    /**
     * An export no longer queued is left alone.
     *
     * @return void
     */
    public function test_an_export_not_queued_is_skipped(): void {
        global $DB;
        $requester = $this->requester(array_values($this->workers));
        $this->setUser($requester);
        $export = dossier_export::queue(\context_system::instance(), $this->templateid, [(int) $this->workers['a']->id]);
        $DB->set_field(dossier_export::TABLE, 'status', export::STATUS_FAILED, ['id' => $export->id]);
        $this->setAdminUser();
        ob_start();
        try {
            $this->runAdhocTasks(build_dossier_export::class);
        } finally {
            ob_end_clean();
        }
        $this->assertSame(export::STATUS_FAILED, (int) $DB->get_field(dossier_export::TABLE, 'status', ['id' => $export->id]));
        $this->assertSame(0, $DB->count_records(dossier::TABLE));
    }

    /**
     * Once the export is committed, a failing announcement is traced and never turns it failed.
     *
     * @return void
     */
    public function test_a_failing_announcement_leaves_the_export_done(): void {
        global $DB;
        $requester = $this->requester(array_values($this->workers));
        $this->setUser($requester);
        $export = dossier_export::queue(\context_system::instance(), $this->templateid, [(int) $this->workers['a']->id]);
        $queued = $DB->get_record('task_adhoc', ['classname' => '\\' . build_dossier_export::class], '*', MUST_EXIST);
        $task = new class extends build_dossier_export {
            /**
             * Fail as an event observer or a message processor might.
             *
             * @param \context $context The hub's context.
             * @param \stdClass $export The export row.
             * @param \stored_file $file The stored ZIP.
             * @return void
             * @throws \moodle_exception Always.
             */
            #[\Override]
            protected function announce(\context $context, \stdClass $export, \stored_file $file): void {
                throw new \moodle_exception('nopermissions', 'error', '', 'announce');
            }
        };
        $task->set_custom_data(json_decode($queued->customdata));
        $task->set_userid((int) $requester->id);

        ob_start();
        $task->execute();
        $output = ob_get_clean();

        $export = $DB->get_record(dossier_export::TABLE, ['id' => $export->id]);
        $this->assertSame(export::STATUS_DONE, (int) $export->status);
        $this->assertCount(1, $this->stored($export));
        $this->assertSame(1, $DB->count_records(dossier::TABLE, ['exportid' => $export->id]));
        $this->assertStringContainsString('could not be announced', $output);
    }
}
