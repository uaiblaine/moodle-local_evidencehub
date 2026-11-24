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

use local_evidencehub\local\dossier_builder;
use local_evidencehub\local\dossier_export;
use local_evidencehub\local\reportengine;
use local_evidencehub\local\reportpreference;
use local_evidencehub\reportbuilder\local\systemreports\hub_pairs;
use local_evidencehub\tests\dossier_scenario;
use local_evidencehub\tests\hub_scenario;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The hub's list as a data report: the shell's props, its strings, its noscript, and the page that renders it.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(hub_report_view::class)]
final class hub_report_view_test extends \advanced_testcase {
    use hub_scenario;
    use dossier_scenario;

    /** @var array The props' keys, every one configuration or strings ({@see hub_report_view::props()}). */
    private const array PROPS = [
        'mount', 'contextid', 'templateid', 'reportid', 'perpage', 'perpages', 'searchmin', 'matrixurl', 'tableurl',
        'exportform', 'labels',
    ];

    /**
     * Label names the shared parts read only in a branch the hub never reaches, each with the branch.
     *
     * - download: the Download menu, absent until stage E4 (the hub passes no download link);
     * - grouping, allgroupings, moregroupsgrouping: a group facet's groupings, which no hub filter has;
     * - waitminutes, waithours, waitdayshours, waitdays: str.ts duration(), the reference's wait, never called.
     */
    private const array UNREACHED = [
        'download', 'grouping', 'allgroupings', 'moregroupsgrouping', 'waitminutes', 'waithours', 'waitdayshours', 'waitdays',
    ];

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('enabled', 1, 'core_competency');
    }

    #[\Override]
    protected function tearDown(): void {
        $_GET = [];
        $_POST = [];
        unset($_SERVER['REQUEST_METHOD']);
        parent::tearDown();
    }

    /**
     * Run the page script with request parameters and return what it printed.
     *
     * @param array $params The parameters.
     * @param string $method The request method; a POST carries the parameters in the body.
     * @return string The HTML.
     */
    private function run_page(array $params, string $method = 'GET'): string {
        // The page script reads these from the global scope.
        global $CFG, $DB, $PAGE, $OUTPUT, $USER, $SITE;
        $_SERVER['REQUEST_METHOD'] = $method;
        $_GET = $method === 'GET' ? $params : [];
        $_POST = $method === 'POST' ? $params : [];
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
     * The page a report is rendered on outside index.php: core's table reads its url and context.
     *
     * @return void
     */
    private function set_page(): void {
        global $PAGE;
        $PAGE->set_context(\context_system::instance());
        $PAGE->set_url(new \moodle_url('/local/evidencehub/index.php'));
    }

    /**
     * A template of one competency with one worker, Ana Souza, whose pair is expired.
     *
     * @return array [template id, the worker, the competency].
     */
    private function scenario(): array {
        [$c1] = $this->competencies(1);
        $templateid = $this->template([$c1]);
        $worker = $this->worker($templateid, ['firstname' => 'Ana', 'lastname' => 'Souza', 'email' => 'ana.souza@example.com']);
        $this->status_row((int) $worker->id, $c1, \local_evidencehub\local\status::STATUS_EXPIRED, 20250101);
        return [$templateid, $worker, $c1];
    }

    /**
     * The props are the listed configuration and the strings, and no record: neither the worker's name nor their
     * e-mail address is in them, while core's table of the same report shows both.
     *
     * Changes that must make it fail: a key added to the props (a row, a figure, a template list); the export form
     * offered to a reader who may not export.
     *
     * @return void
     */
    public function test_the_props_carry_configuration_and_strings_only(): void {
        $this->setAdminUser();
        $this->set_page();
        [$templateid] = $this->scenario();
        $system = \context_system::instance();
        $report = hub_pairs::for_template($system, $templateid);

        $props = (new hub_report_view($system, $report))->props();
        $this->assertSame(self::PROPS, array_keys($props));
        $this->assertSame(hub_report_view::MOUNT, $props['mount']);
        $this->assertSame((int) $system->id, $props['contextid']);
        $this->assertSame($templateid, $props['templateid']);
        $this->assertSame((int) $report->get_report_persistent()->get('id'), $props['reportid']);
        $this->assertSame(reportengine::PERPAGES, $props['perpages']);
        $this->assertSame(reportengine::SEARCH_MIN, $props['searchmin']);
        $this->assertStringContainsString('view=matrix', $props['matrixurl']);
        $this->assertStringContainsString('templateid=' . $templateid, $props['matrixurl']);
        $this->assertStringContainsString('view=' . hub_pairs::VIEW, $props['tableurl']);
        $this->assertSame(['action', 'sesskey'], array_keys($props['exportform']));
        $this->assertSame(sesskey(), $props['exportform']['sesskey']);

        $json = json_encode($props);
        foreach (['Ana', 'Souza', 'ana.souza'] as $fact) {
            $this->assertStringNotContainsString($fact, $json);
        }
        // Control: the report the props name lists the worker.
        $this->assertStringContainsString('Ana Souza', $report->output());

        // A reader who may view the hub but not export gets no export form, so the client draws no selection.
        $reader = $this->viewer([
            'local/evidencehub:view',
            'moodle/competency:templateview',
            'moodle/competency:userevidenceview',
        ]);
        $this->setUser($reader);
        $props = (new hub_report_view($system, hub_pairs::for_template($system, $templateid)))->props();
        $this->assertNull($props['exportform']);
    }

    /**
     * Every label is a string of the pack, and every label name the client's sources read is one the shell writes.
     *
     * Changes that must make it fail: a label dropped from labels() while a component still reads it; a string
     * missing from the pack (get_string() would emit a debugging message the test fails on).
     *
     * @return void
     */
    public function test_every_label_the_client_reads_is_a_real_string(): void {
        global $CFG;
        $labels = hub_report_view::labels();
        foreach ($labels as $name => $text) {
            $this->assertIsString($text, $name);
            $this->assertNotSame('', trim($text), $name);
            $this->assertStringNotContainsString('[[', $text, $name);
        }

        $read = [];
        $root = $CFG->dirroot . '/local/evidencehub/js/esm/src';
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if (!preg_match('/\.tsx?$/', $file->getFilename())) {
                continue;
            }
            $source = (string) file_get_contents($file->getPathname());
            preg_match_all('/labels\.([a-z_]+)/', $source, $matches);
            $read = array_merge($read, $matches[1]);
            // The counted strings: fillCount(labels, '<key>', ...) reads <key> and <key>_one.
            preg_match_all("/fillCount\\(labels, '([a-z_]+)'/", $source, $matches);
            foreach ($matches[1] as $key) {
                $read[] = $key;
                $read[] = $key . '_one';
            }
        }
        $read = array_values(array_diff(array_unique($read), self::UNREACHED));
        // Vacuity guard: the sources were read at all.
        $this->assertGreaterThan(40, count($read));
        sort($read);
        $this->assertSame([], array_values(array_diff($read, array_keys($labels))), 'labels the client reads but the shell lacks');
    }

    /**
     * The shell mounts the bundle and keeps core's rendering of the report in its noscript, without the filter form;
     * outside the noscript no row is in the page.
     *
     * Changes that must make it fail: the mount naming a per-file module; the noscript dropped; the filter form left
     * in it (its script looks for a form a scripting browser never builds).
     *
     * @return void
     */
    public function test_the_noscript_holds_cores_table_without_its_filter_form(): void {
        global $PAGE;
        $this->setAdminUser();
        $this->set_page();
        [$templateid] = $this->scenario();
        $system = \context_system::instance();

        // Control: core's own rendering of this report carries its filter form.
        $this->assertStringContainsString('data-region="filters-form"', hub_pairs::for_template($system, $templateid)->output());

        $html = $PAGE->get_renderer('core')->render(new hub_report_view($system, hub_pairs::for_template($system, $templateid)));
        $this->assertStringContainsString('data-react-component="@moodle/lms/local_evidencehub/bundle"', $html);
        $this->assertStringContainsString('name="templateid"', $html);
        $at = strpos($html, '<noscript>');
        $this->assertNotFalse($at);
        $before = substr($html, 0, $at);
        $noscript = substr($html, $at);
        $this->assertStringContainsString('data-region="core_reportbuilder/report"', $noscript);
        $this->assertStringContainsString('Ana Souza', $noscript);
        $this->assertStringNotContainsString('data-region="filters-form"', $noscript);
        $this->assertStringNotContainsString('Ana Souza', $before);
    }

    /**
     * The page renders the shell for a template the request names, under the heading with the template picker, whose
     * names core prints raw and so are escaped once; a category hub says it is locked to its category.
     *
     * @return void
     */
    public function test_the_page_renders_the_shell_for_a_template(): void {
        global $DB;
        $category = $this->getDataGenerator()->create_category(['name' => 'Operations']);
        $catcontext = \context_coursecat::instance($category->id);
        [$c1] = $this->competencies(1, $catcontext);
        $templateid = $this->template([$c1], $catcontext);
        $this->worker($templateid, ['firstname' => 'Ana', 'lastname' => 'Souza']);
        $DB->set_field('competency_template', 'shortname', 'Safety & Health < 2', ['id' => $templateid]);
        $this->setUser($this->viewer([
            'local/evidencehub:view',
            'moodle/competency:templateview',
            'moodle/competency:userevidenceview',
        ], $catcontext));

        $html = $this->run_page(['contextid' => $catcontext->id, 'templateid' => $templateid]);
        $this->assertStringContainsString('data-region="local-evidencehub-reportview"', $html);
        $this->assertStringContainsString(get_string('hublocked', 'local_evidencehub', 'Operations'), $html);
        $this->assertMatchesRegularExpression('~<option\s+value="' . $templateid . '"\s+selected>~', $html);
        $this->assertStringContainsString('"templateid":' . $templateid, $html);
        // The picker's option is escaped once: neither raw nor twice.
        $this->assertStringContainsString('Safety &amp; Health &lt; 2 (Operations)', $html);
        $this->assertStringNotContainsString('Safety & Health < 2', $html);
        $this->assertStringNotContainsString('&amp;amp;', $html);
        // The old list is not on the page.
        $this->assertStringNotContainsString('data-region="local-evidencehub-hub"', $html);
    }

    /**
     * With no template in the request and none remembered, the hub asks for one.
     *
     * @return void
     */
    public function test_with_no_template_remembered_the_page_asks_for_one(): void {
        $this->setAdminUser();
        $this->scenario();
        $system = \context_system::instance();

        $this->assertSame(0, hub_report_view::last_template($system));
        $html = $this->run_page(['contextid' => $system->id]);
        $this->assertStringContainsString(get_string('hubchoosetemplatefirst', 'local_evidencehub'), $html);
        $this->assertStringNotContainsString('data-region="local-evidencehub-reportview"', $html);
    }

    /**
     * With no template in the request the hub opens the reader's last one in that context, offered there.
     *
     * Changes that must make it fail: the remembered template ignored.
     *
     * @return void
     */
    public function test_the_remembered_template_opens_while_it_is_offered(): void {
        $this->setAdminUser();
        [$templateid] = $this->scenario();
        $system = \context_system::instance();

        reportpreference::remember_template((int) $system->id, $templateid);
        $this->assertSame($templateid, hub_report_view::last_template($system));
        $html = $this->run_page(['contextid' => $system->id]);
        $this->assertStringContainsString('data-region="local-evidencehub-reportview"', $html);
        $this->assertStringContainsString('"templateid":' . $templateid, $html);
    }

    /**
     * A remembered template the hub no longer offers in that context opens the choice, not the template and not an
     * error: here the hub of a category, which does not offer a template of the system.
     *
     * Changes that must make it fail: the remembered template opened without asking whether it is still offered
     * (the page then refuses it with an error instead of asking).
     *
     * @return void
     */
    public function test_a_remembered_template_no_longer_offered_opens_the_choice(): void {
        $this->setAdminUser();
        [$templateid] = $this->scenario();
        $catcontext = \context_coursecat::instance($this->getDataGenerator()->create_category()->id);
        // The category's own template, so the page that asks for one lists something to choose.
        $this->template($this->competencies(1, $catcontext), $catcontext);

        reportpreference::remember_template((int) $catcontext->id, $templateid);
        // The precondition: the template is remembered there.
        $this->assertSame($templateid, reportpreference::last_template((int) $catcontext->id));
        $this->assertSame(0, hub_report_view::last_template($catcontext));
        $html = $this->run_page(['contextid' => $catcontext->id]);
        $this->assertStringContainsString(get_string('hubchoosetemplatefirst', 'local_evidencehub'), $html);
        $this->assertStringNotContainsString('data-region="local-evidencehub-reportview"', $html);
    }

    /**
     * The matrix keeps its own page, and its "List" tab leads to the list's shell.
     *
     * @return void
     */
    public function test_the_matrix_keeps_its_page(): void {
        $this->setAdminUser();
        [$templateid] = $this->scenario();
        $system = \context_system::instance();

        $html = $this->run_page(['contextid' => $system->id, 'templateid' => $templateid, 'view' => 'matrix']);
        $this->assertStringContainsString('local-evidencehub-matrix', $html);
        $this->assertStringNotContainsString('data-region="local-evidencehub-reportview"', $html);
        $this->assertMatchesRegularExpression('~<a href="([^"]*view=list[^"]*)" class="nav-link">~', $html);
    }

    /**
     * Run the page and return the moodle_exception it ends in; fails when it ends otherwise.
     *
     * @param array $params The parameters.
     * @param string $method The request method.
     * @return \moodle_exception The exception.
     */
    private function refusal(array $params, string $method = 'GET'): \moodle_exception {
        try {
            $this->run_page($params, $method);
        } catch (\moodle_exception $e) {
            return $e;
        }
        $this->fail('The page ' . $method . ' ' . json_encode($params) . ' was not refused.');
    }

    /**
     * The export branch of the page refuses a GET, a POST with the wrong sesskey and a reader without
     * exportdossier, and each refusal creates no export row.
     *
     * The control is the same request one step further: the sesskey right moves the refusal from the sesskey to the
     * capability, and a reader who holds the capability is not refused there ({@see dossier_builder::require_export()});
     * the page's success path ends in redirect(), which exits, so tests/behat/hub_report.feature posts an export
     * through the page for it.
     *
     * Changes that must make it fail: require_post(), require_sesskey() or require_export() removed from index.php's
     * export branch.
     *
     * @return void
     */
    public function test_the_export_branch_refuses_a_get_a_bad_sesskey_and_a_reader_without_the_capability(): void {
        global $DB;
        [$templateid, $worker] = $this->scenario();
        $system = \context_system::instance();
        $viewer = $this->viewer([
            'local/evidencehub:view',
            'moodle/competency:templateview',
            'moodle/competency:userevidenceview',
        ]);
        $exporter = $this->requester([$worker]);
        $request = [
            'contextid' => $system->id,
            'templateid' => $templateid,
            'statusfilter' => 1,
            'action' => 'exportdossiers',
            'pairs' => [$worker->id . ':1'],
        ];
        $before = $DB->count_records(dossier_export::TABLE);

        $this->setUser($exporter);
        $this->assertSame('errorpostonly', $this->refusal($request)->errorcode);
        $this->assertSame('invalidsesskey', $this->refusal($request + ['sesskey' => 'wrong'], 'POST')->errorcode);

        $this->setUser($viewer);
        $request['sesskey'] = sesskey();
        $this->assertSame('nopermissions', $this->refusal($request, 'POST')->errorcode);
        $this->assertSame($before, $DB->count_records(dossier_export::TABLE));

        // The control: the reader who holds the capability is not refused where the other one is.
        $this->setUser($exporter);
        dossier_builder::require_export($system);
        $this->assertTrue(dossier_builder::can_export($system));
    }

    /**
     * What the page is asked for when it must refuse a template: every view the page reads, against a template
     * the context does not offer and against one that does not exist.
     *
     * @return array Case name => [view, whether the template exists].
     */
    public static function refused_templates(): array {
        $cases = [];
        foreach (['list', 'bogus', 'table', 'none'] as $view) {
            $cases[$view . ', not offered'] = [$view, true];
            $cases[$view . ', missing'] = [$view, false];
        }
        return $cases;
    }

    /**
     * A template the hub does not offer in the context is refused with one error whatever the view asked for, and
     * so is a template that does not exist.
     *
     * Changes that must make it fail: require_template() skipped by one of the branches (the list, the table, an
     * unknown view), or the shell rendered for a template that is not offered.
     *
     * @param string $view The view parameter; "none" sends none.
     * @param bool $exists Whether the template exists (and is offered in the system, not in the category).
     * @return void
     */
    #[DataProvider('refused_templates')]
    public function test_a_template_that_is_not_offered_is_refused_in_every_view(string $view, bool $exists): void {
        $this->setAdminUser();
        [$templateid] = $this->scenario();
        $catcontext = \context_coursecat::instance($this->getDataGenerator()->create_category()->id);
        // The precondition: the system's template is offered at the system, and not in the category.
        $this->assertNotNull(\local_evidencehub\local\hub_access::offered_template(
            $templateid,
            \context_system::instance()
        ));
        $this->assertNull(\local_evidencehub\local\hub_access::offered_template($templateid, $catcontext));

        $params = ['contextid' => $catcontext->id, 'templateid' => $exists ? $templateid : $templateid + 1000];
        if ($view !== 'none') {
            $params['view'] = $view;
        }
        $this->assertSame('errorhubtemplate', $this->refusal($params)->errorcode);
    }

    /**
     * The control of the refusals above: the same template opens in the context that offers it, whether the view
     * is the list or one the page does not know.
     *
     * @param string $view The view parameter.
     * @return void
     */
    #[DataProvider('offered_views')]
    public function test_an_offered_template_opens_for_the_list_and_an_unknown_view(string $view): void {
        $this->setAdminUser();
        [$templateid] = $this->scenario();
        $html = $this->run_page([
            'contextid' => \context_system::instance()->id,
            'templateid' => $templateid,
            'view' => $view,
        ]);
        $this->assertStringContainsString('data-region="local-evidencehub-reportview"', $html);
    }

    /**
     * The views the control opens.
     *
     * @return array Case name => [view].
     */
    public static function offered_views(): array {
        return ['list' => ['list'], 'unknown' => ['bogus']];
    }

    /**
     * The picker prints a hostile category name escaped exactly once.
     *
     * Changes that must make it fail: the name printed raw, or escaped before core's select prints it.
     *
     * @return void
     */
    public function test_the_picker_escapes_a_hostile_category_name_once(): void {
        $this->setAdminUser();
        $category = $this->getDataGenerator()->create_category(['name' => 'Ops 5 < 6 & co']);
        $catcontext = \context_coursecat::instance($category->id);
        [$c1] = $this->competencies(1, $catcontext);
        $templateid = $this->template([$c1], $catcontext);

        $html = $this->run_page(['contextid' => $catcontext->id, 'templateid' => $templateid]);
        $this->assertSame(1, preg_match('~<option\s+value="' . $templateid . '"[^>]*>(.*?)</option>~s', $html, $option));
        $this->assertStringContainsString('(Ops 5 &lt; 6 &amp; co)', $option[1]);
        $this->assertStringNotContainsString(' < ', $option[1]);
        $this->assertStringNotContainsString('&amp;lt;', $option[1]);
        $this->assertStringNotContainsString('&amp;amp;', $option[1]);
    }
}
