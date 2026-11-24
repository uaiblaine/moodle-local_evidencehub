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

use local_evidencehub\reportbuilder\local\systemreports\hub_pairs;

/**
 * The hub's readiness matrix: a page of workers, one row each, by the template's competencies.
 *
 * Its rows are the workers the pairs report lists at least one pair of under the reader's stored filters, the scope
 * and the suspended choice ({@see reportengine::workers_sql()}, the matching set): the matrix never applies a copy of
 * the list's criteria. Each listed worker shows every competency of the template, from the hub's pair set
 * ({@see hub_query::base_sql()}), so a worker kept by one matching pair shows them all, masked for a restricted pair
 * as the list masks it. Three statements: the matching set's count, one page of its workers by urgency
 * ({@see self::workers()}), and every pair of that page's workers ({@see self::cells()}). Each has a method of its
 * own returning its SQL, so a test can run it alone.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class hub_matrix {
    /** @var int The urgency of a worker with no pair other than compliant, or none with a day: after every date. */
    public const int URGENCY_NONE = hub_query::NO_EXPIRY;

    /**
     * The matrix of a template for a reader.
     *
     * @param \context $context The hub context; it decides the identity fields shown.
     * @param hub_scope $scope The reader's capability cuts.
     * @param hub_filter $filter The template, and whether suspended workers are in the pair set: as the report reads them.
     * @param array $matching The matching set: [SQL of one column, userid; its parameters], from
     *     {@see reportengine::workers_sql()}.
     */
    public function __construct(
        /** @var \context The hub context. */
        private readonly \context $context,
        /** @var hub_scope The reader's capability cuts. */
        private readonly hub_scope $scope,
        /** @var hub_filter The template and the suspended flag. */
        private readonly hub_filter $filter,
        /** @var array The matching set's SQL and parameters. */
        private readonly array $matching,
    ) {
    }

    /**
     * The matrix of a pairs report: its workers are the report's, its cells the pair set as the report reads it (the
     * template, and suspended accounts as the report lists them).
     *
     * @param hub_pairs $report The report, built for the reader with the scope the matrix applies.
     * @return self
     */
    public static function for_report(hub_pairs $report): self {
        $filter = hub_filter::from_values(
            ['templateid' => $report->template_id(), 'suspended' => $report->includes_suspended()],
            $report->get_context()
        );
        return new self($report->get_context(), $report->scope(), $filter, reportengine::workers_sql($report));
    }

    /**
     * The union of parameter arrays that must not share a name.
     *
     * PHP's array union keeps the left value of a repeated key, so two statements' parameters that collided would run
     * one of them with the other's value and no error.
     *
     * @param array ...$sets The parameter arrays, each under names of its own.
     * @return array Every parameter.
     * @throws \coding_exception When two arrays share a name.
     */
    public static function union_params(array ...$sets): array {
        $union = [];
        foreach ($sets as $set) {
            $shared = array_intersect_key($union, $set);
            if ($shared !== []) {
                throw new \coding_exception('Statement parameters share a name: ' . implode(', ', array_keys($shared)));
            }
            $union += $set;
        }
        return $union;
    }

    /**
     * Step 1's statement: the matching workers, the most urgent first, unpaged.
     *
     * A worker's urgency is their lowest "valid until" sort key among their pairs not compliant: 0 (first) for a
     * missing or restricted pair, as the list sorts them, {@see hub_query::NO_EXPIRY} for one valid with no end, and
     * {@see self::URGENCY_NONE} when every pair is compliant. Ties go by name, then by the user id, so paging is stable.
     *
     * @return array [sql, params]; the rows are userid and urgency.
     */
    public function workers_sql(): array {
        [$basesql, $params] = hub_query::base_sql($this->filter, $this->scope, $this->context);
        [$matchingsql, $matchingparams] = $this->matching;
        $sql = "SELECT w.userid, w.urgency
                  FROM (SELECT b.userid,
                               COALESCE(MIN(CASE WHEN b.estatus <> " . status::STATUS_VALID . " THEN b.eexpiry END),
                                        " . self::URGENCY_NONE . ") AS urgency
                          FROM ($basesql) b
                         WHERE b.userid IN ($matchingsql)
                      GROUP BY b.userid) w
                  JOIN {user} u ON u.id = w.userid
              ORDER BY w.urgency, COALESCE(u.lastname, ''), COALESCE(u.firstname, ''), w.userid";
        return [$sql, self::union_params($params, $matchingparams)];
    }

    /**
     * Step 1: the user ids of one page of workers.
     *
     * @param int $page The page, from 0.
     * @param int $perpage Workers per page.
     * @return array List of user ids.
     */
    public function workers(int $page, int $perpage): array {
        global $DB;
        [$sql, $params] = $this->workers_sql();
        $userids = [];
        $rs = $DB->get_recordset_sql($sql, $params, $page * $perpage, $perpage);
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
     * @return array [sql, params]; the rows are userid, competencyid, estatus, eexpiry and the user fields.
     */
    public function cells_sql(array $userids): array {
        global $DB;
        [$basesql, $params] = hub_query::base_sql($this->filter, $this->scope, $this->context);
        [$userin, $userparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'hubmu');
        $fields = \core_user\fields::for_identity($this->context, false)->with_name()->get_sql('u', true);
        $sql = "SELECT b.userid, b.competencyid, b.estatus, b.eexpiry{$fields->selects}
                  FROM ($basesql) b
                  JOIN {user} u ON u.id = b.userid
                       {$fields->joins}
                 WHERE b.userid $userin";
        return [$sql, self::union_params($params, $userparams, $fields->params)];
    }

    /**
     * Step 2: every pair of the page's workers, from the hub's pair set.
     *
     * @param array $userids Step 1's user ids.
     * @return array workers: user id => record with the name and identity fields; cells:
     *     "userid:competencyid" => record with userid, competencyid, estatus and eexpiry as ints.
     */
    public function cells(array $userids): array {
        global $DB;
        $matrix = ['workers' => [], 'cells' => []];
        if (!$userids) {
            return $matrix;
        }
        [$sql, $params] = $this->cells_sql($userids);
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
     * The count statement: how many workers the matrix lists, the matching set's.
     *
     * Counted on every load, over the report's own SQL: the report's cached total counts pairs, not workers.
     *
     * @return array [sql, params].
     */
    public function count_sql(): array {
        [$matchingsql, $matchingparams] = $this->matching;
        return ["SELECT COUNT(1) FROM ($matchingsql) m", $matchingparams];
    }

    /**
     * How many workers the matrix lists.
     *
     * @return int
     */
    public function worker_count(): int {
        global $DB;
        [$sql, $params] = $this->count_sql();
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
