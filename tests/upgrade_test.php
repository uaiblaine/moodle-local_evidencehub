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

namespace local_evidencehub;

use local_evidencehub\local\status;
use local_evidencehub\tests\hub_scenario;
use PHPUnit\Framework\Attributes\CoversFunction;

/**
 * The upgrade steps.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversFunction('xmldb_local_evidencehub_upgrade')]
final class upgrade_test extends \advanced_testcase {
    use hub_scenario;

    #[\Override]
    public static function setUpBeforeClass(): void {
        global $CFG;
        parent::setUpBeforeClass();
        require_once($CFG->libdir . '/upgradelib.php');
        require_once($CFG->dirroot . '/local/evidencehub/db/upgrade.php');
    }

    /**
     * Upgrading from 2026100503 marks the rows a sensitive evidence decides, and only those.
     *
     * @return void
     */
    public function test_the_sensitive_flag_is_filled(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        [$c1, $c2, $c3] = $this->competencies(3);
        $worker = $this->getDataGenerator()->create_user();
        $this->status_row((int) $worker->id, $c1, status::STATUS_VALID, 20300101, 'aso');
        $this->status_row((int) $worker->id, $c2, status::STATUS_VALID, 20300101, 'nrcertificate');
        $this->status_row((int) $worker->id, $c3, status::STATUS_UNVERIFIED);
        // As a 2026100503 site holds them: the column added, nothing filled.
        $DB->set_field('local_evidencehub_status', 'issensitive', 0);
        set_config('version', 2026100503, 'local_evidencehub');

        $this->assertTrue(xmldb_local_evidencehub_upgrade(2026100503));
        $flags = $DB->get_records_menu(
            'local_evidencehub_status',
            ['userid' => $worker->id],
            'competencyid',
            'competencyid, issensitive'
        );
        $this->assertSame([$c1 => 1, $c2 => 0, $c3 => 0], array_map('intval', $flags));
        $this->assertEquals(2026100504, get_config('local_evidencehub', 'version'));
    }
}
