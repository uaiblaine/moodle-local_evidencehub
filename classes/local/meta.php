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
 * Verification states of an evidence's details, as stored in local_evidencehub_meta.verifystatus.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class meta {
    /** @var int Details entered, nobody has verified them yet; also the state after an edit. */
    public const int VERIFY_PENDING = 0;

    /** @var int A holder of local/evidencehub:verify confirmed the details against the file. */
    public const int VERIFY_VERIFIED = 1;

    /** @var int A holder of local/evidencehub:verify refused the details; the note says why. */
    public const int VERIFY_REJECTED = 2;

    /**
     * The human-readable name of a verification state.
     *
     * @param int $state A VERIFY_* constant.
     * @return string
     * @throws \coding_exception On an unknown state.
     */
    public static function verify_label(int $state): string {
        return match ($state) {
            self::VERIFY_PENDING => get_string('verify_pending', 'local_evidencehub'),
            self::VERIFY_VERIFIED => get_string('verify_verified', 'local_evidencehub'),
            self::VERIFY_REJECTED => get_string('verify_rejected', 'local_evidencehub'),
            default => throw new \coding_exception('Unknown verification state: ' . $state),
        };
    }

    /**
     * A stored calendar date (YYYYMMDD) as ISO 8601 text.
     *
     * The dates are calendar days, not instants, so they are formatted without a timezone.
     *
     * @param int|null $date The stored date, or null.
     * @return string|null The date as YYYY-MM-DD, or null when none is stored.
     */
    public static function iso_date(?int $date): ?string {
        if ($date === null) {
            return null;
        }
        return sprintf('%04d-%02d-%02d', intdiv($date, 10000), intdiv($date, 100) % 100, $date % 100);
    }

    /**
     * Whether an integer is a real calendar day written as YYYYMMDD.
     *
     * @param int $date The candidate.
     * @return bool
     */
    public static function is_valid_date(int $date): bool {
        $year = intdiv($date, 10000);
        return $year >= 1900 && $year <= 9999 && checkdate(intdiv($date, 100) % 100, $date % 100, $year);
    }

    /**
     * A calendar day some months later, clamped to the end of a shorter month.
     *
     * 2028-02-29 plus 12 months is 2029-02-28 and 2026-01-31 plus one month is 2026-02-28: a
     * certificate valid for a year from the last day of a month stays valid until the last day.
     *
     * @param int $date A valid YYYYMMDD day.
     * @param int $months How many months to add; never negative.
     * @return int The YYYYMMDD day.
     * @throws \coding_exception On an invalid date or a negative count.
     */
    public static function add_months(int $date, int $months): int {
        if (!self::is_valid_date($date) || $months < 0) {
            throw new \coding_exception('Cannot add ' . $months . ' months to ' . $date);
        }
        $index = intdiv($date, 10000) * 12 + intdiv($date, 100) % 100 - 1 + $months;
        $year = intdiv($index, 12);
        $month = $index % 12 + 1;
        $lastday = (int) (new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month)))->format('t');
        $day = min($date % 100, $lastday);
        return $year * 10000 + $month * 100 + $day;
    }

    /**
     * The calendar day an instant falls on in a timezone.
     *
     * A date selector posts midnight of the chosen day in the user's timezone; reading that
     * instant in UTC would move a UTC-3 user's day back by one.
     *
     * @param int $timestamp The instant.
     * @param string|null $timezone A timezone name; null for the current user's.
     * @return int The YYYYMMDD day.
     */
    public static function from_timestamp(int $timestamp, ?string $timezone = null): int {
        $moment = (new \DateTimeImmutable('@' . $timestamp))->setTimezone(\core_date::get_user_timezone_object($timezone));
        return (int) $moment->format('Ymd');
    }

    /**
     * Midnight of a calendar day in a timezone, as a date selector expects it.
     *
     * @param int $date A valid YYYYMMDD day.
     * @param string|null $timezone A timezone name; null for the current user's.
     * @return int The timestamp.
     * @throws \coding_exception On an invalid date.
     */
    public static function to_timestamp(int $date, ?string $timezone = null): int {
        if (!self::is_valid_date($date)) {
            throw new \coding_exception('Not a calendar date: ' . $date);
        }
        $midnight = new \DateTimeImmutable(self::iso_date($date) . ' 00:00:00', \core_date::get_user_timezone_object($timezone));
        return $midnight->getTimestamp();
    }
}
