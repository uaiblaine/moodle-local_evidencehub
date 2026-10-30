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
 * Recompute the statuses whose next transition has come: an evidence entering its alert window,
 * expiring, or a renewal starting to count.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class evaluate_validity extends \core\task\scheduled_task {
    /**
     * The task's name.
     *
     * @return string
     */
    #[\Override]
    public function get_name(): string {
        return get_string('taskevaluatevalidity', 'local_evidencehub');
    }

    /**
     * Recompute one chunk of the due rows, oldest first; the next run takes the rest.
     *
     * @return void
     */
    #[\Override]
    public function execute(): void {
        if (!\core_competency\api::is_enabled()) {
            mtrace('Competencies are not enabled.');
            return;
        }
        $counts = status_repository::recompute_due(\core\di::get(\core\clock::class)->time());
        mtrace("Recomputed {$counts['pairs']} due validity statuses ({$counts['failed']} failed).");
    }
}
