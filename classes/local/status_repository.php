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

use core_competency\api;
use core_competency\evidence;
use local_evidencehub\event\validity_status_changed;
use local_evidencehub\task\recompute_scope;

/**
 * Reads the candidates of a (user, competency) pair, has the calculator work out its validity and
 * stores the result: the one writer of local_evidencehub_status.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class status_repository {
    /** @var string The table of the materialised statuses. */
    public const string TABLE = 'local_evidencehub_status';

    /** @var int How many pairs one query of a scope reads before they are recomputed. */
    public const int CHUNK = 500;

    /** @var int Seconds a recomputation waits for another one of the same user. */
    private const int LOCK_TIMEOUT = 5;

    /**
     * Work out and store the validity of one pair.
     *
     * Runs under the lock of the user, so two recomputations of the same worker never interleave;
     * the status row is upserted by its (userid, competencyid) key, or deleted when nothing proves
     * the competency ("missing" is never stored). On a status change it writes a log evidence into
     * core's history of the user competency in the same transaction as the row, so a failing log
     * rolls the row back and the next recomputation retries both; it never changes a rating. After
     * the commit it retires the hub's cached KPIs ({@see hub_kpis::bump_generation()}) and fires
     * validity_status_changed: internal observers run at once, even inside a transaction, so they
     * must never see a change that may still roll back. The KPIs are retired only when the row
     * appears, goes, or changes a column they count (status, issensitive): the weekly reconcile
     * rewrites every row, and retiring them for each would empty every node's cached counts. The pairs report's
     * summary reads more (the effective expiry, the deciding evidence, the evidence count), so its own
     * generation also moves when one of those changes; an identical rewrite moves neither.
     *
     * @param int $userid The worker.
     * @param int $competencyid The competency.
     * @return validity|null The stored result, or null when no row is stored.
     * @throws \moodle_exception When another recomputation of the user holds the lock too long.
     */
    public static function recompute(int $userid, int $competencyid): ?validity {
        $lock = \core\lock\lock_config::get_lock_factory('local_evidencehub')->get_lock('user_' . $userid, self::LOCK_TIMEOUT);
        if (!$lock) {
            throw new \moodle_exception('errorstatuslocked', 'local_evidencehub');
        }
        try {
            [$result, $row, $from, $counted, $reportchanged] = self::write($userid, $competencyid);
            if ($counted) {
                // Replaces the pairs report's generation too.
                hub_kpis::bump_generation();
            } else if ($reportchanged) {
                hub_kpis::bump_report_generation();
            }
            if ($row !== null && $from !== (int) $row->status) {
                validity_status_changed::create_from_status($row, $from)->trigger();
            }
            return $result;
        } finally {
            $lock->release();
        }
    }

    /**
     * Recompute one pair now, or, when another recomputation of the user holds the lock, queue it.
     *
     * For observers: the event that asked for it must not lose the recomputation to a busy lock.
     * Any other failure is thrown, for the caller (core's event manager) to handle.
     *
     * @param int $userid The worker.
     * @param int $competencyid The competency.
     * @return void
     */
    public static function recompute_or_queue(int $userid, int $competencyid): void {
        try {
            self::recompute($userid, $competencyid);
        } catch (\moodle_exception $e) {
            if ($e->errorcode !== 'errorstatuslocked' || $e->module !== 'local_evidencehub') {
                throw $e;
            }
            $task = new recompute_scope();
            $task->set_custom_data(['userid' => $userid, 'competencyid' => $competencyid]);
            \core\task\manager::queue_adhoc_task($task, true);
        }
    }

    /**
     * Recompute every competency an evidence is linked to, queueing a pair whose user is locked.
     *
     * @param int $userevidenceid The core evidence.
     * @return void
     */
    public static function recompute_for_userevidence(int $userevidenceid): void {
        global $DB;
        $userid = $DB->get_field('competency_userevidence', 'userid', ['id' => $userevidenceid]);
        if ($userid === false) {
            return;
        }
        $competencyids = $DB->get_fieldset_select(
            'competency_userevidencecomp',
            'competencyid',
            'userevidenceid = :userevidenceid',
            ['userevidenceid' => $userevidenceid]
        );
        foreach ($competencyids as $competencyid) {
            self::recompute_or_queue((int) $userid, (int) $competencyid);
        }
    }

    /**
     * Queue the recomputation of every pair of one competency; never inline, a policy may cover
     * thousands of workers.
     *
     * @param int $competencyid The competency.
     * @return void
     */
    public static function recompute_competency(int $competencyid): void {
        $task = new recompute_scope();
        $task->set_custom_data(['competencyid' => $competencyid]);
        \core\task\manager::queue_adhoc_task($task, true);
    }

    /**
     * Queue the recomputation of every pair of one framework's competencies.
     *
     * @param int $frameworkid The framework.
     * @return void
     */
    public static function recompute_framework(int $frameworkid): void {
        $task = new recompute_scope();
        $task->set_custom_data(['frameworkid' => $frameworkid]);
        \core\task\manager::queue_adhoc_task($task, true);
    }

    /**
     * Recompute every pair that may have a status, of the whole site or of a scope, in chunks.
     *
     * A pair may have a status when an evidence is linked, when a row is stored, or when the user
     * competency is proficient under a policy that counts ratings. The scope is read one chunk at a
     * time, after the last pair done, so the rows the recomputation writes or removes never shift it.
     *
     * @param int|null $competencyid Only this competency, or null.
     * @param int|null $frameworkid Only the competencies of this framework, or null.
     * @return array Counts: pairs (visited), stored (with a row afterwards), failed (any error, traced).
     */
    public static function recompute_pairs(?int $competencyid = null, ?int $frameworkid = null): array {
        global $DB;
        [$pairsql, $params] = self::pairs_sql($competencyid, $frameworkid);
        $sql = "SELECT p.userid, p.competencyid
                  FROM ($pairsql) p
                 WHERE p.userid > :afteruser OR (p.userid = :sameuser AND p.competencyid > :aftercompetency)
              ORDER BY p.userid, p.competencyid";
        $counts = ['pairs' => 0, 'stored' => 0, 'failed' => 0];
        $after = [0, 0];
        do {
            $chunk = [];
            $page = $params + ['afteruser' => $after[0], 'sameuser' => $after[0], 'aftercompetency' => $after[1]];
            $rs = $DB->get_recordset_sql($sql, $page, 0, self::CHUNK);
            foreach ($rs as $pair) {
                $chunk[] = [(int) $pair->userid, (int) $pair->competencyid];
            }
            $rs->close();
            foreach ($chunk as [$userid, $competency]) {
                $counts['pairs']++;
                try {
                    $counts['stored'] += (int) (self::recompute($userid, $competency) !== null);
                } catch (\Throwable $e) {
                    // One pair that fails never stops the others; the next run retries it.
                    $counts['failed']++;
                    self::trace_failure($userid, $competency, $e);
                }
                $after = [$userid, $competency];
            }
        } while (count($chunk) === self::CHUNK);
        return $counts;
    }

    /**
     * Recompute the rows whose status changes by itself by now, oldest first, at most one chunk.
     *
     * A row that fails counts toward the chunk and has its transition put off by an hour, so rows
     * that keep failing never fill every run's chunk and starve the others.
     *
     * @param int $now The current time.
     * @return array Counts: pairs (visited), stored (with a row afterwards), failed (any error, traced).
     */
    public static function recompute_due(int $now): array {
        global $DB;
        $rows = $DB->get_records_select(
            self::TABLE,
            'nexttransition <= :now',
            ['now' => $now],
            'nexttransition ASC, id ASC',
            'id, userid, competencyid',
            0,
            self::CHUNK
        );
        $counts = ['pairs' => 0, 'stored' => 0, 'failed' => 0];
        foreach ($rows as $row) {
            $counts['pairs']++;
            try {
                $counts['stored'] += (int) (self::recompute((int) $row->userid, (int) $row->competencyid) !== null);
            } catch (\Throwable $e) {
                $counts['failed']++;
                self::trace_failure((int) $row->userid, (int) $row->competencyid, $e);
                // Only a row still due: a recomputation that succeeded meanwhile keeps its own value.
                $DB->set_field_select(
                    self::TABLE,
                    'nexttransition',
                    $now + HOURSECS,
                    'id = :id AND nexttransition <= :now',
                    ['id' => $row->id, 'now' => $now]
                );
            }
        }
        return $counts;
    }

    /**
     * Report a pair whose recomputation failed in a task.
     *
     * @param int $userid The worker.
     * @param int $competencyid The competency.
     * @param \Throwable $e What failed.
     * @return void
     */
    private static function trace_failure(int $userid, int $competencyid, \Throwable $e): void {
        mtrace("Recomputing the validity of user $userid in competency $competencyid failed: " . $e->getMessage());
    }

    /**
     * The (userid, competencyid) pairs that may have a status, as a UNION of three portable queries.
     *
     * @param int|null $competencyid Only this competency, or null.
     * @param int|null $frameworkid Only the competencies of this framework, or null.
     * @return array [sql, params].
     */
    private static function pairs_sql(?int $competencyid, ?int $frameworkid): array {
        $params = ['defaultkind' => policy::KIND_TECHNICAL, 'regulatory' => policy::KIND_REGULATORY];
        $filters = [];
        foreach (['linked' => 'uec', 'stored' => 's', 'rated' => 'uc'] as $name => $alias) {
            if ($competencyid !== null) {
                $filters[$name] = " AND $alias.competencyid = :{$name}competency";
                $params[$name . 'competency'] = $competencyid;
            } else if ($frameworkid !== null) {
                $filters[$name] = " AND $alias.competencyid IN (SELECT {$name}c.id FROM {competency} {$name}c
                    WHERE {$name}c.competencyframeworkid = :{$name}framework)";
                $params[$name . 'framework'] = $frameworkid;
            } else {
                $filters[$name] = '';
            }
        }
        $sql = "SELECT ue.userid, uec.competencyid
                  FROM {competency_userevidencecomp} uec
                  JOIN {competency_userevidence} ue ON ue.id = uec.userevidenceid
                 WHERE 1 = 1{$filters['linked']}
                 UNION
                SELECT s.userid, s.competencyid
                  FROM {" . self::TABLE . "} s
                 WHERE 1 = 1{$filters['stored']}
                 UNION
                SELECT uc.userid, uc.competencyid
                  FROM {competency_usercomp} uc
                  JOIN {competency} c ON c.id = uc.competencyid
             LEFT JOIN {" . policy::TABLE . "} pc ON pc.frameworkid = c.competencyframeworkid AND pc.competencyid = c.id
             LEFT JOIN {" . policy::TABLE . "} pf ON pf.frameworkid = c.competencyframeworkid AND pf.competencyid = 0
                 WHERE uc.proficiency = 1
                       AND COALESCE(pc.kind, pf.kind, :defaultkind) <> :regulatory{$filters['rated']}";
        return [$sql, $params];
    }

    /**
     * Compute and write one pair, and on a change its log entry, inside one transaction.
     *
     * @param int $userid The worker.
     * @param int $competencyid The competency.
     * @return array [validity or null, the stored row or null, the previous status or null, whether the
     *     hub's KPIs may count the pair otherwise now, whether the report summary's figures may differ now].
     * @throws \Throwable Whatever failed; the transaction is rolled back first.
     */
    private static function write(int $userid, int $competencyid): array {
        global $DB;
        $transaction = $DB->start_delegated_transaction();
        try {
            $written = self::write_in($userid, $competencyid);
            $transaction->allow_commit();
            return $written;
        } catch (\Throwable $e) {
            $transaction->rollback($e);
        }
    }

    /**
     * The body of write(), inside its transaction.
     *
     * The KPIs read a row's presence, its status and its issensitive ({@see hub_query::base_sql()}), so
     * only a change to one of those is reported as counted.
     *
     * @param int $userid The worker.
     * @param int $competencyid The competency.
     * @return array [validity or null, the stored row or null, the previous status or null, whether the
     *     hub's KPIs may count the pair otherwise now, whether the report summary's figures may differ now].
     */
    private static function write_in(int $userid, int $competencyid): array {
        global $DB;
        $now = \core\di::get(\core\clock::class)->time();
        $existing = $DB->get_record(self::TABLE, ['userid' => $userid, 'competencyid' => $competencyid]);
        $from = $existing ? (int) $existing->status : null;
        $frameworkid = $DB->get_field('competency', 'competencyframeworkid', ['id' => $competencyid]);
        $policy = null;
        $result = null;
        if ($frameworkid !== false) {
            $policy = policy::resolve($competencyid, (int) $frameworkid);
            [$candidates, $proficient] = self::candidates($userid, $competencyid, $policy);
            $result = validity_calculator::calculate($candidates, $policy, self::site_day($now), $proficient);
        }
        if ($result === null || $result->status === null) {
            if ($existing) {
                $DB->delete_records(self::TABLE, ['id' => $existing->id]);
            }
            return [null, null, $from, $existing !== false, $existing !== false];
        }

        $row = (object) [
            'userid' => $userid,
            'competencyid' => $competencyid,
            'status' => $result->status,
            'metaid' => $result->metaid,
            'effectiveexpiry' => $result->effectiveexpiry,
            'evidencecount' => $result->evidencecount,
            'issensitive' => self::deciding_is_sensitive($result->metaid),
            'nexttransition' => $result->nexttransition === null ? null : self::site_midnight($result->nexttransition),
            'timemodified' => $now,
        ];
        $changed = $from !== $result->status;
        // A new row is always a change ($from is null), so issensitive is read only from a stored row.
        $counted = $changed || (int) $existing->issensitive !== $row->issensitive;
        // What the report summary reads beyond the KPIs' columns; a new row has no stored values to compare.
        $reportchanged = $counted
            || self::nullable_int($existing->effectiveexpiry) !== self::nullable_int($row->effectiveexpiry)
            || self::nullable_int($existing->metaid) !== self::nullable_int($row->metaid)
            || (int) $existing->evidencecount !== (int) $row->evidencecount;
        if ($existing) {
            $row->id = (int) $existing->id;
            // When the status value last changed, not when the row was last computed.
            if ($from !== $result->status) {
                $row->timechanged = $now;
            }
            $DB->update_record(self::TABLE, $row);
        } else {
            $row->timechanged = $now;
            $row->id = $DB->insert_record(self::TABLE, $row);
        }
        if ($changed) {
            self::log($row, $policy, $result);
        }
        return [$result, $row, $from, $counted, $reportchanged];
    }

    /**
     * A stored or computed integer that may be null, for comparing the two.
     *
     * @param mixed $value The value.
     * @return int|null
     */
    private static function nullable_int(mixed $value): ?int {
        return $value === null ? null : (int) $value;
    }

    /**
     * Whether the deciding evidence's type is sensitive, as the status row stores it for the hub.
     *
     * Stored, not joined: the hub reads it for every pair of a template. A change to a type's
     * issensitive must therefore recompute that type's pairs.
     *
     * @param int|null $metaid The deciding evidence's details, or null when none decides.
     * @return int 1 or 0.
     */
    private static function deciding_is_sensitive(?int $metaid): int {
        global $DB;
        if ($metaid === null) {
            return 0;
        }
        $sql = "SELECT t.issensitive
                  FROM {" . details::TABLE . "} m
                  JOIN {local_evidencehub_type} t ON t.id = m.typeid
                 WHERE m.id = :metaid";
        return (int) !empty($DB->get_field_sql($sql, ['metaid' => $metaid]));
    }

    /**
     * The candidates of one pair under its policy: every linked evidence and, when the policy
     * counts ratings and the user competency is proficient, its latest rating.
     *
     * @param int $userid The worker.
     * @param int $competencyid The competency.
     * @param \stdClass $policy The effective policy.
     * @return array [list of candidate, whether the user competency is proficient].
     */
    private static function candidates(int $userid, int $competencyid, \stdClass $policy): array {
        global $DB;
        $candidates = [];
        $sql = "SELECT ue.id AS userevidenceid, m.id AS metaid, m.issuedate, m.expirydate, m.hours, m.verifystatus
                  FROM {competency_userevidencecomp} uec
                  JOIN {competency_userevidence} ue ON ue.id = uec.userevidenceid
             LEFT JOIN {" . details::TABLE . "} m ON m.userevidenceid = ue.id
                 WHERE ue.userid = :userid AND uec.competencyid = :competencyid";
        foreach ($DB->get_records_sql($sql, ['userid' => $userid, 'competencyid' => $competencyid]) as $record) {
            $complete = $record->metaid !== null;
            $candidates[] = new candidate(
                source: candidate::SOURCE_EVIDENCE,
                metaid: $complete ? (int) $record->metaid : null,
                userevidenceid: (int) $record->userevidenceid,
                issue: $complete ? (int) $record->issuedate : null,
                expiry: ($complete && $record->expirydate !== null) ? (int) $record->expirydate : null,
                complete: $complete,
                verified: $complete && (int) $record->verifystatus === meta::VERIFY_VERIFIED,
                hours: ($complete && $record->hours !== null) ? (float) $record->hours : null,
                rejected: $complete && (int) $record->verifystatus === meta::VERIFY_REJECTED,
            );
        }

        $sql = "SELECT uc.id, uc.proficiency,
                       (SELECT MAX(e.timecreated)
                          FROM {" . evidence::TABLE . "} e
                         WHERE e.usercompetencyid = uc.id AND e.grade IS NOT NULL) AS ratedat
                  FROM {competency_usercomp} uc
                 WHERE uc.userid = :userid AND uc.competencyid = :competencyid";
        $usercompetency = $DB->get_record_sql($sql, ['userid' => $userid, 'competencyid' => $competencyid]);
        $proficient = $usercompetency && !empty($usercompetency->proficiency);
        // The calculator decides which kinds count it: a regulatory policy never does.
        if ($proficient && $usercompetency->ratedat !== null) {
            $issue = self::site_day((int) $usercompetency->ratedat);
            $months = (int) $policy->cyclemonths;
            $candidates[] = new candidate(
                source: candidate::SOURCE_RATING,
                metaid: null,
                userevidenceid: null,
                issue: $issue,
                expiry: $months > 0 ? meta::add_months($issue, $months) : null,
                complete: true,
                verified: true,
            );
        }
        return [$candidates, $proficient];
    }

    /**
     * Write core's log evidence of a status change, inside the row's transaction.
     *
     * core_competency's add_evidence() throws while competencies are disabled: the row then rolls
     * back with it and waits for a recomputation that can log it.
     *
     * @param \stdClass $row The stored row.
     * @param \stdClass $policy The effective policy.
     * @param validity $result The result.
     * @return void
     */
    private static function log(\stdClass $row, \stdClass $policy, validity $result): void {
        $to = (int) $row->status;
        // A literal key per status: the descidentifier is read back through get_string().
        $identifier = match ($to) {
            status::STATUS_UNVERIFIED => 'evidence_status_unverified',
            status::STATUS_VALID => 'evidence_status_valid',
            status::STATUS_DUE => 'evidence_status_due',
            status::STATUS_CRITICAL => 'evidence_status_critical',
            status::STATUS_EXPIRED => 'evidence_status_expired',
        };
        // Expired under a review policy asks core for a review; the rating itself never changes.
        $recommend = $to === status::STATUS_EXPIRED && (int) $policy->onexpiry === policy::ONEXPIRY_REVIEW;
        api::add_evidence(
            (int) $row->userid,
            (int) $row->competencyid,
            \context_user::instance((int) $row->userid),
            evidence::ACTION_LOG,
            $identifier,
            'local_evidencehub',
            meta::iso_date($result->effectiveexpiry),
            $recommend
        );
    }

    /**
     * The calendar day an instant falls on in the site's timezone, never the user's.
     *
     * @param int $timestamp The instant.
     * @return int The YYYYMMDD day.
     */
    public static function site_day(int $timestamp): int {
        $moment = (new \DateTimeImmutable('@' . $timestamp))->setTimezone(\core_date::get_server_timezone_object());
        return (int) $moment->format('Ymd');
    }

    /**
     * Midnight of a calendar day in the site's timezone.
     *
     * @param int $date A valid YYYYMMDD day.
     * @return int The timestamp.
     */
    public static function site_midnight(int $date): int {
        $midnight = new \DateTimeImmutable(meta::iso_date($date) . ' 00:00:00', \core_date::get_server_timezone_object());
        return $midnight->getTimestamp();
    }
}
