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
 * Recompute every pair that may have a status, healing whatever a missed event left behind.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reconcile_validity extends \core\task\scheduled_task {
    /**
     * The task's name.
     *
     * @return string
     */
    #[\Override]
    public function get_name(): string {
        return get_string('taskreconcilevalidity', 'local_evidencehub');
    }

    /**
     * Recompute the whole site in chunks.
     *
     * @return void
     */
    #[\Override]
    public function execute(): void {
        if (!\core_competency\api::is_enabled()) {
            mtrace('Competencies are not enabled.');
            return;
        }
        $counts = status_repository::recompute_pairs();
        mtrace("Reconciled {$counts['pairs']} pairs: {$counts['stored']} with a validity status, {$counts['failed']} failed.");
    }
}
