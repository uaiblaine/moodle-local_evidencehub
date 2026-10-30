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

use core_competency\plan;

/**
 * The hub's grid: the (worker, competency) pairs a plan template requires, as the viewer may see them.
 *
 * A page is read in two statements (S0: one statement with every detail joined took 0.5-0.8 s on
 * MariaDB over 300k pairs, the deferred join stays under 300 ms): {@see self::page_keys()} sorts only
 * the narrow key of the base set and keeps one page, {@see self::page_details()} reads that page's
 * details by primary and unique keys. The download reads everything in one statement, {@see self::rows()}.
 *
 * Every statement reads the effective status of a pair ({@see self::base_sql()}): a pair decided by a
 * sensitive evidence the viewer may not see is "restricted", in the grid, the status filter, the sort
 * and the counts alike, so no filter or count reveals what the cell hides.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class hub_query {
    /** @var string The detail columns both page_details() and rows() read, over c, f, s, m and t. */
    private const string DETAIL_COLUMNS = "c.id AS competencyid, c.shortname AS competencyname,
                       c.idnumber AS competencyidnumber, f.contextid AS frameworkcontextid,
                       s.effectiveexpiry, s.evidencecount, m.issuer, t.name AS typename";

    /**
     * The grid of a page context, for a viewer.
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
     * The base set as a derived table: one row per pair with its effective status and sort expiry.
     *
     * A pair is (worker, competency) where the worker, a user not deleted (and not suspended unless
     * the filter includes them), has an active plan from the template and the competency is one of
     * the template's. The worker set is cut by the viewer's userevidenceview fragment, the cohort and
     * the search. Columns: userid, competencyid, estatus (effective code), eexpiry (the sort value of
     * the effective expiry: 0 for none, a restricted pair or a missing one).
     *
     * The restriction is worked out once, in the innermost table, so each capability fragment's
     * placeholders appear once per statement. It reads the status row's issensitive (the deciding
     * evidence's type, stored by {@see status_repository}) rather than joining meta and type for every
     * pair: S0's re-measurement halved the base set's cost with it.
     *
     * The search reads the worker's first and last name, and the ID number only when it is among the
     * identity fields the viewer is shown in the page context: a search must not find a worker by a
     * field the page would not print.
     *
     * @param hub_filter $filter The filter; its statuses, sort and page are not applied here.
     * @param hub_scope $scope The viewer's capability cuts.
     * @param \context $context The page context, which decides the identity fields shown.
     * @return array [sql, params].
     */
    public static function base_sql(hub_filter $filter, hub_scope $scope, \context $context): array {
        global $DB;
        $params = [
            'hubtemplateid' => $filter->templateid,
            'hubactive' => plan::STATUS_ACTIVE,
            'hubviewerid' => $scope->viewerid,
        ] + $scope->viewparams + $scope->sensitiveparams;
        $where = '';
        if (!$filter->suspended) {
            $where .= ' AND bu.suspended = 0';
        }
        if ($filter->cohortid) {
            $where .= ' AND EXISTS (SELECT 1 FROM {cohort_members} bcm
                                     WHERE bcm.userid = bp.userid AND bcm.cohortid = :hubcohortid)';
            $params['hubcohortid'] = $filter->cohortid;
        }
        if ($filter->search !== '') {
            $like = '%' . $DB->sql_like_escape($filter->search) . '%';
            $fields = ['firstname', 'lastname'];
            if (in_array('idnumber', \core_user\fields::get_identity_fields($context, false), true)) {
                $fields[] = 'idnumber';
            }
            $likes = [];
            foreach ($fields as $index => $field) {
                $likes[] = $DB->sql_like('bu.' . $field, ':hubsearch' . $index, false);
                $params['hubsearch' . $index] = $like;
            }
            $where .= ' AND (' . implode(' OR ', $likes) . ')';
        }
        $sql = "SELECT r.userid, r.competencyid,
                       CASE WHEN r.restricted = 1 THEN " . status::EFFECTIVE_RESTRICTED . "
                            ELSE COALESCE(r.status, " . status::EFFECTIVE_MISSING . ") END AS estatus,
                       CASE WHEN r.restricted = 1 THEN 0 ELSE COALESCE(r.effectiveexpiry, 0) END AS eexpiry
                  FROM (SELECT bp.userid, btc.competencyid, bs.status, bs.effectiveexpiry,
                               CASE WHEN bs.issensitive = 1 AND bp.userid <> :hubviewerid
                                         AND NOT (bp.userid {$scope->sensitivesql}) THEN 1 ELSE 0 END AS restricted
                          FROM {" . plan::TABLE . "} bp
                          JOIN {user} bu ON bu.id = bp.userid AND bu.deleted = 0
                          JOIN {competency_templatecomp} btc ON btc.templateid = bp.templateid
                     LEFT JOIN {" . status_repository::TABLE . "} bs
                               ON bs.userid = bp.userid AND bs.competencyid = btc.competencyid
                         WHERE bp.templateid = :hubtemplateid AND bp.status = :hubactive
                               AND bp.userid {$scope->viewsql}{$where}) r";
        return [$sql, $params];
    }

    /**
     * The status filter on the base set's alias b, or nothing when every status is shown.
     *
     * @param hub_filter $filter The filter.
     * @return array [sql (empty or a WHERE clause), params].
     */
    private static function status_where(hub_filter $filter): array {
        global $DB;
        if (!$filter->statuses) {
            return ['', []];
        }
        [$insql, $params] = $DB->get_in_or_equal($filter->statuses, SQL_PARAMS_NAMED, 'hubst');
        return ["WHERE b.estatus $insql", $params];
    }

    /**
     * The ORDER BY of the filter's sort, over b, u (user) and c (competency): every sort ends in
     * the pair's unique key, so the order is total and paging is stable.
     *
     * @param hub_filter $filter The filter.
     * @return string
     */
    private static function order_by(hub_filter $filter): string {
        $dir = $filter->dir === 'desc' ? 'DESC' : 'ASC';
        return match ($filter->sort) {
            // COALESCE keeps PostgreSQL from walking the lastname index under LIMIT (S0: 841 ms to 60 ms).
            hub_filter::SORT_WORKER => "COALESCE(u.lastname, '') $dir, COALESCE(u.firstname, '') $dir, b.userid, b.competencyid",
            hub_filter::SORT_COMPETENCY => "c.shortname $dir, b.competencyid, b.userid",
            hub_filter::SORT_STATUS => "b.estatus $dir, b.userid, b.competencyid",
            default => "b.eexpiry $dir, b.userid, b.competencyid",
        };
    }

    /**
     * The joins a sort needs in step 1.
     *
     * @param hub_filter $filter The filter.
     * @return string
     */
    private static function sort_joins(hub_filter $filter): string {
        return match ($filter->sort) {
            hub_filter::SORT_WORKER => 'JOIN {user} u ON u.id = b.userid',
            hub_filter::SORT_COMPETENCY => 'JOIN {competency} c ON c.id = b.competencyid',
            default => '',
        };
    }

    /**
     * Step 1: the keys of one page, in the filter's order.
     *
     * @param hub_filter $filter The filter.
     * @return array List of [userid, competencyid, estatus, eexpiry] as ints.
     */
    public function page_keys(hub_filter $filter): array {
        global $DB;
        [$basesql, $params] = self::base_sql($filter, $this->scope, $this->context);
        [$where, $statusparams] = self::status_where($filter);
        $sql = "SELECT b.userid, b.competencyid, b.estatus, b.eexpiry
                  FROM ($basesql) b
                " . self::sort_joins($filter) . "
                $where
              ORDER BY " . self::order_by($filter);
        $keys = [];
        $rs = $DB->get_recordset_sql($sql, $params + $statusparams, $filter->page * $filter->perpage, $filter->perpage);
        foreach ($rs as $record) {
            $keys[] = [(int) $record->userid, (int) $record->competencyid, (int) $record->estatus, (int) $record->eexpiry];
        }
        $rs->close();
        return $keys;
    }

    /**
     * Step 2: the details of a page's pairs, by primary and unique keys.
     *
     * Reads the users and competencies the page names, crossed, with each combination's status row,
     * deciding evidence and type; the caller keeps the combinations step 1 returned.
     *
     * @param array $keys Step 1's keys.
     * @return array "userid:competencyid" => record: the user's name and identity fields, competency
     *     fields (competencyid, competencyname, competencyidnumber, frameworkcontextid), status columns
     *     (effectiveexpiry, evidencecount) and the deciding evidence's issuer and typename.
     */
    public function page_details(array $keys): array {
        global $DB;
        if (!$keys) {
            return [];
        }
        $userids = array_values(array_unique(array_column($keys, 0)));
        $competencyids = array_values(array_unique(array_column($keys, 1)));
        [$userin, $userparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'hubu');
        [$compin, $compparams] = $DB->get_in_or_equal($competencyids, SQL_PARAMS_NAMED, 'hubc');
        $fields = $this->user_fields();
        $sql = "SELECT u.id AS userid{$fields->selects}, " . self::DETAIL_COLUMNS . "
                  FROM {user} u
                  JOIN {competency} c ON c.id $compin
                  JOIN {competency_framework} f ON f.id = c.competencyframeworkid
             LEFT JOIN {" . status_repository::TABLE . "} s ON s.userid = u.id AND s.competencyid = c.id
             LEFT JOIN {" . details::TABLE . "} m ON m.id = s.metaid
             LEFT JOIN {local_evidencehub_type} t ON t.id = m.typeid
                       {$fields->joins}
                 WHERE u.id $userin";
        $details = [];
        $rs = $DB->get_recordset_sql($sql, $userparams + $compparams + $fields->params);
        foreach ($rs as $record) {
            $details[$record->userid . ':' . $record->competencyid] = $record;
        }
        $rs->close();
        return $details;
    }

    /**
     * One page of the grid: step 1's pairs, in its order, each with step 2's details.
     *
     * @param hub_filter $filter The filter.
     * @return array List of records: userid, competencyid, estatus, eexpiry and the details.
     */
    public function page(hub_filter $filter): array {
        $keys = $this->page_keys($filter);
        $details = $this->page_details($keys);
        $rows = [];
        foreach ($keys as [$userid, $competencyid, $estatus, $eexpiry]) {
            $record = $details[$userid . ':' . $competencyid] ?? null;
            if ($record === null) {
                // The competency or the user went away between the two statements.
                continue;
            }
            $record->userid = $userid;
            $record->competencyid = $competencyid;
            $record->estatus = $estatus;
            $record->eexpiry = $eexpiry;
            $rows[] = $record;
        }
        return $rows;
    }

    /**
     * Every pair of the filter, details joined, in the filter's order and without a limit: the download.
     *
     * @param hub_filter $filter The filter; its page is ignored.
     * @return \moodle_recordset Records as {@see self::page()} returns them; close it after use.
     */
    public function rows(hub_filter $filter): \moodle_recordset {
        global $DB;
        [$basesql, $params] = self::base_sql($filter, $this->scope, $this->context);
        [$where, $statusparams] = self::status_where($filter);
        $fields = $this->user_fields();
        $sql = "SELECT b.userid, b.estatus, b.eexpiry{$fields->selects}, " . self::DETAIL_COLUMNS . "
                  FROM ($basesql) b
                  JOIN {user} u ON u.id = b.userid
                  JOIN {competency} c ON c.id = b.competencyid
                  JOIN {competency_framework} f ON f.id = c.competencyframeworkid
             LEFT JOIN {" . status_repository::TABLE . "} s ON s.userid = b.userid AND s.competencyid = b.competencyid
             LEFT JOIN {" . details::TABLE . "} m ON m.id = s.metaid
             LEFT JOIN {local_evidencehub_type} t ON t.id = m.typeid
                       {$fields->joins}
                $where
              ORDER BY " . self::order_by($filter);
        return $DB->get_recordset_sql($sql, $params + $statusparams + $fields->params);
    }

    /**
     * The worker's name and identity fields the page context shows, as SQL over u.
     *
     * @return \stdClass selects (with a leading comma), joins, params, as core_user\fields::get_sql() returns them.
     */
    private function user_fields(): \stdClass {
        return \core_user\fields::for_identity($this->context, false)->with_name()->get_sql('u', true);
    }

    /**
     * The identity fields the page context shows, in core's order.
     *
     * @return array Field names, e.g. email, idnumber.
     */
    public function identity_fields(): array {
        return \core_user\fields::get_identity_fields($this->context, false);
    }
}
