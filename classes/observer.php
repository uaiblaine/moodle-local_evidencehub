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

use local_evidencehub\local\details;

/**
 * Keeps the details in step with core's evidence when it changes outside this plugin.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class observer {
    /**
     * An evidence changed in tool_lp, a web service or this plugin's own form: a decided one goes
     * back to pending unless a verifier other than the owner changed it.
     *
     * Idempotent: only a verified or rejected row resets, so the details::save() that follows
     * this plugin's own core update finds the row pending and writes no second reset.
     *
     * @param \core\event\competency_user_evidence_updated $event The event.
     * @return void
     */
    public static function user_evidence_updated(\core\event\competency_user_evidence_updated $event): void {
        $meta = details::get((int) $event->objectid);
        if ($meta !== null) {
            details::apply_edit_rule($meta, (int) $event->userid);
        }
    }

    /**
     * An evidence was deleted: its details go with it; the trail stays, append-only.
     *
     * @param \core\event\competency_user_evidence_deleted $event The event.
     * @return void
     */
    public static function user_evidence_deleted(\core\event\competency_user_evidence_deleted $event): void {
        global $DB;
        $DB->delete_records(details::TABLE, ['userevidenceid' => (int) $event->objectid]);
    }
}
