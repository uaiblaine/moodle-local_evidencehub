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

namespace local_evidencehub;

use core\hook\output\before_footer_html_generation;
use core_competency\api;
use core_competency\user_evidence;

/**
 * Hook callbacks.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class hook_callbacks {
    /** @var string Page type of tool_lp's view of one evidence (admin/tool/lp/user_evidence.php). */
    public const string PAGETYPE_VIEW = 'admin-tool-lp-user_evidence';

    /** @var string Page type of tool_lp's list of a user's evidence (admin/tool/lp/user_evidence_list.php). */
    public const string PAGETYPE_LIST = 'admin-tool-lp-user_evidence_list';

    /**
     * On tool_lp's evidence pages, prompt whoever may manage the evidence to complete the
     * validity details that are missing.
     *
     * Both pages set the worker's user context through tool_lp\page_helper::setup_for_user_evidence().
     *
     * @param before_footer_html_generation $hook The hook.
     * @return void
     */
    public static function before_footer_html_generation(before_footer_html_generation $hook): void {
        global $DB, $PAGE;
        if (!in_array($PAGE->pagetype, [self::PAGETYPE_VIEW, self::PAGETYPE_LIST], true)) {
            return;
        }
        if ((int) $PAGE->context->contextlevel !== CONTEXT_USER || !api::is_enabled()) {
            return;
        }
        $userid = (int) $PAGE->context->instanceid;
        if (!user_evidence::can_manage_user($userid)) {
            return;
        }

        $sql = "SELECT COUNT(1)
                  FROM {competency_userevidence} ue
             LEFT JOIN {local_evidencehub_meta} m ON m.userevidenceid = ue.id
                 WHERE ue.userid = :userid AND m.id IS NULL";
        $params = ['userid' => $userid];
        if ($PAGE->pagetype === self::PAGETYPE_VIEW) {
            $sql .= ' AND ue.id = :id';
            $params['id'] = (int) $PAGE->url->get_param('id');
        }
        $missing = (int) $DB->count_records_sql($sql, $params);
        if ($missing === 0) {
            return;
        }

        $message = $PAGE->pagetype === self::PAGETYPE_VIEW
            ? get_string('promptview', 'local_evidencehub')
            : get_string('promptlist', 'local_evidencehub', $missing);
        $PAGE->requires->js_call_amd('local_evidencehub/lp_prompt', 'init', [[
            'url' => (new \moodle_url('/local/evidencehub/evidence.php', ['userid' => $userid]))->out(false),
            'message' => $message,
            'linktext' => get_string('promptlink', 'local_evidencehub'),
        ]]);
    }

    /**
     * Answer local_dimensions' request for the validity pills of a worker's competencies.
     *
     * The pills are decided by {@see local\validity_badges::for_user()}; the hook class belongs to
     * local_dimensions and is not required, so its tones are passed through as plain strings.
     *
     * @param \local_dimensions\hook\competency_status_badges $hook The hook.
     * @return void
     */
    public static function competency_status_badges(\local_dimensions\hook\competency_status_badges $hook): void {
        $badges = local\validity_badges::for_user($hook->get_userid(), $hook->get_competencyids());
        foreach ($badges as $competencyid => $badge) {
            $hook->add_badge((int) $competencyid, $badge['label'], $badge['tone']);
        }
    }
}
