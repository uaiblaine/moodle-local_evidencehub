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
use local_evidencehub\local\meta;
use local_evidencehub\local\status;

/**
 * The materialised validity of a (worker, competency) pair: one local_evidencehub_status row.
 *
 * Every value a sensitive deciding evidence decides (status, expiry, evidence count, when it changed)
 * reads "Restricted" when that evidence is restricted for the user the report is built for, in SQL,
 * so filters and sorting see the restricted value too. The datasource works the restriction out per
 * worker ({@see \local_evidencehub\reportbuilder\datasource\validity}) and hands the condition in.
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
     */
    public function __construct(
        /** @var string SQL true for a restricted row. */
        private readonly string $restricted,
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
            ->add_field($this->masked("{$s}.status", (string) status::EFFECTIVE_RESTRICTED), 'status')
            ->set_is_sortable(true)
            ->add_callback(static fn($value): string => $value === null ? '' : status::effective_label((int) $value));

        $columns[] = (new column('effectiveexpiry', new lang_string('effectiveexpiry', 'local_evidencehub'), $entity))
            ->set_type(column::TYPE_INTEGER)
            ->add_field($this->masked("{$s}.effectiveexpiry"), 'effectiveexpiry')
            ->add_field($this->restricted_flag(), 'restricted')
            ->set_is_sortable(true)
            ->add_callback([self::class, 'format_day']);

        $columns[] = (new column('evidencecount', new lang_string('evidencecount', 'local_evidencehub'), $entity))
            ->set_type(column::TYPE_INTEGER)
            ->add_field($this->masked("{$s}.evidencecount"), 'evidencecount')
            ->add_field($this->restricted_flag(), 'restricted')
            ->set_is_sortable(true)
            ->add_callback(static function ($value, \stdClass $row): string {
                if (!empty($row->restricted)) {
                    return get_string('validityrestricted', 'local_evidencehub');
                }
                return $value === null ? '' : (string) $value;
            });

        $columns[] = (new column('timechanged', new lang_string('timechanged', 'local_evidencehub'), $entity))
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_field($this->masked("{$s}.timechanged"), 'timechanged')
            ->add_field($this->restricted_flag(), 'restricted')
            ->set_is_sortable(true)
            ->add_callback(static function ($value, \stdClass $row): string {
                if (!empty($row->restricted)) {
                    return get_string('validityrestricted', 'local_evidencehub');
                }
                return $value === null ? '' : userdate((int) $value);
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
     * The filters.
     *
     * @return array
     */
    #[\Override]
    protected function get_available_filters(): array {
        $s = $this->get_table_alias('local_evidencehub_status');
        return [
            (new filter(
                select::class,
                'status',
                new lang_string('status', 'local_evidencehub'),
                $this->get_entity_name(),
                $this->masked("{$s}.status", (string) status::EFFECTIVE_RESTRICTED)
            ))->set_options_callback(static function (): array {
                $options = [];
                foreach (status::effective_codes() as $code) {
                    // No row stores "missing".
                    if ($code === status::EFFECTIVE_MISSING) {
                        continue;
                    }
                    $options[$code] = status::effective_label($code);
                }
                return $options;
            }),
        ];
    }
}
