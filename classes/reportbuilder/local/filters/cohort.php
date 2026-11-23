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

namespace local_evidencehub\reportbuilder\local\filters;

use core_reportbuilder\local\helpers\database;

/**
 * One cohort the worker belongs to, or does not.
 *
 * The filter's field is the worker's user id; a cohort is matched by membership, as the hub's own cohort
 * filter matches it ({@see \local_evidencehub\local\hub_query::base_sql()}). A value outside the options
 * filters nothing, so a forged cohort id can only show the rows the reader already reads.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cohort extends reader_select {
    /**
     * The membership condition of the chosen cohort.
     *
     * @param array $values The filter's values: its operator and value.
     * @return array [sql, params], or ['', []] when the filter filters nothing.
     */
    #[\Override]
    public function get_sql_filter(array $values): array {
        $operator = (int) ($values["{$this->name}_operator"] ?? self::ANY_VALUE);
        $value = (string) ($values["{$this->name}_value"] ?? '');
        if (
            !in_array($operator, [self::EQUAL_TO, self::NOT_EQUAL_TO], true)
            || !array_key_exists($value, $this->get_select_options())
        ) {
            return ['', []];
        }
        $param = database::generate_param_name();
        $members = database::generate_alias();
        $exists = "EXISTS (SELECT 1 FROM {cohort_members} {$members}
                            WHERE {$members}.userid = {$this->filter->get_field_sql()} AND {$members}.cohortid = :{$param})";
        $params = $this->filter->get_field_params() + [$param => (int) $value];
        return [$operator === self::EQUAL_TO ? $exists : "NOT {$exists}", $params];
    }
}
