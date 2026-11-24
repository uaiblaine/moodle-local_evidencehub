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
 * The pairs report's cached figures and the generations that retire them: the readiness statement, the hidden
 * workers' count, the cache keys and the store.
 *
 * The figures (the summary, and the rows' total, hidden workers and facet counts) are entries of the application
 * cache reportsummary, keyed per reader and criteria ({@see self::report_summary_key()}, {@see self::report_rows_key()})
 * and retired by a generation: a random value in the cache reportgeneration, replaced whenever something the figures
 * read may have changed. Never a time to live: a lost generation (a cache purge) just starts a new one. A second
 * generation, hubgeneration, is replaced only when the pair set itself or a status changes
 * ({@see self::bump_generation()}), and every replacement of it replaces the report's too.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class hub_kpis {
    /** @var bool Whether the end of this request already replaces the generations a second time. */
    private static bool $deferred = false;

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
     * Retire every cached figure: a status row was written or deleted, or the pair set changed.
     *
     * @return void
     */
    public static function bump_generation(): void {
        \cache::make('local_evidencehub', 'hubgeneration')->set('generation', self::new_generation());
        self::bump_report_generation();
    }

    /**
     * Whether a replacement of the generations is waiting for the end of the request.
     *
     * @return bool
     */
    public static function has_deferred_replacement(): bool {
        return self::$deferred;
    }

    /**
     * Replace both generations once the request's transactions are over: the shutdown function registered by
     * {@see self::defer_when_in_transaction()}.
     *
     * @return void
     */
    public static function replace_after_transaction(): void {
        self::$deferred = false;
        \cache::make('local_evidencehub', 'hubgeneration')->set('generation', self::new_generation());
        \cache::make('local_evidencehub', 'reportgeneration')->set('generation', self::new_generation());
    }

    /**
     * Register one more replacement of both generations for the end of the request when a transaction is open.
     *
     * The replacement made now lets a reader inside the transaction see fresh keys, but a reader arriving after it
     * and before the commit computes the figures from the rows as they were, and stores them under the new
     * generation: stale until the next replacement. Moodle has no commit callback, so the second replacement waits
     * for the shutdown, which runs once the request's transactions have ended. One registration serves every
     * replacement of the request.
     *
     * @return void
     */
    private static function defer_when_in_transaction(): void {
        global $DB;
        if (self::$deferred || !$DB->is_transaction_started()) {
            return;
        }
        self::$deferred = true;
        \core_shutdown_manager::register_function([self::class, 'replace_after_transaction']);
    }

    /**
     * The current generation of the pairs report's summary, starting one when the cache holds none.
     *
     * @return string
     */
    public static function report_generation(): string {
        $cache = \cache::make('local_evidencehub', 'reportgeneration');
        $generation = $cache->get('generation');
        if ($generation === false) {
            $generation = self::new_generation();
            $cache->set('generation', $generation);
        }
        return $generation;
    }

    /**
     * Retire every cached report summary alone: something the summary reads changed that the KPIs do not count
     * (a status row's expiry, deciding evidence or evidence count, or an evidence's details).
     *
     * @return void
     */
    public static function bump_report_generation(): void {
        \cache::make('local_evidencehub', 'reportgeneration')->set('generation', self::new_generation());
        self::defer_when_in_transaction();
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
     * Workers, and how many of them have every pair compliant, due or critical, of any set of pairs: the pairs
     * report's summary reads it over its rows ({@see reportengine::summary()}).
     *
     * A restricted pair is not among those, so a worker whose readiness hangs on evidence the viewer may not see
     * counts as not ready rather than revealing it.
     *
     * @param string $basesql A SELECT of one row per pair with the columns userid and estatus.
     * @param array $params Its parameters; none may be named hubready*.
     * @return array [workers, ready].
     */
    public static function readiness_of(string $basesql, array $params): array {
        global $DB;
        [$readysql, $readyparams] = $DB->get_in_or_equal(self::READY, SQL_PARAMS_NAMED, 'hubready');
        $sql = "SELECT COUNT(1) AS workers, SUM(CASE WHEN x.notready = 0 THEN 1 ELSE 0 END) AS ready
                  FROM (SELECT b.userid, SUM(CASE WHEN b.estatus $readysql THEN 0 ELSE 1 END) AS notready
                          FROM ($basesql) b
                      GROUP BY b.userid) x";
        $record = $DB->get_record_sql($sql, $params + $readyparams);
        return [(int) $record->workers, (int) $record->ready];
    }

    /**
     * The template's workers the viewer may not see: every worker with an active plan from it, outside the viewer's
     * userevidenceview fragment.
     *
     * It takes the template alone, so no criterion a reader chose can reach it: a cohort, the suspended flag or a
     * search would let the viewer learn something about workers they may not see (who is in a cohort, who is
     * suspended, who bears a name) by watching the count change.
     *
     * @param int $templateid The template.
     * @param hub_scope $scope The viewer's capability cuts.
     * @return int
     */
    public static function hidden_workers(int $templateid, hub_scope $scope): int {
        global $DB;
        $params = ['hubtemplateid' => $templateid, 'hubactive' => plan::STATUS_ACTIVE] + $scope->viewparams;
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

    /**
     * The cache key of the pairs report's summary for a reader: what decides its figures, and the generation.
     *
     * The summary ignores the scope and the status filter ({@see reportengine::summary()}), so neither is in the
     * key and a reader who picks a status reuses the figures. Every other stored filter value is, as a hash, and
     * so are the suspended flag and today, which moves the "expiring" window. The report generation retires it.
     * A search is never cached.
     *
     * @param \context $context The hub context.
     * @param int $templateid The template.
     * @param hub_scope $scope The reader's capability cuts.
     * @param array $filtervalues The reader's stored filter values.
     * @param bool $suspended Whether suspended accounts are listed.
     * @param int $today Today, YYYYMMDD.
     * @param array $cohorts The cohort ids the report offers this reader.
     * @return string
     */
    public static function report_summary_key(
        \context $context,
        int $templateid,
        hub_scope $scope,
        array $filtervalues,
        bool $suspended,
        int $today,
        array $cohorts = []
    ): string {
        $values = array_filter(
            $filtervalues,
            static fn(string $name): bool => !str_starts_with($name, reportengine::STATUS_FILTER . '_'),
            ARRAY_FILTER_USE_KEY
        );
        return self::report_key('report', $context, $templateid, $scope, $values, $suspended, '', $cohorts, $today);
    }

    /**
     * The cache key of the pairs report's total and facet counts for a reader: what decides which pairs the rows
     * list, and the generation.
     *
     * Unlike the summary's, every stored filter value is in it, the status filter's included, and so is the scope:
     * both cut the rows. The page, its size and the sort are not: they order and slice the same rows. Today is,
     * because the valid-until filter and facet count from it. A search is never cached ({@see reportengine::rows()}).
     *
     * @param \context $context The hub context.
     * @param int $templateid The template.
     * @param hub_scope $scope The reader's capability cuts.
     * @param array $filtervalues The reader's stored filter values, every filter's.
     * @param bool $suspended Whether suspended accounts are listed.
     * @param string $rowscope The rows' scope ({@see reportengine::SCOPES}).
     * @param int $today Today, YYYYMMDD.
     * @param array $cohorts The cohort ids the report offers this reader.
     * @return string
     */
    public static function report_rows_key(
        \context $context,
        int $templateid,
        hub_scope $scope,
        array $filtervalues,
        bool $suspended,
        string $rowscope,
        int $today,
        array $cohorts
    ): string {
        return self::report_key('rows', $context, $templateid, $scope, $filtervalues, $suspended, $rowscope, $cohorts, $today);
    }

    /**
     * A key of the reportsummary cache: one reader's report under one set of criteria, at the current generation.
     *
     * The reader's scope key carries the viewer and both capability fragments, so an entry is never another reader's,
     * nor this reader's under other rights. The cohorts offered are in it because a stored cohort value applies only
     * while the report offers that cohort ({@see \local_evidencehub\reportbuilder\local\filters\cohort}), and a
     * cohort's visibility changes with no event the generation follows.
     *
     * @param string $kind What the entry holds: 'report' for the summary, 'rows' for the total and the facets.
     * @param \context $context The hub context.
     * @param int $templateid The template.
     * @param hub_scope $scope The reader's capability cuts.
     * @param array $values The stored filter values that decide the entry.
     * @param bool $suspended Whether suspended accounts are listed.
     * @param string $rowscope The rows' scope, '' for an entry that ignores it.
     * @param array $cohorts The cohort ids offered.
     * @param int $today Today, YYYYMMDD.
     * @return string
     */
    private static function report_key(
        string $kind,
        \context $context,
        int $templateid,
        hub_scope $scope,
        array $values,
        bool $suspended,
        string $rowscope,
        array $cohorts,
        int $today
    ): string {
        ksort($values);
        $cohorts = array_map('intval', $cohorts);
        sort($cohorts);
        return sha1(json_encode([
            $kind,
            $context->id,
            $templateid,
            $scope->key(),
            sha1(json_encode($values)),
            (int) $suspended,
            $rowscope,
            $cohorts,
            $today,
            self::template_signature($templateid),
            self::report_generation(),
        ]));
    }

    /**
     * Figures from the reportsummary cache under a key, computed and stored when it holds none.
     *
     * @param string $key A key from {@see self::report_summary_key()}.
     * @param callable $compute Returns the figures: an array of scalars and arrays.
     * @return array The figures.
     */
    public static function remember(string $key, callable $compute): array {
        $figures = self::recall($key);
        if ($figures === null) {
            $figures = $compute();
            self::keep($key, $figures);
        }
        return $figures;
    }

    /**
     * What the reportsummary cache holds under a key.
     *
     * @param string $key A key from {@see self::report_summary_key()} or {@see self::report_rows_key()}.
     * @return array|null The entry, or null when it holds none.
     */
    public static function recall(string $key): ?array {
        $entry = \cache::make('local_evidencehub', 'reportsummary')->get($key);
        return is_array($entry) ? $entry : null;
    }

    /**
     * Store an entry in the reportsummary cache: codes, ids and counts only, never a label or a name.
     *
     * @param string $key A key from {@see self::report_summary_key()} or {@see self::report_rows_key()}.
     * @param array $entry An array of scalars and arrays.
     * @return void
     */
    public static function keep(string $key, array $entry): void {
        \cache::make('local_evidencehub', 'reportsummary')->set($key, $entry);
    }
}
