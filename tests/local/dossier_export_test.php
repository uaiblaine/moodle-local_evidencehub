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

use core_reportbuilder\manager;
use local_evidencehub\output\hub_matrix_page;
use local_evidencehub\output\hub_report_view;
use local_evidencehub\reportbuilder\local\systemreports\hub_pairs;
use local_evidencehub\task\build_dossier_export;
use local_evidencehub\tests\dossier_scenario;
use local_evidencehub\tests\hub_scenario;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversFunction;

/**
 * The hub's side of the dossiers: who is offered the action, the selection checked against the visible set,
 * the threshold between streaming and the ad hoc task, and who may download a prepared ZIP.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(dossier_export::class)]
#[CoversClass(dossier_config::class)]
#[CoversClass(hub_matrix_page::class)]
#[CoversClass(hub_report_view::class)]
#[CoversFunction('local_evidencehub_pluginfile')]
final class dossier_export_test extends \advanced_testcase {
    use hub_scenario;
    use dossier_scenario;

    /** @var int The template. */
    private int $templateid;

    /** @var int Its competency. */
    private int $competencyid;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('enabled', 1, 'core_competency');
        [$this->competencyid] = $this->competencies(1);
        $this->templateid = $this->template([$this->competencyid]);
    }

    #[\Override]
    protected function tearDown(): void {
        $_POST = [];
        $_GET = [];
        unset($_SERVER['REQUEST_METHOD']);
        parent::tearDown();
    }

    /**
     * Exporting and revoking take a POST only.
     *
     * @return void
     */
    public function test_state_changes_need_a_post(): void {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        dossier_export::require_post();
        foreach (['GET', 'HEAD'] as $method) {
            $_SERVER['REQUEST_METHOD'] = $method;
            try {
                dossier_export::require_post();
                $this->fail('A ' . $method . ' was accepted.');
            } catch (\moodle_exception $e) {
                $this->assertSame('errorpostonly', $e->errorcode);
            }
        }
    }

    /**
     * The hub offers "Export dossiers" only to a holder of exportdossier: the list's dock through its export form, the
     * matrix's button, whose form names the template and returns to the matrix.
     *
     * @return void
     */
    public function test_the_action_is_offered_with_exportdossier_only(): void {
        global $PAGE;
        $worker = $this->worker($this->templateid);
        $system = \context_system::instance();
        $output = $PAGE->get_renderer('core');
        $viewer = $this->viewer(['local/evidencehub:view', 'moodle/competency:userevidenceview', 'moodle/competency:templateview']);
        $exporter = $this->requester([$worker]);
        $pages = function () use ($system, $output): array {
            manager::reset_caches();
            $report = hub_pairs::for_template($system, $this->templateid, ['scope' => 'all']);
            $matrix = (new hub_matrix_page($system, $report, 'all'))->export_for_template($output);
            return [(new hub_report_view($system, $report))->props(), $matrix];
        };

        $this->setUser($viewer);
        [$props, $matrix] = $pages();
        $this->assertNull($props['exportform']);
        $this->assertFalse($matrix['canexport']);
        $html = $output->render_from_template('local_evidencehub/hub_matrix', $matrix);
        $this->assertStringNotContainsString('value="exportdossiers"', $html);

        $this->setUser($exporter);
        [$props, $matrix] = $pages();
        $this->assertSame(['action', 'sesskey'], array_keys($props['exportform']));
        $this->assertTrue($matrix['canexport']);
        $html = $output->render_from_template('local_evidencehub/hub_matrix', $matrix);
        $this->assertStringContainsString('value="exportdossiers"', $html);
        $this->assertStringContainsString('name="templateid" value="' . $this->templateid . '"', $html);
        $this->assertStringContainsString('name="view" value="matrix"', $html);
    }

    /**
     * The posted workers are kept only when they belong to the template's pair set as the reader may view it, with
     * suspended accounts as the reader's preference lists them, and by nothing else: a worker whose every pair is
     * compliant, outside the list's default scope, is kept.
     *
     * @return void
     */
    public function test_the_selection_is_checked_against_the_visible_set(): void {
        $readable = $this->worker($this->templateid);
        $compliant = $this->worker($this->templateid);
        $this->status_row((int) $compliant->id, $this->competencyid, status::STATUS_VALID, 20990101);
        $unreadable = $this->worker($this->templateid);
        $elsewhere = $this->worker($this->template([$this->competencyid]));
        $suspended = $this->worker($this->templateid, ['suspended' => 1]);
        $this->setUser($this->requester([$readable, $compliant, $elsewhere, $suspended]));
        $system = \context_system::instance();

        $kept = dossier_export::selection(
            $system,
            $this->templateid,
            hub_scope::for_user(),
            [$readable->id, $compliant->id, $unreadable->id, $elsewhere->id, $suspended->id, 0, -3]
        );
        $this->assertSame([(int) $readable->id, (int) $compliant->id], $kept);
        // The control: with suspended accounts listed in the reader's preference, the suspended one is kept.
        reportpreference::write_grid(null, [], true);
        $kept = dossier_export::selection($system, $this->templateid, hub_scope::for_user(), [$readable->id, $suspended->id]);
        $this->assertEqualsCanonicalizing([(int) $readable->id, (int) $suspended->id], $kept);

        try {
            dossier_export::selection($system, $this->templateid, hub_scope::for_user(), [$unreadable->id]);
            $this->fail('A selection with no visible worker was accepted.');
        } catch (\moodle_exception $e) {
            $this->assertSame('errordossiernoselection', $e->errorcode);
        }
        set_config('maxexportworkers', 1, 'local_evidencehub');
        $this->expectExceptionMessage(get_string('errordossiertoomany', 'local_evidencehub', 1));
        dossier_export::selection($system, $this->templateid, hub_scope::for_user(), [$readable->id, $suspended->id]);
    }

    /**
     * A posted pair of another shape, or too many pairs, is refused; a repeated one counts once.
     *
     * @return void
     */
    public function test_malformed_pairs_are_refused(): void {
        $this->assertSame(['3:4' => true], dossier_export::parse_pairs(['3:4', '3:4']));
        foreach (['3', '3:4:5', 'a:b', '0:4', '-1:4', '3:4 OR 1=1'] as $value) {
            try {
                dossier_export::parse_pairs([$value]);
                $this->fail($value . ' was accepted.');
            } catch (\moodle_exception $e) {
                $this->assertSame('errorhubpairs', $e->errorcode);
            }
        }
        // The bound: MAX_PAIRS is accepted, one more is not.
        $this->assertCount(1, dossier_export::parse_pairs(array_fill(0, dossier_export::MAX_PAIRS, '1:1')));
        $this->expectException(\moodle_exception::class);
        dossier_export::parse_pairs(array_fill(0, dossier_export::MAX_PAIRS + 1, '1:1'));
    }

    /**
     * The form posts workers[] from the matrix or pairs[] from the list; both become worker ids.
     *
     * @return void
     */
    public function test_posted_workers_and_pairs_become_workers(): void {
        $_POST = ['workers' => ['7', '9', '7'], 'pairs' => ['11:3', '11:4', '9:3']];
        $this->assertEqualsCanonicalizing([7, 9, 11], dossier_export::posted_workers());
        $_POST = ['pairs' => ['nonsense']];
        $this->expectException(\moodle_exception::class);
        dossier_export::posted_workers();
    }

    /**
     * Streaming is for at most syncmaxworkers workers and syncmaxbytes of files, both inclusive.
     *
     * @return void
     */
    public function test_the_thresholds(): void {
        $this->assertTrue(dossier_export::streams(10, 104857600));
        $this->assertFalse(dossier_export::streams(11, 0));
        $this->assertFalse(dossier_export::streams(1, 104857601));
        set_config('syncmaxworkers', 2, 'local_evidencehub');
        set_config('syncmaxbytes', 5, 'local_evidencehub');
        $this->assertTrue(dossier_export::streams(2, 5));
        $this->assertFalse(dossier_export::streams(3, 5));
        $this->assertFalse(dossier_export::streams(2, 6));
    }

    /**
     * start() hands back a plan to stream below both thresholds and queues the task as the requester above
     * either, with an export row waiting for it.
     *
     * @return void
     */
    public function test_start_picks_the_stream_or_the_task(): void {
        global $DB;
        $first = $this->worker($this->templateid);
        $second = $this->worker($this->templateid);
        $this->decided($first, $this->competencyid, 'nrcertificate', ['/cert.pdf' => '0123456789']);
        $requester = $this->requester([$first, $second]);
        $this->setUser($requester);
        $system = \context_system::instance();
        $both = [(int) $first->id, (int) $second->id];

        $plan = dossier_export::start($system, $this->templateid, $both);
        $this->assertInstanceOf(dossier_plan::class, $plan);
        $this->assertSame(10, $plan->bytes);
        $this->assertSame(0, $DB->count_records(dossier_export::TABLE));
        $this->assertSame(0, $DB->count_records('task_adhoc', ['classname' => '\\' . build_dossier_export::class]));

        // Above the byte threshold, even a single worker goes to the task.
        set_config('syncmaxbytes', 9, 'local_evidencehub');
        $this->assertNull(dossier_export::start($system, $this->templateid, [(int) $first->id]));
        set_config('syncmaxbytes', 100, 'local_evidencehub');
        set_config('syncmaxworkers', 1, 'local_evidencehub');
        $this->assertNull(dossier_export::start($system, $this->templateid, $both));

        $exports = array_values($DB->get_records(dossier_export::TABLE, null, 'id'));
        $this->assertCount(2, $exports);
        $this->assertSame([1, 2], array_map(fn($e) => (int) $e->subjectcount, $exports));
        $this->assertSame([export::STATUS_QUEUED, export::STATUS_QUEUED], array_map(fn($e) => (int) $e->status, $exports));
        $tasks = array_values($DB->get_records('task_adhoc', ['classname' => '\\' . build_dossier_export::class], 'id'));
        $this->assertCount(2, $tasks);
        $this->assertSame((int) $requester->id, (int) $tasks[1]->userid);
        $data = json_decode($tasks[1]->customdata);
        $this->assertSame((int) $exports[1]->id, (int) $data->exportid);
        $this->assertSame($system->id, (int) $data->contextid);
        $this->assertSame([$system->id, $system->id], array_map(fn($e) => (int) $e->contextid, $exports));
        $this->assertSame($both, array_map('intval', $data->userids));
    }

    /**
     * A done export of a requester, with its ZIP stored.
     *
     * @param \stdClass $requester The requester.
     * @param array $columns More columns of the export row.
     * @return array [export row, stored_file].
     */
    private function prepared(\stdClass $requester, array $columns = []): array {
        $export = $this->getDataGenerator()->get_plugin_generator('local_evidencehub')->create_export($columns + [
            'requestedby' => $requester->id,
            'templateid' => $this->templateid,
            'contextid' => \context_system::instance()->id,
            'status' => export::STATUS_DONE,
            'timeexpires' => time() + DAYSECS,
        ]);
        return [$export, dossier_export::store($export, $this->zip_on_disk())];
    }

    /**
     * A small file on disk to store.
     *
     * @return string Its path.
     */
    private function zip_on_disk(): string {
        $path = make_request_directory() . '/dossiers.zip';
        file_put_contents($path, 'zip bytes');
        return $path;
    }

    /**
     * The pluginfile arguments of a stored ZIP.
     *
     * @param \stored_file $file The file.
     * @return array
     */
    private function args(\stored_file $file): array {
        return [$file->get_itemid(), $file->get_filename()];
    }

    /**
     * The requester downloads their ZIP; another user, an expired or unfinished export and a lost capability
     * are refused.
     *
     * @return void
     */
    public function test_only_the_requester_downloads_a_live_export(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/local/evidencehub/lib.php');
        $worker = $this->worker($this->templateid);
        $requester = $this->requester([$worker]);
        $other = $this->requester([$worker]);
        [$export, $file] = $this->prepared($requester);
        $context = \context_user::instance($requester->id);

        $this->setUser($requester);
        $served = dossier_export::servable_file($context, dossier_export::FILEAREA, $this->args($file));
        $this->assertSame((int) $file->get_id(), (int) $served->get_id());
        $this->assertSame('zip bytes', $served->get_content());
        $this->assertNull(dossier_export::servable_file($context, 'other', $this->args($file)));

        // Another user, in the requester's context or in their own with the requester's item.
        $this->setUser($other);
        $this->assertNull(dossier_export::servable_file($context, dossier_export::FILEAREA, $this->args($file)));
        $this->assertNull(dossier_export::servable_file(
            \context_user::instance($other->id),
            dossier_export::FILEAREA,
            $this->args($file)
        ));
        $this->assertFalse(local_evidencehub_pluginfile(get_site(), null, $context, 'export', $this->args($file), true));

        // Expired, unfinished, or the capability gone.
        $this->setUser($requester);
        $DB->set_field(dossier_export::TABLE, 'timeexpires', time() - 1, ['id' => $export->id]);
        $this->assertNull(dossier_export::servable_file($context, dossier_export::FILEAREA, $this->args($file)));
        $DB->set_field(dossier_export::TABLE, 'timeexpires', time() + DAYSECS, ['id' => $export->id]);
        $DB->set_field(dossier_export::TABLE, 'status', export::STATUS_RUNNING, ['id' => $export->id]);
        $this->assertNull(dossier_export::servable_file($context, dossier_export::FILEAREA, $this->args($file)));
        $DB->set_field(dossier_export::TABLE, 'status', export::STATUS_DONE, ['id' => $export->id]);
        $this->assertNotNull(dossier_export::servable_file($context, dossier_export::FILEAREA, $this->args($file)));
        foreach (['local/evidencehub:view', 'local/evidencehub:exportdossier'] as $capability) {
            $DB->set_field(dossier_export::TABLE, 'requestedby', $other->id, ['id' => $export->id]);
            $this->setUser($other);
            $othercontext = \context_user::instance($other->id);
            $otherfile = dossier_export::store($DB->get_record(dossier_export::TABLE, ['id' => $export->id]), $this->zip_on_disk());
            $this->assertNotNull(dossier_export::servable_file($othercontext, dossier_export::FILEAREA, $this->args($otherfile)));
            $prohibit = $this->getDataGenerator()->create_role();
            assign_capability($capability, CAP_PROHIBIT, $prohibit, \context_system::instance()->id, true);
            role_assign($prohibit, $other->id, \context_system::instance()->id);
            accesslib_clear_all_caches_for_unit_testing();
            $served = dossier_export::servable_file($othercontext, dossier_export::FILEAREA, $this->args($otherfile));
            $this->assertNull($served, $capability);
            $otherfile->delete();
            $other = $this->requester([$worker]);
        }
    }

    /**
     * A category exporter downloads the ZIP of a template in their category while they hold exportdossier there.
     *
     * @return void
     */
    public function test_a_category_exporter_downloads_their_categorys_export(): void {
        $category = \context_coursecat::instance($this->getDataGenerator()->create_category()->id);
        $templateid = $this->template([$this->competencyid], $category);
        $requester = $this->viewer(
            ['local/evidencehub:view', 'local/evidencehub:exportdossier', 'moodle/competency:templateview'],
            $category
        );
        [, $file] = $this->prepared($requester, ['templateid' => $templateid, 'contextid' => $category->id]);
        $sitewide = $this->prepared($requester)[1];
        $context = \context_user::instance($requester->id);
        $this->setUser($requester);
        $this->assertNotNull(dossier_export::servable_file($context, dossier_export::FILEAREA, $this->args($file)));
        // A site template's export needs the capability on the site.
        $this->assertNull(dossier_export::servable_file($context, dossier_export::FILEAREA, $this->args($sitewide)));
    }

    /**
     * An exporter demoted on the site, who keeps the capability in the template's category, no longer downloads
     * an export authorised on the site; still holding it there, they do (the control). A row from before the
     * context was recorded falls back to the template's context.
     *
     * @return void
     */
    public function test_the_download_rechecks_the_hub_context(): void {
        global $DB;
        $category = \context_coursecat::instance($this->getDataGenerator()->create_category()->id);
        $templateid = $this->template([$this->competencyid], $category);
        $requester = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        foreach (['local/evidencehub:view', 'local/evidencehub:exportdossier', 'moodle/competency:templateview'] as $capability) {
            assign_capability($capability, CAP_ALLOW, $roleid, \context_system::instance()->id, true);
        }
        role_assign($roleid, $requester->id, \context_system::instance()->id);
        role_assign($roleid, $requester->id, $category->id);
        accesslib_clear_all_caches_for_unit_testing();
        [$export, $file] = $this->prepared($requester, ['templateid' => $templateid]);
        $context = \context_user::instance($requester->id);
        $this->setUser($requester);
        $this->assertNotNull(dossier_export::servable_file($context, dossier_export::FILEAREA, $this->args($file)));

        role_unassign($roleid, $requester->id, \context_system::instance()->id);
        accesslib_clear_all_caches_for_unit_testing();
        $this->assertNull(dossier_export::servable_file($context, dossier_export::FILEAREA, $this->args($file)));
        // An older row without its context: the template's category decides.
        $DB->set_field(dossier_export::TABLE, 'contextid', null, ['id' => $export->id]);
        $this->assertNotNull(dossier_export::servable_file($context, dossier_export::FILEAREA, $this->args($file)));
    }

    /**
     * A ZIP holding a dossier about a worker the requester no longer reads is refused whole.
     *
     * @return void
     */
    public function test_the_download_rechecks_every_worker(): void {
        $readable = $this->worker($this->templateid);
        $dropped = $this->worker($this->templateid);
        $requester = $this->requester([$readable, $dropped]);
        [$export, $file] = $this->prepared($requester);
        $generator = $this->getDataGenerator()->get_plugin_generator('local_evidencehub');
        foreach ([$readable, $dropped] as $worker) {
            $generator->create_dossier([
                'userid' => $worker->id,
                'templateid' => $this->templateid,
                'exportid' => $export->id,
                'createdby' => $requester->id,
            ]);
        }
        $context = \context_user::instance($requester->id);
        $this->setUser($requester);
        $this->assertNotNull(dossier_export::servable_file($context, dossier_export::FILEAREA, $this->args($file)));

        $prohibit = $this->getDataGenerator()->create_role();
        assign_capability('moodle/competency:userevidenceview', CAP_PROHIBIT, $prohibit, \context_system::instance()->id, true);
        role_assign($prohibit, $requester->id, \context_user::instance($dropped->id)->id);
        accesslib_clear_all_caches_for_unit_testing();
        $this->assertNull(dossier_export::servable_file($context, dossier_export::FILEAREA, $this->args($file)));
    }
}
