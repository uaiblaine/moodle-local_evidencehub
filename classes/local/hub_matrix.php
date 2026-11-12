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

/**
 * The hub's readiness matrix: a page of workers, one row each, by the template's competencies.
 *
 * Read in two statements over the list's base set ({@see hub_query::base_sql()}), so the matrix and
 * the list never disagree on a pair: {@see self::workers()} groups the base set by worker, keeps the
 * workers with at least one pair in the filter's statuses, sorts them and keeps one page;
 * {@see self::cells()} reads every pair of that page's workers, with their names. Each statement has
 * a method of its own returning its SQL, so a test can run it alone.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class hub_matrix {
    /** @var int The urgency of a worker with no pair other than compliant: after every date. */
    public const int URGENCY_NONE = 99999999;

    /**
     * The matrix of a page context, for a viewer.
     *
     * @param \context $context The page context; it decides the identity fields shown.
     * @param hub_scope $scope The viewer's capability cuts.
     */
    public function __construct(
        /** @var \context The page context. */
        private readonly \context $context,
        /** @var hub_scope The viewer's capability cuts. */
        private readonly hub_scope $scope,
    ) {
    }

    /**
     * The status filter as a HAVING over the base set's alias b: a worker stays with one pair in the
     * filter's statuses, and shows every pair.
     *
     * @param hub_filter $filter The filter.
     * @return array [sql (empty or a HAVING clause), params].
     */
    private static function status_having(hub_filter $filter): array {
        global $DB;
        if (!$filter->statuses) {
            return ['', []];
        }
        [$insql, $params] = $DB->get_in_or_equal($filter->statuses, SQL_PARAMS_NAMED, 'hubmst');
        return ["HAVING SUM(CASE WHEN b.estatus $insql THEN 1 ELSE 0 END) > 0", $params];
    }

    /**
     * The ORDER BY of the filter's sort over w (the grouped workers) and u (user): by name, or by
     * urgency then name; every sort ends in the user id, so paging is stable.
     *
     * The matrix sorts by worker only: the list's other sorts (competency, status) read as the
     * default, most urgent first, since one row holds every competency and status of a worker.
     *
     * @param hub_filter $filter The filter.
     * @return string
     */
    private static function order_by(hub_filter $filter): string {
        $dir = $filter->dir === 'desc' ? 'DESC' : 'ASC';
        // COALESCE keeps PostgreSQL from walking the lastname index under LIMIT, as in the list.
        $name = "COALESCE(u.lastname, '') $dir, COALESCE(u.firstname, '') $dir";
        return match ($filter->sort) {
            hub_filter::SORT_WORKER => "$name, w.userid",
            default => "w.urgency $dir, COALESCE(u.lastname, ''), COALESCE(u.firstname, ''), w.userid",
        };
    }

    /**
     * Step 1's statement: the filter's workers in its order, unpaged.
     *
     * A worker's urgency is their lowest effective expiry among their pairs not compliant: 0 (first)
     * for a missing or restricted pair, as the list sorts them; {@see self::URGENCY_NONE} when every
     * pair is compliant.
     *
     * @param hub_filter $filter The filter.
     * @param hub_scope $scope The viewer's capability cuts.
     * @param \context $context The page context.
     * @return array [sql, params]; the rows are userid and urgency.
     */
    public static function workers_sql(hub_filter $filter, hub_scope $scope, \context $context): array {
        [$basesql, $params] = hub_query::base_sql($filter, $scope, $context);
        [$having, $statusparams] = self::status_having($filter);
        $sql = "SELECT w.userid, w.urgency
                  FROM (SELECT b.userid,
                               COALESCE(MIN(CASE WHEN b.estatus <> " . status::STATUS_VALID . " THEN b.eexpiry END),
                                        " . self::URGENCY_NONE . ") AS urgency
                          FROM ($basesql) b
                      GROUP BY b.userid
                               $having) w
                  JOIN {user} u ON u.id = w.userid
              ORDER BY " . self::order_by($filter);
        return [$sql, $params + $statusparams];
    }

    /**
     * Step 1: the user ids of one page of workers, in the filter's order.
     *
     * @param hub_filter $filter The filter.
     * @return array List of user ids.
     */
    public function workers(hub_filter $filter): array {
        global $DB;
        [$sql, $params] = self::workers_sql($filter, $this->scope, $this->context);
        $userids = [];
        $rs = $DB->get_recordset_sql($sql, $params, $filter->page * $filter->perpage, $filter->perpage);
        foreach ($rs as $record) {
            $userids[] = (int) $record->userid;
        }
        $rs->close();
        return $userids;
    }

    /**
     * Step 2's statement: every pair of some workers, with their names and identity fields.
     *
     * @param array $userids The workers, from step 1; not empty.
     * @param hub_filter $filter The filter; its statuses do not apply (a row shows every pair).
     * @param hub_scope $scope The viewer's capability cuts.
     * @param \context $context The page context.
     * @return array [sql, params]; the rows are userid, competencyid, estatus, eexpiry and the user fields.
     */
    public static function cells_sql(array $userids, hub_filter $filter, hub_scope $scope, \context $context): array {
        global $DB;
        [$basesql, $params] = hub_query::base_sql($filter, $scope, $context);
        [$userin, $userparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'hubmu');
        $fields = \core_user\fields::for_identity($context, false)->with_name()->get_sql('u', true);
        $sql = "SELECT b.userid, b.competencyid, b.estatus, b.eexpiry{$fields->selects}
                  FROM ($basesql) b
                  JOIN {user} u ON u.id = b.userid
                       {$fields->joins}
                 WHERE b.userid $userin";
        return [$sql, $params + $userparams + $fields->params];
    }

    /**
     * Step 2: every pair of the page's workers, from the same base set as the list.
     *
     * @param array $userids Step 1's user ids.
     * @param hub_filter $filter The filter.
     * @return array workers: user id => record with the name and identity fields; cells:
     *     "userid:competencyid" => record with userid, competencyid, estatus and eexpiry as ints.
     */
    public function cells(array $userids, hub_filter $filter): array {
        global $DB;
        $matrix = ['workers' => [], 'cells' => []];
        if (!$userids) {
            return $matrix;
        }
        [$sql, $params] = self::cells_sql($userids, $filter, $this->scope, $this->context);
        $rs = $DB->get_recordset_sql($sql, $params);
        foreach ($rs as $record) {
            $userid = (int) $record->userid;
            $competencyid = (int) $record->competencyid;
            $matrix['cells'][$userid . ':' . $competencyid] = (object) [
                'userid' => $userid,
                'competencyid' => $competencyid,
                'estatus' => (int) $record->estatus,
                'eexpiry' => (int) $record->eexpiry,
            ];
            if (!isset($matrix['workers'][$userid])) {
                unset($record->competencyid, $record->estatus, $record->eexpiry);
                $record->userid = $userid;
                $matrix['workers'][$userid] = $record;
            }
        }
        $rs->close();
        return $matrix;
    }

    /**
     * The count statement: how many workers the filter's statuses keep.
     *
     * @param hub_filter $filter The filter.
     * @param hub_scope $scope The viewer's capability cuts.
     * @param \context $context The page context.
     * @return array [sql, params].
     */
    public static function count_sql(hub_filter $filter, hub_scope $scope, \context $context): array {
        [$basesql, $params] = hub_query::base_sql($filter, $scope, $context);
        [$having, $statusparams] = self::status_having($filter);
        $sql = "SELECT COUNT(1)
                  FROM (SELECT b.userid
                          FROM ($basesql) b
                      GROUP BY b.userid
                               $having) w";
        return [$sql, $params + $statusparams];
    }

    /**
     * How many workers the pager counts.
     *
     * With no status chip every worker of the base set is a row, which the KPIs counted already
     * (readiness' workers, cached); a chip narrows the rows, and one count statement over the same
     * worker filter counts them.
     *
     * @param hub_filter $filter The filter.
     * @param array $kpis The filter's KPIs, from hub_kpis::get().
     * @return int
     */
    public function worker_count(hub_filter $filter, array $kpis): int {
        global $DB;
        if (!$filter->statuses) {
            return (int) $kpis['workers'];
        }
        [$sql, $params] = self::count_sql($filter, $this->scope, $this->context);
        return (int) $DB->count_records_sql($sql, $params);
    }

    /**
     * The template's competencies in the template's own order, each with its policy kind.
     *
     * Mirrors core's template_competency::list_competencies() order (sortorder, then id). The policies
     * of the columns' frameworks are read once and resolved per column ({@see policy::resolve_from()}).
     *
     * Takes the filter, never a template id: only hub_filter::from_values() builds one, after checking
     * that the page context offers its template to the viewer.
     *
     * @param hub_filter $filter The validated filter; only its template counts.
     * @return array List of records: id, shortname, frameworkid, frameworkcontextid and kind (a
     *     policy KIND_* constant), as ints except shortname.
     */
    public static function columns(hub_filter $filter): array {
        global $DB;
        $sql = "SELECT c.id, c.shortname, c.competencyframeworkid AS frameworkid, f.contextid AS frameworkcontextid
                  FROM {competency_templatecomp} tc
                  JOIN {competency} c ON c.id = tc.competencyid
                  JOIN {competency_framework} f ON f.id = c.competencyframeworkid
                 WHERE tc.templateid = :templateid
              ORDER BY tc.sortorder, tc.id";
        $columns = [];
        $rs = $DB->get_recordset_sql($sql, ['templateid' => $filter->templateid]);
        foreach ($rs as $record) {
            $columns[] = (object) [
                'id' => (int) $record->id,
                'shortname' => $record->shortname,
                'frameworkid' => (int) $record->frameworkid,
                'frameworkcontextid' => (int) $record->frameworkcontextid,
            ];
        }
        $rs->close();
        if (!$columns) {
            return [];
        }

        $byframework = [];
        [$insql, $params] = $DB->get_in_or_equal(array_unique(array_column($columns, 'frameworkid')), SQL_PARAMS_NAMED, 'hubfw');
        foreach ($DB->get_records_select(policy::TABLE, "frameworkid $insql", $params) as $row) {
            $byframework[(int) $row->frameworkid][] = $row;
        }
        foreach ($columns as $column) {
            $column->kind = policy::resolve_from($byframework[$column->frameworkid] ?? [], $column->id)->kind;
        }
        return $columns;
    }
}
