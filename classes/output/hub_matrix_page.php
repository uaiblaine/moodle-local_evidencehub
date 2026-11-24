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

namespace local_evidencehub\output;

use core\output\renderable;
use core\output\renderer_base;
use core\output\templatable;
use local_evidencehub\local\dossier_builder;
use local_evidencehub\local\hub_filter;
use local_evidencehub\local\hub_matrix;
use local_evidencehub\local\hub_query;
use local_evidencehub\local\meta;
use local_evidencehub\local\policy;
use local_evidencehub\local\reportengine;
use local_evidencehub\local\status;
use local_evidencehub\local\status_repository;
use local_evidencehub\reportbuilder\local\systemreports\hub_pairs;

/**
 * The hub's readiness matrix of a template: its own page (index.php?view=matrix), rendered by the server.
 *
 * The rows are the workers the pairs report lists under the reader's stored filters, the scope and the suspended
 * choice (decision 11, {@see hub_matrix}): the list's criteria, read from the report, never set here. So the page has
 * no filter of its own: the template picker the list's shell has, one line saying which criteria are in force with a
 * link back to the list to change them, the legend, the matrix, its pager and the dossier export of the workers
 * ticked. Every cell is rendered in place with a fixed table layout, so nothing reflows after load.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hub_matrix_page implements renderable, templatable {
    /** @var string The value of index.php's view parameter that renders the matrix. */
    public const string VIEW = 'matrix';

    /** @var int The page from which on a request is read as the last page: a bound, not a limit anyone meets. */
    private const int MAX_PAGE = 100000;

    /** @var int Workers per page, one of reportengine::PERPAGES. */
    private readonly int $perpage;

    /** @var int The page, from 0. */
    private readonly int $page;

    /**
     * The matrix of a template in a hub context.
     *
     * @param \context $context The hub context.
     * @param hub_pairs $report The pairs report of the template, built with the scope as its parameter.
     * @param string $scope The scope in force, from reportengine::SCOPES.
     * @param int $page The requested page, from 0.
     * @param int $perpage The requested page size; one reportengine::PERPAGES does not list reads as its default.
     */
    public function __construct(
        /** @var \context The hub context. */
        private readonly \context $context,
        /** @var hub_pairs The report. */
        private readonly hub_pairs $report,
        /** @var string The scope. */
        private readonly string $scope,
        int $page = 0,
        int $perpage = 0,
    ) {
        $this->page = min(self::MAX_PAGE, max(0, $page));
        $this->perpage = in_array($perpage, reportengine::PERPAGES, true) ? $perpage : reportengine::DEFAULT_PERPAGE;
    }

    /**
     * The matrix of a template: index.php with the context, the template, the view and the scope.
     *
     * @param \context $context The hub context.
     * @param int $templateid The template.
     * @param string $scope The scope; the default, attention, is left out.
     * @return \moodle_url
     */
    public static function url(\context $context, int $templateid, string $scope = reportengine::SCOPE_ATTENTION): \moodle_url {
        $params = ['contextid' => $context->id, 'templateid' => $templateid, 'view' => self::VIEW];
        if ($scope !== reportengine::SCOPE_ATTENTION) {
            $params['scope'] = $scope;
        }
        return new \moodle_url('/local/evidencehub/index.php', $params);
    }

    /**
     * This page's own address, with its page size when it is not the default.
     *
     * @return \moodle_url
     */
    public function page_url(): \moodle_url {
        $url = self::url($this->context, $this->report->template_id(), $this->scope);
        if ($this->perpage !== reportengine::DEFAULT_PERPAGE) {
            $url->param('perpage', $this->perpage);
        }
        return $url;
    }

    /**
     * The template context.
     *
     * @param renderer_base $output The renderer.
     * @return array The context of templates/hub_matrix.mustache.
     */
    #[\Override]
    public function export_for_template(renderer_base $output): array {
        $templateid = $this->report->template_id();
        $matrix = hub_matrix::for_report($this->report);
        $total = $matrix->worker_count();
        $columns = $this->columns(hub_matrix::columns(hub_filter::from_values(['templateid' => $templateid], $this->context)));
        $userids = $matrix->workers($this->page, $this->perpage);
        $rows = $this->rows($userids, $matrix->cells($userids), $columns);
        $criteria = reportengine::criteria($this->report);
        return [
            'title' => get_string('hubmatrixtitle', 'local_evidencehub'),
            'picker' => hub_report_view::picker($output, $this->context, $templateid, ['view' => self::VIEW]),
            'locked' => (int) $this->context->contextlevel === CONTEXT_COURSECAT,
            'contextname' => $this->context->get_context_name(false, false, false),
            'contextid' => (int) $this->context->id,
            'templateid' => $templateid,
            'scope' => $this->scope,
            'scopetext' => $this->scope === reportengine::SCOPE_ALL
                ? get_string('hubmatrixscopeall', 'local_evidencehub')
                : get_string('hubmatrixscopeattention', 'local_evidencehub'),
            'criteria' => array_map(fn(array $criterion): array => [
                'text' => $criterion['value'] === ''
                    ? $criterion['title']
                    : get_string('hubmatrixcriterion', 'local_evidencehub', (object) $criterion),
            ], $criteria),
            'listurl' => hub_report_view::url($this->context, $templateid)->out(false),
            'actionurl' => (new \moodle_url('/local/evidencehub/index.php'))->out(false),
            'total' => get_string('hubmatrixtotal', 'local_evidencehub', $total),
            'legend' => array_map(fn(int $code): array => [
                'label' => status::effective_label($code),
                'badgeclass' => status::effective_badge_class($code),
            ], status::effective_codes()),
            'columncount' => count($columns),
            'columns' => $columns,
            'rows' => $rows,
            'hasrows' => !empty($rows),
            'pagingbar' => $output->render(new \paging_bar($total, $this->page, $this->perpage, $this->page_url())),
            'sesskey' => sesskey(),
            // The dossiers' own checks run again where they are built: this only offers the action.
            'canexport' => dossier_builder::can_export($this->context),
        ];
    }

    /**
     * The column headings: each competency's plain short name and its policy kind's tag.
     *
     * @param array $columns The records of hub_matrix::columns().
     * @return array List of [id, name, kind, kindlabel].
     */
    private function columns(array $columns): array {
        return array_map(fn(\stdClass $column): array => [
            'id' => $column->id,
            'name' => format_string(
                $column->shortname,
                true,
                ['context' => \context::instance_by_id($column->frameworkcontextid), 'escape' => false]
            ),
            'kind' => policy::kind_tag($column->kind),
            'kindlabel' => policy::kind_label($column->kind),
        ], $columns);
    }

    /**
     * One row per worker, in step 1's order, each with a cell per column.
     *
     * The row's checkbox posts the worker's id (workers[]), not its pairs: the dossiers are one per
     * worker, and a page of 100 workers by 25 competencies would post 2500 pairs, past
     * dossier_export::MAX_PAIRS and PHP's default max_input_vars.
     *
     * @param array $userids Step 1's workers.
     * @param array $matrix Step 2's workers and cells.
     * @param array $columns The column headings.
     * @return array List of row contexts.
     */
    private function rows(array $userids, array $matrix, array $columns): array {
        $fullnames = has_capability('moodle/site:viewfullnames', $this->context);
        $identity = \core_user\fields::get_identity_fields($this->context, false);
        $today = status_repository::site_day(\core\di::get(\core\clock::class)->time());
        $rows = [];
        foreach ($userids as $userid) {
            $worker = $matrix['workers'][$userid] ?? null;
            if ($worker === null) {
                // The worker went away between the two statements.
                continue;
            }
            $name = fullname($worker, $fullnames);
            $cells = [];
            foreach ($columns as $column) {
                $cell = $matrix['cells'][$userid . ':' . $column['id']] ?? null;
                if ($cell === null) {
                    // The competency joined the template between the two statements.
                    $cells[] = ['empty' => true];
                    continue;
                }
                $cells[] = $this->cell($cell, $name, $column['name'], $today);
            }
            $rows[] = [
                'userid' => $userid,
                'workername' => $name,
                'workerurl' => (new \moodle_url('/local/evidencehub/evidence.php', ['userid' => $userid]))->out(false),
                'selectlabel' => get_string('hubselectrow', 'local_evidencehub', $name),
                'identity' => array_map(fn(string $field): array => ['value' => (string) ($worker->$field ?? '')], $identity),
                'cells' => $cells,
            ];
        }
        return $rows;
    }

    /**
     * One cell: the effective status's colour and label, a short text, and what the drawer needs.
     *
     * @param \stdClass $cell A cell of hub_matrix::cells().
     * @param string $worker The worker's plain name.
     * @param string $competency The competency's plain short name.
     * @param int $today Today, YYYYMMDD in the site's timezone.
     * @return array The cell's context.
     */
    private function cell(\stdClass $cell, string $worker, string $competency, int $today): array {
        $label = status::effective_label($cell->estatus);
        $names = (object) ['worker' => $worker, 'competency' => $competency];
        return [
            'empty' => false,
            'userid' => $cell->userid,
            'competencyid' => $cell->competencyid,
            'code' => $cell->estatus,
            'statuslabel' => $label,
            'badgeclass' => status::effective_badge_class($cell->estatus),
            'text' => self::cell_text($cell->estatus, $cell->eexpiry, $today),
            'restricted' => $cell->estatus === status::EFFECTIVE_RESTRICTED,
            'celllabel' => get_string('hubmatrixcell', 'local_evidencehub', (object) [
                'worker' => $worker,
                'competency' => $competency,
                'status' => $label,
            ]),
            'drawertitle' => get_string('drawertitle', 'local_evidencehub', $names),
        ];
    }

    /**
     * A cell's short visible text: days left while due, days since once expired, a dash when missing.
     *
     * A restricted pair shows no number: the base set reads its expiry as 0
     * ({@see \local_evidencehub\local\hub_query::base_sql()}), and a pair valid with no end has no day either.
     *
     * @param int $code The effective status.
     * @param int $expiry The "valid until" sort key as the base set reads it ({@see hub_query::expiry_day()}).
     * @param int $today Today, YYYYMMDD.
     * @return string
     */
    public static function cell_text(int $code, int $expiry, int $today): string {
        $day = hub_query::expiry_day($expiry);
        return match (true) {
            $code === status::EFFECTIVE_MISSING => get_string('hubmatrixmissing', 'local_evidencehub'),
            $day === null => '',
            $code === status::STATUS_DUE, $code === status::STATUS_CRITICAL
                => get_string('hubmatrixdaysleft', 'local_evidencehub', meta::days_between($today, $day)),
            $code === status::STATUS_EXPIRED
                => get_string('hubmatrixdayssince', 'local_evidencehub', meta::days_between($day, $today)),
            default => '',
        };
    }
}
