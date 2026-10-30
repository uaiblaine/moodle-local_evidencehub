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

namespace local_evidencehub\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_evidencehub\local\audit;
use local_evidencehub\local\hub_filter;
use local_evidencehub\local\meta;
use local_evidencehub\local\status;

/**
 * Privacy provider.
 *
 * Every row belongs to one worker and lives in that worker's user context, as core_competency's
 * user evidence does: the details of the worker's evidence, the materialised validity, the audit
 * trail of the details and the dossiers issued about the worker. A dossier export request lives
 * in the requester's user context.
 *
 * The user's last filter of the hub is a user preference, exported with the others.
 *
 * Other users appear as actors (who verified, who acted in the trail, who issued a dossier). They
 * find those rows in their own export, but deleting an actor's data leaves the worker's rows
 * alone, as {@see \core_competency\privacy\provider::delete_data_for_user()} does for its
 * usermodified columns: the data is the worker's.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\user_preference_provider {
    /**
     * Describe the stored personal data.
     *
     * @param collection $collection The metadata collection.
     * @return collection The updated collection.
     */
    #[\Override]
    public static function get_metadata(collection $collection): collection {
        $collection->add_subsystem_link('core_competency', [], 'privacy:metadata:core_competency');

        $collection->add_database_table('local_evidencehub_meta', [
            'userevidenceid' => 'privacy:metadata:local_evidencehub_meta:userevidenceid',
            'userid' => 'privacy:metadata:local_evidencehub_meta:userid',
            'typeid' => 'privacy:metadata:local_evidencehub_meta:typeid',
            'issuedate' => 'privacy:metadata:local_evidencehub_meta:issuedate',
            'expirydate' => 'privacy:metadata:local_evidencehub_meta:expirydate',
            'noexpiry' => 'privacy:metadata:local_evidencehub_meta:noexpiry',
            'issuer' => 'privacy:metadata:local_evidencehub_meta:issuer',
            'authcode' => 'privacy:metadata:local_evidencehub_meta:authcode',
            'hours' => 'privacy:metadata:local_evidencehub_meta:hours',
            'verifystatus' => 'privacy:metadata:local_evidencehub_meta:verifystatus',
            'verifiedby' => 'privacy:metadata:local_evidencehub_meta:verifiedby',
            'timeverified' => 'privacy:metadata:local_evidencehub_meta:timeverified',
            'verifynote' => 'privacy:metadata:local_evidencehub_meta:verifynote',
            'timecreated' => 'privacy:metadata:timecreated',
            'timemodified' => 'privacy:metadata:timemodified',
        ], 'privacy:metadata:local_evidencehub_meta');

        $collection->add_database_table('local_evidencehub_status', [
            'userid' => 'privacy:metadata:local_evidencehub_status:userid',
            'competencyid' => 'privacy:metadata:local_evidencehub_status:competencyid',
            'status' => 'privacy:metadata:local_evidencehub_status:status',
            'effectiveexpiry' => 'privacy:metadata:local_evidencehub_status:effectiveexpiry',
            'evidencecount' => 'privacy:metadata:local_evidencehub_status:evidencecount',
            'timechanged' => 'privacy:metadata:local_evidencehub_status:timechanged',
        ], 'privacy:metadata:local_evidencehub_status');

        $collection->add_database_table('local_evidencehub_audit', [
            'userevidenceid' => 'privacy:metadata:local_evidencehub_audit:userevidenceid',
            'relateduserid' => 'privacy:metadata:local_evidencehub_audit:relateduserid',
            'userid' => 'privacy:metadata:local_evidencehub_audit:userid',
            'action' => 'privacy:metadata:local_evidencehub_audit:action',
            'note' => 'privacy:metadata:local_evidencehub_audit:note',
            'timecreated' => 'privacy:metadata:timecreated',
        ], 'privacy:metadata:local_evidencehub_audit');

        $collection->add_database_table('local_evidencehub_dossier', [
            'code' => 'privacy:metadata:local_evidencehub_dossier:code',
            'userid' => 'privacy:metadata:local_evidencehub_dossier:userid',
            'templateid' => 'privacy:metadata:local_evidencehub_dossier:templateid',
            'createdby' => 'privacy:metadata:local_evidencehub_dossier:createdby',
            'snapshot' => 'privacy:metadata:local_evidencehub_dossier:snapshot',
            'revoked' => 'privacy:metadata:local_evidencehub_dossier:revoked',
            'timecreated' => 'privacy:metadata:timecreated',
        ], 'privacy:metadata:local_evidencehub_dossier');

        $collection->add_database_table('local_evidencehub_export', [
            'requestedby' => 'privacy:metadata:local_evidencehub_export:requestedby',
            'templateid' => 'privacy:metadata:local_evidencehub_export:templateid',
            'subjectcount' => 'privacy:metadata:local_evidencehub_export:subjectcount',
            'timecreated' => 'privacy:metadata:timecreated',
        ], 'privacy:metadata:local_evidencehub_export');

        $collection->add_user_preference(hub_filter::PREFERENCE, 'privacy:metadata:preference:hubfilter');

        return $collection;
    }

    /**
     * The user contexts holding the user's data, as the worker or as an actor.
     *
     * @param int $userid The user.
     * @return contextlist
     */
    #[\Override]
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $level = CONTEXT_USER;

        $contextlist->add_from_sql("
            SELECT ctx.id
              FROM {local_evidencehub_meta} m
              JOIN {context} ctx ON ctx.instanceid = m.userid AND ctx.contextlevel = :level
             WHERE m.userid = :userid OR m.verifiedby = :verifiedby
        ", ['level' => $level, 'userid' => $userid, 'verifiedby' => $userid]);

        $contextlist->add_from_sql("
            SELECT ctx.id
              FROM {local_evidencehub_status} s
              JOIN {context} ctx ON ctx.instanceid = s.userid AND ctx.contextlevel = :level
             WHERE s.userid = :userid
        ", ['level' => $level, 'userid' => $userid]);

        $contextlist->add_from_sql("
            SELECT ctx.id
              FROM {local_evidencehub_audit} a
              JOIN {context} ctx ON ctx.instanceid = a.relateduserid AND ctx.contextlevel = :level
             WHERE a.relateduserid = :relateduserid OR a.userid = :userid
        ", ['level' => $level, 'relateduserid' => $userid, 'userid' => $userid]);

        $contextlist->add_from_sql("
            SELECT ctx.id
              FROM {local_evidencehub_dossier} d
              JOIN {context} ctx ON ctx.instanceid = d.userid AND ctx.contextlevel = :level
             WHERE d.userid = :userid OR d.createdby = :createdby
        ", ['level' => $level, 'userid' => $userid, 'createdby' => $userid]);

        $contextlist->add_from_sql("
            SELECT ctx.id
              FROM {local_evidencehub_export} e
              JOIN {context} ctx ON ctx.instanceid = e.requestedby AND ctx.contextlevel = :level
             WHERE e.requestedby = :userid
        ", ['level' => $level, 'userid' => $userid]);

        return $contextlist;
    }

    /**
     * The users with data in a user context: the worker and every actor on the worker's rows.
     *
     * @param userlist $userlist The list to fill.
     * @return void
     */
    #[\Override]
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if ($context->contextlevel != CONTEXT_USER) {
            return;
        }
        $params = ['subject' => $context->instanceid];

        $userlist->add_from_sql('userid', 'SELECT userid FROM {local_evidencehub_meta} WHERE userid = :subject', $params);
        $userlist->add_from_sql('verifiedby', 'SELECT verifiedby FROM {local_evidencehub_meta} WHERE userid = :subject', $params);
        $userlist->add_from_sql('userid', 'SELECT userid FROM {local_evidencehub_status} WHERE userid = :subject', $params);
        $userlist->add_from_sql(
            'relateduserid',
            'SELECT relateduserid FROM {local_evidencehub_audit} WHERE relateduserid = :subject',
            $params
        );
        $userlist->add_from_sql('userid', 'SELECT userid FROM {local_evidencehub_audit} WHERE relateduserid = :subject', $params);
        $userlist->add_from_sql('userid', 'SELECT userid FROM {local_evidencehub_dossier} WHERE userid = :subject', $params);
        $userlist->add_from_sql('createdby', 'SELECT createdby FROM {local_evidencehub_dossier} WHERE userid = :subject', $params);
        $userlist->add_from_sql(
            'requestedby',
            'SELECT requestedby FROM {local_evidencehub_export} WHERE requestedby = :subject',
            $params
        );
    }

    /**
     * Export the user's data: everything about them in their own context, and in another worker's
     * context the rows they acted on.
     *
     * @param approved_contextlist $contextlist The approved contexts.
     * @return void
     */
    #[\Override]
    public static function export_user_data(approved_contextlist $contextlist): void {
        $userid = (int) $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_USER) {
                continue;
            }
            $subjectid = (int) $context->instanceid;
            $own = $subjectid === $userid;
            static::export_details($context, $subjectid, $userid, $own);
            static::export_trail($context, $subjectid, $userid, $own);
            static::export_dossiers($context, $subjectid, $userid, $own);
            if ($own) {
                static::export_statuses($context, $userid);
                static::export_requests($context, $userid);
            }
        }
    }

    /**
     * Export the user's last hub filter, as stored: JSON of the validated filter.
     *
     * @param int $userid The user.
     * @return void
     */
    #[\Override]
    public static function export_user_preferences(int $userid): void {
        $filter = get_user_preferences(hub_filter::PREFERENCE, null, $userid);
        if ($filter !== null) {
            writer::export_user_preference(
                'local_evidencehub',
                hub_filter::PREFERENCE,
                $filter,
                get_string('privacy:metadata:preference:hubfilter', 'local_evidencehub')
            );
        }
    }

    /**
     * Delete everything stored in a user context.
     *
     * @param \context $context The context.
     * @return void
     */
    #[\Override]
    public static function delete_data_for_all_users_in_context(\context $context): void {
        if ($context->contextlevel == CONTEXT_USER) {
            static::delete_worker((int) $context->instanceid);
        }
    }

    /**
     * Delete the user's data in the approved contexts.
     *
     * Only the user's own context is cleared; their actor columns in other workers' rows stay, the
     * way core_competency treats usermodified.
     *
     * @param approved_contextlist $contextlist The approved contexts.
     * @return void
     */
    #[\Override]
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        $userid = (int) $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel == CONTEXT_USER && (int) $context->instanceid === $userid) {
                static::delete_worker($userid);
            }
        }
    }

    /**
     * Delete the data of several users in one context.
     *
     * Only the context's own user is cleared, as in {@see self::delete_data_for_user()}.
     *
     * @param approved_userlist $userlist The approved users and their context.
     * @return void
     */
    #[\Override]
    public static function delete_data_for_users(approved_userlist $userlist): void {
        $context = $userlist->get_context();
        if ($context->contextlevel == CONTEXT_USER && in_array((int) $context->instanceid, $userlist->get_userids())) {
            static::delete_worker((int) $context->instanceid);
        }
    }

    /**
     * Delete every row about one worker, and the worker's own export requests.
     *
     * @param int $userid The worker.
     * @return void
     */
    protected static function delete_worker(int $userid): void {
        global $DB;
        $DB->delete_records('local_evidencehub_audit', ['relateduserid' => $userid]);
        $DB->delete_records('local_evidencehub_meta', ['userid' => $userid]);
        $DB->delete_records('local_evidencehub_status', ['userid' => $userid]);
        $DB->delete_records('local_evidencehub_dossier', ['userid' => $userid]);
        // The dossiers of an export are about other workers and outlive the request's row.
        $DB->set_field_select(
            'local_evidencehub_dossier',
            'exportid',
            null,
            'exportid IN (SELECT id FROM {local_evidencehub_export} WHERE requestedby = :userid)',
            ['userid' => $userid]
        );
        $DB->delete_records('local_evidencehub_export', ['requestedby' => $userid]);
    }

    /**
     * The details of a worker's evidence: all of them for the worker, those verified by the user
     * otherwise.
     *
     * @param \context $context The worker's user context.
     * @param int $subjectid The worker.
     * @param int $userid The user whose data is exported.
     * @param bool $own Whether the user is the worker.
     * @return void
     */
    protected static function export_details(\context $context, int $subjectid, int $userid, bool $own): void {
        global $DB;
        $where = 'm.userid = :subject';
        $params = ['subject' => $subjectid];
        if (!$own) {
            $where .= ' AND m.verifiedby = :userid';
            $params['userid'] = $userid;
        }
        $records = $DB->get_records_sql("
            SELECT m.*, ue.name AS evidencename, t.name AS typename
              FROM {local_evidencehub_meta} m
              JOIN {local_evidencehub_type} t ON t.id = m.typeid
         LEFT JOIN {competency_userevidence} ue ON ue.id = m.userevidenceid
             WHERE $where
          ORDER BY m.id
        ", $params);
        foreach ($records as $record) {
            $name = format_string($record->evidencename ?? '', true, ['context' => $context]);
            writer::with_context($context)->export_data(static::path(
                get_string('privacy:path:details', 'local_evidencehub'),
                $name . ' (' . $record->userevidenceid . ')'
            ), (object) [
                'type' => format_string($record->typename, true, ['context' => \context_system::instance()]),
                'issuedate' => meta::iso_date((int) $record->issuedate),
                'expirydate' => meta::iso_date($record->expirydate === null ? null : (int) $record->expirydate),
                'noexpiry' => transform::yesno($record->noexpiry),
                'issuer' => $record->issuer,
                'authcode' => $record->authcode,
                'hours' => $record->hours,
                'verifystatus' => meta::verify_label((int) $record->verifystatus),
                'verifiedbyyou' => transform::yesno((int) $record->verifiedby === $userid),
                'timeverified' => $record->timeverified ? transform::datetime($record->timeverified) : null,
                'verifynote' => $record->verifynote,
                'timecreated' => transform::datetime($record->timecreated),
                'timemodified' => transform::datetime($record->timemodified),
            ]);
        }
    }

    /**
     * The audit trail of a worker's evidence: all of it for the worker, the user's own actions
     * otherwise.
     *
     * @param \context $context The worker's user context.
     * @param int $subjectid The worker.
     * @param int $userid The user whose data is exported.
     * @param bool $own Whether the user is the worker.
     * @return void
     */
    protected static function export_trail(\context $context, int $subjectid, int $userid, bool $own): void {
        global $DB;
        $conditions = ['relateduserid' => $subjectid];
        if (!$own) {
            $conditions['userid'] = $userid;
        }
        $entries = [];
        foreach ($DB->get_records('local_evidencehub_audit', $conditions, 'timecreated, id') as $record) {
            $entries[] = (object) [
                'userevidenceid' => $record->userevidenceid,
                'action' => audit::label($record->action),
                'byyou' => transform::yesno((int) $record->userid === $userid),
                'note' => $record->note,
                'timecreated' => transform::datetime($record->timecreated),
            ];
        }
        if ($entries) {
            writer::with_context($context)->export_data(
                static::path(get_string('privacy:path:trail', 'local_evidencehub')),
                (object) ['entries' => $entries]
            );
        }
    }

    /**
     * The dossiers issued about a worker: all of them for the worker, those the user issued
     * otherwise.
     *
     * @param \context $context The worker's user context.
     * @param int $subjectid The worker.
     * @param int $userid The user whose data is exported.
     * @param bool $own Whether the user is the worker.
     * @return void
     */
    protected static function export_dossiers(\context $context, int $subjectid, int $userid, bool $own): void {
        global $DB;
        $where = 'd.userid = :subject';
        $params = ['subject' => $subjectid];
        if (!$own) {
            $where .= ' AND d.createdby = :userid';
            $params['userid'] = $userid;
        }
        $records = $DB->get_records_sql("
            SELECT d.*, t.shortname AS templatename
              FROM {local_evidencehub_dossier} d
         LEFT JOIN {competency_template} t ON t.id = d.templateid
             WHERE $where
          ORDER BY d.timecreated, d.id
        ", $params);
        foreach ($records as $record) {
            writer::with_context($context)->export_data(static::path(
                get_string('privacy:path:dossiers', 'local_evidencehub'),
                $record->code
            ), (object) [
                'code' => $record->code,
                'template' => format_string($record->templatename ?? '', true, ['context' => \context_system::instance()]),
                'createdbyyou' => transform::yesno((int) $record->createdby === $userid),
                'snapshot' => json_decode($record->snapshot),
                'revoked' => transform::yesno($record->revoked),
                'timecreated' => transform::datetime($record->timecreated),
            ]);
        }
    }

    /**
     * The validity of each of the worker's competencies.
     *
     * @param \context $context The worker's user context.
     * @param int $userid The worker.
     * @return void
     */
    protected static function export_statuses(\context $context, int $userid): void {
        global $DB;
        $records = $DB->get_records_sql("
            SELECT s.*, c.shortname AS competencyname
              FROM {local_evidencehub_status} s
         LEFT JOIN {competency} c ON c.id = s.competencyid
             WHERE s.userid = :userid
          ORDER BY s.competencyid
        ", ['userid' => $userid]);
        $entries = [];
        foreach ($records as $record) {
            $entries[] = (object) [
                'competency' => format_string($record->competencyname ?? '', true, ['context' => \context_system::instance()]),
                'status' => status::label((int) $record->status),
                'effectiveexpiry' => meta::iso_date($record->effectiveexpiry === null ? null : (int) $record->effectiveexpiry),
                'evidencecount' => (int) $record->evidencecount,
                'timechanged' => transform::datetime($record->timechanged),
            ];
        }
        if ($entries) {
            writer::with_context($context)->export_data(
                static::path(get_string('privacy:path:statuses', 'local_evidencehub')),
                (object) ['competencies' => $entries]
            );
        }
    }

    /**
     * The dossier export requests the user made.
     *
     * @param \context $context The user's own context.
     * @param int $userid The user.
     * @return void
     */
    protected static function export_requests(\context $context, int $userid): void {
        global $DB;
        $entries = [];
        foreach ($DB->get_records('local_evidencehub_export', ['requestedby' => $userid], 'timecreated, id') as $record) {
            $entries[] = (object) [
                'templateid' => (int) $record->templateid,
                'subjectcount' => (int) $record->subjectcount,
                'timecreated' => transform::datetime($record->timecreated),
            ];
        }
        if ($entries) {
            writer::with_context($context)->export_data(
                static::path(get_string('privacy:path:requests', 'local_evidencehub')),
                (object) ['requests' => $entries]
            );
        }
    }

    /**
     * The export path under the plugin's own folder.
     *
     * @param string ...$parts The folders below the plugin's.
     * @return array
     */
    protected static function path(string ...$parts): array {
        return array_merge([get_string('pluginname', 'local_evidencehub')], $parts);
    }
}
