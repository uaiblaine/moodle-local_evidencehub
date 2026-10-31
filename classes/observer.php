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

namespace local_evidencehub;

use core_competency\evidence;
use local_evidencehub\event\evidence_details_updated;
use local_evidencehub\event\validity_status_changed;
use local_evidencehub\local\alerts;
use local_evidencehub\local\details;
use local_evidencehub\local\meta;
use local_evidencehub\local\hub_kpis;
use local_evidencehub\local\policy;
use local_evidencehub\local\status_repository;

/**
 * Keeps the details in step with core's evidence when it changes outside this plugin, decides
 * which (user, competency) pairs to recompute when something they depend on changes, and retires
 * the hub's cached KPIs when the set of pairs a template requires may have changed.
 *
 * Every recomputation goes through {@see status_repository}; an observer only picks the pairs. A pair
 * whose user is locked by another recomputation is queued, not lost; any other failure is left to
 * core's event manager, which reports it through debugging() and carries on with the request.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class observer {
    /**
     * An evidence changed in tool_lp, a web service or this plugin's own form: a decided one goes
     * back to pending unless a verifier other than the owner changed it.
     *
     * Idempotent: only a verified or rejected row resets, so the details::save() that follows
     * this plugin's own core update finds the row pending and writes no second reset. A reset asks the
     * verifiers again ({@see alerts::request_verification()}).
     *
     * @param \core\event\competency_user_evidence_updated $event The event.
     * @return void
     */
    public static function user_evidence_updated(\core\event\competency_user_evidence_updated $event): void {
        $meta = details::get((int) $event->objectid);
        if ($meta !== null && details::apply_edit_rule($meta, (int) $event->userid)) {
            alerts::request_verification((int) $meta->id);
        }
        status_repository::recompute_for_userevidence((int) $event->objectid);
    }

    /**
     * An evidence was deleted: its details go with it; the trail stays, append-only.
     *
     * Core unlinked the competencies before this event, and each unlink recomputed its pair
     * ({@see self::evidence_created()}), so nothing is left to recompute here.
     *
     * @param \core\event\competency_user_evidence_deleted $event The event.
     * @return void
     */
    public static function user_evidence_deleted(\core\event\competency_user_evidence_deleted $event): void {
        global $DB;
        $DB->delete_records(details::TABLE, ['userevidenceid' => (int) $event->objectid]);
    }

    /**
     * A core evidence was written into a user competency's history: a link or an unlink of an
     * evidence of prior learning, or a rating, changes what proves that competency.
     *
     * The plugin's own log evidence is skipped: it is written by a recomputation, which would
     * otherwise start another one, and another.
     *
     * @param \core\event\competency_evidence_created $event The event.
     * @return void
     */
    public static function evidence_created(\core\event\competency_evidence_created $event): void {
        global $DB;
        $record = $DB->get_record(evidence::TABLE, ['id' => (int) $event->objectid]);
        if (!$record || $record->desccomponent === 'local_evidencehub') {
            return;
        }
        $linking = $record->desccomponent === 'core_competency' && in_array(
            $record->descidentifier,
            ['evidence_evidenceofpriorlearninglinked', 'evidence_evidenceofpriorlearningunlinked'],
            true
        );
        if ($linking || $record->grade !== null) {
            status_repository::recompute_or_queue((int) $event->relateduserid, (int) $event->other['competencyid']);
        }
    }

    /**
     * A user competency was rated, in a plan, a course or on its own.
     *
     * The rating also lands as an evidence row, so the pair is recomputed twice; a recomputation is
     * idempotent and the second finds nothing to change.
     *
     * @param \core\event\base $event competency_user_competency_rated, _rated_in_plan or _rated_in_course.
     * @return void
     */
    public static function user_competency_rated(\core\event\base $event): void {
        global $DB;
        // The plain rated event names only the user competency; the other two carry the competency.
        $competencyid = $event->other['competencyid']
            ?? $DB->get_field('competency_usercomp', 'competencyid', ['id' => (int) $event->objectid]);
        if ($competencyid) {
            status_repository::recompute_or_queue((int) $event->relateduserid, (int) $competencyid);
        }
    }

    /**
     * The details of an evidence were entered, changed, verified or rejected.
     *
     * @param \core\event\base $event evidence_details_updated, evidence_verified or evidence_rejected.
     * @return void
     */
    public static function details_changed(\core\event\base $event): void {
        status_repository::recompute_for_userevidence((int) $event->other['userevidenceid']);
    }

    /**
     * The details of an evidence were entered or changed: when they are pending, the verifiers are asked.
     *
     * The stored row decides (the event's snapshot): a verifier's own edit leaves a decided row decided.
     *
     * @param evidence_details_updated $event The event.
     * @return void
     */
    public static function details_updated(evidence_details_updated $event): void {
        $meta = $event->get_record_snapshot(details::TABLE, (int) $event->objectid);
        if ($meta && (int) $meta->verifystatus === meta::VERIFY_PENDING) {
            alerts::request_verification((int) $meta->id);
        }
    }

    /**
     * A pair's status changed: the worker is told when it entered due, critical or expired.
     *
     * Registered as an external observer (internal false), so core runs it only once every open
     * transaction committed, and never for a change that rolled back.
     *
     * @param validity_status_changed $event The event.
     * @return void
     */
    public static function validity_status_changed(validity_status_changed $event): void {
        alerts::validity_changed($event);
    }

    /**
     * A competency was deleted: its statuses and its own policy go with it.
     *
     * @param \core\event\competency_deleted $event The event.
     * @return void
     */
    public static function competency_deleted(\core\event\competency_deleted $event): void {
        global $DB;
        $DB->delete_records(status_repository::TABLE, ['competencyid' => (int) $event->objectid]);
        $DB->delete_records(policy::TABLE, ['competencyid' => (int) $event->objectid]);
        hub_kpis::bump_generation();
    }

    /**
     * A framework was deleted: its policies go; each of its competencies' own deleted event cleans the rest.
     *
     * @param \core\event\competency_framework_deleted $event The event.
     * @return void
     */
    public static function competency_framework_deleted(\core\event\competency_framework_deleted $event): void {
        global $DB;
        $DB->delete_records(policy::TABLE, ['frameworkid' => (int) $event->objectid]);
    }

    /**
     * A user was deleted: their statuses go.
     *
     * @param \core\event\user_deleted $event The event.
     * @return void
     */
    public static function user_deleted(\core\event\user_deleted $event): void {
        global $DB;
        $DB->delete_records(status_repository::TABLE, ['userid' => (int) $event->objectid]);
        hub_kpis::bump_generation();
    }

    /**
     * Something the hub's base set depends on changed: a plan's status or template, a cohort's
     * members, a user's name or suspension. The cached KPIs are retired; nothing is recomputed.
     *
     * @param \core\event\base $event A plan, cohort or user event (db/events.php lists them).
     * @return void
     */
    public static function base_set_changed(\core\event\base $event): void {
        hub_kpis::bump_generation();
    }
}
