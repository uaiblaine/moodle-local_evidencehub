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
 * Admin tree entries.
 *
 * Both pages do their own checks, the policies page in each framework's context, so the entries
 * name the plugin's capabilities rather than moodle/site:config.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$ADMIN->add('competencies', new admin_externalpage(
    'local_evidencehub_policies',
    new lang_string('policiespage', 'local_evidencehub'),
    new moodle_url('/local/evidencehub/policies.php'),
    'local/evidencehub:managepolicies'
));

$ADMIN->add('competencies', new admin_externalpage(
    'local_evidencehub_hub',
    new lang_string('hubpage', 'local_evidencehub'),
    new moodle_url('/local/evidencehub/index.php'),
    'local/evidencehub:view'
));
