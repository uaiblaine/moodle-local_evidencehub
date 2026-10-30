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

/**
 * Tests for the calendar date helpers.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_evidencehub\local;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Month arithmetic clamps to month ends, and days move to and from instants in the user's timezone.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(meta::class)]
final class meta_test extends \advanced_testcase {
    /**
     * Additions across leap days, month ends and years.
     *
     * @return array Case name => [date, months, expected].
     */
    public static function month_additions(): array {
        return [
            'leap day plus a year' => [20280229, 12, 20290228],
            'leap day plus four years' => [20280229, 48, 20320229],
            'end of January plus one' => [20260131, 1, 20260228],
            'end of January in a leap year' => [20280131, 1, 20280229],
            'thirty-first into a thirty-day month' => [20260331, 1, 20260430],
            'across the year' => [20261115, 3, 20270215],
            'two years' => [20260115, 24, 20280115],
            'nothing' => [20260115, 0, 20260115],
        ];
    }

    /**
     * Adding months keeps the day, or the last day of a shorter month.
     *
     * @param int $date The start.
     * @param int $months Months to add.
     * @param int $expected The result.
     * @return void
     */
    #[DataProvider('month_additions')]
    public function test_adding_months_clamps_to_the_month_end(int $date, int $months, int $expected): void {
        $this->assertSame($expected, meta::add_months($date, $months));
    }

    /**
     * An impossible start or a negative count is refused.
     *
     * @return void
     */
    public function test_adding_months_refuses_bad_input(): void {
        $this->expectException(\coding_exception::class);
        meta::add_months(20260230, 1);
    }

    /**
     * Only real days in a plausible range are valid.
     *
     * @return void
     */
    public function test_only_real_days_are_valid(): void {
        $this->assertTrue(meta::is_valid_date(20280229));
        $this->assertFalse(meta::is_valid_date(20270229));
        $this->assertFalse(meta::is_valid_date(20261301));
        $this->assertFalse(meta::is_valid_date(20260100));
        $this->assertFalse(meta::is_valid_date(0));
    }

    /**
     * Instants near midnight, read in timezones on both sides of UTC.
     *
     * @return array Case name => [timestamp, timezone, expected day].
     */
    public static function instants(): array {
        // 2026-03-01 02:30 UTC.
        $earlyutc = 1772332200;
        // 2026-02-28 20:00 UTC.
        $lateutc = 1772308800;
        return [
            'UTC-3 before its midnight' => [$earlyutc, 'America/Sao_Paulo', 20260228],
            'UTC' => [$earlyutc, 'UTC', 20260301],
            'UTC+9 after its midnight' => [$lateutc, 'Asia/Tokyo', 20260301],
            'UTC-3 the same evening' => [$lateutc, 'America/Sao_Paulo', 20260228],
        ];
    }

    /**
     * An instant falls on the day its timezone sees.
     *
     * @param int $timestamp The instant.
     * @param string $timezone The timezone.
     * @param int $expected The day.
     * @return void
     */
    #[DataProvider('instants')]
    public function test_an_instant_falls_on_the_day_of_its_timezone(int $timestamp, string $timezone, int $expected): void {
        $this->assertSame($expected, meta::from_timestamp($timestamp, $timezone));
    }

    /**
     * Midnight of a day round-trips in each timezone, and is a different instant in each.
     *
     * @return void
     */
    public function test_a_day_round_trips_through_midnight(): void {
        $saopaulo = meta::to_timestamp(20280229, 'America/Sao_Paulo');
        $tokyo = meta::to_timestamp(20280229, 'Asia/Tokyo');
        $this->assertSame(20280229, meta::from_timestamp($saopaulo, 'America/Sao_Paulo'));
        $this->assertSame(20280229, meta::from_timestamp($tokyo, 'Asia/Tokyo'));
        $this->assertSame(12 * HOURSECS, $saopaulo - $tokyo);
        // Read in the other timezone, the instant is another day: the helpers never mix them.
        $this->assertSame(20280228, meta::from_timestamp($saopaulo - HOURSECS, 'America/Sao_Paulo'));
    }

    /**
     * Without a timezone the current user's is used, as the date selector does.
     *
     * @return void
     */
    public function test_the_current_users_timezone_is_the_default(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(['timezone' => 'Asia/Tokyo']);
        $this->setUser($user);
        $this->assertSame(meta::to_timestamp(20260115, 'Asia/Tokyo'), meta::to_timestamp(20260115));
        $this->assertNotSame(meta::to_timestamp(20260115, 'America/Sao_Paulo'), meta::to_timestamp(20260115));
    }

    /**
     * Day arithmetic across month ends, leap days and years.
     *
     * @return array Case name => [date, days, expected].
     */
    public static function day_additions(): array {
        return [
            'into the next month' => [20260630, 1, 20260701],
            'onto a leap day' => [20280228, 1, 20280229],
            'over a leap day' => [20280228, 2, 20280301],
            'back over a year' => [20260101, -1, 20251231],
            'sixty days back' => [20260814, -60, 20260615],
            'nothing' => [20260115, 0, 20260115],
        ];
    }

    /**
     * Adding days moves the calendar day, and days_between() measures it back.
     *
     * @param int $date The start.
     * @param int $days Days to add.
     * @param int $expected The result.
     * @return void
     */
    #[DataProvider('day_additions')]
    public function test_day_arithmetic(int $date, int $days, int $expected): void {
        $this->assertSame($expected, meta::add_days($date, $days));
        $this->assertSame($days, meta::days_between($date, $expected));
        $this->assertSame(-$days, meta::days_between($expected, $date));
    }

    /**
     * Day arithmetic ignores the timezones' daylight saving changes.
     *
     * @return void
     */
    public function test_day_arithmetic_ignores_daylight_saving(): void {
        $this->resetAfterTest();
        $this->setTimezone('Europe/London', 'Europe/London');
        // Europe moves its clocks on 2026-03-29 and 2026-10-25.
        $this->assertSame(20260330, meta::add_days(20260328, 2));
        $this->assertSame(31, meta::days_between(20261001, 20261101));
    }

    /**
     * An impossible day is refused.
     *
     * @return void
     */
    public function test_day_arithmetic_refuses_bad_input(): void {
        $this->expectException(\coding_exception::class);
        meta::add_days(20260230, 1);
    }
}
