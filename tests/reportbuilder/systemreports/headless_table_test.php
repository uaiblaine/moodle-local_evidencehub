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

namespace local_evidencehub\reportbuilder\systemreports;

use core_reportbuilder\manager;
use core_reportbuilder\table\system_report_table;
use core_reportbuilder\tests\core_reportbuilder_testcase;
use local_evidencehub\local\reportpreference;
use local_evidencehub\local\status;
use local_evidencehub\reportbuilder\local\systemreports\headless_table;
use local_evidencehub\reportbuilder\local\systemreports\hub_pairs;
use local_evidencehub\tests\hub_scenario;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The pairs report's table read in two statements on the pair's two-part key: the same pairs, values and order
 * as core's own read, stable paging over tied rows, the total, and a pair gone between the statements.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(headless_table::class)]
#[CoversClass(hub_pairs::class)]
final class headless_table_test extends core_reportbuilder_testcase {
    use hub_scenario;

    /** @var int The template. */
    private int $templateid;

    /** @var array The workers, in creation order. */
    private array $workers = [];

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('enabled', 1, 'core_competency');
        $this->setAdminUser();
        $competencies = $this->competencies(3);
        $this->templateid = $this->template($competencies);
        // Twelve workers by three competencies, 36 pairs (nine of them missing), many tied on every sort key: the
        // same status, the same day, the same type.
        $names = ['Ana', 'Bia', 'Cid', 'Dora', 'Eli', 'Fabi', 'Gil', 'Hugo', 'Iris', 'Joao', 'Kai', 'Lia'];
        foreach ($names as $index => $name) {
            $worker = $this->worker($this->templateid, ['firstname' => $name, 'lastname' => $index % 2 ? 'Lima' : 'Souza']);
            $this->workers[] = $worker;
            foreach ($competencies as $position => $competencyid) {
                if (($index + $position) % 4 === 3) {
                    // Missing: no row.
                    continue;
                }
                $code = [status::STATUS_VALID, status::STATUS_DUE, status::STATUS_EXPIRED][($index + $position) % 3];
                $this->status_row((int) $worker->id, $competencyid, $code, 20270101 + ($position % 2), 'nrcertificate');
            }
        }
        // Every catalogue column, so every sortable column is compared.
        $catalogue = ['status', 'expiry', 'count', 'type', 'issuer', 'competencyidnumber', 'framework', 'issuedate',
            'expirydate', 'verification', 'changed'];
        reportpreference::write_grid($catalogue, [], null);
    }

    /**
     * The report for the current user, built afresh, as a request of its own.
     *
     * @return hub_pairs
     */
    private function report(): hub_pairs {
        manager::reset_caches();
        return hub_pairs::for_template(\context_system::instance(), $this->templateid);
    }

    /**
     * Core's own read of the report, every row, sorted by a column.
     *
     * @param string $alias The column alias to sort by.
     * @param int $order SORT_ASC or SORT_DESC.
     * @return array "userid:competencyid" => formatted cells by alias, in core's order.
     */
    private function core_rows(string $alias, int $order): array {
        global $SESSION;
        unset($SESSION->flextable);
        $report = $this->report();
        $table = system_report_table::create((int) $report->get_report_persistent()->get('id'), []);
        $table->define_baseurl(new \moodle_url('/local/evidencehub/index.php'));
        $table->set_sortdata([['sortby' => $alias, 'sortorder' => $order]]);
        $table->setup();
        $table->query_db(1000, false);
        $aliases = array_keys($report->get_active_columns_by_alias());
        $rows = [];
        foreach ($table->rawdata as $record) {
            $rows[$record->userid . ':' . $record->competencyid] = $this->cells($table, $record, $aliases);
        }
        $table->close_recordset();
        return $rows;
    }

    /**
     * A headless table of the report, sorted by a column, set up.
     *
     * @param string|null $alias The column alias to sort by; the report's own sort when null.
     * @param int $order SORT_ASC or SORT_DESC.
     * @return headless_table
     */
    private function table(?string $alias = null, int $order = SORT_ASC): headless_table {
        global $SESSION;
        unset($SESSION->flextable);
        $report = $this->report();
        $table = headless_table::create((int) $report->get_report_persistent()->get('id'), []);
        $table->guess_base_url();
        $table->set_sortdata($alias === null ? [] : [['sortby' => $alias, 'sortorder' => $order]]);
        $table->setup();
        return $table;
    }

    /**
     * Every page of a headless table, read as the engine reads them.
     *
     * @param headless_table $table The table, set up.
     * @param int $perpage Rows per page.
     * @return array [the rows by pair in page order, each page's total].
     */
    private function headless_rows(headless_table $table, int $perpage): array {
        $rows = [];
        $totals = [];
        $aliases = array_keys($table->get_report()->get_active_columns_by_alias());
        for ($page = 0; $page < 100; $page++) {
            $table->set_page_number($page + 1);
            $table->query_db($perpage, false);
            if ($table->rawdata === []) {
                break;
            }
            $this->assertLessThanOrEqual($perpage, count($table->rawdata));
            $totals[] = (int) $table->totalrows;
            foreach ($table->rawdata as $record) {
                $key = $record->userid . ':' . $record->competencyid;
                $this->assertArrayNotHasKey($key, $rows, 'A pair came on two pages.');
                $rows[$key] = $this->cells($table, $record, $aliases);
            }
        }
        return [$rows, $totals];
    }

    /**
     * The formatted cells of a row, by column alias.
     *
     * @param system_report_table $table The table.
     * @param \stdClass $record The row.
     * @param array $aliases The aliases of the report's active columns.
     * @return array Alias => cell.
     */
    private function cells(system_report_table $table, \stdClass $record, array $aliases): array {
        $formatted = $table->format_row(clone $record);
        $cells = [];
        foreach ($aliases as $alias) {
            // Core's action links carry a random id of their own on each rendering.
            $cells[$alias] = preg_replace('/ id="action_link[^"]*"/', '', (string) $formatted[$alias]);
        }
        return $cells;
    }

    /**
     * For every sortable column and both directions, the pages of the two-statement read hold the pairs, the cells
     * and the order of core's single read.
     *
     * @return void
     */
    public function test_the_pages_are_cores_rows_in_cores_order(): void {
        $sortable = [];
        foreach ($this->report()->get_active_columns() as $column) {
            if ($column->get_is_sortable()) {
                $sortable[$column->get_unique_identifier()] = $column->get_column_alias();
            }
        }
        // The control: the comparison covers the whole catalogue, not a default set.
        $this->assertGreaterThanOrEqual(13, count($sortable));
        foreach ($sortable as $identifier => $alias) {
            foreach ([SORT_ASC => 'asc', SORT_DESC => 'desc'] as $order => $name) {
                $core = $this->core_rows($alias, $order);
                [$headless, $totals] = $this->headless_rows($this->table($alias, $order), 7);
                $this->assertCount(36, $core, "{$identifier} {$name}");
                $this->assertSame(array_keys($core), array_keys($headless), "{$identifier} {$name}: order");
                $this->assertSame($core, $headless, "{$identifier} {$name}: cells");
                $this->assertSame(array_fill(0, 6, 36), $totals, "{$identifier} {$name}: totals");
            }
        }
    }

    /**
     * Pairs tied on the sort (here on the status, and inside it on the day) page with no pair on two pages and
     * none left out, across every page of a walk of five; each page's order is the one of the whole read.
     *
     * @return void
     */
    public function test_tied_rows_page_once_each(): void {
        $status = $this->report()->get_column('validity:status')->get_column_alias();
        foreach ([SORT_ASC, SORT_DESC] as $order) {
            [$rows, $totals] = $this->headless_rows($this->table($status, $order), 5);
            $this->assertCount(36, $rows);
            $this->assertSame(array_fill(0, 8, 36), $totals);
            [$whole] = $this->headless_rows($this->table($status, $order), 100);
            $this->assertSame(array_keys($whole), array_keys($rows));
        }
        // The control: the walk ran over ties, many pairs to one status.
        $this->assertGreaterThan(5, count(array_filter(
            $rows,
            fn(array $cells): bool => $cells[$status] === status::effective_label(status::STATUS_VALID)
        )));
    }

    /**
     * The page's order ends in both parts of the pair's key, after the reader's sort.
     *
     * @return void
     */
    public function test_the_page_order_ends_in_the_pairs_key(): void {
        $table = $this->table();
        $this->assertNotSame('', $table->get_sql_sort());
        $this->assertSame($table->get_sql_sort() . ', results.userid, results.competencyid', $table->page_order());
    }

    /**
     * The total is the count of the rows, on every page, and a page past the last one reads nothing.
     *
     * @return void
     */
    public function test_the_total_is_the_count(): void {
        $table = $this->table();
        $this->assertSame(36, $table->get_total_row_count());
        $table->set_page_number(3);
        $table->query_db(10, false);
        $this->assertCount(10, $table->rawdata);
        $this->assertSame(36, (int) $table->totalrows);
        $table->set_page_number(9);
        $table->query_db(10, false);
        $this->assertSame([], $table->rawdata);
    }

    /**
     * A pair gone between the two statements (its worker deleted) is left out of the page; the others keep their
     * order.
     *
     * @return void
     */
    public function test_a_pair_gone_between_the_statements_is_dropped(): void {
        global $DB, $SESSION;
        unset($SESSION->flextable);
        $report = $this->report();
        $table = new class ('system-report-table-' . $report->get_report_persistent()->get('id'), []) extends headless_table {
            /** @var int The worker deleted before the second statement. */
            public int $gone = 0;

            /**
             * Delete a worker, then read the page's pairs.
             *
             * @param array $keys The page's keys.
             * @return array The rows.
             */
            protected function read_pairs(array $keys): array {
                global $DB;
                $DB->set_field('user', 'deleted', 1, ['id' => $this->gone]);
                return parent::read_pairs($keys);
            }
        };
        $table->gone = (int) $this->workers[0]->id;
        $table->guess_base_url();
        $table->set_sortdata([]);
        $table->setup();
        $table->set_page_number(1);
        $table->query_db(100, false);

        $keys = array_map(fn(\stdClass $row): string => $row->userid . ':' . $row->competencyid, $table->rawdata);
        $this->assertSame(36, (int) $table->totalrows, 'The first statement counted the worker.');
        $this->assertCount(36 - 3, $keys);
        $this->assertSame([], array_filter($keys, fn(string $key): bool => str_starts_with($key, $table->gone . ':')));
        // The control: the worker is restored and listed again.
        $DB->set_field('user', 'deleted', 0, ['id' => $table->gone]);
        [$rows] = $this->headless_rows($this->table(), 100);
        $this->assertCount(36, $rows);
    }

    /**
     * A page is two statements, whatever its size.
     *
     * @return void
     */
    public function test_a_page_is_two_statements(): void {
        global $DB;
        foreach ([5, 30] as $perpage) {
            $table = $this->table();
            $table->set_page_number(1);
            $before = $DB->perf_get_queries();
            $table->query_db($perpage, false);
            $this->assertSame(2, $DB->perf_get_queries() - $before, "{$perpage} per page");
            $this->assertCount($perpage, $table->rawdata);
        }
    }
}
