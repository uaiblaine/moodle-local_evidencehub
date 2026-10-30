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
 * Callbacks core calls by name.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Add the evidence hub to a course category's More menu, for holders of local/evidencehub:view there.
 *
 * Opened from a category, the hub offers that category's plan templates and its subcategories' only.
 *
 * @param navigation_node $navigation The category's settings node.
 * @param context_coursecat $context The category's context.
 * @return void
 */
function local_evidencehub_extend_navigation_category_settings(navigation_node $navigation, context_coursecat $context): void {
    if (!\core_competency\api::is_enabled() || !has_capability('local/evidencehub:view', $context)) {
        return;
    }
    $node = navigation_node::create(
        get_string('hubpage', 'local_evidencehub'),
        new moodle_url('/local/evidencehub/index.php', ['contextid' => $context->id]),
        navigation_node::TYPE_SETTING,
        null,
        'local_evidencehub_hub',
        new pix_icon('i/competencies', '')
    );
    $node->set_force_into_more_menu(true);
    $navigation->add_node($node);
}

/**
 * The inspection drawer of one (worker, competency) pair of the hub, as core_get_fragment serves it.
 *
 * Core only validates the context: every check is made here, never trusted to the hub page that
 * opened the drawer. The context must be the system or a category where the viewer holds
 * local/evidencehub:view; the viewer must read the worker's evidence (the fact the hub's SQL cut
 * reads); the competency must exist and be readable by the viewer (core's competencyview or
 * competencymanage in its framework's context); a template, when given, must be one the hub offers there.
 *
 * @param array $args The fragment's context, and userid, competencyid and optionally templateid.
 * @return string The drawer's body.
 * @throws moodle_exception When a check refuses.
 */
function local_evidencehub_output_fragment_pair(array $args): string {
    global $PAGE;
    $context = $args['context'];
    \local_evidencehub\local\hub_access::require_view($context);
    \core_competency\api::require_enabled();

    $userid = (int) clean_param($args['userid'] ?? 0, PARAM_INT);
    $competencyid = (int) clean_param($args['competencyid'] ?? 0, PARAM_INT);
    $templateid = (int) clean_param($args['templateid'] ?? 0, PARAM_INT);

    $user = \core_user::get_user($userid);
    if (!$user || $user->deleted) {
        throw new moodle_exception('invaliduser');
    }
    if (!\core_competency\user_evidence::can_read_user($userid)) {
        throw new required_capability_exception(
            context_user::instance($userid),
            'moodle/competency:userevidenceview',
            'nopermissions',
            ''
        );
    }
    if (!\core_competency\competency::record_exists($competencyid)) {
        throw new moodle_exception('invalidrecord', 'error', '', 'competency');
    }
    // Its name, ID number and policy are printed: core's own read check of the competency.
    \core_competency\api::read_competency($competencyid);
    if ($templateid) {
        \local_evidencehub\local\hub_access::require_template($templateid, $context);
    }

    $drawer = new \local_evidencehub\output\pair_drawer($context, $userid, $competencyid, $templateid);
    $output = $PAGE->get_renderer('core');
    return $output->render_from_template('local_evidencehub/pair_drawer', $drawer->export_for_template($output));
}
