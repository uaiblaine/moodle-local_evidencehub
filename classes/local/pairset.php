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
use core_reportbuilder\local\helpers\database;

/**
 * The hub's pair set as SQL parts: who is listed, which pairs a template requires, and when a pair is
 * restricted for a reader.
 *
 * A pair is (worker, competency): a user not deleted with an active plan from the template, by each of the
 * template's competencies, with the status row LEFT joined (no row is "missing"). The pair set is cut by
 * the reader's userevidenceview fragment, and a pair decided by a sensitive evidence is restricted unless
 * the reader is the worker or holds viewsensitive over them ({@see hub_scope}).
 *
 * Every surface that reads the pair set or the restricted rule builds it here: the hub's base set
 * ({@see hub_query::base_sql()}), the custom-report datasource
 * ({@see \local_evidencehub\reportbuilder\datasource\validity}) and the system report
 * ({@see \local_evidencehub\reportbuilder\local\systemreports\hub_pairs}). Each part takes the aliases of
 * the surface's tables and a namer for its placeholders: the hub keeps its own names
 * ({@see self::names_as_given()}), report builder needs its generated ones ({@see self::report_names()}).
 * The capability fragments carry placeholders, which may appear once per statement: the restricted
 * condition is meant to be read once, in the innermost table or in a join.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class pairset {
    /**
     * A namer that keeps every placeholder's name: for SQL whose names are already unique in its statement.
     *
     * @return \Closure Name => the same name.
     */
    public static function names_as_given(): \Closure {
        return static fn(string $name): string => $name;
    }

    /**
     * A namer that gives every placeholder a name of report builder's, as its conditions and joins require.
     *
     * @return \Closure Name => a new name from {@see database::generate_param_name()}.
     */
    public static function report_names(): \Closure {
        return static fn(string $name): string => database::generate_param_name();
    }

    /**
     * The joins from the plan to the pair set's other tables.
     *
     * @param string $plan The alias of the competency_plan table, the set's first table.
     * @param string $user The alias to give the user table.
     * @param string $templatecomp The alias to give competency_templatecomp.
     * @param string $status The alias to give the status table; LEFT joined, so a missing pair has none.
     * @return array List of JOIN clauses, in order.
     */
    public static function joins(string $plan, string $user, string $templatecomp, string $status): array {
        return [
            "JOIN {user} {$user} ON {$user}.id = {$plan}.userid AND {$user}.deleted = 0",
            "JOIN {competency_templatecomp} {$templatecomp} ON {$templatecomp}.templateid = {$plan}.templateid",
            "LEFT JOIN {" . status_repository::TABLE . "} {$status}
                       ON {$status}.userid = {$plan}.userid AND {$status}.competencyid = {$templatecomp}.competencyid",
        ];
    }

    /**
     * The pair set's conditions over the plan: the template, an active plan, and the workers the reader may view.
     *
     * @param string $plan The alias of the competency_plan table.
     * @param int $templateid The plan template.
     * @param hub_scope $scope The reader's capability cuts.
     * @param \Closure $names The namer of the placeholders.
     * @return array [sql, params]: one condition.
     */
    public static function where(string $plan, int $templateid, hub_scope $scope, \Closure $names): array {
        [$visible, $visibleparams] = self::visible("{$plan}.userid", $scope, $names);
        [$sql, $params] = self::renamed(
            "{$plan}.templateid = :hubtemplateid AND {$plan}.status = :hubactive",
            ['hubtemplateid' => $templateid, 'hubactive' => plan::STATUS_ACTIVE],
            $names
        );
        return ["{$sql} AND {$visible}", $params + $visibleparams];
    }

    /**
     * The workers the reader may view: userevidenceview in the worker's user context.
     *
     * @param string $userid The SQL of the worker's user id.
     * @param hub_scope $scope The reader's capability cuts.
     * @param \Closure $names The namer of the placeholders.
     * @return array [sql, params]: one condition.
     */
    public static function visible(string $userid, hub_scope $scope, \Closure $names): array {
        return self::renamed("{$userid} {$scope->viewsql}", $scope->viewparams, $names);
    }

    /**
     * True for a status row restricted to the reader: decided by a sensitive evidence, not the reader's own,
     * and of a worker over whom the reader lacks viewsensitive.
     *
     * Reads the row's stored issensitive (the deciding evidence's type, {@see status_repository}), never a
     * join to the evidence and its type. False for a missing pair, whose row is NULL.
     *
     * @param string $status The alias of the status row the condition reads issensitive from.
     * @param string $userid The SQL of the worker's user id.
     * @param hub_scope $scope The reader's capability cuts.
     * @param \Closure $names The namer of the placeholders.
     * @return array [sql, params]: one condition.
     */
    public static function restricted(string $status, string $userid, hub_scope $scope, \Closure $names): array {
        return self::renamed(
            "{$status}.issensitive = 1 AND {$userid} <> :hubviewerid AND NOT ({$userid} {$scope->sensitivesql})",
            ['hubviewerid' => $scope->viewerid] + $scope->sensitiveparams,
            $names
        );
    }

    /**
     * The status row joined a second time only when it is restricted to the reader: "{$alias}.id IS NOT NULL"
     * then reads as the restricted condition, however many columns, filters and sorts read it, with the
     * capability fragment's placeholders once in the statement.
     *
     * @param string $alias The alias to give the second status row.
     * @param string $status The alias of the status row already joined.
     * @param string $userid The SQL of the worker's user id.
     * @param hub_scope $scope The reader's capability cuts.
     * @param \Closure $names The namer of the placeholders.
     * @return array [sql, params]: one LEFT JOIN clause.
     */
    public static function restricted_join(
        string $alias,
        string $status,
        string $userid,
        hub_scope $scope,
        \Closure $names
    ): array {
        [$restricted, $params] = self::restricted($alias, $userid, $scope, $names);
        return [
            "LEFT JOIN {" . status_repository::TABLE . "} {$alias} ON {$alias}.id = {$status}.id AND {$restricted}",
            $params,
        ];
    }

    /**
     * SQL with its placeholders renamed by a namer, each name asked once.
     *
     * @param string $sql The SQL.
     * @param array $params Its named parameters.
     * @param \Closure $names The namer.
     * @return array [sql, params].
     */
    private static function renamed(string $sql, array $params, \Closure $names): array {
        $map = [];
        foreach (array_keys($params) as $name) {
            $map[$name] = $names($name);
        }
        return database::sql_replace_parameters($sql, $params, static fn(string $name): string => $map[$name]);
    }
}
