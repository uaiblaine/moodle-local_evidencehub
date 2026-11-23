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

namespace local_evidencehub\reportbuilder\local\systemreports;

use core\lang_string;
use core_competency\api;
use core_competency\plan;
use core_competency\reportbuilder\local\entities\competency;
use core_reportbuilder\local\entities\user;
use core_reportbuilder\local\helpers\database;
use core_reportbuilder\local\report\column;
use core_reportbuilder\local\report\filter;
use core_reportbuilder\system_report;
use core_reportbuilder\system_report_factory;
use core_user\fields;
use local_evidencehub\local\details;
use local_evidencehub\local\hub_access;
use local_evidencehub\local\hub_query;
use local_evidencehub\local\hub_scope;
use local_evidencehub\local\pairset;
use local_evidencehub\local\status_repository;
use local_evidencehub\reportbuilder\local\entities\evidence_details;
use local_evidencehub\reportbuilder\local\entities\validity;
use local_evidencehub\reportbuilder\local\filters\cohort;

/**
 * The hub's pairs of one plan template in one context, as core's system report.
 *
 * The rows are the hub's pair set ({@see pairset}) for the reader: one row per (worker, competency) of an
 * active plan from the template, the workers the reader may view, suspended accounts left out, a missing
 * pair listed as "missing" and a pair decided by a sensitive evidence the reader may not see as
 * "restricted". Every value a restricted pair hides is masked in SQL, so no column, filter or sort
 * reveals it ({@see validity}, {@see evidence_details}).
 *
 * The report belongs to the hub's context (the system or a course category) and is identified by the
 * template, its item id: core keeps one set of stored filters per reader, context and template. The rows
 * are cut by the context's own rule and the template, both checked by {@see self::may_view()} on every
 * request, never by a parameter a client sends.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hub_pairs extends system_report {
    /** @var string The value of index.php's view parameter that renders this report. */
    public const string VIEW = 'table';

    /** @var string The report area, beside the component, in core's report persistent. */
    public const string AREA = 'hub';

    /**
     * The report of a template's pairs in a hub context, for the current user.
     *
     * @param \context $context The hub's context: the system or a course category.
     * @param int $templateid The plan template, the report's item.
     * @param array $parameters Report parameters; a non-empty 'download' makes the report refuse.
     * @return self
     * @throws \core_reportbuilder\exception\report_access_exception When {@see self::may_view()} refuses.
     */
    public static function for_template(\context $context, int $templateid, array $parameters = []): self {
        return system_report_factory::create(self::class, $context, 'local_evidencehub', self::AREA, $templateid, $parameters);
    }

    /**
     * The report's name.
     *
     * @return string
     */
    #[\Override]
    public static function get_name(): string {
        return get_string('report:hub_pairs', 'local_evidencehub');
    }

    /**
     * Whether the current user may view this report: {@see self::may_view()} on its context and template.
     *
     * Every sort, filter and page turn rebuilds the report from its persistent, so this runs on each of them.
     *
     * @return bool
     */
    #[\Override]
    protected function can_view(): bool {
        // Core's dynamic table builds the report with the caller's parameters and downloads when one of them
        // is 'download', a path that never asks can_be_downloaded(); the hub's own download fires an audit event.
        if ($this->get_parameter('download', '', PARAM_RAW) !== '') {
            return false;
        }
        return self::may_view($this->get_context(), $this->templateid());
    }

    /**
     * Whether the current user may view the pairs of a template in a context, without building the report.
     *
     * The hub's own rules, as its page applies them: a signed-in user who is not a guest (core reaches the
     * report without the page), a hub context with local/evidencehub:view there, competencies enabled, and
     * the template offered in that context ({@see hub_access}).
     *
     * @param \context $context The hub's context.
     * @param int $templateid The plan template.
     * @return bool
     */
    public static function may_view(\context $context, int $templateid): bool {
        return isloggedin()
            && !isguestuser()
            && hub_access::can_view($context)
            && api::is_enabled()
            && hub_access::offered_template($templateid, $context) !== null;
    }

    /**
     * The template the report lists, its item id.
     *
     * @return int
     */
    private function templateid(): int {
        return (int) $this->get_report_persistent()->get('itemid');
    }

    /**
     * Build the report: the pair set, its entities, columns, filters and sort.
     *
     * Runs before {@see self::can_view()}, so nothing here may assume the context or the template is valid:
     * the rows are cut by them, and the report is refused before any row is read.
     *
     * @return void
     */
    #[\Override]
    protected function initialise(): void {
        $scope = hub_scope::for_user();
        $names = pairset::report_names();
        $p = database::generate_alias();
        $tc = database::generate_alias();
        $s = database::generate_alias();
        $m = database::generate_alias();
        $t = database::generate_alias();
        $r = database::generate_alias();
        $this->set_main_table(plan::TABLE, $p);

        $userentity = new user();
        $u = $userentity->get_table_alias('user');
        foreach (pairset::joins($p, $u, $tc, $s) as $join) {
            $this->add_join($join);
        }
        [$where, $params] = pairset::where($p, $this->templateid(), $scope, $names);
        $this->add_base_condition_sql($where, $params);
        $this->add_base_condition_sql("{$u}.suspended = 0");

        // The deciding evidence and its type, for the details entity.
        $this->add_join("LEFT JOIN {" . details::TABLE . "} {$m} ON {$m}.id = {$s}.metaid");
        $this->add_join("LEFT JOIN {local_evidencehub_type} {$t} ON {$t}.id = {$m}.typeid");
        // The status row again, joined only when it is restricted to the reader.
        [$restrictedjoin, $restrictedparams] = pairset::restricted_join($r, $s, "{$p}.userid", $scope, $names);
        $this->add_join($restrictedjoin, $restrictedparams);
        $restricted = "{$r}.id IS NOT NULL";

        $competencyentity = new competency();
        $c = $competencyentity->get_table_alias('competency');
        $this->add_join("JOIN {competency} {$c} ON {$c}.id = {$tc}.competencyid");

        $this->add_entity($userentity);
        $this->add_entity($competencyentity);
        $this->add_entity((new validity($restricted, true))->set_table_alias(status_repository::TABLE, $s));
        $this->add_entity((new evidence_details($restricted, true))
            ->set_table_alias(details::TABLE, $m)
            ->set_table_alias('local_evidencehub_type', $t));

        // The pair's key, for a reader of the rows that needs it.
        $this->add_base_fields("{$p}.userid, {$tc}.competencyid");

        $this->add_report_columns($userentity, $p, $tc, $c);
        $this->add_report_filters($p);

        $this->set_initial_sort_column('validity:effectiveexpiry', SORT_ASC);
        // Core's download would lose the hub's download event: the hub's own download stays.
        $this->set_downloadable(false);
    }

    /**
     * Add the columns: the worker, the identity fields the reader may see, the competency and the validity.
     *
     * @param user $userentity The worker's entity.
     * @param string $p The plan's alias.
     * @param string $tc The template competency's alias.
     * @param string $c The competency's alias.
     * @return void
     */
    private function add_report_columns(user $userentity, string $p, string $tc, string $c): void {
        $this->add_column($this->worker_column($userentity));
        // The list is empty outside moodle/site:viewuseridentity: absence, not a blanked cell.
        // Only the user table's fields, as the hub's list shows them: no custom profile field column.
        foreach (hub_query::identity_fields_in($this->get_context()) as $field) {
            $this->add_column($userentity->get_identity_column($field));
        }
        $this->add_column($this->competency_column($c));
        $this->add_column_from_entity('validity:status');
        // The hub's default order: restricted and missing first (their key is 0), then the earliest day, then
        // the pair's key, so tied rows keep one order across pages.
        $this->add_column_from_entity('validity:effectiveexpiry')
            ->add_field("{$p}.userid", 'pairuserid')
            ->add_field("{$tc}.competencyid", 'paircompetencyid')
            ->set_is_sortable(true, ['effectiveexpiry', 'pairuserid', 'paircompetencyid']);
        $this->add_column_from_entity('validity:evidencecount');
        $this->add_column_from_entity('evidence_details:type');
        $this->add_column_from_entity('evidence_details:issuer');

        // Each cell carries its column's heading, for a layout that shows the rows as cards.
        foreach ($this->get_active_columns() as $column) {
            $column->add_attributes(['data-cardtitle' => $column->get_title()]);
        }
    }

    /**
     * The worker's full name, linked to their evidence page.
     *
     * Full names follow moodle/site:viewfullnames in the report's context, as the hub's download does.
     *
     * @param user $userentity The worker's entity.
     * @return column
     */
    private function worker_column(user $userentity): column {
        $u = $userentity->get_table_alias('user');
        $namefields = user::get_name_fields_select($u);
        $viewfullnames = has_capability('moodle/site:viewfullnames', $this->get_context());
        return (new column('worker', new lang_string('worker', 'local_evidencehub'), $userentity->get_entity_name()))
            ->add_fields("{$u}.id, {$namefields}")
            ->set_is_sortable(true, explode(', ', $namefields))
            ->add_callback(static function ($value, \stdClass $row) use ($viewfullnames): string {
                global $OUTPUT;
                if ($value === null) {
                    return '';
                }
                foreach (fields::get_name_fields() as $field) {
                    $row->{$field} ??= '';
                }
                $url = new \moodle_url('/local/evidencehub/evidence.php', ['userid' => (int) $value]);
                return $OUTPUT->action_link($url, s(fullname($row, $viewfullnames)));
            });
    }

    /**
     * The competency's short name, formatted in its framework's context as the hub formats it.
     *
     * @param string $c The competency's alias.
     * @return column
     */
    private function competency_column(string $c): column {
        $f = database::generate_alias();
        return (new column('name', new lang_string('competency', 'local_evidencehub'), 'competency'))
            ->add_join("JOIN {competency_framework} {$f} ON {$f}.id = {$c}.competencyframeworkid")
            ->add_fields("{$c}.shortname, {$f}.contextid")
            ->set_is_sortable(true, ["{$c}.shortname"])
            ->add_callback(static function ($value, \stdClass $row): string {
                if ($value === null) {
                    return '';
                }
                return format_string($value, true, ['context' => \context::instance_by_id((int) $row->contextid)]);
            });
    }

    /**
     * Add the filters: the status, the cohort, the type of evidence and the worker's name.
     *
     * @param string $p The plan's alias.
     * @return void
     */
    private function add_report_filters(string $p): void {
        $this->add_filter_from_entity('validity:status');
        $this->add_filter((new filter(
            cohort::class,
            'cohort',
            new lang_string('cohort', 'core_cohort'),
            'user',
            "{$p}.userid"
        ))->set_options($this->cohort_options()));
        $this->add_filter_from_entity('evidence_details:type');
        $this->add_filter_from_entity('user:fullname');
    }

    /**
     * The cohorts the hub offers this reader in the report's context, as the filter's options.
     *
     * Read for the context in hand even before {@see self::can_view()} refuses it: the list is the
     * hub's own rule ({@see hub_access::cohorts()}), and a refused report reads no row.
     *
     * @return array Cohort id => name, escaped for the filter form.
     */
    private function cohort_options(): array {
        $options = [];
        foreach (hub_access::cohorts($this->get_context()) as $cohort) {
            $options[$cohort->id] = format_string($cohort->name, true, ['context' => $this->get_context()]);
        }
        return $options;
    }
}
