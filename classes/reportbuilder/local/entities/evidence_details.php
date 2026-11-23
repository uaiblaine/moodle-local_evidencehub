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

/**
 * The details of the evidence that decides a pair's validity, and its type.
 *
 * Under the same per-worker rule as {@see validity}: when the deciding evidence is restricted for the
 * user the report is built for, its type, issuer, dates and verification read "Restricted", in SQL,
 * so neither a filter nor a sort can pick a restricted pair out by any of them.
 *
 * Over the hub's pair set ({@see \local_evidencehub\reportbuilder\local\systemreports\hub_pairs}) a restricted
 * pair's cells are left empty, as the hub's grid leaves them, and the type and issuer sort on a value
 * rather than NULL, so the order is the same on every database.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class evidence_details extends base {
    /**
     * The entity, given the condition under which a row is restricted.
     *
     * @param string $restricted SQL true for a row whose deciding evidence the user may not see.
     * @param bool $pairs Whether the tables are LEFT joined to the hub's pair set.
     */
    public function __construct(
        /** @var string SQL true for a restricted row. */
        private readonly string $restricted,
        /** @var bool Whether the tables are LEFT joined to the pair set. */
        private readonly bool $pairs = false,
    ) {
    }

    /**
     * The tables the entity reads.
     *
     * @return array
     */
    #[\Override]
    protected function get_default_tables(): array {
        return ['local_evidencehub_meta', 'local_evidencehub_type'];
    }

    /**
     * The entity's title.
     *
     * @return lang_string
     */
    #[\Override]
    protected function get_default_entity_title(): lang_string {
        return new lang_string('entity:details', 'local_evidencehub');
    }

    /**
     * An expression, or NULL on a restricted row.
     *
     * @param string $expression The SQL expression.
     * @return string
     */
    private function masked(string $expression): string {
        return "CASE WHEN {$this->restricted} THEN NULL ELSE {$expression} END";
    }

    /**
     * A text column's value: masked, and over the pair set never NULL, so it sorts the same on every database.
     *
     * @param string $expression The SQL expression.
     * @return string
     */
    private function text(string $expression): string {
        return $this->pairs ? "COALESCE(" . $this->masked($expression) . ", '')" : $this->masked($expression);
    }

    /**
     * A number column's value: masked, and over the pair set never NULL, so it sorts the same on every database.
     *
     * @param string $expression The SQL expression.
     * @param int $empty The value of a missing pair and, masked, of a restricted one: one no row stores.
     * @return string
     */
    private function number(string $expression, int $empty): string {
        return $this->pairs ? "COALESCE(" . $this->masked($expression) . ", {$empty})" : $this->masked($expression);
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
        $m = $this->get_table_alias('local_evidencehub_meta');
        $t = $this->get_table_alias('local_evidencehub_type');
        $entity = $this->get_entity_name();
        $columns = [];
        // Over the pair set the status cell says "Restricted"; the others stay empty, as in the hub's grid.
        $restrictedtext = $this->pairs ? '' : get_string('validityrestricted', 'local_evidencehub');

        $columns[] = (new column('type', new lang_string('type', 'local_evidencehub'), $entity))
            ->add_field($this->text("{$t}.name"), 'name')
            ->add_field($this->restricted_flag(), 'restricted')
            ->set_is_sortable(true)
            ->add_callback(static function ($value, \stdClass $row) use ($restrictedtext): string {
                if (!empty($row->restricted)) {
                    return $restrictedtext;
                }
                $context = \context_system::instance();
                return $value === null ? '' : format_string($value, true, ['context' => $context, 'escape' => false]);
            });

        $columns[] = (new column('issuer', new lang_string('issuer', 'local_evidencehub'), $entity))
            ->add_field($this->text("{$m}.issuer"), 'issuer')
            ->add_field($this->restricted_flag(), 'restricted')
            ->set_is_sortable(true)
            ->add_callback(static function ($value, \stdClass $row) use ($restrictedtext): string {
                if (!empty($row->restricted)) {
                    return $restrictedtext;
                }
                return s((string) $value);
            });

        foreach (['issuedate', 'expirydate'] as $name) {
            $columns[] = (new column($name, new lang_string($name, 'local_evidencehub'), $entity))
                ->set_type(column::TYPE_INTEGER)
                ->add_field($this->number("{$m}.{$name}", 0), $name)
                ->add_field($this->restricted_flag(), 'restricted')
                ->set_is_sortable(true)
                ->add_callback($this->pairs ? [validity::class, 'format_pair_day'] : [validity::class, 'format_day']);
        }

        $columns[] = (new column('verifystatus', new lang_string('verification', 'local_evidencehub'), $entity))
            ->set_type(column::TYPE_INTEGER)
            ->add_field($this->number("{$m}.verifystatus", -1), 'verifystatus')
            ->add_field($this->restricted_flag(), 'restricted')
            ->set_is_sortable(true)
            ->add_callback(static function ($value, \stdClass $row) use ($restrictedtext): string {
                if (!empty($row->restricted)) {
                    return $restrictedtext;
                }
                // Over the pair set a pair without a deciding evidence reads -1.
                return $value === null || (int) $value < 0 ? '' : meta::verify_label((int) $value);
            });

        return $columns;
    }

    /**
     * The filters.
     *
     * @return array
     */
    #[\Override]
    protected function get_available_filters(): array {
        $m = $this->get_table_alias('local_evidencehub_meta');
        $t = $this->get_table_alias('local_evidencehub_type');
        return [
            (new filter(
                select::class,
                'type',
                new lang_string('type', 'local_evidencehub'),
                $this->get_entity_name(),
                $this->masked("{$t}.id")
            ))
                ->set_options_callback(static function (): array {
                    global $DB;
                    $options = [];
                    foreach ($DB->get_records('local_evidencehub_type', null, 'sortorder, id', 'id, name') as $type) {
                        $options[(int) $type->id] = format_string($type->name, true, ['context' => \context_system::instance()]);
                    }
                    return $options;
                }),
            (new filter(
                select::class,
                'verifystatus',
                new lang_string('verification', 'local_evidencehub'),
                $this->get_entity_name(),
                $this->masked("{$m}.verifystatus")
            ))->set_options_callback(static fn(): array => [
                meta::VERIFY_PENDING => meta::verify_label(meta::VERIFY_PENDING),
                meta::VERIFY_VERIFIED => meta::verify_label(meta::VERIFY_VERIFIED),
                meta::VERIFY_REJECTED => meta::verify_label(meta::VERIFY_REJECTED),
            ]),
        ];
    }
}
