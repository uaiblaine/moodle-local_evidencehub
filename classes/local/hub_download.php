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
 * The rows the hub's download hands to core's dataformat writers: the grid's columns, the grid's
 * restriction, every pair of the filter or only the ones the checkboxes posted.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class hub_download {
    /** @var int The most pairs one download may name; the checkboxes of one page name at most 100. */
    public const int MAX_PAIRS = 1000;

    /**
     * The download of a filter.
     *
     * @param \context $context The page context.
     * @param hub_query $query The grid of that context for the viewer.
     */
    public function __construct(
        /** @var \context The page context. */
        private readonly \context $context,
        /** @var hub_query The grid. */
        private readonly hub_query $query,
    ) {
    }

    /**
     * The posted "userid:competencyid" pairs, as keys.
     *
     * @param array $raw The posted values.
     * @return array "userid:competencyid" => true.
     * @throws \moodle_exception On a value of another shape or too many pairs.
     */
    public static function parse_pairs(array $raw): array {
        if (count($raw) > self::MAX_PAIRS) {
            throw new \moodle_exception('errorhubpairs', 'local_evidencehub');
        }
        $pairs = [];
        foreach ($raw as $value) {
            if (!is_string($value) || !preg_match('/^([1-9]\d*):([1-9]\d*)$/', $value, $matches)) {
                throw new \moodle_exception('errorhubpairs', 'local_evidencehub');
            }
            $pairs[(int) $matches[1] . ':' . (int) $matches[2]] = true;
        }
        return $pairs;
    }

    /**
     * The column headings, in row order.
     *
     * @return array Column key => heading.
     */
    public function columns(): array {
        $columns = ['worker' => get_string('worker', 'local_evidencehub')];
        foreach ($this->query->identity_fields() as $field) {
            $columns[$field] = \core_user\fields::get_display_name($field);
        }
        return $columns + [
            'competency' => get_string('competency', 'local_evidencehub'),
            'competencyidnumber' => get_string('competencyidnumber', 'local_evidencehub'),
            'status' => get_string('status', 'local_evidencehub'),
            'expiry' => get_string('effectiveexpiry', 'local_evidencehub'),
            'evidencecount' => get_string('evidencecount', 'local_evidencehub'),
            'type' => get_string('type', 'local_evidencehub'),
            'issuer' => get_string('issuer', 'local_evidencehub'),
        ];
    }

    /**
     * The rows of a filter: every pair, or only the posted ones that belong to the filter's set.
     *
     * A posted pair outside the filter's set (another template, a worker the viewer may not see) is
     * dropped: the set is the filter's, the posted list only narrows it.
     *
     * @param hub_filter $filter The filter.
     * @param array|null $pairs "userid:competencyid" => true, or null for every pair.
     * @return \Generator Lists of cell values, keyed as {@see self::columns()}.
     */
    public function rows(hub_filter $filter, ?array $pairs = null): \Generator {
        $rs = $this->query->rows($filter);
        try {
            foreach ($rs as $record) {
                if ($pairs !== null && !isset($pairs[$record->userid . ':' . $record->competencyid])) {
                    continue;
                }
                yield $this->row($record);
            }
        } finally {
            $rs->close();
        }
    }

    /**
     * How many rows a download holds, for its event before the stream starts.
     *
     * @param hub_filter $filter The filter.
     * @param array|null $pairs The posted pairs, or null.
     * @param array $kpis The filter's KPIs.
     * @return int
     */
    public function count(hub_filter $filter, ?array $pairs, array $kpis): int {
        if ($pairs === null) {
            return hub_kpis::pager_count($kpis, $filter);
        }
        return iterator_count($this->rows($filter, $pairs));
    }

    /**
     * One row's cells.
     *
     * @param \stdClass $record A record of hub_query::rows().
     * @return array Column key => plain text, or the evidence count; a restricted pair keeps its status
     *     and identity only: no date, count, type or issuer.
     */
    public function row(\stdClass $record): array {
        $restricted = (int) $record->estatus === status::EFFECTIVE_RESTRICTED;
        $cells = ['worker' => fullname($record, has_capability('moodle/site:viewfullnames', $this->context))];
        foreach ($this->query->identity_fields() as $field) {
            $cells[$field] = (string) ($record->$field ?? '');
        }
        $frameworkcontext = \context::instance_by_id((int) $record->frameworkcontextid);
        $system = \context_system::instance();
        return $cells + [
            'competency' => format_string($record->competencyname, true, ['context' => $frameworkcontext, 'escape' => false]),
            'competencyidnumber' => (string) $record->competencyidnumber,
            'status' => status::effective_label((int) $record->estatus),
            'expiry' => $restricted || $record->effectiveexpiry === null ? '' : meta::iso_date((int) $record->effectiveexpiry),
            'evidencecount' => $restricted ? '' : (int) ($record->evidencecount ?? 0),
            'type' => $restricted || $record->typename === null
                ? '' : format_string($record->typename, true, ['context' => $system, 'escape' => false]),
            'issuer' => $restricted ? '' : (string) ($record->issuer ?? ''),
        ];
    }
}
