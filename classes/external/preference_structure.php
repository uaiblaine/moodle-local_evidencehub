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

namespace local_evidencehub\external;

use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * The shape of the reader's grid on the hub's pairs report, shared by the rows and by the preference's writer;
 * {@see \local_evidencehub\local\reportpreference::export()} fills it.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class preference_structure {
    /**
     * The structure.
     *
     * @return external_single_structure Whether the default columns show, the chosen keys, the widths and
     *     whether suspended accounts are listed.
     */
    public static function get(): external_single_structure {
        return new external_single_structure([
            'usedefault' => new external_value(PARAM_BOOL, 'Whether the default columns show'),
            'columns' => new external_multiple_structure(new external_value(PARAM_RAW, 'Column key')),
            'widths' => new external_multiple_structure(new external_single_structure([
                'key' => new external_value(PARAM_RAW, 'Column key'),
                'width' => new external_value(PARAM_INT, 'Width in pixels'),
            ])),
            'suspended' => new external_value(PARAM_BOOL, 'Whether suspended accounts are listed'),
        ]);
    }
}
