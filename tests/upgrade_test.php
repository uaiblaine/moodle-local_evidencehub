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
        $this->assertEquals(2026100523, get_config('local_evidencehub', 'version'));
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
        $this->assertEquals(2026100523, get_config('local_evidencehub', 'version'));
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
        $this->assertEquals(2026100523, get_config('local_evidencehub', 'version'));
    }

    /**
     * A log entry of the plugin in core's competency history, written as core's add_evidence() writes it.
     *
     * @param int $userid The worker.
     * @param int $competencyid The competency.
     * @param string $identifier The description's string id.
     * @param string $component The description's component.
     * @return int The entry's id.
     */
    private function history_entry(int $userid, int $competencyid, string $identifier, string $component): int {
        global $DB;
        $uc = $DB->get_record('competency_usercomp', ['userid' => $userid, 'competencyid' => $competencyid]);
        $ucid = $uc ? (int) $uc->id : (int) $this->getDataGenerator()->get_plugin_generator('core_competency')
            ->create_user_competency(['userid' => $userid, 'competencyid' => $competencyid])->get('id');
        return (int) $DB->insert_record('competency_evidence', (object) [
            'usercompetencyid' => $ucid,
            'contextid' => \context_user::instance($userid)->id,
            'action' => \core_competency\evidence::ACTION_LOG,
            'descidentifier' => $identifier,
            'desccomponent' => $component,
            'desca' => json_encode('2027-01-15'),
            'timecreated' => time(),
            'timemodified' => time(),
            'usermodified' => 2,
        ]);
    }

    /**
     * Upgrading from 2026100522 rewrites the plugin's status entries of a sensitive pair into the neutral one, with no
     * date: core's competency pages show them to readers without viewsensitive.
     *
     * Changes that must make it fail: the step ignoring the pair's flag, matching the pair by competency or worker
     * alone, or taking another component's entries or the plugin's other entries. Each has its control here: the same
     * worker's plain pair, another worker's plain pair of the same competency, core's own entry on the sensitive pair,
     * and an identifier that only an unescaped LIKE wildcard matches.
     *
     * @return void
     */
    public function test_the_status_entries_of_a_sensitive_pair_are_neutralised(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        [$c1, $c2] = $this->competencies(2);
        $worker = (int) $this->getDataGenerator()->create_user()->id;
        $other = (int) $this->getDataGenerator()->create_user()->id;
        $this->status_row($worker, $c1, status::STATUS_VALID, 20300101, 'aso');
        $this->status_row($worker, $c2, status::STATUS_VALID, 20300101, 'nrcertificate');
        $this->status_row($other, $c1, status::STATUS_VALID, 20300101, 'nrcertificate');
        $sensitive = [
            $this->history_entry($worker, $c1, 'evidence_status_valid', 'local_evidencehub'),
            $this->history_entry($worker, $c1, 'evidence_status_expired', 'local_evidencehub'),
        ];
        $kept = [
            'plain pair' => $this->history_entry($worker, $c2, 'evidence_status_due', 'local_evidencehub'),
            'other worker' => $this->history_entry($other, $c1, 'evidence_status_critical', 'local_evidencehub'),
            'core entry' => $this->history_entry($worker, $c1, 'evidence_status_valid', 'core_competency'),
            'wildcard' => $this->history_entry($worker, $c1, 'evidence1status2valid', 'local_evidencehub'),
        ];
        $this->assertSame(
            1,
            (int) $DB->get_field('local_evidencehub_status', 'issensitive', ['userid' => $worker, 'competencyid' => $c1]),
            'Precondition: the pair is flagged sensitive.'
        );
        set_config('version', 2026100522, 'local_evidencehub');

        $this->assertTrue(xmldb_local_evidencehub_upgrade(2026100522));

        foreach ($sensitive as $id) {
            $entry = $DB->get_record('competency_evidence', ['id' => $id], '*', MUST_EXIST);
            $this->assertSame('evidence_status_restricted', $entry->descidentifier);
            $this->assertNull($entry->desca);
            $this->assertSame(
                get_string('evidence_status_restricted', 'local_evidencehub'),
                (string) (new \core_competency\evidence($id))->get_description()
            );
        }
        foreach ($kept as $name => $id) {
            $entry = $DB->get_record('competency_evidence', ['id' => $id], '*', MUST_EXIST);
            $this->assertNotSame('evidence_status_restricted', $entry->descidentifier, $name);
            $this->assertSame(json_encode('2027-01-15'), $entry->desca, $name);
        }
        $this->assertEquals(2026100523, get_config('local_evidencehub', 'version'));
    }
}
