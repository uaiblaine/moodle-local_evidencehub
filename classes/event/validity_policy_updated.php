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
 * The validity policy of a competency framework, or of one of its competencies, was set or removed.
 *
 * @property-read array $other {
 *      - int frameworkid: the framework.
 *      - int competencyid: the competency, 0 for the framework's own policy.
 * }
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class validity_policy_updated extends base {
    /**
     * The event about one policy row, written or removed.
     *
     * @param \context $context The framework's context.
     * @param \stdClass $row The local_evidencehub_policy row.
     * @return self
     */
    public static function create_from_policy(\context $context, \stdClass $row): self {
        $event = self::create([
            'contextid' => $context->id,
            'objectid' => (int) $row->id,
            'other' => [
                'frameworkid' => (int) $row->frameworkid,
                'competencyid' => (int) $row->competencyid,
            ],
        ]);
        $event->add_record_snapshot('local_evidencehub_policy', $row);
        return $event;
    }

    /**
     * Initialise the fixed properties.
     *
     * @return void
     */
    #[\Override]
    protected function init(): void {
        $this->data['objecttable'] = 'local_evidencehub_policy';
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
        return get_string('eventvaliditypolicyupdated', 'local_evidencehub');
    }

    /**
     * What happened, not localised.
     *
     * @return string
     */
    #[\Override]
    public function get_description(): string {
        return "The user with id '$this->userid' changed the validity policy with id '$this->objectid' of the competency " .
            "framework with id '{$this->other['frameworkid']}' (competency id '{$this->other['competencyid']}').";
    }

    /**
     * The framework's policies page.
     *
     * @return \moodle_url
     */
    #[\Override]
    public function get_url(): \moodle_url {
        return new \moodle_url('/local/evidencehub/policies.php', ['frameworkid' => $this->other['frameworkid']]);
    }

    /**
     * Refuse an event without its framework or competency.
     *
     * @return void
     * @throws \coding_exception When a value is missing.
     */
    #[\Override]
    protected function validate_data(): void {
        parent::validate_data();
        foreach (['frameworkid', 'competencyid'] as $key) {
            if (!isset($this->other[$key])) {
                throw new \coding_exception("The '$key' value must be set in other.");
            }
        }
    }

    /**
     * The policy row is not backed up, so the object id maps to nothing.
     *
     * @return string
     */
    #[\Override]
    public static function get_objectid_mapping(): string {
        return base::NOT_MAPPED;
    }

    /**
     * The ids in other map to nothing on restore.
     *
     * @return array
     */
    #[\Override]
    public static function get_other_mapping(): array {
        return ['frameworkid' => base::NOT_MAPPED, 'competencyid' => base::NOT_MAPPED];
    }
}
