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

use core_competency\user_evidence;

/**
 * Users holding chosen capabilities, evidence and details, for the tests.
 *
 * Every role is assigned in the system context, so it reaches every worker's user context the
 * way a manager role does.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait scenario {
    /**
     * A new user holding exactly these capabilities on top of the authenticated user's.
     *
     * @param array $capabilities Capability names.
     * @return \stdClass The user.
     */
    protected function user_with(array $capabilities = []): \stdClass {
        $dg = $this->getDataGenerator();
        $user = $dg->create_user();
        $this->grant($user, $capabilities);
        return $user;
    }

    /**
     * Give an existing user more capabilities.
     *
     * @param \stdClass $user The user.
     * @param array $capabilities Capability names.
     * @return void
     */
    protected function grant(\stdClass $user, array $capabilities): void {
        if (!$capabilities) {
            return;
        }
        $system = \context_system::instance();
        $roleid = $this->getDataGenerator()->create_role();
        foreach ($capabilities as $capability) {
            assign_capability($capability, CAP_ALLOW, $roleid, $system->id, true);
        }
        role_assign($roleid, $user->id, $system->id);
        accesslib_clear_all_caches_for_unit_testing();
    }

    /**
     * The id of a seeded type.
     *
     * @param string $shortname The type's shortname.
     * @return int
     */
    protected function type_id(string $shortname): int {
        global $DB;
        return (int) $DB->get_field('local_evidencehub_type', 'id', ['shortname' => $shortname], MUST_EXIST);
    }

    /**
     * A core evidence of a user, created by the generator.
     *
     * @param int $userid The owner.
     * @return user_evidence
     */
    protected function evidence_of(int $userid): user_evidence {
        return $this->getDataGenerator()->get_plugin_generator('core_competency')->create_user_evidence(['userid' => $userid]);
    }

    /**
     * Valid details of a regulatory training certificate, with some values replaced.
     *
     * @param array $overrides Field => value.
     * @return \stdClass
     */
    protected function details_data(array $overrides = []): \stdClass {
        return (object) ($overrides + [
            'typeid' => $this->type_id('nrcertificate'),
            'issuedate' => 20260115,
            'expirydate' => 20280115,
            'noexpiry' => 0,
            'issuer' => 'Training centre',
            'authcode' => '',
            'hours' => 8,
        ]);
    }

    /**
     * The actions of the trail about one meta row, oldest first.
     *
     * @param int $metaid The meta row id.
     * @return array List of [action, actor id].
     */
    protected function trail(int $metaid): array {
        global $DB;
        return array_values(array_map(
            fn(\stdClass $row): array => [$row->action, (int) $row->userid],
            $DB->get_records('local_evidencehub_audit', ['metaid' => $metaid], 'id')
        ));
    }
}
