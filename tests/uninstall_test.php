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

use core_reportbuilder\local\helpers\user_filter_manager;
use core_reportbuilder\local\models\report;
use core_reportbuilder\local\models\user_filter;
use core_reportbuilder\local\report\base;
use core_reportbuilder\local\systemreports\reports_list;
use core_reportbuilder\manager;
use local_evidencehub\reportbuilder\local\systemreports\hub_pairs;
use local_evidencehub\tests\hub_scenario;
use PHPUnit\Framework\Attributes\CoversFunction;

/**
 * What the plugin's uninstall removes that core's uninstall_plugin() leaves behind.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversFunction('xmldb_local_evidencehub_uninstall')]
final class uninstall_test extends \advanced_testcase {
    use hub_scenario;

    #[\Override]
    public static function setUpBeforeClass(): void {
        global $CFG;
        parent::setUpBeforeClass();
        require_once($CFG->dirroot . '/local/evidencehub/db/uninstall.php');
    }

    /**
     * The uninstall deletes the pairs report's persistents with every reader's stored filters, the one built under a
     * component a client named included, and the plugin's own user preferences; another plugin's report filed under
     * this plugin's component, with its stored filters, and another preference stay.
     *
     * Changes that must make it fail: the reports cut by the component instead of the report's source (the other
     * plugin's report goes, or the pairs report under another component stays); a preference left behind, or one of
     * another component taken with them.
     *
     * @return void
     */
    public function test_the_plugins_reports_filters_and_preferences_go(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('enabled', 1, 'core_competency');
        $this->setAdminUser();
        $templateid = $this->template($this->competencies(1));
        $reader = $this->getDataGenerator()->create_user();
        $report = hub_pairs::for_template(\context_system::instance(), $templateid);
        $reportid = (int) $report->get_report_persistent()->get('id');
        user_filter_manager::set($reportid, ['validity:status_operator' => 1, 'validity:status_value' => 5]);
        user_filter_manager::set($reportid, ['user:cohort_operator' => 1, 'user:cohort_value' => 3], (int) $reader->id);

        // Another plugin's system report filed under this plugin's component, with a reader's stored filters.
        $other = new report(0, (object) [
            'name' => '',
            'source' => reports_list::class,
            'type' => base::TYPE_SYSTEM_REPORT,
            'contextid' => \context_system::instance()->id,
            'component' => 'local_evidencehub',
            'area' => hub_pairs::AREA,
            'itemid' => $templateid,
        ]);
        $other->create();
        user_filter_manager::set((int) $other->get('id'), ['validity:status_value' => 2], (int) $reader->id);
        // The pairs report under a component a client named, its source spelled as PHP would still resolve it.
        $named = new report(0, (object) [
            'name' => '',
            'source' => '\\' . strtoupper(hub_pairs::class),
            'type' => base::TYPE_SYSTEM_REPORT,
            'contextid' => \context_system::instance()->id,
            'component' => 'local_other',
            'area' => 'anything',
            'itemid' => $templateid,
        ]);
        $named->create();
        user_filter_manager::set((int) $named->get('id'), ['validity:status_value' => 4], (int) $reader->id);

        set_user_preference('local_evidencehub_report', json_encode(['suspended' => true]), $reader);
        set_user_preference('local_evidencehub_hubfilter', json_encode(['templateid' => $templateid]), $reader);
        set_user_preference('local_other_report', 'kept', $reader);
        // Lookalikes: the names are matched whole, never by prefix.
        set_user_preference('local_evidencehub_hubfilterx', 'kept', $reader);
        set_user_preference('local_evidencehub_reportx', 'kept', $reader);
        // The precondition: the plugin's rows are there.
        $this->assertSame(2, $DB->count_records(user_filter::TABLE, ['reportid' => $reportid]));
        $this->assertSame(1, $DB->count_records(user_filter::TABLE, ['reportid' => $named->get('id')]));
        manager::reset_caches();

        $this->assertTrue(xmldb_local_evidencehub_uninstall());

        $this->assertFalse($DB->record_exists(report::TABLE, ['id' => $reportid]));
        $this->assertSame(0, $DB->count_records(user_filter::TABLE, ['reportid' => $reportid]));
        $this->assertFalse($DB->record_exists(report::TABLE, ['id' => $named->get('id')]));
        $this->assertSame(0, $DB->count_records(user_filter::TABLE, ['reportid' => $named->get('id')]));
        $this->assertSame(0, $DB->count_records_select(
            'user_preferences',
            'name IN (:report, :old)',
            ['report' => 'local_evidencehub_report', 'old' => 'local_evidencehub_hubfilter']
        ));
        // The controls: the other plugin's report, its stored filters and another preference stay.
        $this->assertTrue($DB->record_exists(report::TABLE, ['id' => $other->get('id')]));
        $this->assertSame(1, $DB->count_records(user_filter::TABLE, ['reportid' => $other->get('id')]));
        $this->assertSame('kept', get_user_preferences('local_other_report', null, $reader));
        $this->assertSame('kept', get_user_preferences('local_evidencehub_hubfilterx', null, $reader));
        $this->assertSame('kept', get_user_preferences('local_evidencehub_reportx', null, $reader));
    }
}
