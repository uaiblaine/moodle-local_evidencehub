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

namespace local_evidencehub\local;

use core_table\base_export_format;
use local_evidencehub\event\hub_downloaded;
use local_evidencehub\reportbuilder\local\systemreports\headless_table;
use local_evidencehub\reportbuilder\local\systemreports\hub_pairs;

/**
 * The download of the hub's list: the pairs report's own table, written by core's dataformat writer (decision 9, D114).
 *
 * The file holds what the list shows under the criteria in force: the report is built for the reader with the scope
 * and the search as its own parameters, which it adds as base conditions ({@see reportengine::conditions()}), so it
 * carries the reader's stored filters, the suspended choice and the chosen columns, each value masked in SQL for a
 * restricted pair. The rows' sort is the one the list asked for, from the same vocabulary as the rows service.
 *
 * This is the only door to the file: the report stays not downloadable (`set_downloadable(false)`), so core's
 * reportbuilder/download.php refuses it, and its can_view() refuses a 'download' parameter, so core's dynamic table
 * cannot download it either. Here, and only here, the download is logged ({@see hub_downloaded}), once, with the
 * number of rows the file holds, counted in one statement before the file starts.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class report_download {
    /**
     * A download ready to send; {@see self::open()} builds one.
     *
     * @param int $templateid The template.
     * @param string $format The dataformat, enabled.
     * @param headless_table $table The report's table, built for the reader, not yet downloading.
     */
    private function __construct(
        /** @var int The template. */
        private readonly int $templateid,
        /** @var string The dataformat. */
        private readonly string $format,
        /** @var headless_table The report's table. */
        private readonly headless_table $table,
    ) {
    }

    /**
     * Refuse a format the site has not enabled.
     *
     * @param string $format The requested dataformat.
     * @return void
     * @throws \moodle_exception On any other value.
     */
    public static function require_format(string $format): void {
        if (!array_key_exists($format, \core\plugininfo\dataformat::get_enabled_plugins())) {
            throw new \moodle_exception('invalidparameter', 'debug');
        }
    }

    /**
     * The download of a template's pairs for the current user, every check passed and the event fired.
     *
     * The caller has already required view in the context and the template there (index.php); the format is checked
     * here, and the report's own can_view() runs again while it is built, so no table exists for a reader it refuses.
     * The event fires before a byte of the file, with the rows the file will hold: what follows writes those rows.
     *
     * @param \context $context The hub context.
     * @param int $templateid The template.
     * @param string $format The requested dataformat.
     * @param array $options 'scope' and 'search' as the list applies them, 'sort' (a column key) and 'direction'
     *     ('asc' or 'desc'); anything else is ignored.
     * @return self
     * @throws \moodle_exception On a format not enabled.
     * @throws \core_reportbuilder\exception\report_access_exception When the report refuses the reader.
     */
    public static function open(\context $context, int $templateid, string $format, array $options): self {
        self::require_format($format);
        $parameters = [
            'scope' => reportengine::scope((string) ($options['scope'] ?? reportengine::SCOPE_ATTENTION)),
            'search' => reportengine::search((string) ($options['search'] ?? '')),
        ];
        $report = hub_pairs::for_template($context, $templateid, $parameters);

        /** @var headless_table $table */
        $table = headless_table::create((int) $report->get_report_persistent()->get('id'), $report->get_parameters());
        $tablereport = $table->get_report();
        $keys = $tablereport->active_keys();
        $columns = $tablereport->get_active_columns();
        $sort = (string) ($options['sort'] ?? '');
        $direction = ($options['direction'] ?? '') === 'desc' ? SORT_DESC : SORT_ASC;
        // An explicit sort from the report's own columns, or the report's own; never one core kept in the session.
        $sortdata = [];
        if (isset($keys[$sort]) && $columns[$keys[$sort]]->get_is_sortable()) {
            $sortdata[] = ['sortby' => $columns[$keys[$sort]]->get_column_alias(), 'sortorder' => $direction];
        }
        $table->set_sortdata($sortdata);
        $table->define_baseurl(new \moodle_url('/local/evidencehub/index.php', ['contextid' => $context->id]));

        $download = new self($templateid, $format, $table);
        hub_downloaded::create_from_download($context, $templateid, $download->rows())->trigger();
        return $download;
    }

    /**
     * How many rows the file holds: one count over the table's FROM and WHERE, the rows the file is written from.
     *
     * @return int
     */
    public function rows(): int {
        return $this->table->count_rows();
    }

    /**
     * Write the file: core's table, downloading, in the chosen format. Core's writer ends the request once the file
     * is written; a test hands its own writer, which does not.
     *
     * @param base_export_format|null $writer A writer to use instead of core's for the format; tests only.
     * @return void
     */
    public function send(?base_export_format $writer = null): void {
        if ($writer !== null) {
            $this->table->export_class_instance($writer);
        }
        $this->table->is_downloading(
            $this->format,
            clean_filename(get_string('hubdownloadname', 'local_evidencehub', $this->templateid))
        );
        $this->table->out($this->table->get_default_per_page(), false);
    }
}
