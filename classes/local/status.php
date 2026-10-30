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

    /** @var int Effective status of a pair decided by an evidence the viewer may not see; per viewer, never stored. */
    public const int EFFECTIVE_RESTRICTED = -1;

    /** @var int Effective status of a required pair with no status row; computed, never stored. */
    public const int EFFECTIVE_MISSING = 0;

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

    /**
     * Every effective status the hub filters, sorts and counts by, in display order.
     *
     * @return array List of codes: EFFECTIVE_RESTRICTED, EFFECTIVE_MISSING and the STATUS_* constants.
     */
    public static function effective_codes(): array {
        return [
            self::EFFECTIVE_MISSING,
            self::STATUS_UNVERIFIED,
            self::STATUS_EXPIRED,
            self::STATUS_CRITICAL,
            self::STATUS_DUE,
            self::STATUS_VALID,
            self::EFFECTIVE_RESTRICTED,
        ];
    }

    /**
     * The human-readable name of an effective status.
     *
     * @param int $code An effective status code.
     * @return string
     * @throws \coding_exception On an unknown code.
     */
    public static function effective_label(int $code): string {
        return match ($code) {
            self::EFFECTIVE_RESTRICTED => get_string('validityrestricted', 'local_evidencehub'),
            self::EFFECTIVE_MISSING => self::label(null),
            default => self::label($code),
        };
    }

    /**
     * The badge classes of an effective status, each with its text colour stated.
     *
     * @param int $code An effective status code.
     * @return string
     * @throws \coding_exception On an unknown code.
     */
    public static function effective_badge_class(int $code): string {
        return match ($code) {
            self::EFFECTIVE_RESTRICTED, self::EFFECTIVE_MISSING => self::badge_class(null),
            default => self::badge_class($code),
        };
    }

    /**
     * The badge classes of a status, each with its text colour stated.
     *
     * @param int|null $status A STATUS_* constant, or null for a pair with no status row.
     * @return string
     * @throws \coding_exception On an unknown status.
     */
    public static function badge_class(?int $status): string {
        return match ($status) {
            null, self::STATUS_UNVERIFIED => 'bg-secondary text-dark',
            self::STATUS_VALID => 'bg-success text-white',
            self::STATUS_DUE => 'bg-warning text-dark',
            self::STATUS_CRITICAL => 'bg-danger text-white',
            self::STATUS_EXPIRED => 'bg-dark text-white',
            default => throw new \coding_exception('Unknown evidence hub status: ' . $status),
        };
    }
}
