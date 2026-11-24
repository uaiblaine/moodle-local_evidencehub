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
 * What the plugin leaves in core's tables that core's uninstall does not remove.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Remove the plugin's rows in core's tables that core's uninstall_plugin() (lib/adminlib.php) leaves behind.
 *
 * Core drops the plugin's own tables, its configuration, capabilities, tasks, events, files and message providers,
 * but not the report builder persistents a system report of the plugin created (core keeps one per hub context and
 * template, component local_evidencehub, with every reader's stored filter values, which can hold a cohort, a
 * competency or a typed name), nor user preferences. Each report is deleted through core's persistent, whose
 * before_delete() removes its stored filters, columns, filters, audiences and schedules; then the plugin's own user
 * preferences by their exact names, the old list's stored filter among them in case its upgrade step never ran.
 *
 * @return bool
 */
function xmldb_local_evidencehub_uninstall() {
    global $DB;
    foreach (\core_reportbuilder\local\models\report::get_records(['component' => 'local_evidencehub']) as $report) {
        $report->delete();
    }
    [$insql, $params] = $DB->get_in_or_equal(['local_evidencehub_report', 'local_evidencehub_hubfilter']);
    $DB->delete_records_select('user_preferences', "name $insql", $params);
    return true;
}
