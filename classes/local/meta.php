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
}
