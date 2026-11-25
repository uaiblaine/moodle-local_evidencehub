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

use core_reportbuilder\local\models\report;
use core_reportbuilder\local\report\base;
use core_reportbuilder\local\systemreports\reports_list;
use core_reportbuilder\manager;
use core_reportbuilder\system_report;
use core_reportbuilder\system_report_factory;
use local_evidencehub\reportbuilder\local\systemreports\hub_pairs;
use local_evidencehub\tests\hub_scenario;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Which of core's report persistents are the plugin's.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(reportpersistents::class)]
final class reportpersistents_test extends \advanced_testcase {
    use hub_scenario;

    /**
     * Every system report class under the plugin's tree is one of the sources the cut names.
     *
     * The rule is "every system report of the plugin": a new report class whose source is not listed would leave the
     * persistents core's services build for it, under another component, outside every finder and deletion.
     *
     * @return void
     */
    public function test_every_system_report_of_the_plugin_is_a_listed_source(): void {
        $found = [];
        $classes = \core_component::get_component_classes_in_namespace(
            'local_evidencehub',
            'reportbuilder\\local\\systemreports'
        );
        foreach (array_keys($classes) as $class) {
            $reflection = new \ReflectionClass($class);
            if ($reflection->isSubclassOf(system_report::class) && !$reflection->isAbstract()) {
                $found[] = ltrim($class, '\\');
            }
        }
        $this->assertNotEmpty($found);
        $this->assertEqualsCanonicalizing($found, reportpersistents::SOURCES);
    }

    /**
     * A report persistent of the given shape, written as core's model writes it.
     *
     * @param string $source The stored source.
     * @param int $type A base::TYPE_* constant.
     * @param string $component The stored component.
     * @return int The persistent's id.
     */
    private function persistent(string $source, int $type, string $component): int {
        $report = new report(0, (object) [
            'name' => '',
            'source' => $source,
            'type' => $type,
            'contextid' => \context_system::instance()->id,
            'component' => $component,
            'area' => 'anything',
            'itemid' => 0,
        ]);
        return (int) $report->create()->get('id');
    }

    /**
     * The cut takes the pairs report whatever component it was built under, through core's own factory or with its
     * source spelled as PHP would still resolve it, and nothing else: not another plugin's system report filed under
     * this component, not a custom report.
     *
     * Changes that must make it fail: the cut reading the component (the report core's services build under another
     * component is missed, and another plugin's report under this one is taken); the cut comparing the source's
     * spelling exactly; the cut not asking for a system report.
     *
     * @return void
     */
    public function test_the_cut_is_the_reports_source_whatever_its_component(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('enabled', 1, 'core_competency');
        $this->setAdminUser();
        $templateid = $this->template($this->competencies(1));
        $context = \context_system::instance();

        $own = (int) hub_pairs::for_template($context, $templateid)->get_report_persistent()->get('id');
        // What core's services do with a component the client names: the factory creates the persistent first.
        $named = (int) system_report_factory::create(hub_pairs::class, $context, 'local_other', 'theirs', $templateid)
            ->get_report_persistent()->get('id');
        $spelled = $this->persistent('\\' . strtoupper(hub_pairs::class), base::TYPE_SYSTEM_REPORT, '');
        // The controls: none of these is the plugin's.
        $foreign = $this->persistent(reports_list::class, base::TYPE_SYSTEM_REPORT, 'local_evidencehub');
        $custom = $this->persistent(hub_pairs::class, base::TYPE_CUSTOM_REPORT, 'local_evidencehub');
        manager::reset_caches();
        $this->assertNotSame($own, $named);

        [$isplugins, $params] = reportpersistents::is_plugins('r');
        $ids = $DB->get_fieldset_sql('SELECT r.id FROM {' . report::TABLE . "} r WHERE {$isplugins}", $params);

        $this->assertEqualsCanonicalizing([$own, $named, $spelled], array_map('intval', $ids));
        $this->assertNotContains($foreign, array_map('intval', $ids));
        $this->assertNotContains($custom, array_map('intval', $ids));
    }

    /**
     * Two prefixes give two sets of parameter names, so the condition can stand twice in one statement.
     *
     * @return void
     */
    public function test_the_parameters_carry_the_prefix(): void {
        [, $first] = reportpersistents::is_plugins('r', 'a');
        [, $second] = reportpersistents::is_plugins('r', 'b');
        $this->assertSame([], array_intersect_key($first, $second));
        $this->assertCount(count($first), $second);
    }
}
