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
 * Tests for the policy form.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_evidencehub\form;

use local_evidencehub\local\policy as policy_api;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Who reaches the form in which framework, what it starts from, and what it saves.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(policy::class)]
final class policy_test extends \advanced_testcase {
    /** @var \core_competency\competency_framework A framework in the system context. */
    private $systemframework;

    /** @var \core_competency\competency_framework A framework in a course category. */
    private $catframework;

    /** @var \core_competency\competency A competency of the category framework. */
    private $competency;

    /** @var \stdClass A user managing policies in that category only. */
    private \stdClass $catmanager;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('enabled', 1, 'core_competency');
        $generator = $this->getDataGenerator();
        $competencies = $generator->get_plugin_generator('core_competency');
        $category = $generator->create_category();
        $catcontext = \context_coursecat::instance($category->id);
        $this->systemframework = $competencies->create_framework();
        $this->catframework = $competencies->create_framework(['contextid' => $catcontext->id]);
        $this->competency = $competencies->create_competency(['competencyframeworkid' => $this->catframework->get('id')]);
        $this->catmanager = $generator->create_user();
        $roleid = $generator->create_role();
        assign_capability('local/evidencehub:managepolicies', CAP_ALLOW, $roleid, $catcontext->id, true);
        role_assign($roleid, $this->catmanager->id, $catcontext->id);
        accesslib_clear_all_caches_for_unit_testing();
    }

    /**
     * The form as the modal's web service builds it, submitted with these values or not submitted.
     *
     * @param int $frameworkid The framework.
     * @param int $competencyid The competency, 0 for the framework.
     * @param array|null $values The submitted policy; null for a form being opened.
     * @return policy
     */
    private function form(int $frameworkid, int $competencyid, ?array $values = null): policy {
        $args = ['frameworkid' => $frameworkid, 'competencyid' => $competencyid];
        if ($values === null) {
            return new policy(null, null, 'post', '', [], true, $args, true);
        }
        $submission = policy::mock_ajax_submit($args + $values + [
            'kind' => policy_api::KIND_REGULATORY,
            'requireverified' => 1,
            'requireproficiency' => 0,
            'minhours' => '',
            'alert1' => 90,
            'alert2' => 30,
            'gracedays' => 0,
            'cyclemonths' => 0,
            'onexpiry' => policy_api::ONEXPIRY_LOG,
        ]);
        return new policy(null, null, 'post', '', [], true, $submission, true);
    }

    /**
     * The errors of a form that did not validate.
     *
     * @param policy $form The form.
     * @return array Field => error.
     */
    private function errors(policy $form): array {
        return (new \ReflectionProperty(\moodleform::class, '_form'))->getValue($form)->_errors;
    }

    /**
     * A category manager saves the policy of their category's framework and of its competency.
     *
     * @return void
     */
    public function test_a_category_manager_saves_their_frameworks_policy(): void {
        global $DB;
        $this->setUser($this->catmanager);
        $frameworkid = (int) $this->catframework->get('id');
        $form = $this->form($frameworkid, 0, ['alert1' => 120]);
        $this->assertTrue($form->is_validated(), json_encode($this->errors($form)));
        $id = $form->process_dynamic_submission();
        $this->assertSame(120, (int) $DB->get_field(policy_api::TABLE, 'alert1', ['id' => $id]));

        $form = $this->form($frameworkid, (int) $this->competency->get('id'), ['kind' => policy_api::KIND_TECHNICAL]);
        $this->assertTrue($form->is_validated(), json_encode($this->errors($form)));
        $form->process_dynamic_submission();
        $resolved = policy_api::resolve((int) $this->competency->get('id'), $frameworkid);
        $this->assertSame(policy_api::SOURCE_COMPETENCY, $resolved->source);
    }

    /**
     * A regulatory policy saves as the browser sends it: without the fields its kind hides.
     *
     * A field hidden by hideIf is disabled, so grace days, cycle months and the proficiency
     * checkbox never reach the server for a regulatory policy.
     *
     * @return void
     */
    public function test_a_regulatory_policy_saves_without_its_hidden_fields(): void {
        global $DB;
        $this->setUser($this->catmanager);
        $frameworkid = (int) $this->catframework->get('id');
        $submission = policy::mock_ajax_submit([
            'frameworkid' => $frameworkid,
            'competencyid' => 0,
            'kind' => policy_api::KIND_REGULATORY,
            'requireverified' => 1,
            'minhours' => '',
            'alert1' => 90,
            'alert2' => 30,
            'onexpiry' => policy_api::ONEXPIRY_LOG,
        ]);
        $form = new policy(null, null, 'post', '', [], true, $submission, true);
        $this->assertTrue($form->is_validated(), json_encode($this->errors($form)));
        $id = $form->process_dynamic_submission();
        $row = $DB->get_record(policy_api::TABLE, ['id' => $id], '*', MUST_EXIST);
        $this->assertSame(policy_api::KIND_REGULATORY, (int) $row->kind);
        $this->assertSame(90, (int) $row->alert1);
        $this->assertSame(0, (int) $row->gracedays);
        $this->assertSame(0, (int) $row->cyclemonths);
    }

    /**
     * The same manager never reaches the form of a system framework.
     *
     * @return void
     */
    public function test_a_category_manager_is_refused_a_system_framework(): void {
        $this->setUser($this->catmanager);
        try {
            $this->form((int) $this->systemframework->get('id'), 0, []);
            $this->fail('A category manager reached the policy of a system framework.');
        } catch (\required_capability_exception $e) {
            $this->assertStringContainsString(get_capability_string('local/evidencehub:managepolicies'), $e->getMessage());
        }
    }

    /**
     * A competency of one framework is refused under another framework's id.
     *
     * @return void
     */
    public function test_a_competency_of_another_framework_is_refused(): void {
        $this->setAdminUser();
        try {
            $this->form((int) $this->systemframework->get('id'), (int) $this->competency->get('id'), []);
            $this->fail('The form opened a competency under another framework.');
        } catch (\moodle_exception $e) {
            $this->assertSame('errorwrongframework', $e->errorcode);
        }
        $form = $this->form((int) $this->catframework->get('id'), (int) $this->competency->get('id'));
        $this->assertInstanceOf(policy::class, $form);
    }

    /**
     * The API's rules show on their fields.
     *
     * @return void
     */
    public function test_the_rules_show_on_the_fields(): void {
        $this->setAdminUser();
        $form = $this->form((int) $this->catframework->get('id'), 0, ['alert1' => 10, 'alert2' => 20]);
        $this->assertFalse($form->is_validated());
        $this->assertSame(get_string('errorpolicyalerts', 'local_evidencehub'), $this->errors($form)['alert2']);
    }

    /**
     * A new override starts from the framework's policy, with its kind's defaults; an existing row
     * starts from itself.
     *
     * @return void
     */
    public function test_what_the_form_starts_from(): void {
        $this->setAdminUser();
        $frameworkid = (int) $this->catframework->get('id');
        $competencyid = (int) $this->competency->get('id');
        policy_api::save((object) [
            'frameworkid' => $frameworkid,
            'competencyid' => 0,
            'kind' => policy_api::KIND_REGULATORY,
            'requireverified' => 0,
            'requireproficiency' => 0,
            'minhours' => 4,
            'alert1' => 45,
            'alert2' => 15,
            'gracedays' => 0,
            'cyclemonths' => 0,
            'onexpiry' => policy_api::ONEXPIRY_REVIEW,
        ]);
        $form = $this->form($frameworkid, $competencyid);
        $form->set_data_for_dynamic_submission();
        $defaults = (new \ReflectionProperty(\moodleform::class, '_form'))->getValue($form)->_defaultValues;
        $this->assertEquals([policy_api::KIND_REGULATORY, 1, 45, 15, 4.0, policy_api::ONEXPIRY_REVIEW], [
            $defaults['kind'], $defaults['requireverified'], $defaults['alert1'], $defaults['alert2'], $defaults['minhours'],
            $defaults['onexpiry'],
        ]);

        // The framework's own row starts from itself, its requireverified off as saved.
        $form = $this->form($frameworkid, 0);
        $form->set_data_for_dynamic_submission();
        $defaults = (new \ReflectionProperty(\moodleform::class, '_form'))->getValue($form)->_defaultValues;
        $this->assertEquals([0, 45], [$defaults['requireverified'], $defaults['alert1']]);
    }
}
