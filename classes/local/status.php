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
 * Validity statuses of a (user, competency) pair, as stored in local_evidencehub_status.
 *
 * "Missing" has no constant on purpose: a pair the plan template requires and no status row covers
 * is computed at query time, so the table never holds a row per required pair.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class status {
    /** @var int Evidence exists, none counts yet (unverified, incomplete details, too few hours). */
    public const int STATUS_UNVERIFIED = 1;

    /** @var int A counted evidence covers today with more than the policy's first alert window left. */
    public const int STATUS_VALID = 2;

    /** @var int Covered today, within the policy's first alert window (alert1 days). */
    public const int STATUS_DUE = 3;

    /** @var int Covered today, within the policy's second alert window (alert2 days). */
    public const int STATUS_CRITICAL = 4;

    /** @var int Counted evidence exists and none covers today. */
    public const int STATUS_EXPIRED = 5;

    /**
     * The human-readable name of a status.
     *
     * @param int|null $status A STATUS_* constant, or null for a required pair with no status row.
     * @return string
     * @throws \coding_exception On an unknown status.
     */
    public static function label(?int $status): string {
        return match ($status) {
            null => get_string('status_missing', 'local_evidencehub'),
            self::STATUS_UNVERIFIED => get_string('status_unverified', 'local_evidencehub'),
            self::STATUS_VALID => get_string('status_valid', 'local_evidencehub'),
            self::STATUS_DUE => get_string('status_due', 'local_evidencehub'),
            self::STATUS_CRITICAL => get_string('status_critical', 'local_evidencehub'),
            self::STATUS_EXPIRED => get_string('status_expired', 'local_evidencehub'),
            default => throw new \coding_exception('Unknown evidence hub status: ' . $status),
        };
    }
}
