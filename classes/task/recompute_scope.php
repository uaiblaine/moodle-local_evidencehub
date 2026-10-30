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

namespace local_evidencehub\task;

use local_evidencehub\local\status_repository;

/**
 * Recompute the pairs of one competency or one framework after its policy changed, or one pair an
 * observer could not recompute because its user was locked.
 *
 * Custom data: userid and competencyid (one pair), competencyid, or frameworkid.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class recompute_scope extends \core\task\adhoc_task {
    /**
     * The task's name.
     *
     * @return string
     */
    #[\Override]
    public function get_name(): string {
        return get_string('taskrecomputescope', 'local_evidencehub');
    }

    /**
     * Recompute the scope in chunks.
     *
     * @return void
     * @throws \coding_exception Without a competency or a framework in the custom data.
     */
    #[\Override]
    public function execute(): void {
        if (!\core_competency\api::is_enabled()) {
            mtrace('Competencies are not enabled.');
            return;
        }
        $data = (object) $this->get_custom_data();
        if (!empty($data->userid) && !empty($data->competencyid)) {
            // A lock timeout here fails the task, and core retries it later.
            $stored = status_repository::recompute((int) $data->userid, (int) $data->competencyid) !== null;
            mtrace("Recomputed the validity of user {$data->userid} in competency {$data->competencyid}: " .
                ($stored ? 'stored.' : 'no status.'));
            return;
        }
        if (!empty($data->competencyid)) {
            $counts = status_repository::recompute_pairs((int) $data->competencyid, null);
        } else if (!empty($data->frameworkid)) {
            $counts = status_repository::recompute_pairs(null, (int) $data->frameworkid);
        } else {
            throw new \coding_exception('A competencyid or a frameworkid is required.');
        }
        mtrace("Recomputed {$counts['pairs']} pairs: {$counts['stored']} with a validity status, {$counts['failed']} failed.");
    }
}
