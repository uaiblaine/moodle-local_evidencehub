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

namespace local_evidencehub\tests;

use core_competency\plan;
use local_evidencehub\local\hub_filter;
use local_evidencehub\local\hub_query;
use local_evidencehub\local\hub_scope;
use local_evidencehub\local\policy;

/**
 * Plan templates, workers with plans from them, status rows and viewers, for the hub's tests.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait hub_scenario {
    /**
     * A framework with competencies, in a context.
     *
     * @param int $count How many competencies.
     * @param \context|null $context The framework's context; the system when null.
     * @return array List of competency ids, by short name C1, C2, ...
     */
    protected function competencies(int $count, ?\context $context = null): array {
        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $generator->create_framework(['contextid' => ($context ?? \context_system::instance())->id]);
        $ids = [];
        for ($i = 1; $i <= $count; $i++) {
            $competency = $generator->create_competency([
                'competencyframeworkid' => $framework->get('id'),
                'shortname' => 'C' . $i,
                'idnumber' => 'C' . $i . '-' . $framework->get('id'),
            ]);
            $ids[] = (int) $competency->get('id');
        }
        return $ids;
    }

    /**
     * A plan template requiring some competencies.
     *
     * @param array $competencyids The competencies.
     * @param \context|null $context The template's context; the system when null.
     * @return int The template id.
     */
    protected function template(array $competencyids, ?\context $context = null): int {
        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $template = $generator->create_template(['contextid' => ($context ?? \context_system::instance())->id]);
        foreach ($competencyids as $competencyid) {
            $generator->create_template_competency(['templateid' => $template->get('id'), 'competencyid' => $competencyid]);
        }
        return (int) $template->get('id');
    }

    /**
     * A worker with a plan from a template.
     *
     * @param int $templateid The template.
     * @param array $user User fields.
     * @param int $planstatus The plan's status; active by default.
     * @return \stdClass The user.
     */
    protected function worker(int $templateid, array $user = [], int $planstatus = plan::STATUS_ACTIVE): \stdClass {
        $worker = $this->getDataGenerator()->create_user($user);
        $this->getDataGenerator()->get_plugin_generator('local_evidencehub')->create_template_plan([
            'userid' => $worker->id,
            'templateid' => $templateid,
            'status' => $planstatus,
        ]);
        return $worker;
    }

    /**
     * A status row, decided by an evidence of a type when one is named.
     *
     * @param int $userid The worker.
     * @param int $competencyid The competency.
     * @param int $status A STATUS_* constant.
     * @param int|null $expiry The effective expiry, YYYYMMDD.
     * @param string|null $type The shortname of the deciding evidence's type; no deciding evidence when null.
     * @return void
     */
    protected function status_row(int $userid, int $competencyid, int $status, ?int $expiry = null, ?string $type = null): void {
        global $DB;
        $generator = $this->getDataGenerator()->get_plugin_generator('local_evidencehub');
        $metaid = null;
        if ($type !== null) {
            $typeid = (int) $DB->get_field('local_evidencehub_type', 'id', ['shortname' => $type], MUST_EXIST);
            $metaid = (int) $generator->create_meta(['userid' => $userid, 'typeid' => $typeid, 'issuer' => 'Issuer ' . $type])->id;
        }
        $generator->create_status([
            'userid' => $userid,
            'competencyid' => $competencyid,
            'status' => $status,
            'effectiveexpiry' => $expiry,
            'metaid' => $metaid,
        ]);
    }

    /**
     * Put a competency under a regulatory policy, saved as an administrator; the current user is restored.
     *
     * @param int $competencyid The competency.
     * @return void
     */
    protected function regulatory(int $competencyid): void {
        global $DB, $USER;
        $previous = $USER;
        $this->setAdminUser();
        policy::save((object) [
            'frameworkid' => (int) $DB->get_field('competency', 'competencyframeworkid', ['id' => $competencyid], MUST_EXIST),
            'competencyid' => $competencyid,
            'kind' => policy::KIND_REGULATORY,
            'requireverified' => 0,
            'requireproficiency' => 0,
            'minhours' => null,
            'alert1' => 60,
            'alert2' => 30,
            'gracedays' => 0,
            'cyclemonths' => 0,
            'onexpiry' => policy::ONEXPIRY_LOG,
        ]);
        $this->setUser($previous->id ? $previous : null);
    }

    /**
     * A user holding capabilities through a role assigned in a context.
     *
     * @param array $capabilities Capability names.
     * @param \context|null $context Where the role is assigned; the system when null.
     * @return \stdClass The user.
     */
    protected function viewer(array $capabilities, ?\context $context = null): \stdClass {
        $generator = $this->getDataGenerator();
        $user = $generator->create_user();
        $context ??= \context_system::instance();
        $roleid = $generator->create_role();
        foreach ($capabilities as $capability) {
            assign_capability($capability, CAP_ALLOW, $roleid, $context->id, true);
        }
        role_assign($roleid, $user->id, $context->id);
        accesslib_clear_all_caches_for_unit_testing();
        return $user;
    }

    /**
     * Every pair of the hub's base set under a filter, as "userid:competencyid" => effective status, in the order of
     * its "valid until" key, then the pair's key: the order the pairs report's default sort gives.
     *
     * @param \context $context The context.
     * @param hub_filter $filter The filter.
     * @return array In that order.
     */
    protected function base_pairs(\context $context, hub_filter $filter): array {
        global $DB;
        [$sql, $params] = hub_query::base_sql($filter, hub_scope::for_user(), $context);
        $pairs = [];
        $records = $DB->get_recordset_sql("SELECT b.userid, b.competencyid, b.estatus
                                             FROM ($sql) b
                                         ORDER BY b.eexpiry, b.userid, b.competencyid", $params);
        foreach ($records as $row) {
            $this->assertArrayNotHasKey($row->userid . ':' . $row->competencyid, $pairs, 'A pair came twice.');
            $pairs[$row->userid . ':' . $row->competencyid] = (int) $row->estatus;
        }
        $records->close();
        return $pairs;
    }

    /**
     * A filter on a template, validated in the system context.
     *
     * @param int $templateid The template.
     * @param array $values More values, as hub_filter::from_values() reads them.
     * @param \context|null $context The page context; the system when null.
     * @return hub_filter
     */
    protected function filter(int $templateid, array $values = [], ?\context $context = null): hub_filter {
        return hub_filter::from_values($values + ['templateid' => $templateid], $context ?? \context_system::instance());
    }

    /**
     * The "userid:competencyid" key of a pair.
     *
     * @param \stdClass|int $user The user or its id.
     * @param int $competencyid The competency.
     * @return string
     */
    protected function pair(\stdClass|int $user, int $competencyid): string {
        return (is_object($user) ? $user->id : $user) . ':' . $competencyid;
    }
}
