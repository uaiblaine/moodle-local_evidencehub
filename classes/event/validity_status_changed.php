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
 * The validity status of a competency of a user changed.
 *
 * @property-read array $other {
 *      - int|null from: the previous local\status STATUS_* value, null on a first status.
 *      - int to: the new STATUS_* value.
 *      - int competencyid: the competency.
 * }
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class validity_status_changed extends base {
    /**
     * The event about one status row, fired after it was written.
     *
     * @param \stdClass $row The local_evidencehub_status row.
     * @param int|null $from The previous status, null on a first row.
     * @return self
     */
    public static function create_from_status(\stdClass $row, ?int $from): self {
        $event = self::create([
            'contextid' => \context_user::instance((int) $row->userid)->id,
            'objectid' => (int) $row->id,
            'relateduserid' => (int) $row->userid,
            'other' => [
                'from' => $from,
                'to' => (int) $row->status,
                'competencyid' => (int) $row->competencyid,
            ],
        ]);
        $event->add_record_snapshot('local_evidencehub_status', $row);
        return $event;
    }

    /**
     * Initialise the fixed properties.
     *
     * @return void
     */
    #[\Override]
    protected function init(): void {
        $this->data['objecttable'] = 'local_evidencehub_status';
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * The localised event name.
     *
     * @return string
     */
    #[\Override]
    public static function get_name(): string {
        return get_string('eventvaliditystatuschanged', 'local_evidencehub');
    }

    /**
     * What happened, not localised.
     *
     * @return string
     */
    #[\Override]
    public function get_description(): string {
        $from = $this->other['from'] ?? 'none';
        return "The validity status of the competency with id '{$this->other['competencyid']}' of the user with id " .
            "'$this->relateduserid' changed from '$from' to '{$this->other['to']}'.";
    }

    /**
     * The worker's evidence page.
     *
     * @return \moodle_url
     */
    #[\Override]
    public function get_url(): \moodle_url {
        return new \moodle_url('/local/evidencehub/evidence.php', ['userid' => $this->relateduserid]);
    }

    /**
     * Refuse an event without its worker, competency or statuses.
     *
     * @return void
     * @throws \coding_exception When a value is missing.
     */
    #[\Override]
    protected function validate_data(): void {
        parent::validate_data();
        if (!isset($this->relateduserid)) {
            throw new \coding_exception('The \'relateduserid\' must be set.');
        }
        foreach (['to', 'competencyid'] as $key) {
            if (!isset($this->other[$key])) {
                throw new \coding_exception("The '$key' value must be set in other.");
            }
        }
        if (!array_key_exists('from', $this->other)) {
            throw new \coding_exception('The \'from\' value must be set in other, null on a first status.');
        }
    }

    /**
     * The status row is not backed up, so the object id maps to nothing.
     *
     * @return string
     */
    #[\Override]
    public static function get_objectid_mapping(): string {
        return base::NOT_MAPPED;
    }

    /**
     * The competency id in other maps to nothing on restore.
     *
     * @return array
     */
    #[\Override]
    public static function get_other_mapping(): array {
        return ['competencyid' => base::NOT_MAPPED];
    }
}
