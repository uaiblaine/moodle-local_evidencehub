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

use local_evidencehub\local\dossier_export;

/**
 * Delete the prepared dossier ZIPs kept past their retention and mark their exports expired.
 *
 * The dossier rows stay: they back the public verification page.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cleanup_dossier_exports extends \core\task\scheduled_task {
    /**
     * The task's name.
     *
     * @return string
     */
    #[\Override]
    public function get_name(): string {
        return get_string('taskcleanupdossierexports', 'local_evidencehub');
    }

    /**
     * Delete the expired ZIPs.
     *
     * @return void
     */
    #[\Override]
    public function execute(): void {
        $count = dossier_export::cleanup(\core\di::get(\core\clock::class)->time());
        mtrace("Deleted the files of $count expired dossier exports.");
    }
}
