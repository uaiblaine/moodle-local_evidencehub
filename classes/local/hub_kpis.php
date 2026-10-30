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
 * The hub's KPI bar: how many pairs of the filter's base set have each effective status, how many
 * workers are ready, how many are hidden by permissions. The grid's pager count is the sum of the
 * shown statuses' counts, so the page never runs a count of its own and the tiles always add up to it.
 *
 * Cached in the application cache hubkpis, except for a search (free text would grow the cache without
 * bound). The key holds the filter's cuts (template, cohort, suspended: not its statuses, sort or
 * page), the page context, the viewer's scope, a signature of the template's competencies, and the
 * generation: a random value in the cache hubgeneration, replaced whenever the
 * base set or a status may have changed ({@see self::bump_generation()}). Never a time to live: a lost
 * generation (a cache purge) just starts a new one.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class hub_kpis {
    /** @var array The effective statuses a worker is ready with. */
    public const array READY = [status::STATUS_VALID, status::STATUS_DUE, status::STATUS_CRITICAL];

    /**
     * The current generation, starting one when the cache holds none.
     *
     * @return string
     */
    public static function generation(): string {
        $cache = \cache::make('local_evidencehub', 'hubgeneration');
        $generation = $cache->get('generation');
        if ($generation === false) {
            $generation = self::new_generation();
            $cache->set('generation', $generation);
        }
        return $generation;
    }

    /**
     * Retire every cached KPI: a status row was written or deleted, or the base set changed.
     *
     * @return void
     */
    public static function bump_generation(): void {
        \cache::make('local_evidencehub', 'hubgeneration')->set('generation', self::new_generation());
    }

    /**
     * A new random generation.
     *
     * @return string
     */
    private static function new_generation(): string {
        return bin2hex(random_bytes(8));
    }

    /**
     * The KPIs of a filter for a viewer, from the cache when it holds them.
     *
     * @param hub_filter $filter The filter; only its template, cohort, search and suspended count.
     * @param hub_scope $scope The viewer's capability cuts.
     * @param \context $context The page context, which decides whether the search reads ID numbers.
     * @return array counts (effective code => pairs, every code), pairs, workers, ready, hidden.
     */
    public static function get(hub_filter $filter, hub_scope $scope, \context $context): array {
        if ($filter->search !== '') {
            // Free text would grow the cache without bound: a search is counted every time.
            return self::compute($filter, $scope, $context);
        }
        $key = sha1(json_encode([
            $filter->templateid,
            $filter->cohortid,
            $context->id,
            (int) $filter->suspended,
            $scope->key(),
            self::template_signature($filter->templateid),
            self::generation(),
        ]));
        $cache = \cache::make('local_evidencehub', 'hubkpis');
        $kpis = $cache->get($key);
        if ($kpis === false) {
            $kpis = self::compute($filter, $scope, $context);
            $cache->set($key, $kpis);
        }
        return $kpis;
    }

    /**
     * The KPIs of a filter for a viewer, from the database.
     *
     * @param hub_filter $filter The filter.
     * @param hub_scope $scope The viewer's capability cuts.
     * @param \context $context The page context.
     * @return array As {@see self::get()} returns them.
     */
    public static function compute(hub_filter $filter, hub_scope $scope, \context $context): array {
        $counts = self::distribution($filter, $scope, $context);
        [$workers, $ready] = self::readiness($filter, $scope, $context);
        return [
            'counts' => $counts,
            'pairs' => array_sum($counts),
            'workers' => $workers,
            'ready' => $ready,
            'hidden' => self::hidden($filter, $scope),
        ];
    }

    /**
     * How many pairs the grid of a filter has: the sum of its shown statuses' counts.
     *
     * @param array $kpis The KPIs of the filter.
     * @param hub_filter $filter The filter.
     * @return int
     */
    public static function pager_count(array $kpis, hub_filter $filter): int {
        $total = 0;
        foreach ($filter->shown_statuses() as $code) {
            $total += $kpis['counts'][$code] ?? 0;
        }
        return $total;
    }

    /**
     * Pairs per effective status (S0 Q4 shape).
     *
     * @param hub_filter $filter The filter.
     * @param hub_scope $scope The viewer's capability cuts.
     * @param \context $context The page context.
     * @return array Effective code => pairs, every code present.
     */
    public static function distribution(hub_filter $filter, hub_scope $scope, \context $context): array {
        global $DB;
        [$basesql, $params] = hub_query::base_sql($filter, $scope, $context);
        $sql = "SELECT b.estatus, COUNT(1) AS pairs
                  FROM ($basesql) b
              GROUP BY b.estatus";
        $counts = array_fill_keys(status::effective_codes(), 0);
        foreach ($DB->get_records_sql_menu($sql, $params) as $code => $pairs) {
            $counts[(int) $code] = (int) $pairs;
        }
        return $counts;
    }

    /**
     * Workers, and how many of them have every pair compliant, due or critical (S0 Q5 shape).
     *
     * A restricted pair is not among those, so a worker whose readiness hangs on evidence the viewer
     * may not see counts as not ready rather than revealing it.
     *
     * @param hub_filter $filter The filter.
     * @param hub_scope $scope The viewer's capability cuts.
     * @param \context $context The page context.
     * @return array [workers, ready].
     */
    public static function readiness(hub_filter $filter, hub_scope $scope, \context $context): array {
        global $DB;
        [$basesql, $params] = hub_query::base_sql($filter, $scope, $context);
        [$readysql, $readyparams] = $DB->get_in_or_equal(self::READY, SQL_PARAMS_NAMED, 'hubready');
        $sql = "SELECT COUNT(1) AS workers, SUM(CASE WHEN x.notready = 0 THEN 1 ELSE 0 END) AS ready
                  FROM (SELECT b.userid, SUM(CASE WHEN b.estatus $readysql THEN 0 ELSE 1 END) AS notready
                          FROM ($basesql) b
                      GROUP BY b.userid) x";
        $record = $DB->get_record_sql($sql, $params + $readyparams);
        return [(int) $record->workers, (int) $record->ready];
    }

    /**
     * The template's workers the viewer may not see: every worker with an active plan from it, outside
     * the viewer's userevidenceview fragment.
     *
     * None of the filter's cuts applies: a cohort, the suspended flag or a search would let the viewer
     * learn something about workers they may not see (who is in a cohort, who is suspended, who bears
     * a name) by watching the count change.
     *
     * @param hub_filter $filter The filter; only its template counts.
     * @param hub_scope $scope The viewer's capability cuts.
     * @return int
     */
    public static function hidden(hub_filter $filter, hub_scope $scope): int {
        global $DB;
        $params = ['hubtemplateid' => $filter->templateid, 'hubactive' => plan::STATUS_ACTIVE] + $scope->viewparams;
        $sql = "SELECT COUNT(DISTINCT p.userid)
                  FROM {" . plan::TABLE . "} p
                  JOIN {user} u ON u.id = p.userid AND u.deleted = 0
                 WHERE p.templateid = :hubtemplateid AND p.status = :hubactive
                       AND NOT (p.userid {$scope->viewsql})";
        return (int) $DB->count_records_sql($sql, $params);
    }

    /**
     * A signature of the template's competencies: core fires no event when one is added or removed,
     * so the key reads it instead of a generation bump.
     *
     * @param int $templateid The template.
     * @return string
     */
    public static function template_signature(int $templateid): string {
        global $DB;
        $record = $DB->get_record_sql(
            "SELECT COUNT(1) AS competencies, MAX(id) AS lastid FROM {competency_templatecomp} WHERE templateid = :templateid",
            ['templateid' => $templateid]
        );
        return $record->competencies . ':' . $record->lastid;
    }
}
