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
 * Tests for the validity policy section of local_dimensions' Competency hub panel.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_evidencehub\local;

use local_evidencehub\tests\scenario;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Who sees a competency's policy in the hub's panel, what its rows say, and who gets the edit link.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(policy_section::class)]
final class policy_section_test extends \advanced_testcase {
    use scenario;

    /** @var int A framework in a course category. */
    private int $frameworkid;

    /** @var \context_coursecat The framework's category. */
    private \context_coursecat $category;

    /** @var int A competency of the framework with a policy of its own. */
    private int $own;

    /** @var int A competency of the framework that inherits the framework's policy. */
    private int $inherits;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('enabled', 1, 'core_competency');
        $this->category = \context_coursecat::instance((int) $this->getDataGenerator()->create_category()->id);
        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $this->frameworkid = (int) $generator->create_framework(['contextid' => $this->category->id])->get('id');
        $this->own = (int) $generator->create_competency(['competencyframeworkid' => $this->frameworkid])->get('id');
        $this->inherits = (int) $generator->create_competency(['competencyframeworkid' => $this->frameworkid])->get('id');
    }

    /**
     * Save the framework's policy and the first competency's own, as an administrator.
     *
     * @return void
     */
    private function set_policies(): void {
        $this->setAdminUser();
        $base = [
            'frameworkid' => $this->frameworkid,
            'requireverified' => 1,
            'requireproficiency' => 0,
            'minhours' => '',
            'alert1' => 90,
            'alert2' => 15,
            'gracedays' => 0,
            'cyclemonths' => 0,
            'onexpiry' => policy::ONEXPIRY_REVIEW,
        ];
        policy::save((object) (['competencyid' => 0, 'kind' => policy::KIND_REGULATORY] + $base));
        policy::save((object) ([
            'competencyid' => $this->own,
            'kind' => policy::KIND_TECHNICAL,
            'requireverified' => 0,
            'minhours' => '8',
            'gracedays' => 10,
            'cyclemonths' => 12,
            'onexpiry' => policy::ONEXPIRY_LOG,
        ] + $base));
    }

    /**
     * A row as the section builds it.
     *
     * @param string $label The label's string id in local_evidencehub.
     * @param string $value The value.
     * @param string $tone The tone, '' for none.
     * @return array
     */
    private static function row(string $label, string $value, string $tone = ''): array {
        return ['label' => get_string($label, 'local_evidencehub'), 'value' => $value, 'tone' => $tone];
    }

    /**
     * Grant capabilities to a new user in one context only.
     *
     * @param array $capabilities Capability names.
     * @param \context $context Where.
     * @return \stdClass The user.
     */
    private function user_in(array $capabilities, \context $context): \stdClass {
        $user = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        foreach ($capabilities as $capability) {
            assign_capability($capability, CAP_ALLOW, $roleid, $context->id, true);
        }
        role_assign($roleid, $user->id, $context->id);
        accesslib_clear_all_caches_for_unit_testing();
        return $user;
    }

    /**
     * A competency's own policy: every cell policies.php shows, in its order, and the source as an info label.
     *
     * @return void
     */
    public function test_an_own_policy_is_shown_as_policies_php_shows_it(): void {
        $this->set_policies();
        $section = policy_section::for_competency($this->own, $this->frameworkid);
        $this->assertSame('policy', $section['key']);
        $this->assertSame(get_string('policysection', 'local_evidencehub'), $section['title']);
        $this->assertSame([
            self::row('kind', 'Technical'),
            self::row('requireverified', 'No'),
            self::row('minhours', '8'),
            self::row('alerts', '90 and 15 days before'),
            self::row('gracedays', '10'),
            self::row('cyclemonths', '12'),
            self::row('onexpiry', 'Log it'),
            self::row('source', 'This competency', policy_section::TONE_INFO),
        ], $section['rows']);
    }

    /**
     * An inherited policy says so, and leaves out the cells its kind does not use, as policies.php does.
     *
     * @return void
     */
    public function test_an_inherited_policy_names_the_framework(): void {
        $this->set_policies();
        $section = policy_section::for_competency($this->inherits, $this->frameworkid);
        $this->assertSame([
            self::row('kind', 'Regulatory'),
            self::row('requireverified', 'Yes'),
            self::row('alerts', '90 and 15 days before'),
            self::row('onexpiry', 'Log it and request a review'),
            self::row('source', 'The framework', policy_section::TONE_NEUTRAL),
        ], $section['rows']);
    }

    /**
     * With no policy set the section shows the built-in default, named as policies.php names it.
     *
     * @return void
     */
    public function test_no_policy_shows_the_default(): void {
        $this->setAdminUser();
        $section = policy_section::for_competency($this->inherits, $this->frameworkid);
        $this->assertSame([
            self::row('kind', 'Technical'),
            self::row('requireverified', 'No'),
            self::row('alerts', '60 and 30 days before'),
            self::row('gracedays', '0'),
            self::row('cyclemonths', '0'),
            self::row('onexpiry', 'Log it'),
            self::row('source', 'Default', policy_section::TONE_NEUTRAL),
        ], $section['rows']);
    }

    /**
     * A viewer holding neither capability gets no section; view in the framework's category gives the section
     * without the link (the control), and the same capability in another category gives nothing.
     *
     * @return void
     */
    public function test_the_section_follows_the_capabilities_in_the_framework_context(): void {
        $this->set_policies();
        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertNull(policy_section::for_competency($this->own, $this->frameworkid));

        $elsewhere = \context_coursecat::instance((int) $this->getDataGenerator()->create_category()->id);
        $this->setUser($this->user_in(['local/evidencehub:view', 'local/evidencehub:managepolicies'], $elsewhere));
        $this->assertNull(policy_section::for_competency($this->own, $this->frameworkid));

        $this->setUser($this->user_in(['local/evidencehub:view'], $this->category));
        $section = policy_section::for_competency($this->own, $this->frameworkid);
        $this->assertNotNull($section);
        $this->assertNull($section['linkurl']);
        $this->assertSame('', $section['linklabel']);
    }

    /**
     * Only who may manage the framework's policies gets "Edit policy", to the framework's page on policies.php; a
     * holder of view alone does not (the control above).
     *
     * @return void
     */
    public function test_the_edit_link_goes_to_a_policy_manager_only(): void {
        $this->set_policies();
        $this->setUser($this->user_in(['local/evidencehub:managepolicies'], $this->category));
        $section = policy_section::for_competency($this->own, $this->frameworkid);
        $expected = new \moodle_url('/local/evidencehub/policies.php', ['frameworkid' => $this->frameworkid]);
        $this->assertSame($expected->out(false), $section['linkurl']->out(false));
        $this->assertSame(get_string('actioneditpolicy', 'local_evidencehub'), $section['linklabel']);

        $this->setUser($this->user_in(['local/evidencehub:view'], $this->category));
        $this->assertNull(policy_section::for_competency($this->own, $this->frameworkid)['linkurl']);
    }

    /**
     * Nothing for a guest, with competencies disabled, or for a framework that is gone; each beside its control.
     *
     * @return void
     */
    public function test_nothing_for_a_guest_a_disabled_site_or_a_missing_framework(): void {
        global $DB;
        $guestrole = (int) $DB->get_field('role', 'id', ['shortname' => 'guest'], MUST_EXIST);
        assign_capability('local/evidencehub:view', CAP_ALLOW, $guestrole, \context_system::instance()->id, true);
        accesslib_clear_all_caches_for_unit_testing();
        $this->setGuestUser();
        $this->assertNull(policy_section::for_competency($this->own, $this->frameworkid));

        $this->setAdminUser();
        $this->assertNotNull(policy_section::for_competency($this->own, $this->frameworkid));
        $this->assertNull(policy_section::for_competency($this->own, $this->frameworkid + 1000));
        set_config('enabled', 0, 'core_competency');
        $this->assertNull(policy_section::for_competency($this->own, $this->frameworkid));
    }

    /**
     * A pair whose framework is not the competency's own gives no section, even where the viewer may see every
     * policy; the matching pair is the control.
     *
     * @return void
     */
    public function test_a_framework_that_is_not_the_competency_s_gives_nothing(): void {
        $this->set_policies();
        $foreign = (int) $this->getDataGenerator()->get_plugin_generator('core_competency')->create_framework()->get('id');
        $this->assertNotNull(policy_section::for_competency($this->own, $this->frameworkid));
        $this->assertNull(policy_section::for_competency($this->own, $foreign));
        $this->assertNull(policy_section::for_competency($this->own + 1000, $this->frameworkid));
    }

    /**
     * One competency costs the same few statements whatever its policy: no query grows with the framework.
     *
     * @return void
     */
    public function test_one_call_costs_a_fixed_number_of_statements(): void {
        global $DB;
        $this->set_policies();
        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        for ($i = 0; $i < 5; $i++) {
            $generator->create_competency(['competencyframeworkid' => $this->frameworkid]);
        }
        $this->setUser($this->user_in(['local/evidencehub:managepolicies'], $this->category));
        // Warm the access data and the context cache every call shares.
        policy_section::for_competency($this->own, $this->frameworkid);

        $reads = [];
        foreach ([$this->own, $this->inherits] as $competencyid) {
            $before = $DB->perf_get_reads();
            $this->assertNotNull(policy_section::for_competency($competencyid, $this->frameworkid));
            $reads[] = $DB->perf_get_reads() - $before;
        }
        $this->assertSame($reads[0], $reads[1]);
        $this->assertLessThanOrEqual(3, $reads[0]);
    }
}
