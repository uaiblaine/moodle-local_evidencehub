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
 * Creates the plugin's rows directly, bypassing the APIs that write them in production, so a test can seed
 * states those APIs would not produce (verified details, a status row no recomputation wrote).
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
     * A site-level plan template of many competencies with many workers and a status row for most pairs,
     * in bulk: the matrix's size test seeds 200 workers by 25 competencies in one Behat step.
     *
     * Workers are users "Seed 001", "Seed 002", ...; competencies "M01", "M02", ... (ID numbers
     * "<template>-M01", ...) in a framework of their own. The pairs cycle through compliant, due, critical,
     * expired, awaiting verification and missing (no row), with expiry dates around today.
     *
     * @param array $record template (its short name), workers and competencies (counts).
     * @return \core_competency\template
     * @throws coding_exception Without a template name or with no workers or competencies.
     */
    public function create_matrix_seed(array $record): \core_competency\template {
        global $DB;
        $workers = (int) ($record['workers'] ?? 0);
        $count = (int) ($record['competencies'] ?? 0);
        if (empty($record['template']) || $workers < 1 || $count < 1) {
            throw new coding_exception('The template, workers and competencies values are required.');
        }
        $generator = $this->datagenerator->get_plugin_generator('core_competency');
        $framework = $generator->create_framework([
            'shortname' => $record['template'],
            'idnumber' => $record['template'] . '-framework',
        ]);
        $competencyids = [];
        for ($i = 1; $i <= $count; $i++) {
            $code = sprintf('M%02d', $i);
            $competencyids[] = (int) $generator->create_competency([
                'competencyframeworkid' => $framework->get('id'),
                'shortname' => $code,
                'idnumber' => $record['template'] . '-' . $code,
            ])->get('id');
        }
        $template = $this->create_plan_template(['shortname' => $record['template'], 'competencyids' => $competencyids]);

        $today = \local_evidencehub\local\status_repository::site_day(time());
        $shapes = [
            [status::STATUS_VALID, 400],
            [status::STATUS_DUE, 45],
            [status::STATUS_CRITICAL, 10],
            [status::STATUS_EXPIRED, -20],
            [status::STATUS_UNVERIFIED, null],
        ];
        $now = time();
        $rows = [];
        for ($w = 1; $w <= $workers; $w++) {
            $user = $this->datagenerator->create_user(['firstname' => 'Seed', 'lastname' => sprintf('%03d', $w)]);
            $this->create_template_plan(['userid' => $user->id, 'templateid' => $template->get('id')]);
            foreach ($competencyids as $index => $competencyid) {
                $shape = ($w + $index) % (count($shapes) + 1);
                if ($shape === count($shapes)) {
                    // Missing: no row.
                    continue;
                }
                [$code, $days] = $shapes[$shape];
                $rows[] = [
                    'userid' => $user->id,
                    'competencyid' => $competencyid,
                    'status' => $code,
                    'effectiveexpiry' => $days === null ? null : meta::add_days($today, $days),
                    'evidencecount' => 1,
                    'issensitive' => 0,
                    'timechanged' => $now,
                    'timemodified' => $now,
                ];
            }
        }
        $DB->insert_records('local_evidencehub_status', $rows);
        hub_kpis::bump_generation();
        return $template;
    }

    /**
     * A core evidence of a user linked to a competency, with details and their "created" audit row,
     * and the pair recomputed as the plugin's observers would.
     *
     * @param array $record userid and competencyid (required), name, and columns of
     *     local_evidencehub_meta (typeid, issuedate, expirydate, issuer, verifystatus, ...).
     * @return stdClass The meta row.
     * @throws coding_exception Without a userid or a competencyid.
     */
    public function create_evidence(array $record): stdClass {
        if (empty($record['userid']) || empty($record['competencyid'])) {
            throw new coding_exception('The userid and competencyid values are required.');
        }
        $competencygenerator = $this->datagenerator->get_plugin_generator('core_competency');
        $evidence = $competencygenerator->create_user_evidence(array_filter([
            'userid' => $record['userid'],
            'name' => $record['name'] ?? null,
        ]));
        $competencygenerator->create_user_evidence_competency([
            'userevidenceid' => $evidence->get('id'),
            'competencyid' => $record['competencyid'],
        ]);
        $competencyid = (int) $record['competencyid'];
        unset($record['name'], $record['competencyid']);
        $meta = $this->create_meta(['userevidenceid' => $evidence->get('id')] + $record);
        $this->create_audit($meta, (int) $meta->userid);
        \local_evidencehub\local\status_repository::recompute((int) $meta->userid, $competencyid);
        return $meta;
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
