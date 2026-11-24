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

namespace local_evidencehub\reportbuilder\local\systemreports;

use core_reportbuilder\local\helpers\database;
use core_reportbuilder\local\report\filter;
use core_reportbuilder\system_report;
use core_reportbuilder\table\system_report_table;

/**
 * Core's system report table for the hub's pairs, read without rendering it.
 *
 * The table core's dynamic table and its download build, so the rows carry the report's base conditions (the
 * template, the reader's userevidenceview cut, the restricted rule), the reader's stored filters and the
 * formatters: the headless rows ({@see \local_evidencehub\local\reportengine}) cannot widen what the report
 * shows. Four additions only: a way to append a condition to the table's WHERE, which can only narrow it,
 * that WHERE rebuilt without one filter for the facet counts, a page read whose cost does not grow with
 * the page number, and a count of the rows that the page read can be given instead of counting again.
 *
 * A row is a pair (worker, competency), which has no id of its own: the page is keyed by both parts
 * ({@see hub_pairs::pair_key_sql()}), read in the base fields as userid and competencyid.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class headless_table extends system_report_table {
    /** @var array Each condition {@see self::narrow()} appended: [the SQL, its parameters]. */
    protected $narrowings = [];

    /** @var array|null The reader's stored filter values, read once for every WHERE rebuilt. */
    protected $filtervalues = null;

    /** @var int|null The rows' total when the caller already knows it ({@see self::know_total()}). */
    protected $knowntotal = null;

    /**
     * The report this table was built from.
     *
     * @return system_report
     */
    public function get_report(): system_report {
        return $this->report;
    }

    /**
     * Append a condition to the table's WHERE clause, with AND.
     *
     * @param string $sql The condition, its parameters named.
     * @param array $params Its parameters, under names of their own.
     * @return void
     */
    public function narrow(string $sql, array $params): void {
        $this->sql->where = "({$this->sql->where}) AND ({$sql})";
        $this->sql->params = array_merge($this->sql->params, $params);
        $this->narrowings[] = [$sql, $params];
    }

    /**
     * The reader's stored filter values, read from core's store once per table.
     *
     * @return array
     */
    public function filter_values(): array {
        $this->filtervalues ??= $this->report->get_filter_values();
        return $this->filtervalues;
    }

    /**
     * The table's WHERE clause without one filter's condition: what the rows would be under every other
     * condition, for the panel's facet counts.
     *
     * Rebuilt as core's base_report_table::init_sql() builds the table's own (the report's base condition, then
     * each active filter's SQL for the reader's stored values, each with parameters of its own), then the
     * conditions {@see self::narrow()} appended, in order; the filter named is left out, and with a name no
     * filter has it is the table's own WHERE. The FROM is the table's: a filter left out keeps any join it
     * brought, which joins to one row or none and so narrows nothing.
     *
     * @param string $exclude The unique identifier of the filter to leave out.
     * @return array [the WHERE clause, its parameters].
     */
    public function where_without(string $exclude): array {
        [$base, $params] = $this->report->get_base_condition();
        $wheres = $base !== '' ? ["({$base})"] : [];
        $values = $this->filter_values();
        $index = 0;
        foreach ($this->report->get_active_filters() as $identifier => $filter) {
            $index++;
            if ($identifier === $exclude) {
                continue;
            }
            $class = $filter->get_filter_class();
            [$sql, $filterparams] = $class::create($filter)->get_sql_filter($values);
            if ($sql === '') {
                continue;
            }
            [$sql, $filterparams] = database::sql_replace_parameters(
                $sql,
                $filterparams,
                static fn(string $name): string => "{$name}_fw{$index}"
            );
            $wheres[] = "({$sql})";
            $params = array_merge($params, $filterparams);
        }
        foreach ($this->narrowings as [$sql, $narrowparams]) {
            $wheres[] = "({$sql})";
            $params = array_merge($params, $narrowparams);
        }
        return [$wheres ? implode(' AND ', $wheres) : '1=1', $params];
    }

    /**
     * The table's FROM with the filters' joins added, those it does not carry yet.
     *
     * Core joins a filter's tables only while the filter applies, and a facet counts by a filter's field
     * whether it applies or not.
     *
     * @param filter ...$filters The filters.
     * @return string The FROM clause.
     */
    public function from_with(filter ...$filters): string {
        $from = $this->sql->from;
        foreach ($filters as $filter) {
            foreach (array_unique($filter->get_joins()) as $join) {
                if (!str_contains($from, $join)) {
                    $from .= ' ' . $join;
                }
            }
        }
        return $from;
    }

    /**
     * Give the rows' total, so the next page reads send no count: a total the caller kept from an earlier read of
     * the same rows ({@see \local_evidencehub\local\reportengine::rows()}). Null counts again.
     *
     * @param int|null $total The total, or null.
     * @return void
     */
    public function know_total(?int $total): void {
        $this->knowntotal = $total;
    }

    /**
     * How many rows the table lists: one count over the table's FROM and WHERE, with no column, no order and no
     * window.
     *
     * Every join a condition does not read is a LEFT join on a primary key, which each database drops from a
     * statement that reads none of its columns, so this counts the pair set, the status rows and what the conditions
     * name. A table that groups its rows is counted as core counts it.
     *
     * @return int
     */
    public function count_rows(): int {
        global $DB;
        if ($this->groupbysql !== '') {
            return $this->get_total_row_count();
        }
        return (int) $DB->count_records_sql(
            "SELECT COUNT(1) FROM {$this->sql->from} WHERE {$this->sql->where}",
            $this->sql->params
        );
    }

    /**
     * Read one page in two statements: the page's pairs and the total, then those pairs whole.
     *
     * Core's own read ({@see \core_reportbuilder\table\base_report_table::query_db()}) selects every column
     * under COUNT(1) OVER() with LIMIT and OFFSET, so the database computes each column of every pair before
     * the page. The first statement here selects only the pair's key over the same SQL, which leaves the
     * database nothing to compute but the sort keys; the second reads the page's pairs whole, with the
     * table's own conditions as well, and puts them in the first statement's order, leaving out a pair that
     * went away between the two.
     *
     * The count follows core's {@see \moodle_database::get_counted_recordset_sql()}: a window count where the
     * database offers one, a count of its own first where it does not. A total given by {@see self::know_total()}
     * replaces it: the window count makes the database join and hold every row before it sorts the page.
     *
     * @param int $pagesize Rows per page.
     * @param bool $useinitialsbar Unused, as in core's report table.
     * @return void
     */
    public function query_db($pagesize, $useinitialsbar = true): void {
        global $DB;

        $this->pagesize($pagesize, 0);
        $tablesql = $this->get_table_sql(false);
        $countalias = database::generate_alias();
        $count = '';
        if ($this->knowntotal === null) {
            $count = $DB->is_count_window_function_supported()
                ? 'COUNT(1) OVER()'
                : (string) $DB->count_records_sql("SELECT COUNT(1) FROM ({$tablesql}) results", $this->sql->params);
            $count = ", {$count} AS {$countalias}";
        }
        $keys = [];
        $total = 0;
        // A recordset, because a worker heads many rows; with a limit it is one statement on every family.
        $recordset = $DB->get_recordset_sql(
            "SELECT results.userid, results.competencyid{$count}
               FROM ({$tablesql}) results
           ORDER BY {$this->page_order()}",
            $this->sql->params,
            (int) $this->get_page_start(),
            (int) $this->get_page_size()
        );
        foreach ($recordset as $record) {
            $keys[] = [(int) $record->userid, (int) $record->competencyid];
            if ($this->knowntotal === null) {
                $total = (int) $record->{$countalias};
            }
        }
        $recordset->close();
        $total = $this->knowntotal ?? $total;
        $this->pagesize($pagesize, $total);

        $rows = $keys ? $this->read_pairs($keys) : [];
        $this->rawdata = [];
        foreach ($keys as [$userid, $competencyid]) {
            if (isset($rows[$userid . ':' . $competencyid])) {
                $this->rawdata[] = $rows[$userid . ':' . $competencyid];
            }
        }
    }

    /**
     * The second statement: the page's pairs whole, under the table's own conditions, named exactly.
     *
     * Each pair is one "(userid = :u AND competencyid = :c)" term of an OR, every placeholder named once. The
     * page's workers and competencies are also listed as two IN lists: they say nothing the OR does not, but
     * they give each database a condition on one table it can read an index by, which an OR spanning two
     * tables does not give MariaDB's range optimiser.
     *
     * @param array $keys The page's keys, each [userid, competencyid]; at most a page (100).
     * @return array "userid:competencyid" => the row.
     */
    protected function read_pairs(array $keys): array {
        global $DB;
        [$usersql, $competencysql] = $this->report->pair_key_sql();
        [$userin, $userparams] = $DB->get_in_or_equal(
            array_values(array_unique(array_column($keys, 0))),
            SQL_PARAMS_NAMED,
            database::generate_param_name() . '_'
        );
        [$competencyin, $competencyparams] = $DB->get_in_or_equal(
            array_values(array_unique(array_column($keys, 1))),
            SQL_PARAMS_NAMED,
            database::generate_param_name() . '_'
        );
        $terms = [];
        $pairparams = [];
        foreach ($keys as [$userid, $competencyid]) {
            $u = database::generate_param_name();
            $c = database::generate_param_name();
            $terms[] = "({$usersql} = :{$u} AND {$competencysql} = :{$c})";
            $pairparams[$u] = $userid;
            $pairparams[$c] = $competencyid;
        }
        // With a limit, as core's own read has: PostgreSQL's driver reads a recordset with no limit through a
        // cursor, three statements where this is one.
        $recordset = $DB->get_recordset_sql(
            "SELECT {$this->sql->fields} FROM {$this->sql->from}
              WHERE ({$this->sql->where}) AND {$usersql} {$userin} AND {$competencysql} {$competencyin}
                    AND (" . implode(' OR ', $terms) . ") {$this->groupbysql}",
            $this->sql->params + $userparams + $competencyparams + $pairparams,
            0,
            count($keys)
        );
        $rows = [];
        foreach ($recordset as $record) {
            $rows[$record->userid . ':' . $record->competencyid] = $record;
        }
        $recordset->close();
        return $rows;
    }

    /**
     * The ORDER BY of the page's pairs: the reader's sort, then both parts of the pair's key.
     *
     * Each page is a statement of its own, and pairs tied on the sort (many share an expiry day, or a status)
     * could otherwise land on two pages or on none: the pair's key is unique.
     *
     * @return string The ORDER BY list, over the aliases of the table's SQL wrapped as results.
     */
    public function page_order(): string {
        $sort = $this->get_sql_sort();
        return ($sort !== '' ? "{$sort}, " : '') . 'results.userid, results.competencyid';
    }
}
