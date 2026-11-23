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

namespace local_evidencehub\reportbuilder\local\filters;

use local_evidencehub\local\meta;
use local_evidencehub\local\status_repository;

/**
 * "Valid until" as one choice: expired, or valid until a day within 30, 60 or 90 days from today.
 *
 * Core's number filter would store absolute days ("less than or equal to 20261223"), which go stale the next
 * morning, and it compares 0 like any number, while a pair with no day (missing, restricted, or no expiry)
 * reads 0 here. So the stored value is the choice's name and the days are worked out when the SQL is built,
 * "today" being the site's calendar day from core's clock ({@see status_repository::site_day()}). A pair
 * whose field reads 0 matches no choice.
 *
 * The field is the entity's masked effective expiry ({@see \local_evidencehub\reportbuilder\local\entities\validity}),
 * which carries no parameters: the restriction lives in a join. Each condition reads the field once all the
 * same, so a field with parameters would still name each of them once.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class validuntil extends reader_select {
    /** @var string The choice of the pairs whose day has passed. */
    public const string EXPIRED = 'expired';

    /** @var array The windows offered, in days from today, both ends included. */
    public const array WITHIN = [30, 60, 90];

    /**
     * The choices, in the order the panel lists them.
     *
     * @return array Choice => label.
     */
    public static function choices(): array {
        $choices = [self::EXPIRED => get_string('validuntil_expired', 'local_evidencehub')];
        foreach (self::WITHIN as $days) {
            $choices[self::within($days)] = get_string('validuntil_within', 'local_evidencehub', $days);
        }
        return $choices;
    }

    /**
     * The name of the choice "within some days".
     *
     * @param int $days One of WITHIN.
     * @return string
     */
    public static function within(int $days): string {
        return 'within' . $days;
    }

    /**
     * Today, the site's calendar day.
     *
     * @return int The YYYYMMDD day.
     */
    public static function today(): int {
        return status_repository::site_day(\core\di::get(\core\clock::class)->time());
    }

    /**
     * The SQL true of a field holding a day that the choice selects.
     *
     * Integers only, computed here: no parameters. "Expired" is a day before today; "within n days" is a day
     * from today to today plus n, both included. A field reading 0 matches neither.
     *
     * @param string $field The SQL of the YYYYMMDD day, 0 for none.
     * @param string $choice A key of {@see self::choices()}.
     * @param int $today Today, YYYYMMDD.
     * @return string The condition, or '' for an unknown choice.
     */
    public static function condition(string $field, string $choice, int $today): string {
        if ($choice === self::EXPIRED) {
            return "{$field} BETWEEN 1 AND " . meta::add_days($today, -1);
        }
        foreach (self::WITHIN as $days) {
            if ($choice === self::within($days)) {
                return "{$field} BETWEEN {$today} AND " . meta::add_days($today, $days);
            }
        }
        return '';
    }

    /**
     * The condition of the chosen window; only "is equal to" filters.
     *
     * @param array $values The filter's values: its operator and value.
     * @return array [sql, params], or ['', []] when the filter filters nothing.
     */
    #[\Override]
    public function get_sql_filter(array $values): array {
        $operator = (int) ($values["{$this->name}_operator"] ?? self::ANY_VALUE);
        $value = (string) ($values["{$this->name}_value"] ?? '');
        if ($operator !== self::EQUAL_TO || !array_key_exists($value, $this->get_select_options())) {
            return ['', []];
        }
        return [self::condition($this->filter->get_field_sql(), $value, self::today()), $this->filter->get_field_params()];
    }
}
