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
use local_evidencehub\local\hub_filter;
use local_evidencehub\local\hub_matrix;
use local_evidencehub\local\hub_scope;
use local_evidencehub\local\meta;
use local_evidencehub\local\policy;
use local_evidencehub\local\status;
use local_evidencehub\local\status_repository;

/**
 * The hub's readiness matrix: the legend, one page of workers by the template's competencies, the pager.
 *
 * Rendered by the server in full, every cell of the page in place, so nothing reflows after load.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hub_matrix_page implements renderable, templatable {
    /**
     * The matrix of a filter.
     *
     * @param \context $context The page context.
     * @param hub_filter $filter The validated filter.
     * @param hub_scope $scope The viewer's capability cuts.
     * @param array $kpis The filter's KPIs, from hub_kpis::get(): their worker count serves the pager.
     */
    public function __construct(
        /** @var \context The page context. */
        private readonly \context $context,
        /** @var hub_filter The filter. */
        private readonly hub_filter $filter,
        /** @var hub_scope The viewer's capability cuts. */
        private readonly hub_scope $scope,
        /** @var array The filter's KPIs. */
        private readonly array $kpis,
    ) {
    }

    /**
     * The template context.
     *
     * @param renderer_base $output The renderer.
     * @return array The context of templates/hub_matrix.mustache, but contextid and actionurl, which the
     *     hub page's context carries.
     */
    #[\Override]
    public function export_for_template(renderer_base $output): array {
        $filter = $this->filter;
        $matrix = new hub_matrix($this->context, $this->scope);
        $total = $matrix->worker_count($filter, $this->kpis);
        $columns = $this->columns(hub_matrix::columns($filter));
        $userids = $matrix->workers($filter);
        $rows = $this->rows($userids, $matrix->cells($userids, $filter), $columns);
        $pagerurl = new \moodle_url(
            '/local/evidencehub/index.php',
            ['contextid' => $this->context->id] + $filter->with(['page' => 0])->to_params()
        );
        return [
            'total' => get_string('hubmatrixtotal', 'local_evidencehub', $total),
            'legend' => array_map(fn(int $code): array => [
                'label' => status::effective_label($code),
                'badgeclass' => status::effective_badge_class($code),
            ], status::effective_codes()),
            'columncount' => count($columns),
            'columns' => $columns,
            'rows' => $rows,
            'hasrows' => !empty($rows),
            'pagingbar' => $output->render(new \paging_bar($total, $filter->page, $filter->perpage, $pagerurl)),
            'sesskey' => sesskey(),
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
     * hub_download::MAX_PAIRS and PHP's default max_input_vars.
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
     * ({@see \local_evidencehub\local\hub_query::base_sql()}).
     *
     * @param int $code The effective status.
     * @param int $expiry The effective expiry as the base set reads it, YYYYMMDD or 0 for none.
     * @param int $today Today, YYYYMMDD.
     * @return string
     */
    public static function cell_text(int $code, int $expiry, int $today): string {
        return match (true) {
            $code === status::EFFECTIVE_MISSING => get_string('hubmatrixmissing', 'local_evidencehub'),
            $expiry === 0 => '',
            $code === status::STATUS_DUE, $code === status::STATUS_CRITICAL
                => get_string('hubmatrixdaysleft', 'local_evidencehub', meta::days_between($today, $expiry)),
            $code === status::STATUS_EXPIRED
                => get_string('hubmatrixdayssince', 'local_evidencehub', meta::days_between($expiry, $today)),
            default => '',
        };
    }
}
