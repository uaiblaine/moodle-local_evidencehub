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

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_evidencehub\local\reportengine;
use local_evidencehub\local\reportpreference;

/**
 * Store the reader's grid on the hub's pairs report (local_evidencehub_set_report_preference).
 *
 * The columns chosen, the widths dragged and whether suspended accounts are listed, in one user preference for
 * the whole site ({@see reportpreference}). The report reads it in initialise() and intersects it with its own
 * catalogue, so a stored key can never add a column the reader may not see. Taken behind the report's own
 * door, checked without building the report ({@see reportengine::locate()}, which reads
 * {@see \local_evidencehub\reportbuilder\local\systemreports\hub_pairs::may_view()}): core keeps one
 * instance of a report per request, so a report built here would keep the old columns for a rows call
 * batched after this one. The context and the template only judge that standing.
 *
 * No event: a reader's display preference, as core's own table preferences, which log nothing.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class set_report_preference extends external_api {
    /** @var int The suspended argument that leaves the stored flag as it is. */
    public const int KEEP = -1;

    /**
     * The parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'contextid' => new external_value(PARAM_INT, 'The hub context the reader is on'),
            'templateid' => new external_value(PARAM_INT, 'The learning plan template the reader is on'),
            'usedefault' => new external_value(
                PARAM_BOOL,
                'Show the default columns and widths, forgetting the choice',
                VALUE_DEFAULT,
                false
            ),
            'columns' => new external_multiple_structure(
                new external_value(PARAM_RAW, 'Column key'),
                'The chosen columns',
                VALUE_DEFAULT,
                []
            ),
            'widths' => new external_multiple_structure(
                new external_single_structure([
                    'key' => new external_value(PARAM_RAW, 'Column key'),
                    'width' => new external_value(PARAM_INT, 'Width in pixels'),
                ]),
                'Every width the reader set; empty for the default widths',
                VALUE_DEFAULT,
                []
            ),
            'suspended' => new external_value(
                PARAM_INT,
                'List suspended accounts: 1 yes, 0 no, -1 leave as stored',
                VALUE_DEFAULT,
                self::KEEP
            ),
        ]);
    }

    /**
     * Store the grid.
     *
     * @param int $contextid The hub context.
     * @param int $templateid The template.
     * @param bool $usedefault Whether to forget the column choice and the widths.
     * @param array $columns The chosen keys.
     * @param array $widths Each: key, width.
     * @param int $suspended 1, 0, or KEEP.
     * @return array What was stored; see execute_returns().
     * @throws \core_reportbuilder\exception\report_access_exception When the reader may not open these pairs.
     * @throws \moodle_exception For a guest.
     * @throws \required_capability_exception Without local/evidencehub:view in the context.
     */
    public static function execute(
        int $contextid,
        int $templateid,
        bool $usedefault = false,
        array $columns = [],
        array $widths = [],
        int $suspended = self::KEEP
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'contextid' => $contextid,
            'templateid' => $templateid,
            'usedefault' => $usedefault,
            'columns' => $columns,
            'widths' => $widths,
            'suspended' => $suspended,
        ]);
        [$context] = reportengine::locate($params['contextid'], $params['templateid']);
        self::validate_context($context);
        if (isguestuser()) {
            throw new \moodle_exception('noguest');
        }
        require_capability('local/evidencehub:view', $context);

        $sizes = [];
        foreach ($params['widths'] as $width) {
            $sizes[(string) $width['key']] = $width['width'];
        }
        $flag = match ($params['suspended']) {
            1 => true,
            0 => false,
            default => null,
        };
        $stored = $params['usedefault']
            ? reportpreference::write_grid(null, [], $flag)
            : reportpreference::write_grid($params['columns'], $sizes, $flag);
        return reportpreference::export($stored);
    }

    /**
     * What comes back: the grid as stored, cleaned.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return preference_structure::get();
    }
}
