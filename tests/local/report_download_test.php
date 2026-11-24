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

use core_reportbuilder\local\filters\select;
use core_reportbuilder\manager;
use local_evidencehub\event\hub_downloaded;
use local_evidencehub\reportbuilder\local\systemreports\hub_pairs;
use local_evidencehub\tests\hub_scenario;
use local_evidencehub\tests\recording_export_format;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The list's download: the pairs report's own table under the criteria in force, its event, and the only door to it.
 *
 * The refusals of index.php's download branch are entered through the page in tests/output/hub_report_view_test.php;
 * here the download itself, which core's writer would end the request with, is written to a recording writer.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(report_download::class)]
#[CoversClass(hub_downloaded::class)]
final class report_download_test extends \advanced_testcase {
    use hub_scenario;

    /** @var int The template. */
    private int $templateid;

    /** @var array The workers: ana (expired, restricted for a reader without viewsensitive, missing), bia (compliant). */
    private array $workers = [];

    /** @var array The competencies. */
    private array $competencies = [];

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('enabled', 1, 'core_competency');
        $this->setAdminUser();
        $this->competencies = $this->competencies(3);
        [$c1, $c2, $c3] = $this->competencies;
        $this->templateid = $this->template($this->competencies);
        $ana = $this->worker($this->templateid, ['firstname' => 'Ana', 'lastname' => 'Souza']);
        $bia = $this->worker($this->templateid, ['firstname' => 'Bia', 'lastname' => 'Lima']);
        $this->status_row((int) $ana->id, $c1, status::STATUS_EXPIRED, 20250101, 'nrcertificate');
        $this->status_row((int) $ana->id, $c2, status::STATUS_DUE, 20261201, 'aso');
        foreach ([$c1, $c2, $c3] as $competencyid) {
            $this->status_row((int) $bia->id, $competencyid, status::STATUS_VALID, 20990101);
        }
        $this->workers = ['ana' => $ana, 'bia' => $bia];
    }

    #[\Override]
    protected function tearDown(): void {
        $_GET = [];
        $_POST = [];
        parent::tearDown();
    }

    /**
     * A reader of every worker without viewsensitive.
     *
     * @return \stdClass
     */
    private function reader(): \stdClass {
        return $this->viewer(['local/evidencehub:view', 'moodle/competency:templateview', 'moodle/competency:userevidenceview']);
    }

    /**
     * Write the download of the template for the current user and return what the file holds.
     *
     * @param array $options The criteria, as index.php passes them.
     * @return recording_export_format
     */
    private function file(array $options = []): recording_export_format {
        manager::reset_caches();
        $writer = new recording_export_format();
        report_download::open(\context_system::instance(), $this->templateid, 'csv', $options)->send($writer);
        $this->assertTrue($writer->finished);
        return $writer;
    }

    /**
     * The list's rows for the current user under the same criteria, cell by cell as plain text, in the list's order.
     *
     * @param string $scope The scope.
     * @param string $search The search.
     * @return array Each a list of cells.
     */
    private function list_rows(string $scope, string $search = ''): array {
        manager::reset_caches();
        $report = hub_pairs::for_template(\context_system::instance(), $this->templateid);
        $answer = reportengine::rows($report, ['scope' => $scope, 'search' => $search, 'perpage' => 100, 'facets' => false]);
        $plain = new recording_export_format();
        return array_map(
            fn(array $row): array => $plain->format_data(array_column($row['cells'], 'html')),
            $answer['rows']
        );
    }

    /**
     * The file holds the list's rows, cell for cell and in its order, masked for a restricted pair as the list masks
     * it; its headings are the list's columns.
     *
     * Changes that must make it fail: the file read from anything but the report's table (another copy of the rows,
     * unmasked cells), or with another scope than the list's.
     *
     * @return void
     */
    public function test_the_file_holds_the_lists_rows_masked(): void {
        $this->setUser($this->reader());
        $file = $this->file(['scope' => reportengine::SCOPE_ALL]);
        $rows = $this->list_rows(reportengine::SCOPE_ALL);
        $this->assertCount(6, $rows);
        $this->assertSame($rows, $file->rows);
        $restricted = status::effective_label(status::EFFECTIVE_RESTRICTED);
        $masked = array_values(array_filter($file->rows, fn(array $row): bool => in_array($restricted, $row, true)));
        $this->assertCount(1, $masked);
        // A restricted pair keeps its worker, its competency and its status only.
        $this->assertNotContains('2026-12-01', $masked[0]);
        $this->assertNotContains('Issuer aso', $masked[0]);
        $this->assertContains(get_string('worker', 'local_evidencehub'), $file->headers);
        $this->assertContains(get_string('effectiveexpiry', 'local_evidencehub'), $file->headers);

        // The control: a reader with viewsensitive reads that pair's day and issuer in the file.
        $this->setAdminUser();
        $file = $this->file(['scope' => reportengine::SCOPE_ALL]);
        $this->assertNotContains($restricted, array_merge(...$file->rows));
        $this->assertContains('2026-12-01', array_merge(...$file->rows));
        $this->assertSame($this->list_rows(reportengine::SCOPE_ALL), $file->rows);
    }

    /**
     * The scope, the search, the reader's stored filters and the sort in force decide the file's rows, as the list's.
     *
     * Changes that must make it fail: any of them dropped from the download.
     *
     * @return void
     */
    public function test_the_criteria_in_force_are_followed(): void {
        $ana = fullname($this->workers['ana']);
        $bia = fullname($this->workers['bia']);
        $workers = fn(recording_export_format $file): array => array_values(array_unique(array_column($file->rows, 0)));

        // The scope: "Needs attention" leaves Bia's compliant pairs out; "All" lists them.
        $this->assertSame([$ana], $workers($this->file(['scope' => reportengine::SCOPE_ATTENTION])));
        $this->assertEqualsCanonicalizing([$ana, $bia], $workers($this->file(['scope' => reportengine::SCOPE_ALL])));
        $this->assertSame($this->list_rows(reportengine::SCOPE_ATTENTION), $this->file(['scope' => 'attention'])->rows);

        // The search, by the worker's name.
        $this->assertSame([$bia], $workers($this->file(['scope' => reportengine::SCOPE_ALL, 'search' => 'Lima'])));

        // The reader's stored status filter.
        manager::reset_caches();
        hub_pairs::for_template(\context_system::instance(), $this->templateid)->set_filter_values([
            'validity:status_operator' => select::EQUAL_TO,
            'validity:status_value' => status::STATUS_EXPIRED,
        ]);
        $file = $this->file(['scope' => reportengine::SCOPE_ALL]);
        $this->assertCount(1, $file->rows);
        $this->assertSame($ana, $file->rows[0][0]);
        manager::reset_caches();
        hub_pairs::for_template(\context_system::instance(), $this->templateid)->set_filter_values([]);

        // The sort the list asked for, by worker (Ana before Bia), both ways.
        $file = $this->file(['scope' => reportengine::SCOPE_ALL, 'sort' => 'worker', 'direction' => 'desc']);
        $this->assertSame($bia, $file->rows[0][0]);
        $file = $this->file(['scope' => reportengine::SCOPE_ALL, 'sort' => 'worker', 'direction' => 'asc']);
        $this->assertSame($ana, $file->rows[0][0]);
    }

    /**
     * The file's columns are the reader's chosen ones.
     *
     * @return void
     */
    public function test_the_chosen_columns_are_followed(): void {
        $type = get_string('type', 'local_evidencehub');
        $issuedate = get_string('issuedate', 'local_evidencehub');
        $file = $this->file();
        $this->assertContains($type, $file->headers);
        $this->assertNotContains($issuedate, $file->headers);

        reportpreference::write_grid(['status', 'issuedate'], [], null);
        $file = $this->file();
        $this->assertContains($issuedate, $file->headers);
        $this->assertNotContains($type, $file->headers);
    }

    /**
     * The event fires once, before the file, with the number of rows the file holds, in the hub's context.
     *
     * Changes that must make it fail: the event dropped, fired twice, or counting rows the file does not hold.
     *
     * @return void
     */
    public function test_the_event_counts_the_files_rows(): void {
        foreach ([reportengine::SCOPE_ALL => 6, reportengine::SCOPE_ATTENTION => 3] as $scope => $expected) {
            $sink = $this->redirectEvents();
            $file = $this->file(['scope' => $scope]);
            $events = array_values(array_filter($sink->get_events(), fn($event): bool => $event instanceof hub_downloaded));
            $sink->close();
            $this->assertCount(1, $events, $scope);
            $this->assertSame(['templateid' => $this->templateid, 'rows' => $expected], $events[0]->other, $scope);
            $this->assertCount($expected, $file->rows, $scope);
            $this->assertSame(\context_system::instance()->id, $events[0]->contextid);
            $this->assertStringContainsString('templateid=' . $this->templateid, $events[0]->get_url()->out(false));
        }
    }

    /**
     * A format the site has not enabled is refused before anything is built or logged; an enabled one is accepted.
     *
     * @return void
     */
    public function test_a_format_not_enabled_is_refused(): void {
        $sink = $this->redirectEvents();
        foreach (['nonsense', 'csv; drop'] as $format) {
            try {
                report_download::open(\context_system::instance(), $this->templateid, $format, []);
                $this->fail($format . ' was accepted.');
            } catch (\moodle_exception $e) {
                $this->assertSame('invalidparameter', $e->errorcode);
            }
        }
        set_config('disabled', 1, 'dataformat_json');
        try {
            report_download::require_format('json');
            $this->fail('A disabled format was accepted.');
        } catch (\moodle_exception $e) {
            $this->assertSame('invalidparameter', $e->errorcode);
        }
        $this->assertSame([], $sink->get_events());
        // The control: an enabled format passes.
        report_download::require_format('csv');
    }

    /**
     * The report the download builds carries no 'download' parameter and stays not downloadable, so core's own doors
     * (reportbuilder/download.php, the dynamic table's download) refuse it and this entry is the only one; a reader the
     * report refuses gets no file and no event.
     *
     * Changes that must make it fail: a 'download' parameter passed to the report (its can_view() refuses it, so the
     * download itself fails), or the report made downloadable.
     *
     * @return void
     */
    public function test_the_report_stays_closed_to_cores_download(): void {
        manager::reset_caches();
        report_download::open(\context_system::instance(), $this->templateid, 'csv', []);
        $report = hub_pairs::for_template(\context_system::instance(), $this->templateid);
        $this->assertArrayNotHasKey('download', $report->get_parameters());
        $this->assertFalse($report->can_be_downloaded());

        $this->setUser($this->getDataGenerator()->create_user());
        $sink = $this->redirectEvents();
        manager::reset_caches();
        try {
            report_download::open(\context_system::instance(), $this->templateid, 'csv', []);
            $this->fail('A reader without view got a download.');
        } catch (\core_reportbuilder\exception\report_access_exception $e) {
            $this->assertSame([], array_filter($sink->get_events(), fn($event): bool => $event instanceof hub_downloaded));
        }
    }

    /**
     * A request that names core's dynamic table service leaves the file as it is: the table still holds its report.
     *
     * Changes that must make it fail: the table built without its report under that parameter (the download then
     * fails before a byte).
     *
     * @return void
     */
    public function test_the_dynamic_table_parameter_does_not_change_the_file(): void {
        $this->setUser($this->reader());
        $without = $this->file(['scope' => reportengine::SCOPE_ALL]);
        $this->assertCount(6, $without->rows);

        $_GET['info'] = 'core_table_get_dynamic_table_content';
        $with = $this->file(['scope' => reportengine::SCOPE_ALL]);
        $this->assertSame($without->headers, $with->headers);
        $this->assertSame($without->rows, $with->rows);
    }

    /**
     * The table's own request parameters (reset, sort, direction, hidden column, initials, page) leave the file's rows
     * and columns as they are without them; a restricted pair stays masked.
     *
     * Changes that must make it fail: the download reading the table's session or request state instead of the
     * report's own sort and columns. The order is asserted only where those parameters ask for another one.
     *
     * @return void
     */
    public function test_the_tables_request_parameters_do_not_change_the_file(): void {
        $this->setUser($this->reader());
        $restricted = status::effective_label(status::EFFECTIVE_RESTRICTED);
        $without = $this->file(['scope' => reportengine::SCOPE_ALL]);
        $this->assertCount(6, $without->rows);
        $report = hub_pairs::for_template(\context_system::instance(), $this->templateid);
        $columns = $report->get_active_columns();
        $aliases = array_map(fn($column): string => $column->get_column_alias(), array_values($columns));
        $this->assertGreaterThan(1, count($aliases));

        $changes = [
            'treset' => ['treset' => 1, 'tsort' => $aliases[0], 'tdir' => SORT_DESC],
            'thide' => ['thide' => $aliases[1]],
            'tifirst' => ['tifirst' => 'A', 'tilast' => 'S'],
            'page' => ['page' => 3],
        ];
        foreach ($changes as $name => $request) {
            $_GET = $request;
            $with = $this->file(['scope' => reportengine::SCOPE_ALL]);
            $this->assertSame($without->headers, $with->headers, $name);
            $this->assertEqualsCanonicalizing($without->rows, $with->rows, $name);
            $masked = array_filter($with->rows, fn(array $row): bool => in_array($restricted, $row, true));
            $this->assertCount(1, $masked, $name);
            $this->assertNotContains('2026-12-01', array_merge(...$with->rows), $name);
            if ($name !== 'treset') {
                $this->assertSame($without->rows, $with->rows, $name);
            }
        }
        $_GET = [];

        // The control: the same pair is unmasked for a reader with viewsensitive, so the masking above is the reader's.
        $this->setAdminUser();
        $this->assertContains('2026-12-01', array_merge(...$this->file(['scope' => reportengine::SCOPE_ALL])->rows));
    }
}
