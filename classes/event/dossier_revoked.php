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
 * A readiness dossier was revoked: its verification page now says so.
 *
 * @property-read array $other {
 *      - int templateid: the plan template the dossier covers.
 * }
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class dossier_revoked extends base {
    /**
     * The event about one dossier row.
     *
     * @param \stdClass $dossier The local_evidencehub_dossier row, revoked.
     * @return self
     */
    public static function create_from_dossier(\stdClass $dossier): self {
        $event = self::create([
            'contextid' => \context_user::instance((int) $dossier->userid)->id,
            'objectid' => (int) $dossier->id,
            'relateduserid' => (int) $dossier->userid,
            'other' => ['templateid' => (int) $dossier->templateid],
        ]);
        $event->add_record_snapshot('local_evidencehub_dossier', $dossier);
        return $event;
    }

    /**
     * Initialise the fixed properties.
     *
     * @return void
     */
    #[\Override]
    protected function init(): void {
        $this->data['objecttable'] = 'local_evidencehub_dossier';
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
        return get_string('eventdossierrevoked', 'local_evidencehub');
    }

    /**
     * What happened, not localised.
     *
     * @return string
     */
    #[\Override]
    public function get_description(): string {
        return "The user with id '$this->userid' revoked the readiness dossier with id '$this->objectid' about the user " .
            "with id '$this->relateduserid'.";
    }

    /**
     * The worker's evidence page, which lists the dossiers.
     *
     * @return \moodle_url
     */
    #[\Override]
    public function get_url(): \moodle_url {
        return new \moodle_url('/local/evidencehub/evidence.php', ['userid' => $this->relateduserid]);
    }

    /**
     * Refuse an event without its worker or template.
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
        if (!isset($this->other['templateid'])) {
            throw new \coding_exception('The \'templateid\' value must be set in other.');
        }
    }

    /**
     * The dossier row is not backed up, so the object id maps to nothing.
     *
     * @return string
     */
    #[\Override]
    public static function get_objectid_mapping(): string {
        return base::NOT_MAPPED;
    }

    /**
     * The template id maps to nothing on restore.
     *
     * @return array
     */
    #[\Override]
    public static function get_other_mapping(): array {
        return ['templateid' => base::NOT_MAPPED];
    }
}
