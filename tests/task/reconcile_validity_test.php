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
 * Tests for the weekly reconciliation and the recomputation of a policy's scope.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_evidencehub\task;

use local_evidencehub\local\policy;
use local_evidencehub\local\status;
use local_evidencehub\local\status_repository;
use local_evidencehub\tests\held_lock_factory;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Reconciliation heals what a missed event left; a policy's scope recomputes exactly its pairs.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(reconcile_validity::class)]
#[CoversClass(recompute_scope::class)]
#[CoversClass(status_repository::class)]
final class reconcile_validity_test extends \advanced_testcase {
    /** @var \core_competency\competency_framework Framework A. */
    private $frameworka;

    /** @var array Competencies of framework A, two. */
    private array $competenciesa = [];

    /** @var \core_competency\competency The competency of framework B. */
    private $competencyb;

    /** @var \stdClass The worker. */
    private \stdClass $worker;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('enabled', 1, 'core_competency');
        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $this->frameworka = $generator->create_framework();
        $frameworkb = $generator->create_framework();
        for ($i = 0; $i < 2; $i++) {
            $this->competenciesa[] = $generator->create_competency(['competencyframeworkid' => $this->frameworka->get('id')]);
        }
        $this->competencyb = $generator->create_competency(['competencyframeworkid' => $frameworkb->get('id')]);
        $this->worker = $this->getDataGenerator()->create_user();
        // Links written without events, as an import or a missed event leaves them.
        foreach (array_merge($this->competenciesa, [$this->competencyb]) as $competency) {
            $ue = $generator->create_user_evidence(['userid' => $this->worker->id]);
            $link = ['userevidenceid' => $ue->get('id'), 'competencyid' => $competency->get('id')];
            $generator->create_user_evidence_competency($link);
        }
    }

    /**
     * The competencies the worker has a status row of, sorted.
     *
     * @return array Competency ids.
     */
    private function stored(): array {
        global $DB;
        $ids = array_map('intval', $DB->get_fieldset_select(status_repository::TABLE, 'competencyid', '1 = 1'));
        sort($ids);
        return $ids;
    }

    /**
     * Ids of competencies, sorted.
     *
     * @param array $competencies The competencies.
     * @return array
     */
    private static function ids(array $competencies): array {
        $ids = array_map(fn($competency): int => (int) $competency->get('id'), $competencies);
        sort($ids);
        return $ids;
    }

    /**
     * The reconciliation writes the rows no event wrote and removes the one nothing proves.
     *
     * @return void
     */
    public function test_reconcile_heals_the_rows(): void {
        global $DB;
        $this->assertSame([], $this->stored());
        $orphan = $this->getDataGenerator()->get_plugin_generator('local_evidencehub')->create_status([
            'userid' => $this->getDataGenerator()->create_user()->id,
            'competencyid' => $this->competencyb->get('id'),
        ]);
        ob_start();
        (new reconcile_validity())->execute();
        $output = ob_get_clean();
        $this->assertStringContainsString('Reconciled 4 pairs: 3 with a validity status', $output);
        $this->assertSame(self::ids(array_merge($this->competenciesa, [$this->competencyb])), $this->stored());
        $this->assertFalse($DB->record_exists(status_repository::TABLE, ['id' => $orphan->id]));
        $this->assertSame(3, $DB->count_records(status_repository::TABLE, ['status' => status::STATUS_UNVERIFIED]));
    }

    /**
     * Pairs that fail are reported with their ids and never stop the others.
     *
     * @return void
     */
    public function test_a_failing_pair_does_not_stop_the_others(): void {
        global $DB;
        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $other = $this->getDataGenerator()->create_user();
        $ue = $generator->create_user_evidence(['userid' => $other->id]);
        $link = ['userevidenceid' => $ue->get('id'), 'competencyid' => $this->competencyb->get('id')];
        $generator->create_user_evidence_competency($link);
        $output = held_lock_factory::while_held([(int) $this->worker->id], function (): string {
            ob_start();
            (new reconcile_validity())->execute();
            return ob_get_clean();
        });
        $this->assertStringContainsString('Reconciled 4 pairs: 1 with a validity status, 3 failed.', $output);
        $failed = "Recomputing the validity of user {$this->worker->id} in competency {$this->competencyb->get('id')} failed";
        $this->assertStringContainsString($failed, $output);
        $this->assertTrue($DB->record_exists(status_repository::TABLE, ['userid' => $other->id]));
        $this->assertFalse($DB->record_exists(status_repository::TABLE, ['userid' => $this->worker->id]));
    }

    /**
     * The reconciliation reads every chunk, not only the first.
     *
     * @return void
     */
    public function test_reconcile_reads_every_chunk(): void {
        global $DB;
        $records = $this->getDataGenerator()->get_plugin_generator('local_evidencehub');
        // Rows nothing proves, of ids no user has (test sequences start anywhere): each one visited is removed.
        $first = (int) $DB->get_field_sql('SELECT MAX(id) FROM {user}') + 1000;
        for ($i = 0; $i <= status_repository::CHUNK; $i++) {
            $records->create_status(['userid' => $first + $i, 'competencyid' => $this->competencyb->get('id')]);
        }
        ob_start();
        (new reconcile_validity())->execute();
        $output = ob_get_clean();
        $left = $DB->get_fieldset_select(status_repository::TABLE, 'userid', 'userid >= :first', ['first' => $first]);
        $this->assertSame([], $left, $output);
        $this->assertStringContainsString('Reconciled ' . (status_repository::CHUNK + 4) . ' pairs', $output);
    }

    /**
     * A proficient rating alone has a pair under a policy that counts ratings, none under a regulatory one.
     *
     * @return void
     */
    public function test_reconcile_finds_rated_pairs_by_policy(): void {
        global $DB;
        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $rated = $this->getDataGenerator()->create_user();
        $uc = $generator->create_user_competency([
            'userid' => $rated->id,
            'competencyid' => $this->competencyb->get('id'),
            'grade' => 4,
            'proficiency' => 1,
        ]);
        $rating = ['usercompetencyid' => $uc->get('id'), 'action' => \core_competency\evidence::ACTION_OVERRIDE, 'grade' => 4];
        $generator->create_evidence($rating);
        $uncounted = $generator->create_user_competency([
            'userid' => $rated->id,
            'competencyid' => $this->competenciesa[0]->get('id'),
            'grade' => 4,
            'proficiency' => 1,
        ]);
        $DB->insert_record(policy::TABLE, (object) [
            'frameworkid' => $this->frameworka->get('id'),
            'competencyid' => 0,
            'kind' => policy::KIND_REGULATORY,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        ob_start();
        (new reconcile_validity())->execute();
        ob_end_clean();
        $this->assertSame(status::STATUS_VALID, (int) $DB->get_field(status_repository::TABLE, 'status', [
            'userid' => $rated->id,
            'competencyid' => $this->competencyb->get('id'),
        ]));
        $this->assertFalse($DB->record_exists(status_repository::TABLE, [
            'userid' => $rated->id,
            'competencyid' => $uncounted->get('competencyid'),
        ]));
    }

    /**
     * A competency's scope recomputes that competency only.
     *
     * @return void
     */
    public function test_a_competency_scope(): void {
        $task = new recompute_scope();
        $task->set_custom_data(['competencyid' => $this->competenciesa[0]->get('id')]);
        ob_start();
        $task->execute();
        ob_end_clean();
        $this->assertSame(self::ids([$this->competenciesa[0]]), $this->stored());
    }

    /**
     * A framework's scope recomputes its competencies and no other framework's.
     *
     * @return void
     */
    public function test_a_framework_scope(): void {
        $task = new recompute_scope();
        $task->set_custom_data(['frameworkid' => $this->frameworka->get('id')]);
        ob_start();
        $task->execute();
        ob_end_clean();
        $this->assertSame(self::ids($this->competenciesa), $this->stored());
    }

    /**
     * A scope with neither id is refused.
     *
     * @return void
     */
    public function test_a_scope_needs_an_id(): void {
        $task = new recompute_scope();
        $task->set_custom_data([]);
        $this->expectException(\coding_exception::class);
        $task->execute();
    }

    /**
     * A policy change reaches the stored statuses through the queued task.
     *
     * @return void
     */
    public function test_a_policy_change_recomputes_its_scope(): void {
        global $DB;
        $this->setAdminUser();
        $competency = $this->competenciesa[0];
        $ue = $DB->get_field('competency_userevidencecomp', 'userevidenceid', ['competencyid' => $competency->get('id')]);
        $this->getDataGenerator()->get_plugin_generator('local_evidencehub')->create_meta([
            'userid' => $this->worker->id,
            'userevidenceid' => $ue,
            'issuedate' => 20260101,
            'expirydate' => 20990101,
        ]);
        status_repository::recompute((int) $this->worker->id, (int) $competency->get('id'));
        $status = fn(): int => (int) $DB->get_field(status_repository::TABLE, 'status', ['competencyid' => $competency->get('id')]);
        $this->assertSame(status::STATUS_VALID, $status());

        policy::save((object) [
            'frameworkid' => (int) $this->frameworka->get('id'),
            'competencyid' => (int) $competency->get('id'),
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
        $this->assertSame(status::STATUS_VALID, $status(), 'The policy change recomputed inline.');
        ob_start();
        $this->runAdhocTasks(recompute_scope::class);
        ob_end_clean();
        $this->assertSame(status::STATUS_UNVERIFIED, $status());
    }
}
