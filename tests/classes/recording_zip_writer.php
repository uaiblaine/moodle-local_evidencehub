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

namespace local_evidencehub\tests;

use core_files\local\archive_writer\zip_writer;

/**
 * A ZIP file writer that records, as each file enters the archive, how many dossier rows exist.
 *
 * Build it with recording_zip_writer::file_instance(<file name>): core's factory makes an instance of the
 * calling class.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class recording_zip_writer extends zip_writer {
    /** @var array Name in the archive => dossier rows stored when it was added. */
    public array $rowsat = [];

    /**
     * Record the dossier rows, then add the file.
     *
     * @param string $name The path in the archive.
     * @param string $data The content.
     * @return void
     */
    #[\Override]
    public function add_file_from_string(string $name, string $data): void {
        global $DB;
        $this->rowsat[$name] = $DB->count_records('local_evidencehub_dossier');
        parent::add_file_from_string($name, $data);
    }
}
