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
 * A page of the hub's pairs, read headlessly (local_evidencehub_report_rows).
 *
 * The context and the template come from the client and are both checked ({@see reportengine::locate()});
 * the rows are the system report's own, through the table core builds for it, so its base conditions, the
 * reader's stored filters and the masking hold here as on the page ({@see reportengine::rows()}). A client
 * sets the filters with core_reportbuilder_set_filters and reads the rows again.
 *
 * The one write: the template is remembered as the reader's last in that context (decision 2), a user
 * preference written only when it changes ({@see reportpreference::remember_template()}). The service holds
 * the session's write lock as every service that does not declare a read-only session does.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class report_rows extends external_api {
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
            'page' => new external_value(PARAM_INT, 'The page, from 0', VALUE_DEFAULT, 0),
            'perpage' => new external_value(
                PARAM_INT,
                'Rows per page: 30, 50 or 100',
                VALUE_DEFAULT,
                reportengine::DEFAULT_PERPAGE
            ),
            'sort' => new external_value(PARAM_RAW, 'The column key to sort by, empty for the report\'s sort', VALUE_DEFAULT, ''),
            'direction' => new external_value(PARAM_ALPHA, 'asc or desc', VALUE_DEFAULT, 'asc'),
            'facets' => new external_value(PARAM_BOOL, 'Whether to count the filter panel\'s options', VALUE_DEFAULT, true),
        ]);
    }

    /**
     * Read a page of pairs.
     *
     * @param int $contextid The hub context.
     * @param int $templateid The template.
     * @param string $scope Which pairs.
     * @param string $search Part of a name.
     * @param int $page The page, from 0.
     * @param int $perpage Rows per page.
     * @param string $sort The column key to sort by.
     * @param string $direction asc or desc.
     * @param bool $facets Whether to count the filter panel's options.
     * @return array See execute_returns().
     * @throws \core_reportbuilder\exception\report_access_exception When the reader may not open these pairs.
     * @throws \moodle_exception For a guest.
     * @throws \required_capability_exception Without local/evidencehub:view in the context.
     */
    public static function execute(
        int $contextid,
        int $templateid,
        string $scope = reportengine::SCOPE_ATTENTION,
        string $search = '',
        int $page = 0,
        int $perpage = reportengine::DEFAULT_PERPAGE,
        string $sort = '',
        string $direction = 'asc',
        bool $facets = true
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'contextid' => $contextid,
            'templateid' => $templateid,
            'scope' => $scope,
            'search' => $search,
            'page' => $page,
            'perpage' => $perpage,
            'sort' => $sort,
            'direction' => $direction,
            'facets' => $facets,
        ]);
        [$context] = reportengine::locate($params['contextid'], $params['templateid']);
        self::validate_context($context);
        if (isguestuser()) {
            throw new \moodle_exception('noguest');
        }
        require_capability('local/evidencehub:view', $context);

        $rows = reportengine::rows(reportengine::report($context, $params['templateid']), $params);
        reportpreference::remember_template((int) $context->id, $params['templateid']);
        return $rows;
    }

    /**
     * What comes back: an allowlist.
     *
     * Titles, labels and names are plain, which the client renders as text; cells are the report's formatted
     * HTML, escaped by its formatters.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'scope' => new external_value(PARAM_ALPHA, 'Which pairs'),
            'search' => new external_value(PARAM_RAW, 'The search applied, empty for none'),
            'sort' => new external_value(PARAM_RAW, 'The column key sorted by, empty for the report\'s own sort'),
            'direction' => new external_value(PARAM_ALPHA, 'asc, desc, or empty'),
            'page' => new external_value(PARAM_INT, 'The page returned, from 0'),
            'perpage' => new external_value(PARAM_INT, 'Rows per page'),
            'total' => new external_value(PARAM_INT, 'Pairs across every page'),
            'hidden' => new external_value(PARAM_INT, 'The template\'s workers the reader may not view'),
            'candecide' => new external_value(PARAM_BOOL, 'Whether the reader may export the dossiers of selected rows'),
            'columns' => new external_multiple_structure(new external_single_structure([
                'key' => new external_value(PARAM_RAW, 'Column key'),
                'title' => new external_value(PARAM_RAW, 'Title, plain'),
                'sortable' => new external_value(PARAM_BOOL, 'Whether it sorts'),
                'default' => new external_value(PARAM_BOOL, 'Whether the default set has it'),
                'active' => new external_value(PARAM_BOOL, 'Whether the report shows it'),
                'fixed' => new external_value(PARAM_BOOL, 'Whether it is always shown'),
            ])),
            'presets' => new external_multiple_structure(new external_single_structure([
                'name' => new external_value(PARAM_ALPHA, 'The set\'s name'),
                'columns' => new external_multiple_structure(
                    new external_value(PARAM_RAW, 'Column key, as the preference stores it')
                ),
                'active' => new external_value(PARAM_BOOL, 'Whether the report shows this set'),
            ])),
            'rows' => new external_multiple_structure(new external_single_structure([
                'userid' => new external_value(PARAM_INT, 'The worker'),
                'competencyid' => new external_value(PARAM_INT, 'The competency'),
                'status' => new external_value(PARAM_INT, 'The effective status code, -1 restricted, 0 missing'),
                'restricted' => new external_value(PARAM_BOOL, 'Whether the deciding evidence is restricted to the reader'),
                'evidenceurl' => new external_value(PARAM_URL, 'The worker\'s evidence page'),
                'inspect' => new external_value(PARAM_BOOL, 'Whether the reader may open the pair\'s drawer'),
                'selectable' => new external_value(PARAM_BOOL, 'Whether the reader may select the row for a bulk action'),
                'drawertitle' => new external_value(PARAM_RAW, 'The drawer\'s title, plain'),
                'inspectlabel' => new external_value(PARAM_RAW, 'The drawer action\'s accessible name, plain'),
                'cells' => new external_multiple_structure(new external_single_structure([
                    'key' => new external_value(PARAM_RAW, 'Column key'),
                    'html' => new external_value(PARAM_RAW, 'Formatted cell'),
                ])),
            ])),
            'counted' => new external_value(PARAM_BOOL, 'Whether the filters\' options carry their counts'),
            'filters' => new external_multiple_structure(new external_single_structure([
                'name' => new external_value(PARAM_RAW, 'Filter unique identifier'),
                'title' => new external_value(PARAM_RAW, 'Title, plain'),
                'type' => new external_value(PARAM_ALPHANUMEXT, 'Filter class name'),
                'facet' => new external_value(PARAM_ALPHA, 'The panel\'s facet: choice, or empty for none'),
                'options' => new external_multiple_structure(new external_single_structure([
                    'value' => new external_value(PARAM_RAW, 'Value'),
                    'label' => new external_value(PARAM_RAW, 'Label, plain'),
                    'count' => new external_value(
                        PARAM_INT,
                        'Pairs with this option under every other criterion, -1 when not counted'
                    ),
                ])),
                'applied' => new external_value(PARAM_BOOL, 'Whether the reader\'s stored values narrow the rows'),
                'values' => new external_value(PARAM_RAW, 'The reader\'s stored values for it, JSON'),
            ])),
            'preference' => preference_structure::get(),
        ]);
    }
}
