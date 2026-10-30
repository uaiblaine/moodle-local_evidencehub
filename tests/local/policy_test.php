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
 * Tests for the policy API.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_evidencehub\local;

use local_evidencehub\event\validity_policy_updated;
use local_evidencehub\task\recompute_scope;
use local_evidencehub\tests\scenario;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Which policy applies, what a policy may say, and who may change it.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(policy::class)]
#[CoversClass(validity_policy_updated::class)]
final class policy_test extends \advanced_testcase {
    use scenario;

    /** @var \core_competency\competency_framework A framework in the system context. */
    private $framework;

    /** @var \core_competency\competency A competency of it. */
    private $competency;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('enabled', 1, 'core_competency');
        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $this->framework = $generator->create_framework();
        $this->competency = $generator->create_competency(['competencyframeworkid' => $this->framework->get('id')]);
        $this->setAdminUser();
    }

    /**
     * A valid policy of the framework, with some values replaced.
     *
     * @param array $overrides Field => value.
     * @return \stdClass
     */
    private function data(array $overrides = []): \stdClass {
        return (object) ($overrides + [
            'frameworkid' => (int) $this->framework->get('id'),
            'competencyid' => 0,
            'kind' => policy::KIND_REGULATORY,
            'requireverified' => 1,
            'requireproficiency' => 0,
            'minhours' => '',
            'alert1' => 90,
            'alert2' => 15,
            'gracedays' => 0,
            'cyclemonths' => 0,
            'onexpiry' => policy::ONEXPIRY_REVIEW,
        ]);
    }

    /**
     * The competency's own row beats the framework's, which beats the default; each says so.
     *
     * @return void
     */
    public function test_resolution_order_and_source(): void {
        $frameworkid = (int) $this->framework->get('id');
        $competencyid = (int) $this->competency->get('id');
        $resolved = policy::resolve($competencyid, $frameworkid);
        $this->assertSame(policy::SOURCE_DEFAULT, $resolved->source);
        $this->assertSame(policy::KIND_TECHNICAL, $resolved->kind);
        $this->assertSame([0, 0, null, 60, 30, 0, 0, policy::ONEXPIRY_LOG], [
            $resolved->requireverified, $resolved->requireproficiency, $resolved->minhours, $resolved->alert1,
            $resolved->alert2, $resolved->gracedays, $resolved->cyclemonths, $resolved->onexpiry,
        ]);

        policy::save($this->data());
        $resolved = policy::resolve($competencyid, $frameworkid);
        $this->assertSame(policy::SOURCE_FRAMEWORK, $resolved->source);
        $this->assertSame(90, $resolved->alert1);

        policy::save($this->data(['competencyid' => $competencyid, 'alert1' => 120, 'kind' => policy::KIND_TECHNICAL]));
        $resolved = policy::resolve($competencyid, $frameworkid);
        $this->assertSame(policy::SOURCE_COMPETENCY, $resolved->source);
        $this->assertSame([120, policy::KIND_TECHNICAL], [$resolved->alert1, $resolved->kind]);

        // Another competency of the framework still reads the framework's row.
        $other = $this->getDataGenerator()->get_plugin_generator('core_competency')
            ->create_competency(['competencyframeworkid' => $frameworkid]);
        $this->assertSame(policy::SOURCE_FRAMEWORK, policy::resolve((int) $other->get('id'), $frameworkid)->source);
        // A row of another framework never applies.
        $this->assertSame(policy::SOURCE_DEFAULT, policy::resolve($competencyid, $frameworkid + 1000)->source);
    }

    /**
     * Each rule refused, beside its passing neighbour.
     *
     * @return array Case name => [overrides, expected field => error or empty].
     */
    public static function rules(): array {
        return [
            'second alert after the first' => [['alert1' => 10, 'alert2' => 11], ['alert2' => 'errorpolicyalerts']],
            'both alerts on one day' => [['alert1' => 10, 'alert2' => 10], []],
            'no alerts at all' => [['alert1' => 0, 'alert2' => 0], []],
            'negative second alert' => [['alert1' => 10, 'alert2' => -1], ['alert2' => 'errorpolicyrange']],
            'negative grace days' => [['gracedays' => -1], ['gracedays' => 'errorpolicyrange']],
            'too many grace days' => [['gracedays' => 10000], ['gracedays' => 'errorpolicyrange']],
            'the largest grace' => [['gracedays' => 9999], []],
            'negative cycle' => [['cyclemonths' => -1], ['cyclemonths' => 'errorpolicyrange']],
            'behavioural that never expires' => [['kind' => policy::KIND_BEHAVIOURAL, 'cyclemonths' => 0], []],
            'negative minimum hours' => [['minhours' => -0.5], ['minhours' => 'errorpolicyrange']],
            'zero minimum hours' => [['minhours' => 0], []],
            'unknown kind' => [['kind' => 9], ['kind' => 'errorpolicykind']],
            'unknown expiry behaviour' => [['onexpiry' => 9], ['onexpiry' => 'errorpolicyonexpiry']],
        ];
    }

    /**
     * The rules hold in problems() and in save().
     *
     * @param array $overrides Field => value.
     * @param array $expected Field => error string id.
     * @return void
     */
    #[DataProvider('rules')]
    public function test_validation(array $overrides, array $expected): void {
        global $DB;
        $data = $this->data($overrides);
        $this->assertSame($expected, policy::problems($data));
        if ($expected) {
            try {
                policy::save($data);
                $this->fail('A policy breaking a rule was saved.');
            } catch (\moodle_exception $e) {
                $this->assertSame(reset($expected), $e->errorcode);
            }
            $this->assertSame(0, $DB->count_records(policy::TABLE));
        } else {
            policy::save($data);
            $this->assertSame(1, $DB->count_records(policy::TABLE));
        }
    }

    /**
     * A new regulatory row starts verified-only; the other kinds start without it.
     *
     * @return void
     */
    public function test_kind_defaults(): void {
        $this->assertSame(1, policy::kind_defaults(policy::KIND_REGULATORY)->requireverified);
        $this->assertSame(0, policy::kind_defaults(policy::KIND_TECHNICAL)->requireverified);
        $this->assertSame(0, policy::kind_defaults(policy::KIND_BEHAVIOURAL)->requireverified);
        // The resolver never applies them: the default policy is technical and counts unverified evidence.
        $this->assertSame(0, policy::defaults()->requireverified);
    }

    /**
     * Saving again replaces the row; the event fires and the scope is queued on every change.
     *
     * @return void
     */
    public function test_save_upserts_fires_and_queues(): void {
        global $DB;
        $sink = $this->redirectEvents();
        $first = policy::save($this->data());
        $second = policy::save($this->data(['alert1' => 100]));
        $events = array_values(array_filter(
            $sink->get_events(),
            fn($event): bool => $event instanceof validity_policy_updated
        ));
        $sink->close();

        $this->assertSame((int) $first->id, (int) $second->id);
        $this->assertSame(1, $DB->count_records(policy::TABLE));
        $this->assertSame(100, (int) $second->alert1);
        $this->assertCount(2, $events);
        $this->assertSame((int) $this->framework->get('id'), $events[0]->other['frameworkid']);
        $this->assertSame(0, $events[0]->other['competencyid']);
        $queued = \core\task\manager::get_adhoc_tasks(recompute_scope::class);
        $this->assertCount(1, $queued, 'The same scope was queued twice.');
        $this->assertEquals((object) ['frameworkid' => (int) $this->framework->get('id')], reset($queued)->get_custom_data());
    }

    /**
     * A competency's row queues that competency; removing it queues it again and the framework's applies.
     *
     * @return void
     */
    public function test_an_override_and_its_removal(): void {
        $frameworkid = (int) $this->framework->get('id');
        $competencyid = (int) $this->competency->get('id');
        policy::save($this->data());
        policy::save($this->data(['competencyid' => $competencyid, 'kind' => policy::KIND_TECHNICAL, 'requireverified' => 0]));
        $scopes = array_map(
            fn($task) => $task->get_custom_data(),
            array_values(\core\task\manager::get_adhoc_tasks(recompute_scope::class))
        );
        $this->assertContainsEquals((object) ['competencyid' => $competencyid], $scopes);

        $this->assertTrue(policy::delete($frameworkid, $competencyid));
        $this->assertSame(policy::SOURCE_FRAMEWORK, policy::resolve($competencyid, $frameworkid)->source);
        $this->assertFalse(policy::delete($frameworkid, $competencyid), 'A missing row was removed twice.');
    }

    /**
     * A technical row keeps requireproficiency; another kind stores it off.
     *
     * @return void
     */
    public function test_requireproficiency_is_stored_for_technical_only(): void {
        $saved = policy::save($this->data(['kind' => policy::KIND_TECHNICAL, 'requireproficiency' => 1]));
        $this->assertSame(1, (int) $saved->requireproficiency);
        $saved = policy::save($this->data(['kind' => policy::KIND_REGULATORY, 'requireproficiency' => 1]));
        $this->assertSame(0, (int) $saved->requireproficiency);
    }

    /**
     * A category manager manages a framework of their category, never one of the system or another category.
     *
     * @return void
     */
    public function test_managepolicies_in_the_frameworks_context(): void {
        $generator = $this->getDataGenerator();
        $category = $generator->create_category();
        $catcontext = \context_coursecat::instance($category->id);
        $catframework = $generator->get_plugin_generator('core_competency')->create_framework(['contextid' => $catcontext->id]);
        $manager = $generator->create_user();
        $roleid = $generator->create_role();
        assign_capability('local/evidencehub:managepolicies', CAP_ALLOW, $roleid, $catcontext->id, true);
        role_assign($roleid, $manager->id, $catcontext->id);
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($manager);

        $saved = policy::save($this->data(['frameworkid' => (int) $catframework->get('id')]));
        $this->assertSame((int) $catframework->get('id'), (int) $saved->frameworkid);
        try {
            policy::save($this->data());
            $this->fail('A category manager changed the policy of a system framework.');
        } catch (\required_capability_exception $e) {
            $this->assertStringContainsString(get_capability_string('local/evidencehub:managepolicies'), $e->getMessage());
        }
        try {
            policy::delete((int) $this->framework->get('id'), 0);
            $this->fail('A category manager removed the policy of a system framework.');
        } catch (\required_capability_exception $e) {
            $this->assertStringContainsString(get_capability_string('local/evidencehub:managepolicies'), $e->getMessage());
        }
    }

    /**
     * A competency of framework A is never changed under framework B's id; under A's it is.
     *
     * @return void
     */
    public function test_a_competency_of_another_framework_is_refused(): void {
        global $DB;
        $other = $this->getDataGenerator()->get_plugin_generator('core_competency')->create_framework();
        $data = $this->data(['frameworkid' => (int) $other->get('id'), 'competencyid' => (int) $this->competency->get('id')]);
        try {
            policy::save($data);
            $this->fail('A competency was given a policy under another framework.');
        } catch (\moodle_exception $e) {
            $this->assertSame('errorwrongframework', $e->errorcode);
        }
        $this->assertSame(0, $DB->count_records(policy::TABLE));
        policy::save($this->data(['competencyid' => (int) $this->competency->get('id')]));
        $this->assertSame(1, $DB->count_records(policy::TABLE));
    }
}
