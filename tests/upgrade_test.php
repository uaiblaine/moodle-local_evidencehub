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
        // The state the step's fill starts from: the column exists (its field_exists() guard skips adding it)
        // and every flag is still 0.
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
        // The run goes on to the last step's savepoint.
        $this->assertEquals(2026100519, get_config('local_evidencehub', 'version'));
    }

    /**
     * Upgrading from 2026100503 adds the sensitive flag's column where it is missing, then fills it.
     *
     * @return void
     */
    public function test_the_sensitive_flag_column_is_added(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        [$c1, $c2] = $this->competencies(2);
        $worker = $this->getDataGenerator()->create_user();
        $this->status_row((int) $worker->id, $c1, status::STATUS_VALID, 20300101, 'aso');
        $this->status_row((int) $worker->id, $c2, status::STATUS_VALID, 20300101, 'nrcertificate');
        $dbman = $DB->get_manager();
        $table = new \xmldb_table('local_evidencehub_status');
        $field = new \xmldb_field('issensitive');
        // As a 2026100503 site holds the table: no column.
        $dbman->drop_field($table, $field);
        $this->assertFalse($dbman->field_exists($table, $field));
        set_config('version', 2026100503, 'local_evidencehub');

        $this->assertTrue(xmldb_local_evidencehub_upgrade(2026100503));

        $this->assertTrue($dbman->field_exists($table, $field));
        $this->assertTrue($DB->get_columns('local_evidencehub_status')['issensitive']->not_null);
        $flags = $DB->get_records_menu(
            'local_evidencehub_status',
            ['userid' => $worker->id],
            'competencyid',
            'competencyid, issensitive'
        );
        $this->assertSame([$c1 => 1, $c2 => 0], array_map('intval', $flags));
    }

    /**
     * Upgrading from 2026100506 adds the export's hub context, null on the rows already there.
     *
     * @return void
     */
    public function test_the_export_context_is_added(): void {
        global $DB;
        $this->resetAfterTest();
        $dbman = $DB->get_manager();
        $table = new \xmldb_table('local_evidencehub_export');
        $field = new \xmldb_field('contextid');
        $export = $this->getDataGenerator()->get_plugin_generator('local_evidencehub')->create_export([
            'requestedby' => 2,
            'templateid' => 1,
            'contextid' => \context_system::instance()->id,
        ]);
        // As a 2026100506 site holds the table: no column.
        $dbman->drop_field($table, $field);
        $this->assertFalse($dbman->field_exists($table, $field));
        set_config('version', 2026100506, 'local_evidencehub');

        $this->assertTrue(xmldb_local_evidencehub_upgrade(2026100506));

        $this->assertTrue($dbman->field_exists($table, $field));
        $this->assertNull($DB->get_field('local_evidencehub_export', 'contextid', ['id' => $export->id]));
        $this->assertFalse($DB->get_columns('local_evidencehub_export')['contextid']->not_null);
        $this->assertEquals(2026100519, get_config('local_evidencehub', 'version'));
    }

    /**
     * Upgrading from 2026100518 deletes the old list's stored filter of every reader (decision 1: no migration), and
     * nothing else: the same reader's other preferences stay, the pairs report's own among them.
     *
     * Changes that must make it fail: the step deleting by a name pattern that also takes the report's preference, or
     * leaving the old rows.
     *
     * @return void
     */
    public function test_the_old_list_filter_is_deleted(): void {
        global $DB;
        $this->resetAfterTest();
        $reader = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        set_user_preference('local_evidencehub_hubfilter', json_encode(['templateid' => 5]), $reader);
        set_user_preference('local_evidencehub_hubfilter', json_encode(['templateid' => 6]), $other);
        set_user_preference('local_evidencehub_report', json_encode(['suspended' => true]), $reader);
        set_user_preference('local_evidencehub_hubfilterx', 'kept', $reader);
        // The precondition: both readers hold the old row.
        $this->assertSame(2, $DB->count_records('user_preferences', ['name' => 'local_evidencehub_hubfilter']));
        set_config('version', 2026100518, 'local_evidencehub');

        $this->assertTrue(xmldb_local_evidencehub_upgrade(2026100518));

        $this->assertSame(0, $DB->count_records('user_preferences', ['name' => 'local_evidencehub_hubfilter']));
        // The control: the same reader's other preferences stay.
        $this->assertSame(
            json_encode(['suspended' => true]),
            $DB->get_field('user_preferences', 'value', ['userid' => $reader->id, 'name' => 'local_evidencehub_report'])
        );
        $this->assertSame(
            'kept',
            $DB->get_field('user_preferences', 'value', ['userid' => $reader->id, 'name' => 'local_evidencehub_hubfilterx'])
        );
        $this->assertEquals(2026100519, get_config('local_evidencehub', 'version'));
    }
}
