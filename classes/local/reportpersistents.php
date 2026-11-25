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
use core_reportbuilder\local\models\user_filter;
use core_reportbuilder\local\report\base;
use local_evidencehub\reportbuilder\local\systemreports\hub_pairs;

/**
 * Which of core's report persistents are this plugin's, and their removal.
 *
 * Core's services build a system report from the source, context, component, area and item id the client sends, and
 * create its persistent before they ask can_view() (core_reportbuilder\system_report_factory::create()). So a
 * persistent is the plugin's when it is a system report whose source is one of the plugin's system report classes,
 * whatever its component: the class core instantiates is the recorded fact, the component a label the caller chose
 * either way (a client can as well build another plugin's report under this component). The source is compared
 * without case and leading separator, as PHP resolves a class name. A custom report built on the plugin's datasource
 * is its author's, covered by core's provider, and never touched here.
 *
 * A persistent is deleted through core's model, whose before_delete() removes the stored filter values, columns,
 * conditions, audiences and schedules hanging on it.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class reportpersistents {
    /** @var string[] The plugin's system report classes; reportpersistents_test holds the list against the tree. */
    public const array SOURCES = [hub_pairs::class];

    /**
     * The condition that a report persistent is the plugin's.
     *
     * @param string $alias The alias of {reportbuilder_report} in the statement.
     * @param string $prefix The prefix of the parameters' names, unique in the statement.
     * @return array [the SQL condition, its named parameters].
     */
    public static function is_plugins(string $alias, string $prefix = 'rp'): array {
        global $DB;
        $sources = [];
        foreach (self::SOURCES as $source) {
            $sources[] = strtolower($source);
            $sources[] = '\\' . strtolower($source);
        }
        [$insql, $params] = $DB->get_in_or_equal($sources, SQL_PARAMS_NAMED, $prefix . 'src');
        $params[$prefix . 'type'] = base::TYPE_SYSTEM_REPORT;
        return ["({$alias}.type = :{$prefix}type AND LOWER({$alias}.source) {$insql})", $params];
    }

    /**
     * Delete one user's stored filter values of the plugin's reports, in core's table.
     *
     * The one cut shared by the privacy requests and the deletion of the account, after which the user context the
     * privacy finder reads is gone.
     *
     * @param int $userid The reader.
     * @return void
     */
    public static function delete_user_filters(int $userid): void {
        global $DB;
        [$isplugins, $params] = self::is_plugins('r');
        $DB->delete_records_select(
            user_filter::TABLE,
            'usercreated = :userid AND reportid IN (SELECT r.id FROM {' . report::TABLE . "} r WHERE {$isplugins})",
            $params + ['userid' => $userid]
        );
    }

    /**
     * Delete every report of the plugin, with what hangs on each: the plugin's uninstall.
     *
     * @return void
     */
    public static function delete_all(): void {
        global $DB;
        [$isplugins, $params] = self::is_plugins('r');
        $ids = $DB->get_fieldset_sql('SELECT r.id FROM {' . report::TABLE . "} r WHERE {$isplugins}", $params);
        foreach ($ids as $id) {
            (new report((int) $id))->delete();
        }
    }
}
