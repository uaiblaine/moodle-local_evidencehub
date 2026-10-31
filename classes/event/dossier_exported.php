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
 * A dossier export finished: its ZIP was streamed, or prepared and stored for the requester.
 *
 * @property-read array $other {
 *      - int templateid: the plan template.
 *      - int subjectcount: how many dossiers the export holds.
 * }
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class dossier_exported extends base {
    /**
     * The event of one finished export.
     *
     * @param \context $context The hub's context the export was requested in.
     * @param \stdClass $export The local_evidencehub_export row, finished.
     * @return self
     */
    public static function create_from_export(\context $context, \stdClass $export): self {
        $event = self::create([
            'contextid' => $context->id,
            'objectid' => (int) $export->id,
            'other' => ['templateid' => (int) $export->templateid, 'subjectcount' => (int) $export->subjectcount],
        ]);
        $event->add_record_snapshot('local_evidencehub_export', $export);
        return $event;
    }

    /**
     * Initialise the fixed properties.
     *
     * @return void
     */
    #[\Override]
    protected function init(): void {
        $this->data['objecttable'] = 'local_evidencehub_export';
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * The localised event name.
     *
     * @return string
     */
    #[\Override]
    public static function get_name(): string {
        return get_string('eventdossierexported', 'local_evidencehub');
    }

    /**
     * What happened, not localised.
     *
     * @return string
     */
    #[\Override]
    public function get_description(): string {
        return "The user with id '$this->userid' exported {$this->other['subjectcount']} readiness dossiers for the " .
            "learning plan template with id '{$this->other['templateid']}' (export id '$this->objectid').";
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
     * Refuse an event without its template or count.
     *
     * @return void
     * @throws \coding_exception When a value is missing.
     */
    #[\Override]
    protected function validate_data(): void {
        parent::validate_data();
        foreach (['templateid', 'subjectcount'] as $key) {
            if (!isset($this->other[$key])) {
                throw new \coding_exception("The '$key' value must be set in other.");
            }
        }
    }

    /**
     * The export row is not backed up, so the object id maps to nothing.
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
        return ['templateid' => base::NOT_MAPPED, 'subjectcount' => base::NOT_MAPPED];
    }
}
