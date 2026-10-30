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
use local_evidencehub\local\hub_access;
use local_evidencehub\local\hub_download;
use local_evidencehub\local\hub_filter;
use local_evidencehub\local\hub_kpis;
use local_evidencehub\local\hub_query;
use local_evidencehub\local\hub_scope;
use local_evidencehub\local\status;

/**
 * The evidence hub of a context: the filters, the KPI bar, the tabs and one page of the list or the matrix.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hub_page implements renderable, templatable {
    /**
     * The hub of a context.
     *
     * @param \context $context The page context: the system or a course category.
     * @param hub_filter|null $filter The validated filter, or null when no template is chosen.
     * @param hub_scope $scope The viewer's capability cuts.
     */
    public function __construct(
        /** @var \context The page context. */
        private readonly \context $context,
        /** @var hub_filter|null The filter. */
        private readonly ?hub_filter $filter,
        /** @var hub_scope The viewer's capability cuts. */
        private readonly hub_scope $scope,
    ) {
    }

    /**
     * The hub's URL for a filter.
     *
     * @param hub_filter|null $filter The filter, or null for the bare page.
     * @return \moodle_url
     */
    public function url(?hub_filter $filter): \moodle_url {
        $params = ['contextid' => $this->context->id];
        return new \moodle_url('/local/evidencehub/index.php', $filter ? $params + $filter->to_params() : $params);
    }

    /**
     * The template context.
     *
     * @param renderer_base $output The renderer.
     * @return array The context of templates/hub_page.mustache.
     */
    #[\Override]
    public function export_for_template(renderer_base $output): array {
        $data = [
            'contextid' => $this->context->id,
            'actionurl' => (new \moodle_url('/local/evidencehub/index.php'))->out(false),
            'locked' => (int) $this->context->contextlevel === CONTEXT_COURSECAT,
            'contextname' => $this->context->get_context_name(false, false, false),
            'templates' => $this->templates(),
            'cohorts' => $this->cohorts(),
            'search' => $this->filter?->search ?? '',
            'suspended' => (bool) $this->filter?->suspended,
            'perpages' => array_map(fn(int $value): array => [
                'value' => $value,
                'selected' => $value === ($this->filter?->perpage ?? hub_filter::DEFAULT_PERPAGE),
            ], hub_filter::PERPAGES),
            'hasfilter' => $this->filter !== null,
        ];
        $data['hastemplates'] = !empty($data['templates']);
        $data['hascohorts'] = !empty($data['cohorts']);
        if ($this->filter === null) {
            return $data;
        }

        $filter = $this->filter;
        $kpis = hub_kpis::get($filter, $this->scope, $this->context);
        $data += [
            'templateid' => $filter->templateid,
            'hidden' => $this->hidden_fields($filter),
            'tabs' => $this->tabs($filter),
            'chips' => $this->chips($filter),
            'tiles' => $this->tiles($filter, $kpis),
            'readiness' => get_string('hubreadiness', 'local_evidencehub', (object) [
                'ready' => $kpis['ready'],
                'workers' => $kpis['workers'],
                'rate' => $kpis['workers'] ? round(100 * $kpis['ready'] / $kpis['workers']) : 0,
            ]),
            'hiddenworkers' => $kpis['hidden'] ? get_string('hubhiddenworkers', 'local_evidencehub', $kpis['hidden']) : '',
            'noworkers' => $kpis['workers'] === 0,
        ];
        if ($filter->view === hub_filter::VIEW_MATRIX) {
            $data['matrix'] = (new hub_matrix_page($this->context, $filter, $this->scope, $kpis))->export_for_template($output);
            return $data;
        }

        $query = new hub_query($this->context, $this->scope);
        $rows = $query->page($filter);
        $total = hub_kpis::pager_count($kpis, $filter);
        $identity = $query->identity_fields();
        $data += [
            'islist' => true,
            'downloadfields' => array_merge($this->hidden_fields($filter), [
                ['name' => 'templateid', 'value' => $filter->templateid],
                ['name' => 'cohortid', 'value' => $filter->cohortid],
                ['name' => 'search', 'value' => $filter->search],
                ['name' => 'suspended', 'value' => (int) $filter->suspended],
                ['name' => 'perpage', 'value' => $filter->perpage],
            ]),
            'total' => $total,
            'columns' => $this->columns($filter, $identity),
            'rows' => $this->rows($rows, new hub_download($this->context, $query), $identity),
            'hasrows' => !empty($rows),
            'pagingbar' => $output->render(
                new \paging_bar($total, $filter->page, $filter->perpage, $this->url($filter->with(['page' => 0])))
            ),
            'formats' => $this->formats(),
            'sesskey' => sesskey(),
        ];
        return $data;
    }

    /**
     * The templates on offer, the filter's one selected.
     *
     * @return array List of [id, name, contextname, selected].
     */
    private function templates(): array {
        $templates = [];
        foreach (hub_access::templates($this->context) as $template) {
            $context = \context::instance_by_id($template->contextid);
            $templates[] = [
                'id' => $template->id,
                // Plain spellings throughout: the template's double stashes escape them once.
                'name' => format_string($template->shortname, true, ['context' => $context, 'escape' => false]),
                'contextname' => $context->get_context_name(false, false, false),
                'selected' => $template->id === $this->filter?->templateid,
            ];
        }
        return $templates;
    }

    /**
     * The cohorts on offer, the filter's one selected.
     *
     * @return array List of [id, name, selected].
     */
    private function cohorts(): array {
        $cohorts = [];
        foreach (hub_access::cohorts($this->context) as $cohort) {
            $context = \context::instance_by_id((int) $cohort->contextid);
            $cohorts[] = [
                'id' => $cohort->id,
                'name' => format_string($cohort->name, true, ['context' => $context, 'escape' => false]),
                'selected' => $cohort->id === $this->filter?->cohortid,
            ];
        }
        return $cohorts;
    }

    /**
     * The filter's values the forms carry as hidden fields: the statuses, the sort, its direction and the view.
     *
     * @param hub_filter $filter The filter.
     * @return array List of [name, value].
     */
    private function hidden_fields(hub_filter $filter): array {
        $fields = [['name' => 'statusfilter', 'value' => 1]];
        foreach ($filter->statuses as $code) {
            $fields[] = ['name' => 'statuses[]', 'value' => $code];
        }
        $fields[] = ['name' => 'sort', 'value' => $filter->sort];
        $fields[] = ['name' => 'dir', 'value' => $filter->dir];
        $fields[] = ['name' => 'view', 'value' => $filter->view];
        return $fields;
    }

    /**
     * The tabs between the list and the matrix: the same filter, on its first page.
     *
     * @param hub_filter $filter The filter.
     * @return array List of [label, url, active].
     */
    private function tabs(hub_filter $filter): array {
        $tabs = [];
        foreach (hub_filter::VIEW_NAMES as $view) {
            $tabs[] = [
                'label' => match ($view) {
                    hub_filter::VIEW_LIST => get_string('hublist', 'local_evidencehub'),
                    hub_filter::VIEW_MATRIX => get_string('hubmatrix', 'local_evidencehub'),
                },
                'url' => $this->url($filter->with(['view' => $view, 'page' => 0]))->out(false),
                'active' => $filter->view === $view,
            ];
        }
        return $tabs;
    }

    /**
     * The filter with one status toggled, back on the first page.
     *
     * @param hub_filter $filter The filter.
     * @param int $code The effective status.
     * @return hub_filter
     */
    private function toggled(hub_filter $filter, int $code): hub_filter {
        $statuses = $filter->statuses;
        if (in_array($code, $statuses, true)) {
            $statuses = array_values(array_diff($statuses, [$code]));
        } else {
            $statuses = array_values(array_intersect(status::effective_codes(), array_merge($statuses, [$code])));
        }
        return $filter->with(['statuses' => $statuses, 'page' => 0]);
    }

    /**
     * The status chips: "Needs attention", "All", then one per effective status.
     *
     * @param hub_filter $filter The filter.
     * @return array List of [label, url, active].
     */
    private function chips(hub_filter $filter): array {
        $attention = hub_filter::needs_attention();
        $chips = [
            [
                'label' => get_string('hubneedsattention', 'local_evidencehub'),
                'url' => $this->url($filter->with(['statuses' => $attention, 'page' => 0]))->out(false),
                'active' => $filter->statuses === $attention,
            ],
            [
                'label' => get_string('huball', 'local_evidencehub'),
                'url' => $this->url($filter->with(['statuses' => [], 'page' => 0]))->out(false),
                'active' => $filter->statuses === [],
            ],
        ];
        foreach (status::effective_codes() as $code) {
            $chips[] = [
                'label' => status::effective_label($code),
                'url' => $this->url($this->toggled($filter, $code))->out(false),
                'active' => in_array($code, $filter->statuses, true),
            ];
        }
        return $chips;
    }

    /**
     * The KPI tiles, one per effective status; "Restricted" only when some pair is.
     *
     * @param hub_filter $filter The filter.
     * @param array $kpis The filter's KPIs.
     * @return array List of [code, label, count, share, badgeclass, url, active].
     */
    private function tiles(hub_filter $filter, array $kpis): array {
        $tiles = [];
        foreach (status::effective_codes() as $code) {
            $count = $kpis['counts'][$code] ?? 0;
            if ($code === status::EFFECTIVE_RESTRICTED && $count === 0) {
                continue;
            }
            $tiles[] = [
                'code' => $code,
                'label' => status::effective_label($code),
                'count' => $count,
                'share' => get_string('hubshare', 'local_evidencehub', $kpis['pairs'] ? round(100 * $count / $kpis['pairs']) : 0),
                'badgeclass' => status::effective_badge_class($code),
                'url' => $this->url($this->toggled($filter, $code))->out(false),
                'active' => in_array($code, $filter->statuses, true),
            ];
        }
        return $tiles;
    }

    /**
     * The grid's column headings, the sortable ones linking to their sort.
     *
     * @param hub_filter $filter The filter.
     * @param array $identity The identity fields shown.
     * @return array List of [label, sortable, url, sorted, asc].
     */
    private function columns(hub_filter $filter, array $identity): array {
        $column = function (string $label, ?string $sort) use ($filter): array {
            $sorted = $sort !== null && $filter->sort === $sort;
            $dir = $sorted && $filter->dir === 'asc' ? 'desc' : 'asc';
            return [
                'label' => $label,
                'sortable' => $sort !== null,
                'url' => $sort === null ? '' : $this->url($filter->with(['sort' => $sort, 'dir' => $dir, 'page' => 0]))->out(false),
                'sorted' => $sorted,
                'asc' => $sorted && $filter->dir === 'asc',
            ];
        };
        $columns = [$column(get_string('worker', 'local_evidencehub'), hub_filter::SORT_WORKER)];
        foreach ($identity as $field) {
            $columns[] = $column(\core_user\fields::get_display_name($field), null);
        }
        return array_merge($columns, [
            $column(get_string('competency', 'local_evidencehub'), hub_filter::SORT_COMPETENCY),
            $column(get_string('status', 'local_evidencehub'), hub_filter::SORT_STATUS),
            $column(get_string('effectiveexpiry', 'local_evidencehub'), hub_filter::SORT_EXPIRY),
            $column(get_string('evidencecount', 'local_evidencehub'), null),
            $column(get_string('type', 'local_evidencehub'), null),
            $column(get_string('issuer', 'local_evidencehub'), null),
        ]);
    }

    /**
     * The grid's rows: the download's cells ({@see hub_download::row()}, so a restricted pair shows its
     * status only in both), with the pair's key, the link to the worker's evidence, the pill and what
     * the inspection drawer's button needs.
     *
     * @param array $records The page of hub_query::page().
     * @param hub_download $download The download of the same grid.
     * @param array $identity The identity fields shown.
     * @return array List of row contexts.
     */
    private function rows(array $records, hub_download $download, array $identity): array {
        $rows = [];
        foreach ($records as $record) {
            $cells = $download->row($record);
            $names = (object) ['worker' => $cells['worker'], 'competency' => $cells['competency']];
            $rows[] = [
                'pair' => $record->userid . ':' . $record->competencyid,
                'userid' => (int) $record->userid,
                'competencyid' => (int) $record->competencyid,
                'inspectlabel' => get_string('actioninspectlabel', 'local_evidencehub', $names),
                'drawertitle' => get_string('drawertitle', 'local_evidencehub', $names),
                'workername' => $cells['worker'],
                'workerurl' => (new \moodle_url('/local/evidencehub/evidence.php', ['userid' => $record->userid]))->out(false),
                'identity' => array_map(fn(string $field): array => ['value' => $cells[$field]], $identity),
                'competency' => $cells['competency'],
                'statuslabel' => $cells['status'],
                'badgeclass' => status::effective_badge_class($record->estatus),
                'expiry' => $cells['expiry'],
                'evidencecount' => $cells['evidencecount'],
                'type' => $cells['type'],
                'issuer' => $cells['issuer'],
            ];
        }
        return $rows;
    }

    /**
     * The enabled download formats.
     *
     * @return array List of [name, label].
     */
    private function formats(): array {
        $formats = [];
        foreach (\core\plugininfo\dataformat::get_enabled_plugins() as $name) {
            $formats[] = ['name' => $name, 'label' => get_string('dataformat', 'dataformat_' . $name)];
        }
        return $formats;
    }
}
