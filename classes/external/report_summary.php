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

/**
 * The band of the hub's pairs report (local_evidencehub_report_summary).
 *
 * The figures are read over the report's own rows, under every criterion but the scope and the status filter
 * ({@see reportengine::summary()}), behind the same door as the rows.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class report_summary extends external_api {
    /**
     * The parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'contextid' => new external_value(PARAM_INT, 'The hub context: the system or a course category'),
            'templateid' => new external_value(PARAM_INT, 'The learning plan template'),
            'scope' => new external_value(
                PARAM_ALPHA,
                'Which pairs: attention or all',
                VALUE_DEFAULT,
                reportengine::SCOPE_ATTENTION
            ),
            'search' => new external_value(PARAM_TEXT, 'Part of the worker\'s name, two characters or more', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Compute the summary.
     *
     * @param int $contextid The hub context.
     * @param int $templateid The template.
     * @param string $scope Which pairs; echoed, the figures do not depend on it.
     * @param string $search Part of a name.
     * @return array See execute_returns().
     * @throws \core_reportbuilder\exception\report_access_exception When the reader may not open these pairs.
     * @throws \moodle_exception For a guest.
     * @throws \required_capability_exception Without local/evidencehub:view in the context.
     */
    public static function execute(
        int $contextid,
        int $templateid,
        string $scope = reportengine::SCOPE_ATTENTION,
        string $search = ''
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'contextid' => $contextid,
            'templateid' => $templateid,
            'scope' => $scope,
            'search' => $search,
        ]);
        [$context] = reportengine::locate($params['contextid'], $params['templateid']);
        self::validate_context($context);
        if (isguestuser()) {
            throw new \moodle_exception('noguest');
        }
        require_capability('local/evidencehub:view', $context);

        return reportengine::summary(reportengine::report($context, $params['templateid']), $params);
    }

    /**
     * What comes back: an allowlist. Names and labels are plain.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'scope' => new external_value(PARAM_ALPHA, 'Which pairs, as the request named them'),
            'search' => new external_value(PARAM_RAW, 'The search applied, empty for none'),
            'workers' => new external_value(PARAM_INT, 'Workers with a listed pair'),
            'ready' => new external_value(PARAM_INT, 'Workers whose every pair is compliant, due or critical'),
            'rate' => new external_value(PARAM_FLOAT, 'ready / workers, null without workers', VALUE_OPTIONAL, null, NULL_ALLOWED),
            'pairs' => new external_value(PARAM_INT, 'Pairs'),
            'attention' => new external_value(PARAM_INT, 'Pairs needing attention: every status but compliant'),
            'expiring' => new external_value(PARAM_INT, 'Pairs valid until a day from today to 30 days ahead'),
            'distribution' => new external_multiple_structure(new external_single_structure([
                'status' => new external_value(PARAM_INT, 'Effective status code'),
                'label' => new external_value(PARAM_RAW, 'Its label, plain'),
                'count' => new external_value(PARAM_INT, 'Pairs'),
            ])),
            'worst' => new external_multiple_structure(new external_single_structure([
                'competencyid' => new external_value(PARAM_INT, 'Competency'),
                'name' => new external_value(PARAM_RAW, 'Its name, plain'),
                'count' => new external_value(PARAM_INT, 'Pairs needing attention'),
            ])),
            'hidden' => new external_value(PARAM_INT, 'The template\'s workers the reader may not view'),
        ]);
    }
}
