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

use local_evidencehub\local\hub_filter;
use local_evidencehub\local\hub_scope;
use local_evidencehub\local\status;
use local_evidencehub\tests\hub_scenario;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The hub page: its export, its escaping, and the page script for a site and a category manager.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(hub_page::class)]
final class hub_page_test extends \advanced_testcase {
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
     * Without a template the page asks for one; with one it carries tiles, chips, rows and a pager.
     *
     * @return void
     */
    public function test_the_export(): void {
        global $PAGE;
        [$c1] = $this->competencies(1);
        $templateid = $this->template([$c1]);
        $worker = $this->worker($templateid);
        $this->status_row((int) $worker->id, $c1, status::STATUS_EXPIRED, 20250101);
        $system = \context_system::instance();
        $output = $PAGE->get_renderer('core');

        $export = (new hub_page($system, null, hub_scope::for_user()))->export_for_template($output);
        $this->assertFalse($export['hasfilter']);
        $this->assertSame([$templateid], array_column($export['templates'], 'id'));
        $this->assertArrayNotHasKey('rows', $export);

        $filter = hub_filter::from_values(['templateid' => $templateid], $system);
        $export = (new hub_page($system, $filter, hub_scope::for_user()))->export_for_template($output);
        $this->assertTrue($export['hasfilter']);
        $this->assertSame(1, $export['total']);
        $this->assertSame($this->pair($worker, $c1), $export['rows'][0]['pair']);
        $this->assertSame('2025-01-01', $export['rows'][0]['expiry']);
        $this->assertSame(status::label(status::STATUS_EXPIRED), $export['rows'][0]['statuslabel']);
        $tiles = array_column($export['tiles'], null, 'code');
        $this->assertSame(1, $tiles[status::STATUS_EXPIRED]['count']);
        $this->assertTrue($tiles[status::STATUS_EXPIRED]['active']);
        $this->assertFalse($tiles[status::STATUS_VALID]['active']);
        $this->assertArrayNotHasKey(status::EFFECTIVE_RESTRICTED, $tiles);
        // Toggling the expired tile off leaves the other attention statuses.
        parse_str(parse_url($tiles[status::STATUS_EXPIRED]['url'], PHP_URL_QUERY), $query);
        $this->assertNotContains((string) status::STATUS_EXPIRED, $query['statuses']);
        $this->assertContains((string) status::STATUS_DUE, $query['statuses']);
        $this->assertSame('', $export['hiddenworkers']);

        $html = $output->render_from_template('local_evidencehub/hub_page', $export);
        $this->assertStringContainsString('value="' . $this->pair($worker, $c1) . '"', $html);
    }

    /**
     * Template, cohort, worker and competency names leave the export plain and render escaped once.
     *
     * @return void
     */
    public function test_names_are_escaped_once(): void {
        global $PAGE;
        $name = 'Safety & Health < 2';
        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $generator->create_framework();
        $competency = $generator->create_competency(['competencyframeworkid' => $framework->get('id'), 'shortname' => $name]);
        $template = $generator->create_template(['shortname' => $name]);
        $generator->create_template_competency(['templateid' => $template->get('id'), 'competencyid' => $competency->get('id')]);
        $this->worker((int) $template->get('id'), ['firstname' => $name, 'lastname' => 'X']);
        $this->getDataGenerator()->create_cohort(['name' => $name]);
        $system = \context_system::instance();
        $output = $PAGE->get_renderer('core');

        $filter = hub_filter::from_values(['templateid' => $template->get('id'), 'statuses' => []], $system);
        $export = (new hub_page($system, $filter, hub_scope::for_user()))->export_for_template($output);
        $this->assertSame([$name, $name, $name . ' X', $name], [
            $export['templates'][0]['name'],
            $export['cohorts'][0]['name'],
            $export['rows'][0]['workername'],
            $export['rows'][0]['competency'],
        ]);
        $html = $output->render_from_template('local_evidencehub/hub_page', $export);
        $this->assertStringContainsString('Safety &amp; Health &lt; 2', $html);
        $this->assertStringNotContainsString('&amp;amp;', $html);
        $this->assertStringNotContainsString('&amp;lt;', $html);
    }

    /**
     * A site manager opens the hub on the site, picks a template and sees its rows.
     *
     * @return void
     */
    public function test_the_page_renders_for_a_site_manager(): void {
        [$c1] = $this->competencies(1);
        $templateid = $this->template([$c1]);
        $worker = $this->worker($templateid, ['firstname' => 'Ana', 'lastname' => 'Souza']);
        $manager = $this->viewer([
            'local/evidencehub:view',
            'moodle/competency:templateview',
            'moodle/competency:userevidenceview',
        ]);
        $this->setUser($manager);

        $html = $this->run_page(['contextid' => \context_system::instance()->id, 'templateid' => $templateid, 'statusfilter' => 1]);
        $this->assertStringContainsString('data-region="local-evidencehub-hub"', $html);
        $this->assertStringContainsString('Ana Souza', $html);
        $this->assertStringContainsString('value="' . $this->pair($worker, $c1) . '"', $html);
        // The filter the request named is stored.
        $this->assertSame($templateid, hub_filter::from_preference(\context_system::instance())->templateid);
    }

    /**
     * A category manager opens the hub in their category: locked to it, and told who is hidden.
     *
     * @return void
     */
    public function test_the_page_renders_for_a_category_manager(): void {
        $category = $this->getDataGenerator()->create_category(['name' => 'Operations']);
        $catcontext = \context_coursecat::instance($category->id);
        [$c1] = $this->competencies(1, $catcontext);
        $templateid = $this->template([$c1], $catcontext);
        $this->worker($templateid);
        $manager = $this->viewer(['local/evidencehub:view', 'moodle/competency:templateview'], $catcontext);
        $this->setUser($manager);

        $html = $this->run_page(['contextid' => $catcontext->id, 'templateid' => $templateid]);
        $this->assertStringContainsString(get_string('hublocked', 'local_evidencehub', 'Operations'), $html);
        $this->assertStringContainsString(get_string('hubhiddenworkers', 'local_evidencehub', 1), $html);
        $this->assertStringContainsString(get_string('hubnoworkers', 'local_evidencehub'), $html);
    }

    /**
     * A category manager is refused in another category.
     *
     * @return void
     */
    public function test_the_page_refuses_another_category(): void {
        $category = $this->getDataGenerator()->create_category();
        $other = $this->getDataGenerator()->create_category();
        $this->setUser($this->viewer(['local/evidencehub:view'], \context_coursecat::instance($category->id)));
        $this->expectException(\required_capability_exception::class);
        $this->run_page(['contextid' => \context_coursecat::instance($other->id)->id]);
    }

    /**
     * A template of another context is refused by the page, not shown.
     *
     * @return void
     */
    public function test_the_page_refuses_a_template_outside_the_category(): void {
        $category = $this->getDataGenerator()->create_category();
        $catcontext = \context_coursecat::instance($category->id);
        $templateid = $this->template($this->competencies(1));
        $this->expectExceptionMessage(get_string('errorhubtemplate', 'local_evidencehub'));
        $this->run_page(['contextid' => $catcontext->id, 'templateid' => $templateid]);
    }
}
