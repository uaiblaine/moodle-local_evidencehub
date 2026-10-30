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
 * Behat page resolution for local_evidencehub.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

/**
 * Named pages of the plugin, for "I am on the ... page" steps.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_local_evidencehub extends behat_base {
    /**
     * The URL of a page about one thing: "Evidence" takes a username, "Policies" a framework's idnumber,
     * "Hub" a site-level plan template's short name (every status shown).
     *
     * @param string $type The page type.
     * @param string $identifier The username, the framework's idnumber or the template's short name.
     * @return moodle_url
     * @throws Exception On an unknown page type.
     */
    protected function resolve_page_instance_url(string $type, string $identifier): moodle_url {
        global $DB;
        return match (strtolower($type)) {
            'evidence' => new moodle_url('/local/evidencehub/evidence.php', [
                'userid' => $this->get_user_id_by_identifier($identifier),
            ]),
            'policies' => new moodle_url('/local/evidencehub/policies.php', [
                'frameworkid' => $DB->get_field('competency_framework', 'id', ['idnumber' => $identifier], MUST_EXIST),
            ]),
            'hub' => new moodle_url('/local/evidencehub/index.php', [
                'contextid' => context_system::instance()->id,
                'templateid' => $DB->get_field('competency_template', 'id', ['shortname' => $identifier], MUST_EXIST),
                'statusfilter' => 1,
            ]),
            default => throw new Exception('Unrecognised local_evidencehub page type "' . $type . '."'),
        };
    }
}
