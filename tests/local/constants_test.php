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
 * Tests for the stored constants and their labels.
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
 * Every stored value has a label, values never collide, and an unknown one is refused.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(status::class)]
#[CoversClass(meta::class)]
#[CoversClass(audit::class)]
#[CoversClass(policy::class)]
#[CoversClass(export::class)]
final class constants_test extends \advanced_testcase {
    /**
     * The labelled constant groups.
     *
     * @return array Case name => [class, constant prefix, label method].
     */
    public static function labelled_groups(): array {
        return [
            'status' => [status::class, 'STATUS_', 'label'],
            'verification' => [meta::class, 'VERIFY_', 'verify_label'],
            'audit action' => [audit::class, 'ACTION_', 'label'],
            'policy kind' => [policy::class, 'KIND_', 'kind_label'],
            'expiry behaviour' => [policy::class, 'ONEXPIRY_', 'onexpiry_label'],
            'policy source' => [policy::class, 'SOURCE_', 'source_label'],
            'export state' => [export::class, 'STATUS_', 'label'],
        ];
    }

    /**
     * Each constant of a group has a distinct value and a distinct, real label.
     *
     * @param string $class The class.
     * @param string $prefix The constant prefix of the group.
     * @param string $method The label method.
     * @return void
     */
    #[DataProvider('labelled_groups')]
    public function test_every_constant_has_its_own_label(string $class, string $prefix, string $method): void {
        $constants = array_filter(
            (new \ReflectionClass($class))->getConstants(),
            fn(string $name): bool => str_starts_with($name, $prefix),
            ARRAY_FILTER_USE_KEY
        );
        $this->assertNotEmpty($constants);
        $this->assertSame(count($constants), count(array_unique($constants)));
        $labels = array_map([$class, $method], $constants);
        $this->assertSame(count($labels), count(array_unique($labels)));
        foreach ($labels as $label) {
            $this->assertStringNotContainsString('[[', $label);
        }
    }

    /**
     * An unknown value is refused rather than shown as something else.
     *
     * @return void
     */
    public function test_an_unknown_value_is_refused(): void {
        $this->expectException(\coding_exception::class);
        status::label(99);
    }

    /**
     * "Missing" has a label of its own although no row stores it.
     *
     * @return void
     */
    public function test_missing_is_labelled_apart(): void {
        $this->assertSame(get_string('status_missing', 'local_evidencehub'), status::label(null));
        $this->assertNotContains(null, (new \ReflectionClass(status::class))->getConstants());
    }

    /**
     * Stored calendar dates come out as ISO 8601 days.
     *
     * @return void
     */
    public function test_calendar_dates_format_as_iso_days(): void {
        $this->assertSame('2026-02-28', meta::iso_date(20260228));
        $this->assertSame('2028-02-29', meta::iso_date(20280229));
        $this->assertNull(meta::iso_date(null));
    }

    /**
     * Every status, and a pair without a row, has a badge that states its text colour.
     *
     * @return void
     */
    public function test_every_status_badge_states_its_text_colour(): void {
        $statuses = array_values(array_filter(
            (new \ReflectionClass(status::class))->getConstants(),
            fn(string $name): bool => str_starts_with($name, 'STATUS_'),
            ARRAY_FILTER_USE_KEY
        ));
        foreach (array_merge([null], $statuses) as $value) {
            $this->assertMatchesRegularExpression('/^bg-\w+ text-(white|dark)$/', status::badge_class($value));
        }
    }
}
