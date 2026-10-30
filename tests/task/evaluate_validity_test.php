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
 * Tests for the task that follows the next transitions.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_evidencehub\task;

use local_evidencehub\local\status;
use local_evidencehub\local\status_repository;
use local_evidencehub\tests\held_lock_factory;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Only the rows whose transition has come are recomputed, oldest first, one chunk per run.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(evaluate_validity::class)]
#[CoversClass(status_repository::class)]
final class evaluate_validity_test extends \advanced_testcase {
    /** @var \core_competency\competency The competency. */
    private $competency;

    /** @var int The frozen time. */
    private int $now;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('enabled', 1, 'core_competency');
        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $generator->create_framework();
        $this->competency = $generator->create_competency(['competencyframeworkid' => $framework->get('id')]);
        $this->now = (new \DateTimeImmutable('2026-06-15 12:00', new \DateTimeZone('UTC')))->getTimestamp();
        $this->mock_clock_with_frozen($this->now);
    }

    /**
     * A worker with a valid evidence of the competency, its stored row then made wrong on purpose.
     *
     * @param int|null $nexttransition The transition to store.
     * @return \stdClass The status row.
     */
    private function stale_row(?int $nexttransition): \stdClass {
        global $DB;
        $worker = $this->getDataGenerator()->create_user();
        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $ue = $generator->create_user_evidence(['userid' => $worker->id]);
        $link = ['userevidenceid' => $ue->get('id'), 'competencyid' => $this->competency->get('id')];
        $generator->create_user_evidence_competency($link);
        $this->getDataGenerator()->get_plugin_generator('local_evidencehub')->create_meta([
            'userid' => $worker->id,
            'userevidenceid' => $ue->get('id'),
            'issuedate' => 20260101,
            'expirydate' => 20280101,
        ]);
        status_repository::recompute((int) $worker->id, (int) $this->competency->get('id'));
        $row = $DB->get_record(status_repository::TABLE, ['userid' => $worker->id], '*', MUST_EXIST);
        $DB->update_record(status_repository::TABLE, (object) [
            'id' => $row->id,
            'status' => status::STATUS_EXPIRED,
            'nexttransition' => $nexttransition,
        ]);
        return $row;
    }

    /**
     * Run the task, keeping its output.
     *
     * @return string What it printed.
     */
    private function run_task(): string {
        ob_start();
        (new evaluate_validity())->execute();
        return ob_get_clean();
    }

    /**
     * A due row is recomputed; a row not yet due and a row that never changes by itself are not.
     *
     * @return void
     */
    public function test_only_due_rows_are_recomputed(): void {
        global $DB;
        $due = $this->stale_row($this->now - 1);
        $ontime = $this->stale_row($this->now);
        $future = $this->stale_row($this->now + 3600);
        $never = $this->stale_row(null);

        $this->assertStringContainsString('Recomputed 2 due', $this->run_task());
        $this->assertSame(status::STATUS_VALID, (int) $DB->get_field(status_repository::TABLE, 'status', ['id' => $due->id]));
        $this->assertSame(status::STATUS_VALID, (int) $DB->get_field(status_repository::TABLE, 'status', ['id' => $ontime->id]));
        $this->assertSame(status::STATUS_EXPIRED, (int) $DB->get_field(status_repository::TABLE, 'status', ['id' => $future->id]));
        $this->assertSame(status::STATUS_EXPIRED, (int) $DB->get_field(status_repository::TABLE, 'status', ['id' => $never->id]));
    }

    /**
     * One run takes one chunk, the oldest transitions first; the next run takes the rest.
     *
     * @return void
     */
    public function test_one_chunk_per_run_oldest_first(): void {
        global $DB;
        $records = $this->getDataGenerator()->get_plugin_generator('local_evidencehub');
        // Rows with nothing behind them, of ids no user has: their recomputation removes them.
        $first = (int) $DB->get_field_sql('SELECT MAX(id) FROM {user}') + 1000;
        for ($i = 0; $i <= status_repository::CHUNK; $i++) {
            $records->create_status([
                'userid' => $first + $i,
                'competencyid' => $this->competency->get('id'),
                'nexttransition' => $this->now - 10000 + $i,
            ]);
        }
        $this->assertStringContainsString('Recomputed ' . status_repository::CHUNK . ' due', $this->run_task());
        $left = $DB->get_records(status_repository::TABLE);
        $this->assertCount(1, $left);
        $this->assertSame($first + status_repository::CHUNK, (int) reset($left)->userid, 'A newer row went before an older one.');

        $this->run_task();
        $this->assertSame(0, $DB->count_records(status_repository::TABLE));
    }

    /**
     * A row that fails counts toward the run, is put off by an hour and never stops the others.
     *
     * @return void
     */
    public function test_a_failing_row_does_not_stop_the_others(): void {
        global $DB;
        $failing = $this->stale_row($this->now - 100);
        $other = $this->stale_row($this->now - 1);
        $output = held_lock_factory::while_held([(int) $failing->userid], fn(): string => $this->run_task());
        $this->assertStringContainsString('Recomputed 2 due validity statuses (1 failed).', $output);
        $this->assertStringContainsString("Recomputing the validity of user {$failing->userid}", $output);
        $this->assertSame(status::STATUS_VALID, (int) $DB->get_field(status_repository::TABLE, 'status', ['id' => $other->id]));
        $kept = $DB->get_record(status_repository::TABLE, ['id' => $failing->id]);
        $this->assertSame(status::STATUS_EXPIRED, (int) $kept->status);
        $this->assertSame($this->now + HOURSECS, (int) $kept->nexttransition);
    }

    /**
     * With competencies disabled the task changes nothing.
     *
     * @return void
     */
    public function test_nothing_while_competencies_are_disabled(): void {
        global $DB;
        $due = $this->stale_row($this->now - 1);
        set_config('enabled', 0, 'core_competency');
        $this->assertStringContainsString('not enabled', $this->run_task());
        $this->assertSame(status::STATUS_EXPIRED, (int) $DB->get_field(status_repository::TABLE, 'status', ['id' => $due->id]));
    }
}
