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

/**
 * The evidence types the plugin ships with.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class types {
    /**
     * The default types, in display order.
     *
     * Names are read in the language current when the plugin installs and stored: the administrator
     * renames them like any other type, and a multilingual site writes a multilang span.
     *
     * @return array List of records without sortorder and times, each keyed by column name.
     */
    public static function defaults(): array {
        return [
            [
                'shortname' => 'nrcertificate',
                'name' => get_string('type_nrcertificate', 'local_evidencehub'),
                'defaultvalidity' => 24,
                'requireexpiry' => 1,
                'requireauthcode' => 0,
                'issensitive' => 0,
            ],
            [
                'shortname' => 'aso',
                'name' => get_string('type_aso', 'local_evidencehub'),
                'defaultvalidity' => 12,
                'requireexpiry' => 1,
                'requireauthcode' => 0,
                'issensitive' => 1,
            ],
            [
                'shortname' => 'artrrt',
                'name' => get_string('type_artrrt', 'local_evidencehub'),
                'defaultvalidity' => 0,
                'requireexpiry' => 0,
                'requireauthcode' => 1,
                'issensitive' => 0,
            ],
            [
                'shortname' => 'diploma',
                'name' => get_string('type_diploma', 'local_evidencehub'),
                'defaultvalidity' => 0,
                'requireexpiry' => 0,
                'requireauthcode' => 0,
                'issensitive' => 0,
            ],
            [
                'shortname' => 'practical',
                'name' => get_string('type_practical', 'local_evidencehub'),
                'defaultvalidity' => 12,
                'requireexpiry' => 1,
                'requireauthcode' => 0,
                'issensitive' => 0,
            ],
        ];
    }

    /**
     * Insert every default type whose shortname is not taken yet.
     *
     * A type the administrator already has under the same shortname is left as it is, renamed or
     * not, so running this again changes nothing.
     *
     * @return int The number of types inserted.
     */
    public static function seed_defaults(): int {
        global $DB;
        $now = time();
        $inserted = 0;
        foreach (self::defaults() as $position => $type) {
            if ($DB->record_exists('local_evidencehub_type', ['shortname' => $type['shortname']])) {
                continue;
            }
            $type += ['sortorder' => $position + 1, 'timecreated' => $now, 'timemodified' => $now];
            $DB->insert_record('local_evidencehub_type', (object) $type);
            $inserted++;
        }
        return $inserted;
    }
}
