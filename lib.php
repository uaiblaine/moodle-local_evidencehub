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
