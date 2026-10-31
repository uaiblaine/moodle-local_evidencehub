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

namespace local_evidencehub\external;

use core_competency\plan;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core\exception\invalid_parameter_exception;
use local_evidencehub\local\dossier;
use local_evidencehub\local\dossier_config;
use local_evidencehub\local\hub_access;
use local_evidencehub\local\hub_filter;
use local_evidencehub\local\hub_kpis;
use local_evidencehub\local\hub_matrix;
use local_evidencehub\local\hub_query;
use local_evidencehub\local\hub_scope;
use local_evidencehub\local\meta;
use local_evidencehub\local\policy;
use local_evidencehub\local\status;
use local_evidencehub\local\status_repository;

/**
 * A worker's readiness for a plan template, for a gate system calling with a token.
 *
 * Each competency's status is the effective one for the caller, as on the hub: a competency decided by a
 * sensitive evidence reads restricted (-1), without its expiry, to a caller without
 * local/evidencehub:viewsensitive over the worker, and counts as not met in its own blocking flag. The
 * overall ready flag follows the site's readiness basis ({@see dossier_config::readiness_basis()}): the
 * worker's real readiness by default, as a dossier's snapshot states it, or, under "shown", the readiness
 * of the statuses this caller is shown. Either way it names no status or date. A worker who cannot be
 * found, cannot be read or has no
 * active plan from the template gives one error, so the service is no oracle of who exists.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class get_readiness extends external_api {
    /**
     * The parameters: the template, and exactly one of the worker's id or ID number.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'templateid' => new external_value(PARAM_INT, 'The learning plan template id'),
            'userid' => new external_value(PARAM_INT, 'The worker\'s user id; 0 when useridnumber is given', VALUE_DEFAULT, 0),
            'useridnumber' => new external_value(
                PARAM_RAW,
                'The worker\'s ID number; empty when userid is given',
                VALUE_DEFAULT,
                ''
            ),
        ]);
    }

    /**
     * The readiness of a worker for a template.
     *
     * @param int $templateid The learning plan template.
     * @param int $userid The worker, or 0.
     * @param string $useridnumber The worker's ID number, or ''.
     * @return array ready, computed, competencies.
     * @throws invalid_parameter_exception When both or neither worker identifiers are given.
     * @throws \moodle_exception For a guest, an unknown or unoffered template, or a worker not found.
     * @throws \required_capability_exception Without local/evidencehub:view in the template's context.
     */
    public static function execute(int $templateid, int $userid = 0, string $useridnumber = ''): array {
        global $DB;
        [
            'templateid' => $templateid,
            'userid' => $userid,
            'useridnumber' => $useridnumber,
        ] = self::validate_parameters(self::execute_parameters(), [
            'templateid' => $templateid,
            'userid' => $userid,
            'useridnumber' => $useridnumber,
        ]);
        $useridnumber = trim($useridnumber);
        if (($userid > 0) === ($useridnumber !== '')) {
            throw new invalid_parameter_exception('Give exactly one of userid or useridnumber.');
        }
        if (isguestuser()) {
            throw new \moodle_exception('noguest');
        }

        $contextid = $DB->get_field('competency_template', 'contextid', ['id' => $templateid]);
        $context = $contextid === false ? false : \context::instance_by_id((int) $contextid, IGNORE_MISSING);
        if (!$context) {
            // No oracle of the template's existence: a caller without view in the system context gets the
            // refusal they get for a real template outside their reach, anyone else that of an unreadable one.
            $system = \context_system::instance();
            self::validate_context($system);
            hub_access::require_view($system);
            throw new \moodle_exception('errorhubtemplate', 'local_evidencehub');
        }
        self::validate_context($context);
        hub_access::require_view($context);
        // The template must also be readable there, as the hub offers it.
        $filter = hub_filter::from_values(['templateid' => $templateid, 'statuses' => [], 'suspended' => 1], $context);

        $workerid = self::worker($context, $templateid, $userid, $useridnumber);
        return self::readiness($filter, $context, $workerid);
    }

    /**
     * The worker the parameters name, when the caller may read them and they have an active plan from the template.
     *
     * Every refusal is the same error, so an unknown ID number, an unreadable user and a worker without the
     * plan cannot be told apart. An ID number finds a worker only when the caller is shown ID numbers in the
     * template's context, the rule of the hub's search ({@see hub_query::base_sql()}).
     *
     * @param \context $context The template's context.
     * @param int $templateid The template.
     * @param int $userid The worker's id, or 0.
     * @param string $useridnumber The worker's ID number, or ''.
     * @return int The worker's id.
     * @throws \moodle_exception When the worker is not found.
     */
    private static function worker(\context $context, int $templateid, int $userid, string $useridnumber): int {
        global $DB;
        if ($userid > 0) {
            $users = $DB->get_records('user', ['id' => $userid, 'deleted' => 0], '', 'id');
        } else if (!in_array('idnumber', \core_user\fields::get_identity_fields($context, false), true)) {
            $users = [];
        } else {
            // An ID number two users share names neither.
            $users = $DB->get_records('user', ['idnumber' => $useridnumber, 'deleted' => 0], '', 'id', 0, 2);
        }
        $found = count($users) === 1 ? (int) reset($users)->id : 0;
        $readable = $found
            && has_capability('moodle/competency:userevidenceview', \context_user::instance($found))
            && $DB->record_exists(plan::TABLE, ['userid' => $found, 'templateid' => $templateid, 'status' => plan::STATUS_ACTIVE]);
        if (!$readable) {
            throw new \moodle_exception('errorreadinessnotfound', 'local_evidencehub');
        }
        return $found;
    }

    /**
     * The readiness of a worker, each competency as the caller may see it.
     *
     * @param hub_filter $filter The validated filter of the template, suspended workers included.
     * @param \context $context The template's context.
     * @param int $workerid The worker.
     * @return array ready, computed, competencies.
     */
    private static function readiness(hub_filter $filter, \context $context, int $workerid): array {
        global $DB;
        [$basesql, $params] = hub_query::base_sql($filter, hub_scope::for_user(), $context);
        $sql = "SELECT b.competencyid, b.estatus, b.eexpiry, c.idnumber, s.status, s.timemodified
                  FROM ($basesql) b
                  JOIN {competency} c ON c.id = b.competencyid
             LEFT JOIN {" . status_repository::TABLE . "} s ON s.userid = b.userid AND s.competencyid = b.competencyid
                 WHERE b.userid = :rdworkerid";
        $pairs = [];
        $rs = $DB->get_recordset_sql($sql, $params + ['rdworkerid' => $workerid]);
        foreach ($rs as $record) {
            $pairs[(int) $record->competencyid] = $record;
        }
        $rs->close();

        $computed = null;
        $real = [];
        $shown = [];
        $competencies = [];
        foreach (hub_matrix::columns($filter) as $column) {
            $pair = $pairs[$column->id] ?? null;
            $code = $pair === null ? status::EFFECTIVE_MISSING : (int) $pair->estatus;
            $realcode = ($pair === null || $pair->status === null) ? status::EFFECTIVE_MISSING : (int) $pair->status;
            if ($pair !== null && $pair->timemodified !== null) {
                $computed = max($computed ?? 0, (int) $pair->timemodified);
            }
            $real[] = [$realcode, $column->kind];
            $shown[] = [$code, $column->kind];
            $expiry = $pair === null ? 0 : (int) $pair->eexpiry;
            $competencies[] = [
                'id' => $column->id,
                'shortname' => format_string(
                    $column->shortname,
                    true,
                    ['context' => \context::instance_by_id($column->frameworkcontextid), 'escape' => false]
                ),
                'idnumber' => $pair === null ? '' : (string) $pair->idnumber,
                'status' => $code,
                'expiry' => $expiry ? meta::iso_date($expiry) : null,
                // As the hub counts it: a restricted competency is not met, so the flag hides what the status hides.
                'blocking' => $column->kind === policy::KIND_REGULATORY && !in_array($code, hub_kpis::READY, true),
            ];
        }
        return [
            'ready' => dossier::readiness(
                dossier_config::readiness_basis() === dossier_config::READINESS_SHOWN ? $shown : $real
            ) !== dossier::READINESS_NOTREADY,
            'computed' => $computed,
            'competencies' => $competencies,
        ];
    }

    /**
     * The returns, an allowlist.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'ready' => new external_value(
                PARAM_BOOL,
                'Whether the worker is ready: no competency under a regulatory policy is unmet. The site setting '
                    . 'readinessbasis decides whether that counts the worker\'s real statuses or only those the caller sees'
            ),
            'computed' => new external_value(
                PARAM_INT,
                'When the latest status read was computed, or null when no competency has a status',
                VALUE_REQUIRED,
                null,
                NULL_ALLOWED
            ),
            'competencies' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'The competency id'),
                    'shortname' => new external_value(PARAM_TEXT, 'The competency short name, plain'),
                    'idnumber' => new external_value(PARAM_RAW, 'The competency ID number'),
                    'status' => new external_value(
                        PARAM_INT,
                        'The effective status for the caller: -1 restricted, 0 missing, 1 awaiting verification, '
                            . '2 compliant, 3 due, 4 due and critical, 5 expired'
                    ),
                    'expiry' => new external_value(
                        PARAM_ALPHANUMEXT,
                        'The effective expiry, YYYY-MM-DD, or null for none or when restricted',
                        VALUE_REQUIRED,
                        null,
                        NULL_ALLOWED
                    ),
                    'blocking' => new external_value(PARAM_BOOL, 'Whether this competency is unmet under a regulatory policy'),
                ]),
                'The template\'s competencies, in its order'
            ),
        ]);
    }
}
