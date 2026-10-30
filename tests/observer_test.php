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

/**
 * Tests for the observers of core's evidence events.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_evidencehub;

use core_competency\api;
use local_evidencehub\local\audit;
use local_evidencehub\local\details;
use local_evidencehub\local\meta;
use local_evidencehub\local\policy;
use local_evidencehub\local\status;
use local_evidencehub\local\status_repository;
use local_evidencehub\task\recompute_scope;
use local_evidencehub\tests\held_lock_factory;
use local_evidencehub\tests\scenario;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * A change made in tool_lp resets a decided evidence; a deletion drops its details, not its trail;
 * whatever an evidence, a link or a rating changes is recomputed, and deletions clean up.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(observer::class)]
final class observer_test extends \advanced_testcase {
    use scenario;

    /** @var \stdClass The worker. */
    private \stdClass $worker;

    /** @var \stdClass A verifier who may also manage the worker's evidence. */
    private \stdClass $verifier;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('enabled', 1, 'core_competency');
        $this->worker = $this->user_with();
        $this->verifier = $this->user_with([
            'local/evidencehub:verify',
            'moodle/competency:userevidenceview',
            'moodle/competency:userevidencemanage',
        ]);
    }

    /**
     * A verified evidence of the worker.
     *
     * @return \stdClass The meta row.
     */
    private function verified(): \stdClass {
        $ue = $this->evidence_of((int) $this->worker->id);
        $this->setUser($this->worker);
        $meta = details::save($ue, $this->details_data());
        $this->setUser($this->verifier);
        return details::verify((int) $meta->id);
    }

    /**
     * Rename the evidence the way tool_lp's edit page does.
     *
     * @param \stdClass $meta The meta row.
     * @return void
     */
    private function rename_in_tool_lp(\stdClass $meta): void {
        api::update_user_evidence((object) ['id' => (int) $meta->userevidenceid, 'name' => 'Renamed in tool_lp']);
    }

    /**
     * The owner's change in tool_lp sends the verified details back to pending.
     *
     * @return void
     */
    public function test_an_owners_change_in_tool_lp_resets(): void {
        $meta = $this->verified();
        $this->setUser($this->worker);
        $this->rename_in_tool_lp($meta);
        $this->assertSame(meta::VERIFY_PENDING, (int) details::get((int) $meta->userevidenceid)->verifystatus);
        $trail = $this->trail((int) $meta->id);
        $this->assertSame([audit::ACTION_RESET, (int) $this->worker->id], end($trail));
    }

    /**
     * Control: the same change by a verifier other than the owner keeps the decision.
     *
     * @return void
     */
    public function test_a_verifiers_change_in_tool_lp_keeps_the_decision(): void {
        $meta = $this->verified();
        $this->setUser($this->verifier);
        $this->rename_in_tool_lp($meta);
        $this->assertSame(meta::VERIFY_VERIFIED, (int) details::get((int) $meta->userevidenceid)->verifystatus);
        $this->assertNotContains(audit::ACTION_RESET, array_column($this->trail((int) $meta->id), 0));
    }

    /**
     * Deleting the evidence drops its details and keeps the trail; another evidence keeps its details.
     *
     * @return void
     */
    public function test_deleting_the_evidence_drops_its_details_only(): void {
        global $DB;
        $meta = $this->verified();
        $kept = $this->verified();
        $this->setUser($this->worker);
        api::delete_user_evidence((int) $meta->userevidenceid);

        $this->assertNull(details::get((int) $meta->userevidenceid));
        $this->assertNotNull(details::get((int) $kept->userevidenceid));
        $this->assertSame(2, $DB->count_records('local_evidencehub_audit', ['metaid' => $meta->id]));
    }

    /**
     * A framework and a competency of it.
     *
     * @return array [framework, competency].
     */
    private function competency(): array {
        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $generator->create_framework();
        return [$framework, $generator->create_competency(['competencyframeworkid' => $framework->get('id')])];
    }

    /**
     * The stored status of the worker for a competency.
     *
     * @param int $competencyid The competency.
     * @return int|null The status, or null without a row.
     */
    private function status_of(int $competencyid): ?int {
        global $DB;
        $status = $DB->get_field(status_repository::TABLE, 'status', [
            'userid' => $this->worker->id,
            'competencyid' => $competencyid,
        ]);
        return $status === false ? null : (int) $status;
    }

    /**
     * Linking an evidence in core recomputes the pair; unlinking it removes the status.
     *
     * @return void
     */
    public function test_link_and_unlink_recompute(): void {
        [, $competency] = $this->competency();
        $competencyid = (int) $competency->get('id');
        $ue = $this->evidence_of((int) $this->worker->id);
        $this->setAdminUser();
        api::create_user_evidence_competency($ue, $competencyid);
        $this->assertSame(status::STATUS_UNVERIFIED, $this->status_of($competencyid));
        api::delete_user_evidence_competency($ue, $competencyid);
        $this->assertNull($this->status_of($competencyid));
    }

    /**
     * A pair whose user another recomputation holds is queued, not lost; the queued task stores it.
     *
     * @return void
     */
    public function test_a_locked_user_queues_the_pair(): void {
        [, $competency] = $this->competency();
        $competencyid = (int) $competency->get('id');
        $ue = $this->evidence_of((int) $this->worker->id);
        $this->setAdminUser();
        held_lock_factory::while_held([(int) $this->worker->id], function () use ($ue, $competencyid): void {
            api::create_user_evidence_competency($ue, $competencyid);
        });
        $this->assertNull($this->status_of($competencyid));
        $queued = array_map(
            fn($task) => $task->get_custom_data(),
            array_values(\core\task\manager::get_adhoc_tasks(recompute_scope::class))
        );
        $this->assertEquals([(object) ['userid' => (int) $this->worker->id, 'competencyid' => $competencyid]], $queued);

        ob_start();
        $this->runAdhocTasks(recompute_scope::class);
        $output = ob_get_clean();
        $expected = "Recomputed the validity of user {$this->worker->id} in competency $competencyid";
        $this->assertStringContainsString($expected, $output);
        $this->assertSame(status::STATUS_UNVERIFIED, $this->status_of($competencyid));
    }

    /**
     * A rating recomputes the pair: a proficient rating proves a technical competency.
     *
     * @return void
     */
    public function test_a_rating_recomputes(): void {
        [, $competency] = $this->competency();
        $competencyid = (int) $competency->get('id');
        $this->setAdminUser();
        api::grade_competency((int) $this->worker->id, $competencyid, 4);
        $this->assertSame(status::STATUS_VALID, $this->status_of($competencyid));
    }

    /**
     * The rated event alone recomputes the pair, the plain one naming only the user competency.
     *
     * @return void
     */
    public function test_a_rated_event_alone_recomputes(): void {
        [, $competency] = $this->competency();
        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        // Written without events: only the triggered one below reaches the observers.
        $uc = $generator->create_user_competency([
            'userid' => $this->worker->id,
            'competencyid' => $competency->get('id'),
            'grade' => 4,
            'proficiency' => 1,
        ]);
        $rating = ['usercompetencyid' => $uc->get('id'), 'action' => \core_competency\evidence::ACTION_OVERRIDE, 'grade' => 4];
        $generator->create_evidence($rating);
        $this->assertNull($this->status_of((int) $competency->get('id')));
        \core\event\competency_user_competency_rated::create_from_user_competency($uc)->trigger();
        $this->assertSame(status::STATUS_VALID, $this->status_of((int) $competency->get('id')));
    }

    /**
     * The plugin's own log entry never starts a recomputation; a foreign entry with a grade does.
     *
     * @return void
     */
    public function test_the_plugins_own_log_entry_does_not_recompute(): void {
        global $DB;
        [, $competency] = $this->competency();
        $competencyid = (int) $competency->get('id');
        $ue = $this->evidence_of((int) $this->worker->id);
        $this->setAdminUser();
        api::create_user_evidence_competency($ue, $competencyid);
        $context = \context_user::instance((int) $this->worker->id);
        $DB->delete_records(status_repository::TABLE, ['competencyid' => $competencyid]);

        // Even with a grade, which the plugin's own entries never carry, a row of its component is skipped.
        api::add_evidence(
            (int) $this->worker->id,
            $competencyid,
            $context,
            \core_competency\evidence::ACTION_OVERRIDE,
            'evidence_status_valid',
            'local_evidencehub',
            null,
            false,
            null,
            1
        );
        $this->assertNull($this->status_of($competencyid), 'The plugin\'s own log entry started a recomputation.');

        // Control: a foreign entry without a grade does nothing either; one with a grade recomputes.
        api::add_evidence(
            (int) $this->worker->id,
            $competencyid,
            $context,
            \core_competency\evidence::ACTION_LOG,
            'evidence_manualoverride',
            'core_competency'
        );
        $this->assertNull($this->status_of($competencyid));
        api::add_evidence(
            (int) $this->worker->id,
            $competencyid,
            $context,
            \core_competency\evidence::ACTION_OVERRIDE,
            'evidence_manualoverride',
            'core_competency',
            null,
            false,
            null,
            1
        );
        $this->assertNotNull($this->status_of($competencyid));
    }

    /**
     * Entering, verifying and changing details recompute every linked competency.
     *
     * @return void
     */
    public function test_details_events_recompute(): void {
        [$framework, $competency] = $this->competency();
        $competencyid = (int) $competency->get('id');
        $this->setAdminUser();
        policy::save((object) [
            'frameworkid' => (int) $framework->get('id'),
            'competencyid' => 0,
            'kind' => policy::KIND_REGULATORY,
            'requireverified' => 1,
            'requireproficiency' => 0,
            'minhours' => null,
            'alert1' => 60,
            'alert2' => 30,
            'gracedays' => 0,
            'cyclemonths' => 0,
            'onexpiry' => policy::ONEXPIRY_LOG,
        ]);
        $ue = $this->evidence_of((int) $this->worker->id);
        api::create_user_evidence_competency($ue, $competencyid);
        $this->setUser($this->worker);
        $meta = details::save($ue, $this->details_data(['expirydate' => 20990115]));
        $this->assertSame(status::STATUS_UNVERIFIED, $this->status_of($competencyid));

        $this->setUser($this->verifier);
        details::verify((int) $meta->id);
        $this->assertSame(status::STATUS_VALID, $this->status_of($competencyid));

        details::reject((int) $meta->id, 'Unreadable.');
        $this->assertSame(status::STATUS_UNVERIFIED, $this->status_of($competencyid));
    }

    /**
     * A change in tool_lp recomputes the linked competencies too.
     *
     * @return void
     */
    public function test_a_change_in_tool_lp_recomputes(): void {
        global $DB;
        [, $competency] = $this->competency();
        $competencyid = (int) $competency->get('id');
        $ue = $this->evidence_of((int) $this->worker->id);
        $this->setAdminUser();
        api::create_user_evidence_competency($ue, $competencyid);
        $DB->delete_records(status_repository::TABLE, ['competencyid' => $competencyid]);
        api::update_user_evidence((object) ['id' => (int) $ue->get('id'), 'name' => 'Renamed in tool_lp']);
        $this->assertSame(status::STATUS_UNVERIFIED, $this->status_of($competencyid));
    }

    /**
     * Deleting a competency drops its statuses and its policy; deleting a framework drops its
     * policies; deleting a user drops their statuses. Other rows stay.
     *
     * @return void
     */
    public function test_deletions_clean_up(): void {
        global $DB;
        $records = $this->getDataGenerator()->get_plugin_generator('local_evidencehub');
        [$framework, $competency] = $this->competency();
        [$keptframework, $kept] = $this->competency();
        $other = $this->user_with();
        foreach ([$competency, $kept] as $item) {
            $records->create_status(['userid' => $other->id, 'competencyid' => $item->get('id')]);
            $DB->insert_record(policy::TABLE, (object) [
                'frameworkid' => $item->get('competencyframeworkid'),
                'competencyid' => $item->get('id'),
                'kind' => policy::KIND_TECHNICAL,
                'timecreated' => time(),
                'timemodified' => time(),
            ]);
        }
        $DB->insert_record(policy::TABLE, (object) [
            'frameworkid' => $framework->get('id'),
            'competencyid' => 0,
            'kind' => policy::KIND_TECHNICAL,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $this->setAdminUser();

        api::delete_competency((int) $competency->get('id'));
        $this->assertFalse($DB->record_exists(status_repository::TABLE, ['competencyid' => $competency->get('id')]));
        $this->assertFalse($DB->record_exists(policy::TABLE, ['competencyid' => $competency->get('id')]));
        $this->assertTrue($DB->record_exists(status_repository::TABLE, ['competencyid' => $kept->get('id')]));
        $this->assertTrue($DB->record_exists(policy::TABLE, ['competencyid' => $kept->get('id')]));

        api::delete_framework((int) $framework->get('id'));
        $this->assertFalse($DB->record_exists(policy::TABLE, ['frameworkid' => $framework->get('id')]));
        $this->assertTrue($DB->record_exists(policy::TABLE, ['frameworkid' => $keptframework->get('id')]));

        $records->create_status(['userid' => $this->worker->id, 'competencyid' => $kept->get('id')]);
        delete_user($other);
        $this->assertFalse($DB->record_exists(status_repository::TABLE, ['userid' => $other->id]));
        $this->assertTrue($DB->record_exists(status_repository::TABLE, ['userid' => $this->worker->id]));
    }
}
