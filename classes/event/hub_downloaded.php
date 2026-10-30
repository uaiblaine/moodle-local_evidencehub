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

namespace local_evidencehub\event;

use core\event\base;

/**
 * The hub's grid of a plan template was downloaded.
 *
 * @property-read array $other {
 *      - int templateid: the plan template.
 *      - int rows: how many pairs the download holds.
 * }
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class hub_downloaded extends base {
    /**
     * The event of one download.
     *
     * @param \context $context The hub's page context: the system or a course category.
     * @param int $templateid The plan template.
     * @param int $rows How many pairs the download holds.
     * @return self
     */
    public static function create_from_download(\context $context, int $templateid, int $rows): self {
        return self::create([
            'contextid' => $context->id,
            'other' => ['templateid' => $templateid, 'rows' => $rows],
        ]);
    }

    /**
     * Initialise the fixed properties.
     *
     * @return void
     */
    #[\Override]
    protected function init(): void {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * The localised event name.
     *
     * @return string
     */
    #[\Override]
    public static function get_name(): string {
        return get_string('eventhubdownloaded', 'local_evidencehub');
    }

    /**
     * What happened, not localised.
     *
     * @return string
     */
    #[\Override]
    public function get_description(): string {
        return "The user with id '$this->userid' downloaded {$this->other['rows']} rows of the evidence hub for the " .
            "learning plan template with id '{$this->other['templateid']}'.";
    }

    /**
     * The hub on that template.
     *
     * @return \moodle_url
     */
    #[\Override]
    public function get_url(): \moodle_url {
        return new \moodle_url('/local/evidencehub/index.php', [
            'contextid' => $this->contextid,
            'templateid' => $this->other['templateid'],
        ]);
    }

    /**
     * Refuse an event without its template or row count.
     *
     * @return void
     * @throws \coding_exception When a value is missing.
     */
    #[\Override]
    protected function validate_data(): void {
        parent::validate_data();
        foreach (['templateid', 'rows'] as $key) {
            if (!isset($this->other[$key])) {
                throw new \coding_exception("The '$key' value must be set in other.");
            }
        }
    }

    /**
     * The template id maps to nothing on restore.
     *
     * @return array
     */
    #[\Override]
    public static function get_other_mapping(): array {
        return ['templateid' => base::NOT_MAPPED, 'rows' => base::NOT_MAPPED];
    }
}
