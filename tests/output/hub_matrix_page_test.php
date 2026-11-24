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

namespace local_evidencehub\output;

use core_reportbuilder\local\filters\select;
use core_reportbuilder\manager;
use local_evidencehub\local\policy;
use local_evidencehub\local\reportengine;
use local_evidencehub\local\status;
use local_evidencehub\reportbuilder\local\systemreports\hub_pairs;
use local_evidencehub\tests\hub_scenario;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The hub's matrix page: its export, its escaping, the criteria line, and the page script with view=matrix.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(hub_matrix_page::class)]
final class hub_matrix_page_test extends \advanced_testcase {
    use hub_scenario;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('enabled', 1, 'core_competency');
        $this->setAdminUser();
    }

    #[\Override]
    protected function tearDown(): void {
        $_GET = [];
        parent::tearDown();
    }

    /**
     * Run the page script with GET parameters and return what it printed.
     *
     * @param array $params The parameters.
     * @return string The HTML.
     */
    private function run_page(array $params): string {
        // The page script reads these from the global scope.
        global $CFG, $DB, $PAGE, $OUTPUT, $USER, $SITE;
        $_GET = $params;
        $level = ob_get_level();
        ob_start();
        try {
            require($CFG->dirroot . '/local/evidencehub/index.php');
        } finally {
            $html = '';
            while (ob_get_level() > $level) {
                $html = ob_get_clean() . $html;
            }
        }
        return $html;
    }

    /**
     * The export of a template's matrix for the current user, every pair listed (the scope "All").
     *
     * @param int $templateid The template.
     * @param string $scope The scope.
     * @return array
     */
    private function export(int $templateid, string $scope = reportengine::SCOPE_ALL): array {
        global $PAGE;
        $system = \context_system::instance();
        manager::reset_caches();
        $page = new hub_matrix_page($system, hub_pairs::for_template($system, $templateid, ['scope' => $scope]), $scope);
        return $page->export_for_template($PAGE->get_renderer('core'));
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
     * The legend names every effective status; a row carries its worker, a cell per column with its
     * colour, label, text and what the drawer needs; the restricted cell its lock and no number.
     *
     * @return void
     */
    public function test_the_export(): void {
        $this->mock_clock_with_frozen(make_timestamp(2026, 6, 1, 12));
        [$c1, $c2] = $this->competencies(2);
        $templateid = $this->template([$c1, $c2]);
        $worker = $this->worker($templateid, ['firstname' => 'Ana', 'lastname' => 'Souza']);
        $this->status_row((int) $worker->id, $c1, status::STATUS_EXPIRED, 20260520);
        $this->status_row((int) $worker->id, $c2, status::STATUS_DUE, 20260611, 'aso');
        $this->setUser($this->reader());

        $export = $this->export($templateid);
        $this->assertSame(get_string('hubmatrixtotal', 'local_evidencehub', 1), $export['total']);
        $this->assertSame(
            array_map([status::class, 'effective_label'], status::effective_codes()),
            array_column($export['legend'], 'label')
        );
        $this->assertSame(
            [status::effective_badge_class(status::EFFECTIVE_MISSING), status::effective_badge_class(status::STATUS_VALID)],
            [$export['legend'][0]['badgeclass'], $export['legend'][5]['badgeclass']]
        );
        $this->assertSame(['C1', 'C2'], array_column($export['columns'], 'name'));
        $this->assertSame(policy::kind_tag(policy::KIND_TECHNICAL), $export['columns'][0]['kind']);
        $this->assertSame(2, $export['columncount']);

        $row = $export['rows'][0];
        $this->assertSame((int) $worker->id, $row['userid']);
        $this->assertSame('Ana Souza', $row['workername']);
        // The selection names the worker, never the worker's pairs.
        $this->assertArrayNotHasKey('pairs', $row);
        [$expired, $restricted] = $row['cells'];
        $this->assertSame([status::STATUS_EXPIRED, false], [$expired['code'], $expired['restricted']]);
        $this->assertSame(get_string('hubmatrixdayssince', 'local_evidencehub', 12), $expired['text']);
        $this->assertSame(status::effective_badge_class(status::STATUS_EXPIRED), $expired['badgeclass']);
        $this->assertSame([(int) $worker->id, $c1], [$expired['userid'], $expired['competencyid']]);
        $this->assertSame(
            get_string('hubmatrixcell', 'local_evidencehub', (object) [
                'worker' => 'Ana Souza',
                'competency' => 'C1',
                'status' => status::label(status::STATUS_EXPIRED),
            ]),
            $expired['celllabel']
        );
        $names = (object) ['worker' => 'Ana Souza', 'competency' => 'C1'];
        $this->assertSame(get_string('drawertitle', 'local_evidencehub', $names), $expired['drawertitle']);
        // A sensitive deciding evidence: the lock, the restricted label, no number.
        $this->assertSame(
            [status::EFFECTIVE_RESTRICTED, true, ''],
            [$restricted['code'], $restricted['restricted'], $restricted['text']]
        );
        $this->assertSame(status::effective_label(status::EFFECTIVE_RESTRICTED), $restricted['statuslabel']);

        // The control: an administrator reads the due pair's days left.
        $this->setAdminUser();
        $cells = $this->export($templateid)['rows'][0]['cells'];
        $this->assertSame([status::STATUS_DUE, false], [$cells[1]['code'], $cells[1]['restricted']]);
        $this->assertSame(get_string('hubmatrixdaysleft', 'local_evidencehub', 10), $cells[1]['text']);
    }

    /**
     * Worker and competency names leave the export plain and render escaped once, in the heading, the cell's
     * label and the drawer's title.
     *
     * @return void
     */
    public function test_names_are_escaped_once(): void {
        global $PAGE;
        $name = 'Safety & Health < 2';
        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $generator->create_framework();
        $competency = $generator->create_competency(['competencyframeworkid' => $framework->get('id'), 'shortname' => $name]);
        $templateid = $this->template([(int) $competency->get('id')]);
        $this->worker($templateid, ['firstname' => $name, 'lastname' => 'X']);

        $export = $this->export($templateid);
        $this->assertSame($name, $export['columns'][0]['name']);
        $this->assertSame($name . ' X', $export['rows'][0]['workername']);
        $html = $PAGE->get_renderer('core')->render_from_template('local_evidencehub/hub_matrix', $export);
        $this->assertStringNotContainsString('&amp;amp;', $html);
        $this->assertStringNotContainsString('&amp;lt;', $html);
        $escaped = 'Safety &amp; Health &lt; 2';
        $this->assertStringContainsString('<span class="local-evidencehub-matrix-name">' . $escaped . '</span>', $html);
        $this->assertStringContainsString('data-title="' . $escaped . ' X: ' . $escaped . '"', $html);
        $label = get_string('hubmatrixcell', 'local_evidencehub', (object) [
            'worker' => $escaped . ' X',
            'competency' => $escaped,
            'status' => status::label(null),
        ]);
        $this->assertStringContainsString('aria-label="' . $label . '"', $html);
    }

    /**
     * The criteria line says which workers are listed: the scope, then each stored filter in force with its option, a
     * cohort's admin-set name plain in the export and escaped once on the page; the link back is the list's.
     *
     * Changes that must make it fail: the criteria read from anything but the report's stored values; a name printed
     * raw or escaped twice.
     *
     * @return void
     */
    public function test_the_criteria_line(): void {
        global $PAGE;
        [$c1] = $this->competencies(1);
        $templateid = $this->template([$c1]);
        $cohort = $this->getDataGenerator()->create_cohort(['name' => 'Night & day < shift']);
        $system = \context_system::instance();
        manager::reset_caches();
        hub_pairs::for_template($system, $templateid)->set_filter_values([
            'user:cohort_operator' => select::EQUAL_TO,
            'user:cohort_value' => $cohort->id,
        ]);

        $export = $this->export($templateid, reportengine::SCOPE_ATTENTION);
        $this->assertSame(get_string('hubmatrixscopeattention', 'local_evidencehub'), $export['scopetext']);
        $this->assertSame([[
            'text' => get_string('hubmatrixcriterion', 'local_evidencehub', (object) [
                'title' => get_string('cohort', 'core_cohort'),
                'value' => 'Night & day < shift',
            ]),
        ]], $export['criteria']);
        $this->assertSame(hub_report_view::url($system, $templateid)->out(false), $export['listurl']);
        $html = $PAGE->get_renderer('core')->render_from_template('local_evidencehub/hub_matrix', $export);
        $this->assertStringContainsString('Night &amp; day &lt; shift', $html);
        $this->assertStringNotContainsString('&amp;amp;', $html);
        $this->assertStringNotContainsString('Night & day', $html);

        // The control: with no stored filter, the line holds the scope alone.
        manager::reset_caches();
        hub_pairs::for_template($system, $templateid)->set_filter_values([]);
        $export = $this->export($templateid);
        $this->assertSame([], $export['criteria']);
        $this->assertSame(get_string('hubmatrixscopeall', 'local_evidencehub'), $export['scopetext']);
    }

    /**
     * The page with view=matrix shows the template picker, the criteria line, the legend and the cells, each cell a
     * button that opens its pair's drawer; each row's checkbox posts its worker's id, and the export form returns to
     * this matrix and scope.
     *
     * @return void
     */
    public function test_the_page_renders_the_matrix(): void {
        [$c1] = $this->competencies(1);
        $templateid = $this->template([$c1]);
        $worker = $this->worker($templateid, ['firstname' => 'Ana', 'lastname' => 'Souza']);
        $this->status_row((int) $worker->id, $c1, status::STATUS_EXPIRED, 20250101);
        $ready = $this->worker($templateid, ['firstname' => 'Bia', 'lastname' => 'Lima']);
        $this->status_row((int) $ready->id, $c1, status::STATUS_VALID, 20990101);
        $system = \context_system::instance();

        $html = $this->run_page([
            'contextid' => $system->id,
            'templateid' => $templateid,
            'view' => hub_matrix_page::VIEW,
        ]);
        // The page's default scope is the list's: the worker whose every pair is compliant is not a row.
        $this->assertStringNotContainsString('Bia Lima', $html);
        $this->assertMatchesRegularExpression('~<option\s+value="' . $templateid . '"\s+selected>~', $html);
        $this->assertStringContainsString(get_string('hubmatrixscopeattention', 'local_evidencehub'), $html);
        $this->assertStringContainsString('href="' . hub_report_view::url($system, $templateid)->out() . '"', $html);
        $this->assertStringContainsString(get_string('hubmatrixkinds', 'local_evidencehub'), $html);
        $this->assertStringContainsString('local-evidencehub-swatch ' . status::effective_badge_class(status::STATUS_VALID), $html);
        $label = get_string('hubmatrixcell', 'local_evidencehub', (object) [
            'worker' => 'Ana Souza',
            'competency' => 'C1',
            'status' => status::label(status::STATUS_EXPIRED),
        ]);
        $this->assertMatchesRegularExpression(
            '~<button type="button" class="local-evidencehub-cell ' . status::effective_badge_class(status::STATUS_EXPIRED)
                . '" data-action="inspect" data-status="5"\s+data-userid="' . $worker->id . '" data-competencyid="' . $c1
                . '" data-title="Ana Souza: C1"\s+aria-label="' . $label . '">~',
            $html
        );
        // One checkbox per row posting the worker's id, and no pair field in the matrix.
        $this->assertMatchesRegularExpression(
            '~<input type="checkbox" [^>]*data-region="worker"\s+name="workers\[\]" value="' . $worker->id . '"~',
            $html
        );
        $this->assertSame(1, substr_count($html, 'name="workers[]"'));
        $this->assertStringNotContainsString('name="pairs[]"', $html);
        $this->assertStringContainsString('data-templateid="' . $templateid . '"', $html);
        $this->assertStringContainsString('<input type="hidden" name="view" value="matrix">', $html);
        $this->assertStringContainsString('<input type="hidden" name="scope" value="attention">', $html);
        // The old matrix's own filter form and status chips are gone.
        $this->assertStringNotContainsString('name="statuses[]"', $html);
        $this->assertStringNotContainsString('name="download"', $html);
    }

    /**
     * The control of the page's default scope: the scope "All", as the list's View link passes it, lists the worker
     * whose every pair is compliant, and the export form returns to it.
     *
     * Changes that must make it fail: the page's scope parameter not passed to the report.
     *
     * @return void
     */
    public function test_the_page_follows_the_scope_it_is_given(): void {
        [$c1] = $this->competencies(1);
        $templateid = $this->template([$c1]);
        $ready = $this->worker($templateid, ['firstname' => 'Bia', 'lastname' => 'Lima']);
        $this->status_row((int) $ready->id, $c1, status::STATUS_VALID, 20990101);

        $html = $this->run_page([
            'contextid' => \context_system::instance()->id,
            'templateid' => $templateid,
            'view' => hub_matrix_page::VIEW,
            'scope' => 'all',
        ]);
        $this->assertStringContainsString('Bia Lima', $html);
        $this->assertStringContainsString('<input type="hidden" name="scope" value="all">', $html);
        $this->assertStringContainsString(get_string('hubmatrixscopeall', 'local_evidencehub'), $html);
    }

    /**
     * The control of the matrix page: view=list renders the list's shell and no matrix.
     *
     * @return void
     */
    public function test_the_list_view_shows_no_matrix(): void {
        [$c1] = $this->competencies(1);
        $templateid = $this->template([$c1]);
        $worker = $this->worker($templateid);
        $this->status_row((int) $worker->id, $c1, status::STATUS_EXPIRED, 20250101);
        $system = \context_system::instance();

        $html = $this->run_page(['contextid' => $system->id, 'templateid' => $templateid, 'view' => 'list']);
        $this->assertStringContainsString('data-region="local-evidencehub-reportview"', $html);
        $this->assertStringNotContainsString('local-evidencehub-matrix', $html);
    }
}
