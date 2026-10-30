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
 * Data generator.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_evidencehub\local\audit;
use local_evidencehub\local\export;
use local_evidencehub\local\hub_kpis;
use local_evidencehub\local\meta;
use local_evidencehub\local\status;

/**
 * Creates the plugin's rows directly, before the stages that write them through an API exist.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_evidencehub_generator extends component_generator_base {
    /**
     * Details of a core user evidence, creating the evidence when none is given.
     *
     * @param array $record Columns of local_evidencehub_meta; userid is required.
     * @return stdClass The inserted row.
     * @throws coding_exception Without a userid.
     */
    public function create_meta(array $record): stdClass {
        global $DB;
        if (empty($record['userid'])) {
            throw new coding_exception('The userid value is required.');
        }
        if (empty($record['userevidenceid'])) {
            $evidence = $this->datagenerator->get_plugin_generator('core_competency')
                ->create_user_evidence(['userid' => $record['userid']]);
            $record['userevidenceid'] = $evidence->get('id');
        }
        if (empty($record['typeid'])) {
            $record['typeid'] = $DB->get_field('local_evidencehub_type', 'id', ['shortname' => 'nrcertificate'], MUST_EXIST);
        }
        $now = time();
        $record += [
            'issuedate' => 20260101,
            'expirydate' => 20280101,
            'noexpiry' => 0,
            'verifystatus' => meta::VERIFY_PENDING,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $record['id'] = $DB->insert_record('local_evidencehub_meta', (object) $record);
        return (object) $record;
    }

    /**
     * A materialised validity row.
     *
     * @param array $record Columns of local_evidencehub_status; userid and competencyid are required.
     * @return stdClass The inserted row.
     */
    public function create_status(array $record): stdClass {
        global $DB;
        $now = time();
        if (!array_key_exists('issensitive', $record)) {
            // As the repository stores it: the deciding evidence's type, when one decides.
            $record['issensitive'] = empty($record['metaid']) ? 0 : (int) $DB->get_field_sql(
                "SELECT t.issensitive FROM {local_evidencehub_meta} m JOIN {local_evidencehub_type} t ON t.id = m.typeid
                  WHERE m.id = :metaid",
                ['metaid' => $record['metaid']]
            );
        }
        $record += [
            'status' => status::STATUS_VALID,
            'evidencecount' => 1,
            'timechanged' => $now,
            'timemodified' => $now,
        ];
        $record['id'] = $DB->insert_record('local_evidencehub_status', (object) $record);
        // Written beside the repository, so it retires the hub's cached KPIs the way the repository does.
        hub_kpis::bump_generation();
        return (object) $record;
    }

    /**
     * A plan template in the system or a category, requiring some competencies.
     *
     * @param array $record shortname, and optionally categoryid and competencyids.
     * @return \core_competency\template
     */
    public function create_plan_template(array $record): \core_competency\template {
        $generator = $this->datagenerator->get_plugin_generator('core_competency');
        $context = empty($record['categoryid']) ? context_system::instance() : context_coursecat::instance($record['categoryid']);
        $template = $generator->create_template(['shortname' => $record['shortname'] ?? null, 'contextid' => $context->id]);
        foreach ($record['competencyids'] ?? [] as $competencyid) {
            $generator->create_template_competency(['templateid' => $template->get('id'), 'competencyid' => $competencyid]);
        }
        return $template;
    }

    /**
     * A plan of a user from a template, active unless told otherwise.
     *
     * @param array $record Columns of competency_plan; userid and templateid are required.
     * @return \core_competency\plan
     * @throws coding_exception Without a userid or a templateid.
     */
    public function create_template_plan(array $record): \core_competency\plan {
        if (empty($record['userid']) || empty($record['templateid'])) {
            throw new coding_exception('The userid and templateid values are required.');
        }
        return $this->datagenerator->get_plugin_generator('core_competency')->create_plan($record + [
            'status' => \core_competency\plan::STATUS_ACTIVE,
        ]);
    }

    /**
     * An audit trail entry about a details row.
     *
     * @param stdClass $meta The details row, from create_meta().
     * @param int $actorid Who acted.
     * @param string $action An audit ACTION_* constant.
     * @return stdClass The inserted row.
     */
    public function create_audit(stdClass $meta, int $actorid, string $action = audit::ACTION_CREATED): stdClass {
        global $DB;
        $record = (object) [
            'metaid' => $meta->id,
            'userevidenceid' => $meta->userevidenceid,
            'relateduserid' => $meta->userid,
            'userid' => $actorid,
            'action' => $action,
            'note' => 'Note by ' . $actorid,
            'timecreated' => time(),
        ];
        $record->id = $DB->insert_record('local_evidencehub_audit', $record);
        return $record;
    }

    /**
     * An issued dossier.
     *
     * @param array $record Columns of local_evidencehub_dossier; userid, templateid and createdby are required.
     * @return stdClass The inserted row.
     */
    public function create_dossier(array $record): stdClass {
        global $DB;
        $record += [
            'code' => bin2hex(random_bytes(16)),
            'snapshot' => json_encode(['ready' => true]),
            'revoked' => 0,
            'timecreated' => time(),
        ];
        $record['id'] = $DB->insert_record('local_evidencehub_dossier', (object) $record);
        return (object) $record;
    }

    /**
     * A dossier export request.
     *
     * @param array $record Columns of local_evidencehub_export; requestedby and templateid are required.
     * @return stdClass The inserted row.
     */
    public function create_export(array $record): stdClass {
        global $DB;
        $record += [
            'subjectcount' => 1,
            'status' => export::STATUS_DONE,
            'timecreated' => time(),
        ];
        $record['id'] = $DB->insert_record('local_evidencehub_export', (object) $record);
        return (object) $record;
    }
}
