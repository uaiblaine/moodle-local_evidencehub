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
 * The dossier settings (settings.php), each read with its default and its bounds.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class dossier_config {
    /** @var string The identifier setting's value for the user's ID number. */
    public const string IDENTIFIER_IDNUMBER = 'idnumber';

    /** @var string The readiness basis setting's value for the worker's real readiness. */
    public const string READINESS_REAL = 'real';

    /** @var string The readiness basis setting's value for the readiness of what the reader is shown. */
    public const string READINESS_SHOWN = 'shown';

    /** @var int Up to this many workers an export streams in the request. */
    public const int DEFAULT_SYNCMAXWORKERS = 10;

    /** @var int Up to this many bytes of evidence files an export streams in the request: 100 MB. */
    public const int DEFAULT_SYNCMAXBYTES = 104857600;

    /** @var int The most workers one export may name. */
    public const int DEFAULT_MAXEXPORTWORKERS = 500;

    /** @var int Days a prepared ZIP is kept. */
    public const int DEFAULT_RETENTIONDAYS = 7;

    /**
     * The identifiers the setting offers: the ID number, then every custom profile field of the text type.
     *
     * A profile field is offered as profile_field_<shortname>, core_user\fields' own name, so a field
     * whose short name is "idnumber" never collides with the user's ID number.
     *
     * @return array Setting value => plain label.
     */
    public static function identifier_choices(): array {
        global $DB;
        $choices = [self::IDENTIFIER_IDNUMBER => \core_user\fields::get_display_name('idnumber')];
        $fields = $DB->get_records('user_info_field', ['datatype' => 'text'], 'sortorder, id', 'id, shortname, name');
        foreach ($fields as $field) {
            $choices[\core_user\fields::PROFILE_FIELD_PREFIX . $field->shortname] = format_string(
                $field->name,
                true,
                ['context' => \context_system::instance(), 'escape' => false]
            );
        }
        return $choices;
    }

    /**
     * The identifier printed in a dossier's file names and PDF.
     *
     * A profile field that no longer exists, or is no longer of the text type, falls back to the ID
     * number: core_user\fields joins a named field with an inner join, which would drop every worker.
     *
     * @return string 'idnumber' or 'profile_field_<shortname>'.
     */
    public static function identifier_field(): string {
        $value = (string) get_config('local_evidencehub', 'identifierfield');
        if ($value !== self::IDENTIFIER_IDNUMBER && array_key_exists($value, self::identifier_choices())) {
            return $value;
        }
        return self::IDENTIFIER_IDNUMBER;
    }

    /**
     * Whether a sensitive evidence's files may go into a dossier at all; the requester also needs viewsensitive.
     *
     * Off by default: only an explicit '1' turns it on.
     *
     * @return bool
     */
    public static function include_sensitive_files(): bool {
        return get_config('local_evidencehub', 'includesensitivefiles') === '1';
    }

    /**
     * Up to how many workers an export streams in the request.
     *
     * @return int
     */
    public static function sync_max_workers(): int {
        return self::positive('syncmaxworkers', self::DEFAULT_SYNCMAXWORKERS, 0);
    }

    /**
     * Up to how many bytes of evidence files an export streams in the request.
     *
     * @return int
     */
    public static function sync_max_bytes(): int {
        return self::positive('syncmaxbytes', self::DEFAULT_SYNCMAXBYTES, 0);
    }

    /**
     * The most workers one export may name.
     *
     * @return int
     */
    public static function max_export_workers(): int {
        return self::positive('maxexportworkers', self::DEFAULT_MAXEXPORTWORKERS, 1);
    }

    /**
     * Days a prepared ZIP is kept.
     *
     * @return int
     */
    public static function retention_days(): int {
        return self::positive('exportretentiondays', self::DEFAULT_RETENTIONDAYS, 1);
    }

    /**
     * Whether the verification page adds each competency's current status to the snapshot.
     *
     * Off by default (D10): only an explicit '1' turns it on.
     *
     * @return bool
     */
    public static function verify_live(): bool {
        return get_config('local_evidencehub', 'verifylive') === '1';
    }

    /**
     * Which readiness verdict the dossier PDF, the verification page and the web service give a reader.
     *
     * Real by default: only an exact stored 'shown' selects the verdict over what the reader is shown, so
     * an unset, empty or unknown value reads as the worker's real readiness.
     *
     * @return string A READINESS_* constant of this class.
     */
    public static function readiness_basis(): string {
        return get_config('local_evidencehub', 'readinessbasis') === self::READINESS_SHOWN
            ? self::READINESS_SHOWN
            : self::READINESS_REAL;
    }

    /**
     * An integer setting, its default when unset and never below a floor.
     *
     * @param string $name The setting.
     * @param int $default Its default.
     * @param int $floor The lowest value accepted.
     * @return int
     */
    private static function positive(string $name, int $default, int $floor): int {
        $value = get_config('local_evidencehub', $name);
        if ($value === false || $value === '') {
            return $default;
        }
        return max($floor, (int) $value);
    }
}
