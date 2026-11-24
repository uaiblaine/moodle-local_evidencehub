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
 * The hub's pair set as SQL: the (worker, competency) pairs a plan template requires, as a reader may see them.
 *
 * The list of pairs is the pairs report ({@see \local_evidencehub\reportbuilder\local\systemreports\hub_pairs});
 * this base set serves the readers outside it, which read it under other shapes: the matrix's cells
 * ({@see hub_matrix}), the dossier export's selection ({@see dossier_export::selection()}), the weekly digest
 * ({@see digest}) and the readiness web service. Every one reads the effective status of a pair: a pair decided by a
 * sensitive evidence the reader may not see is "restricted", so no surface reveals what the report hides.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class hub_query {
    /**
     * @var int The "valid until" sort key of a pair that has a status and no effective expiry (valid with no end): after
     *     every day, so it sorts as the least urgent (owner decision, 2026-11-23). A restricted and a missing pair
     *     keep the key 0, the most urgent. The pairs report's sort key reads the same value
     *     ({@see \local_evidencehub\reportbuilder\local\entities\validity::expiry_sort_sql()}).
     */
    public const int NO_EXPIRY = 99999999;

    /**
     * The base set as a derived table: one row per pair with its effective status and sort expiry.
     *
     * A pair is (worker, competency) where the worker, a user not deleted (and not suspended unless
     * the filter includes them), has an active plan from the template and the competency is one of
     * the template's. The worker set is cut by the viewer's userevidenceview fragment, the cohort and
     * the search. Columns: userid, competencyid, estatus (effective code), eexpiry (the "valid until" sort
     * key: 0 for a restricted or a missing pair, {@see self::NO_EXPIRY} for a pair with a status and no
     * effective expiry, else the day; {@see self::expiry_day()} reads the day back).
     *
     * The pair set's joins, conditions and restricted rule are {@see pairset}'s. The restriction is worked
     * out once, in the innermost table, so each capability fragment's placeholders appear once per
     * statement.
     *
     * The search reads the worker's first and last name, and the ID number only when it is among the
     * identity fields the viewer is shown in the context: a search must not find a worker by a field the
     * page would not print.
     *
     * @param hub_filter $filter The cuts.
     * @param hub_scope $scope The viewer's capability cuts.
     * @param \context $context The context, which decides the identity fields shown.
     * @return array [sql, params].
     */
    public static function base_sql(hub_filter $filter, hub_scope $scope, \context $context): array {
        global $DB;
        $names = pairset::names_as_given();
        [$pairs, $params] = pairset::where('bp', $filter->templateid, $scope, $names);
        [$restricted, $restrictedparams] = pairset::restricted('bs', 'bp.userid', $scope, $names);
        $params += $restrictedparams;
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
                       CASE WHEN r.restricted = 1 OR r.status IS NULL THEN 0
                            ELSE COALESCE(r.effectiveexpiry, " . self::NO_EXPIRY . ") END AS eexpiry
                  FROM (SELECT bp.userid, btc.competencyid, bs.status, bs.effectiveexpiry,
                               CASE WHEN {$restricted} THEN 1 ELSE 0 END AS restricted
                          FROM {" . plan::TABLE . "} bp
                               " . implode("\n", pairset::joins('bp', 'bu', 'btc', 'bs')) . "
                         WHERE {$pairs}{$where}) r";
        return [$sql, $params];
    }

    /**
     * The day a base set's eexpiry names, for a reader that prints it.
     *
     * @param int $eexpiry The sort key, as {@see self::base_sql()} reads it.
     * @return int|null The day, YYYYMMDD; null for no day (a restricted or a missing pair, or one valid with no end).
     */
    public static function expiry_day(int $eexpiry): ?int {
        return $eexpiry === 0 || $eexpiry === self::NO_EXPIRY ? null : $eexpiry;
    }

    /**
     * The identity fields a context shows to the current user, in core's order, custom profile fields left out.
     *
     * The one list the pairs report and the matrix share, so neither shows a column the other hides.
     *
     * @param \context $context The page context.
     * @return array Field names of the user table, e.g. email, idnumber.
     */
    public static function identity_fields_in(\context $context): array {
        return \core_user\fields::get_identity_fields($context, false);
    }
}
