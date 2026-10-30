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
 * Actions recorded in the append-only trail, local_evidencehub_audit.action.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class audit {
    /** @var string The details were entered for the first time. */
    public const string ACTION_CREATED = 'created';

    /** @var string The details were changed. */
    public const string ACTION_DETAILS = 'details';

    /** @var string The details were verified. */
    public const string ACTION_VERIFIED = 'verified';

    /** @var string The details were rejected. */
    public const string ACTION_REJECTED = 'rejected';

    /** @var string An edit after verification sent the details back to pending. */
    public const string ACTION_RESET = 'reset';

    /**
     * The human-readable name of an action.
     *
     * @param string $action An ACTION_* constant.
     * @return string
     * @throws \coding_exception On an unknown action.
     */
    public static function label(string $action): string {
        return match ($action) {
            self::ACTION_CREATED => get_string('audit_created', 'local_evidencehub'),
            self::ACTION_DETAILS => get_string('audit_details', 'local_evidencehub'),
            self::ACTION_VERIFIED => get_string('audit_verified', 'local_evidencehub'),
            self::ACTION_REJECTED => get_string('audit_rejected', 'local_evidencehub'),
            self::ACTION_RESET => get_string('audit_reset', 'local_evidencehub'),
            default => throw new \coding_exception('Unknown audit action: ' . $action),
        };
    }
}
