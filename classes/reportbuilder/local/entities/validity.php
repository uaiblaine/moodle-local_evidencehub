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

namespace local_evidencehub\reportbuilder\local\entities;

use core\lang_string;
use core_reportbuilder\local\entities\base;
use core_reportbuilder\local\filters\select;
use core_reportbuilder\local\report\column;
use core_reportbuilder\local\report\filter;
use local_evidencehub\local\hub_query;
use local_evidencehub\local\meta;
use local_evidencehub\local\status;
use local_evidencehub\reportbuilder\local\filters\reader_select;
use local_evidencehub\reportbuilder\local\filters\validuntil;

/**
 * The materialised validity of a (worker, competency) pair: one local_evidencehub_status row.
 *
 * Every value a sensitive deciding evidence decides (status, expiry, evidence count, when it changed)
 * reads "Restricted" when that evidence is restricted for the user the report is built for, in SQL,
 * so filters and sorting see the restricted value too. The datasource works the restriction out per
 * worker ({@see \local_evidencehub\reportbuilder\datasource\validity}) and hands the condition in.
 *
 * Over the hub's pair set ({@see \local_evidencehub\reportbuilder\local\systemreports\hub_pairs}) the row
 * is LEFT joined, so the entity reads the effective status there: no row is "missing", in the column, its
 * sort and its filter, every sort key is a value rather than NULL, and a restricted pair's other cells are
 * left empty as the hub's grid leaves them.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class validity extends base {
    /**
     * The entity, given the condition under which a row is restricted.
     *
     * @param string $restricted SQL true for a row whose deciding evidence the user may not see.
     * @param bool $pairs Whether the table is LEFT joined to the hub's pair set, which reads the effective status.
     */
    public function __construct(
        /** @var string SQL true for a restricted row. */
        private readonly string $restricted,
        /** @var bool Whether the table is LEFT joined to the pair set. */
        private readonly bool $pairs = false,
    ) {
    }

    /**
     * The table the entity reads.
     *
     * @return array
     */
    #[\Override]
    protected function get_default_tables(): array {
        return ['local_evidencehub_status'];
    }

    /**
     * The entity's title.
     *
     * @return lang_string
     */
    #[\Override]
    protected function get_default_entity_title(): lang_string {
        return new lang_string('entity:validity', 'local_evidencehub');
    }

    /**
     * An expression, or the restricted value on a restricted row.
     *
     * @param string $expression The SQL expression.
     * @param string $restricted The SQL of the value a restricted row reads.
     * @return string
     */
    private function masked(string $expression, string $restricted = 'NULL'): string {
        return "CASE WHEN {$this->restricted} THEN {$restricted} ELSE {$expression} END";
    }

    /**
     * A column's value: masked, and over the pair set never NULL, so it sorts the same on every database.
     *
     * @param string $expression The SQL expression.
     * @param string $empty The SQL of the value of a missing pair and, masked, of a restricted one.
     * @return string
     */
    private function value(string $expression, string $empty): string {
        return $this->pairs ? $this->masked("COALESCE({$expression}, {$empty})", $empty) : $this->masked($expression);
    }

    /**
     * The effective status: restricted, the stored status, or over the pair set "missing" for no row.
     *
     * @return string SQL.
     */
    private function status_sql(): string {
        $s = $this->get_table_alias('local_evidencehub_status');
        $status = $this->pairs ? "COALESCE({$s}.status, " . status::EFFECTIVE_MISSING . ")" : "{$s}.status";
        return $this->masked($status, (string) status::EFFECTIVE_RESTRICTED);
    }

    /**
     * The effective status as SQL, for a reader of the pair set that needs it beside the columns: the row's
     * status code whatever columns the reader chose ({@see \local_evidencehub\reportbuilder\local\systemreports\hub_pairs}).
     *
     * @return string SQL; no parameters.
     */
    public function effective_status_sql(): string {
        return $this->status_sql();
    }

    /**
     * The effective expiry as the "valid until" filter reads it: masked, 0 for none, as its column reads it.
     *
     * @return string SQL; no parameters.
     */
    private function expiry_sql(): string {
        return $this->value("{$this->get_table_alias('local_evidencehub_status')}.effectiveexpiry", '0');
    }

    /**
     * The "valid until" sort key over the pair set: what the hub's pair set sorts by
     * ({@see \local_evidencehub\local\hub_query::base_sql()}, its eexpiry). A restricted pair and a missing one read 0,
     * the most urgent; a pair with a status and no effective expiry reads {@see hub_query::NO_EXPIRY}, after every
     * day; any other pair its day. The column's value and the "valid until" filter keep reading 0 for a pair with
     * no day ({@see self::expiry_sql()}), so no window matches it.
     *
     * @return string SQL; no parameters.
     */
    public function expiry_sort_sql(): string {
        $s = $this->get_table_alias('local_evidencehub_status');
        return $this->masked(
            "CASE WHEN {$s}.status IS NULL THEN 0 ELSE COALESCE({$s}.effectiveexpiry, " . hub_query::NO_EXPIRY . ") END",
            '0'
        );
    }

    /**
     * What a restricted cell reads: "Restricted", or nothing over the pair set, whose status cell says it.
     *
     * @return string
     */
    private function restricted_text(): string {
        return $this->pairs ? '' : get_string('validityrestricted', 'local_evidencehub');
    }

    /**
     * 1 for a restricted row, else 0.
     *
     * @return string SQL.
     */
    private function restricted_flag(): string {
        return "CASE WHEN {$this->restricted} THEN 1 ELSE 0 END";
    }

    /**
     * The columns.
     *
     * @return array
     */
    #[\Override]
    protected function get_available_columns(): array {
        $s = $this->get_table_alias('local_evidencehub_status');
        $entity = $this->get_entity_name();
        $columns = [];

        $columns[] = (new column('status', new lang_string('status', 'local_evidencehub'), $entity))
            ->set_type(column::TYPE_INTEGER)
            ->add_field($this->status_sql(), 'status')
            ->set_is_sortable(true)
            ->add_callback(static fn($value): string => $value === null ? '' : status::effective_label((int) $value));

        $columns[] = (new column('effectiveexpiry', new lang_string('effectiveexpiry', 'local_evidencehub'), $entity))
            ->set_type(column::TYPE_INTEGER)
            ->add_field($this->value("{$s}.effectiveexpiry", '0'), 'effectiveexpiry')
            ->add_field($this->restricted_flag(), 'restricted')
            ->set_is_sortable(true)
            ->add_callback($this->pairs ? [self::class, 'format_pair_day'] : [self::class, 'format_day']);

        $restrictedtext = $this->restricted_text();
        $columns[] = (new column('evidencecount', new lang_string('evidencecount', 'local_evidencehub'), $entity))
            ->set_type(column::TYPE_INTEGER)
            ->add_field($this->value("{$s}.evidencecount", '0'), 'evidencecount')
            ->add_field($this->restricted_flag(), 'restricted')
            ->set_is_sortable(true)
            ->add_callback(static function ($value, \stdClass $row) use ($restrictedtext): string {
                if (!empty($row->restricted)) {
                    return $restrictedtext;
                }
                return $value === null ? '' : (string) $value;
            });

        $columns[] = (new column('timechanged', new lang_string('timechanged', 'local_evidencehub'), $entity))
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_field($this->value("{$s}.timechanged", '0'), 'timechanged')
            ->add_field($this->restricted_flag(), 'restricted')
            ->set_is_sortable(true)
            ->add_callback(static function ($value, \stdClass $row) use ($restrictedtext): string {
                if (!empty($row->restricted)) {
                    return $restrictedtext;
                }
                return empty($value) ? '' : userdate((int) $value);
            });

        return $columns;
    }

    /**
     * A stored calendar day as ISO text, or "Restricted".
     *
     * Shared with {@see evidence_details}.
     *
     * @param mixed $value The YYYYMMDD day, or null.
     * @param \stdClass $row The column's fields, with restricted.
     * @return string
     */
    public static function format_day($value, \stdClass $row): string {
        if (!empty($row->restricted)) {
            return get_string('validityrestricted', 'local_evidencehub');
        }
        return $value === null ? '' : (string) meta::iso_date((int) $value);
    }

    /**
     * A pair's effective expiry as ISO text, or nothing for a restricted pair or one without a day.
     *
     * @param mixed $value The YYYYMMDD day, 0 for none.
     * @param \stdClass $row The column's fields, with restricted.
     * @return string
     */
    public static function format_pair_day($value, \stdClass $row): string {
        if (!empty($row->restricted) || empty($value)) {
            return '';
        }
        return (string) meta::iso_date((int) $value);
    }

    /**
     * The filters.
     *
     * @return array
     */
    #[\Override]
    protected function get_available_filters(): array {
        $pairs = $this->pairs;
        $filters = [
            (new filter(
                // Core's select caches its options per filter name for the process, and this name's options
                // differ between the datasource and the pair set.
                $pairs ? reader_select::class : select::class,
                'status',
                new lang_string('status', 'local_evidencehub'),
                $this->get_entity_name(),
                $this->status_sql()
            ))->set_options_callback(static function () use ($pairs): array {
                $options = [];
                foreach (status::effective_codes() as $code) {
                    // No row stores "missing": only the pair set has it.
                    if ($code === status::EFFECTIVE_MISSING && !$pairs) {
                        continue;
                    }
                    $options[$code] = status::effective_label($code);
                }
                return $options;
            }),
        ];
        if ($pairs) {
            // Over the pair set only: a missing pair, which only the pair set has, reads 0 and matches no choice.
            $filters[] = (new filter(
                validuntil::class,
                'validuntil',
                new lang_string('effectiveexpiry', 'local_evidencehub'),
                $this->get_entity_name(),
                $this->expiry_sql()
            ))
                ->set_limited_operators([select::ANY_VALUE, select::EQUAL_TO])
                ->set_options_callback(static fn(): array => validuntil::choices());
        }
        return $filters;
    }
}
