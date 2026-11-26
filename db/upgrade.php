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

/**
 * Upgrade steps.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the plugin from an older version.
 *
 * @param int $oldversion The version installed.
 * @return bool
 */
function xmldb_local_evidencehub_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026100504) {
        // The status row carries its deciding evidence's sensitive flag, so the hub reads it without
        // joining meta and type for every pair.
        $table = new xmldb_table('local_evidencehub_status');
        $field = new xmldb_field('issensitive', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'evidencecount');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $DB->execute("UPDATE {local_evidencehub_status}
                         SET issensitive = 1
                       WHERE metaid IN (SELECT m.id
                                          FROM {local_evidencehub_meta} m
                                          JOIN {local_evidencehub_type} t ON t.id = m.typeid
                                         WHERE t.issensitive = 1)");
        upgrade_plugin_savepoint(true, 2026100504, 'local', 'evidencehub');
    }

    if ($oldversion < 2026100507) {
        // The hub's context an export was authorised in: a download re-checks the requester's rights there.
        // Older rows keep null, and their download falls back to the template's context.
        $table = new xmldb_table('local_evidencehub_export');
        $field = new xmldb_field('contextid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'templateid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026100507, 'local', 'evidencehub');
    }

    if ($oldversion < 2026100519) {
        // The old list's last filter, kept per reader, is not carried over (data report study, decision 1): the
        // list's filters live in core's report store now, and every reader starts on the report's defaults. By
        // its exact name, so the pairs report's own preference stays.
        $DB->delete_records('user_preferences', ['name' => 'local_evidencehub_hubfilter']);
        upgrade_plugin_savepoint(true, 2026100519, 'local', 'evidencehub');
    }

    if ($oldversion < 2026100523) {
        // The plugin's status entries in core's competency history named a sensitive pair's status and date, and core
        // shows that history to readers without viewsensitive: a pair whose status row is flagged sensitive now has
        // them all rewritten into the neutral entry, without its date, as status_repository::log() writes it. Inline, as
        // an upgrade step must be; status_repository::neutralise_history() does the same for one pair from now on.
        $like = $DB->sql_like('descidentifier', ':identifiers');
        $DB->execute(
            "UPDATE {competency_evidence}
                SET descidentifier = :restricted, desca = NULL
              WHERE desccomponent = :component
                AND $like
                AND usercompetencyid IN (SELECT uc.id
                                           FROM {competency_usercomp} uc
                                           JOIN {local_evidencehub_status} s
                                                ON s.userid = uc.userid AND s.competencyid = uc.competencyid
                                          WHERE s.issensitive = 1)",
            [
                'restricted' => 'evidence_status_restricted',
                'component' => 'local_evidencehub',
                'identifiers' => $DB->sql_like_escape('evidence_status_') . '%',
            ]
        );
        upgrade_plugin_savepoint(true, 2026100523, 'local', 'evidencehub');
    }

    if ($oldversion < 2026100524) {
        // What a prepared ZIP holds, recorded when it is built, so its download re-checks the rights that content
        // needs: unmasked sensitive rows, and files of a sensitive evidence. What an older ZIP holds is unknown, so
        // every row already there is marked as holding both.
        $table = new xmldb_table('local_evidencehub_export');
        $fields = [
            new xmldb_field('hassensitive', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'timeexpires'),
            new xmldb_field('hassensitivefiles', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'hassensitive'),
        ];
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        $DB->execute('UPDATE {local_evidencehub_export} SET hassensitive = 1, hassensitivefiles = 1');
        upgrade_plugin_savepoint(true, 2026100524, 'local', 'evidencehub');
    }

    return true;
}
