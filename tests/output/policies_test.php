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

/**
 * Tests for the policy pages.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_evidencehub\output;

use local_evidencehub\local\policy;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Each manager lists the frameworks of their contexts; a framework's page says where each policy comes from.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(policy_frameworks::class)]
#[CoversClass(framework_policies::class)]
final class policies_test extends \advanced_testcase {
    /**
     * A category manager lists their category's framework only; the site manager lists both.
     *
     * @return void
     */
    public function test_the_list_follows_each_frameworks_context(): void {
        global $PAGE;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $category = $generator->create_category();
        $catcontext = \context_coursecat::instance($category->id);
        $competencies = $generator->get_plugin_generator('core_competency');
        $system = $competencies->create_framework(['shortname' => 'System framework']);
        $local = $competencies->create_framework(['shortname' => 'Category framework', 'contextid' => $catcontext->id]);
        $manager = $generator->create_user();
        $roleid = $generator->create_role();
        assign_capability('local/evidencehub:managepolicies', CAP_ALLOW, $roleid, $catcontext->id, true);
        role_assign($roleid, $manager->id, $catcontext->id);
        accesslib_clear_all_caches_for_unit_testing();
        $output = $PAGE->get_renderer('core');

        $this->setUser($manager);
        $listed = array_column((new policy_frameworks())->export_for_template($output)['frameworks'], 'id');
        $this->assertSame([(int) $local->get('id')], $listed);

        $this->setUser($generator->create_user());
        $this->assertFalse((new policy_frameworks())->export_for_template($output)['hasframeworks']);

        $this->setAdminUser();
        $listed = array_column((new policy_frameworks())->export_for_template($output)['frameworks'], 'id');
        $this->assertEqualsCanonicalizing([(int) $system->get('id'), (int) $local->get('id')], $listed);
    }

    /**
     * Each competency shows the policy in force and its source; an override is marked as one.
     *
     * @return void
     */
    public function test_each_competency_shows_its_source(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        $competencies = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $competencies->create_framework();
        $frameworkid = (int) $framework->get('id');
        $inherits = $competencies->create_competency(['competencyframeworkid' => $frameworkid, 'shortname' => 'A']);
        $overrides = $competencies->create_competency(['competencyframeworkid' => $frameworkid, 'shortname' => 'B']);
        $output = $PAGE->get_renderer('core');

        $export = (new framework_policies($frameworkid))->export_for_template($output);
        $this->assertSame(get_string('source_default', 'local_evidencehub'), $export['policy']['source']);

        $values = [
            'frameworkid' => $frameworkid,
            'requireverified' => 1,
            'requireproficiency' => 0,
            'minhours' => null,
            'alert1' => 60,
            'alert2' => 30,
            'gracedays' => 0,
            'cyclemonths' => 0,
            'onexpiry' => policy::ONEXPIRY_LOG,
        ];
        policy::save((object) (['competencyid' => 0, 'kind' => policy::KIND_REGULATORY] + $values));
        policy::save((object) (['competencyid' => (int) $overrides->get('id'), 'kind' => policy::KIND_TECHNICAL] + $values));
        $export = (new framework_policies($frameworkid))->export_for_template($output);
        $rows = array_column($export['competencies'], null, 'id');
        $this->assertSame(get_string('source_framework', 'local_evidencehub'), $export['policy']['source']);
        $first = $rows[(int) $inherits->get('id')];
        $this->assertFalse($first['isoverride']);
        $this->assertSame(get_string('source_framework', 'local_evidencehub'), $first['policy']['source']);
        $this->assertSame(get_string('kind_regulatory', 'local_evidencehub'), $first['policy']['kind']);
        $second = $rows[(int) $overrides->get('id')];
        $this->assertTrue($second['isoverride']);
        $this->assertSame(get_string('source_competency', 'local_evidencehub'), $second['policy']['source']);
        $this->assertSame(get_string('kind_technical', 'local_evidencehub'), $second['policy']['kind']);

        $html = $output->render_from_template('local_evidencehub/framework_policies', $export);
        $this->assertStringContainsString(get_string('actionremoveoverride', 'local_evidencehub'), $html);
    }

    /**
     * Framework, category and competency names leave the exports plain and render escaped once.
     *
     * @return void
     */
    public function test_names_are_escaped_once(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        $name = 'Safety & Health < 2';
        $category = $this->getDataGenerator()->create_category(['name' => $name]);
        $competencies = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $competencies->create_framework([
            'shortname' => $name,
            'idnumber' => 'A&B',
            'contextid' => \context_coursecat::instance($category->id)->id,
        ]);
        $competencies->create_competency(['competencyframeworkid' => $framework->get('id'), 'shortname' => $name]);
        $output = $PAGE->get_renderer('core');

        $list = (new policy_frameworks())->export_for_template($output);
        $this->assertSame([$name, 'A&B', $name], [
            $list['frameworks'][0]['name'],
            $list['frameworks'][0]['idnumber'],
            $list['frameworks'][0]['contextname'],
        ]);
        $page = (new framework_policies((int) $framework->get('id')))->export_for_template($output);
        $this->assertSame([$name, $name], [$page['name'], $page['competencies'][0]['name']]);

        foreach (['policy_frameworks' => $list, 'framework_policies' => $page] as $template => $context) {
            $html = $output->render_from_template('local_evidencehub/' . $template, $context);
            $this->assertStringContainsString('Safety &amp; Health &lt; 2', $html, $template);
            $this->assertStringNotContainsString('&amp;amp;', $html, $template);
        }
    }

    /**
     * The page itself renders a framework's competencies for a manager: every export runs after the
     * header, once $OUTPUT is the page's renderer rather than core's bootstrap one.
     *
     * @return void
     */
    public function test_the_framework_page_renders(): void {
        // The page script reads these from the global scope.
        global $CFG, $DB, $PAGE, $OUTPUT, $USER, $SITE;
        $this->resetAfterTest();
        $this->setAdminUser();
        $competencies = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $competencies->create_framework(['shortname' => 'Safety']);
        $competencies->create_competency(['competencyframeworkid' => $framework->get('id'), 'shortname' => 'Work at height']);
        $_GET['frameworkid'] = (int) $framework->get('id');
        $level = ob_get_level();
        ob_start();
        try {
            require($CFG->dirroot . '/local/evidencehub/policies.php');
        } finally {
            $html = '';
            while (ob_get_level() > $level) {
                $html = ob_get_clean() . $html;
            }
            unset($_GET['frameworkid']);
        }
        $this->assertStringContainsString('Work at height', $html);
        $this->assertStringContainsString('data-region="local-evidencehub-policies"', $html);
    }
}
