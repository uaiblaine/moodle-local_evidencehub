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

use core_table\base_export_format;

/**
 * A table writer that records the headings and the rows a download would write, as plain text, and ends nothing.
 *
 * Core's dataformat writer sends headers and ends the request once the file is written; this one lets a test read
 * the file's content as core's would format it (tags and entities removed, as for a format without HTML).
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class recording_export_format extends base_export_format {
    /** @var array The headings, in column order. */
    public array $headers = [];

    /** @var array The rows, each a list of cells in column order. */
    public array $rows = [];

    /** @var bool Whether the document was finished. */
    public bool $finished = false;

    /**
     * A writer with no table yet: the table sets itself when it takes the writer.
     */
    public function __construct() {
        $table = null;
        parent::__construct($table);
        $this->documentstarted = true;
    }

    /**
     * Whether the format keeps HTML: not, as CSV or a spreadsheet.
     *
     * @return bool
     */
    public function supports_html(): bool {
        return false;
    }

    /**
     * Start a table: nothing to record.
     *
     * @param string $sheettitle The sheet's title.
     * @return void
     */
    public function start_table($sheettitle): void {
    }

    /**
     * Record the headings.
     *
     * @param array $headers The headings.
     * @return void
     */
    public function output_headers($headers): void {
        $this->headers = $this->format_data($headers);
    }

    /**
     * Record a row.
     *
     * @param array $row The cells.
     * @return bool
     */
    public function add_data($row) {
        $this->record_headers();
        $this->rows[] = $this->format_data($row);
        return true;
    }

    /**
     * Record the table's headings, once: a table handed a writer of its own counts its output as started, so it never
     * sends the headings itself (\core_table\flexible_table::export_class_instance()).
     *
     * @return void
     */
    private function record_headers(): void {
        if ($this->headers === [] && $this->table !== null) {
            $this->headers = $this->format_data(array_values($this->table->headers));
        }
    }

    /**
     * Finish a table: nothing to record.
     *
     * @return void
     */
    public function finish_table(): void {
    }

    /**
     * Finish the document, without ending the request.
     *
     * @return void
     */
    public function finish_document(): void {
        $this->record_headers();
        $this->finished = true;
    }
}
