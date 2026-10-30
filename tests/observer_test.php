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
use local_evidencehub\tests\scenario;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * A change made in tool_lp resets a decided evidence; a deletion drops its details, not its trail.
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
}
