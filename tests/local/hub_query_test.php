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

use core_competency\plan;
use local_evidencehub\tests\hub_scenario;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The hub's pair set: the base set, the access cut, the effective restricted status, the "valid until" key, the search
 * and the cohort.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(hub_query::class)]
#[CoversClass(hub_access::class)]
#[CoversClass(hub_scope::class)]
final class hub_query_test extends \advanced_testcase {
    use hub_scenario;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('enabled', 1, 'core_competency');
        $this->setAdminUser();
    }

    /**
     * Only active plans of the template, only its competencies, no deleted worker, no suspended one
     * unless asked.
     *
     * @return void
     */
    public function test_the_base_set(): void {
        global $DB;
        [$c1, $c2, $c3] = $this->competencies(3);
        $templateid = $this->template([$c1, $c2]);
        $other = $this->template([$c1, $c3]);
        $active = $this->worker($templateid);
        $draft = $this->worker($templateid, [], plan::STATUS_DRAFT);
        $complete = $this->worker($templateid, [], plan::STATUS_COMPLETE);
        $elsewhere = $this->worker($other);
        $deleted = $this->worker($templateid);
        $DB->set_field('user', 'deleted', 1, ['id' => $deleted->id]);
        $suspended = $this->worker($templateid, ['suspended' => 1]);

        $pairs = $this->base_pairs(\context_system::instance(), $this->filter($templateid));
        $this->assertEqualsCanonicalizing([$this->pair($active, $c1), $this->pair($active, $c2)], array_keys($pairs));
        $this->assertSame([status::EFFECTIVE_MISSING, status::EFFECTIVE_MISSING], array_values($pairs));

        $pairs = $this->base_pairs(\context_system::instance(), $this->filter($templateid, ['suspended' => 1]));
        $this->assertEqualsCanonicalizing([
            $this->pair($active, $c1),
            $this->pair($active, $c2),
            $this->pair($suspended, $c1),
            $this->pair($suspended, $c2),
        ], array_keys($pairs));

        // The control: the other template's worker is in its own grid, with its own competencies.
        $pairs = $this->base_pairs(\context_system::instance(), $this->filter($other));
        $this->assertEqualsCanonicalizing([$this->pair($elsewhere, $c1), $this->pair($elsewhere, $c3)], array_keys($pairs));
        $this->assertNotContains((int) $draft->id, array_map('intval', array_keys($pairs)));
        $this->assertNotContains((int) $complete->id, array_map('intval', array_keys($pairs)));
    }

    /**
     * A category manager without the user-context capability sees nobody and is told how many are hidden;
     * given it over a cohort through tool_cohortroles, exactly the cohort's workers; a site manager sees all.
     *
     * @return void
     */
    public function test_the_access_cut_with_and_without_tool_cohortroles(): void {
        $category = $this->getDataGenerator()->create_category();
        $catcontext = \context_coursecat::instance($category->id);
        [$c1] = $this->competencies(1, $catcontext);
        $templateid = $this->template([$c1], $catcontext);
        $team = $this->worker($templateid);
        $outsider = $this->worker($templateid);
        $cohort = $this->getDataGenerator()->create_cohort(['contextid' => $catcontext->id]);
        cohort_add_member($cohort->id, $team->id);
        $manager = $this->viewer(['local/evidencehub:view', 'moodle/competency:templateview'], $catcontext);
        $filter = $this->filter($templateid, [], $catcontext);

        $this->setUser($manager);
        $this->assertSame([], $this->base_pairs($catcontext, $filter));
        $this->assertSame(2, hub_kpis::hidden_workers($templateid, hub_scope::for_user()));
        $this->assertSame(0, $this->workers($filter, $catcontext));

        // Mentor role over the cohort's user contexts, as tool_cohortroles assigns it.
        $this->setAdminUser();
        $mentorrole = $this->getDataGenerator()->create_role();
        assign_capability('moodle/competency:userevidenceview', CAP_ALLOW, $mentorrole, \context_system::instance()->id, true);
        set_role_contextlevels($mentorrole, [CONTEXT_USER]);
        \tool_cohortroles\api::create_cohort_role_assignment((object) [
            'userid' => $manager->id,
            'roleid' => $mentorrole,
            'cohortid' => $cohort->id,
        ]);
        \tool_cohortroles\api::sync_all_cohort_roles();
        accesslib_clear_all_caches_for_unit_testing();

        $this->setUser($manager);
        $this->assertSame([$this->pair($team, $c1)], array_keys($this->base_pairs($catcontext, $filter)));
        $this->assertSame(1, hub_kpis::hidden_workers($templateid, hub_scope::for_user()));
        $this->assertSame(1, $this->workers($filter, $catcontext));

        $this->setAdminUser();
        $this->assertEqualsCanonicalizing(
            [$this->pair($team, $c1), $this->pair($outsider, $c1)],
            array_keys($this->base_pairs($catcontext, $filter))
        );
        $this->assertSame(0, hub_kpis::hidden_workers($templateid, hub_scope::for_user()));
    }

    /**
     * A pair decided by a sensitive evidence reads "restricted" for a viewer without viewsensitive, its status and its
     * "valid until" key alike; with viewsensitive it reads its own status and day.
     *
     * @return void
     */
    public function test_a_sensitive_deciding_evidence_restricts_the_pair_everywhere(): void {
        [$c1, $c2] = $this->competencies(2);
        $templateid = $this->template([$c1, $c2]);
        $worker = $this->worker($templateid);
        $this->status_row((int) $worker->id, $c1, status::STATUS_EXPIRED, 20250101, 'aso');
        $this->status_row((int) $worker->id, $c2, status::STATUS_EXPIRED, 20240101, 'nrcertificate');
        $viewer = $this->viewer(['local/evidencehub:view', 'moodle/competency:userevidenceview']);
        $sensitive = $this->pair($worker, $c1);
        $plain = $this->pair($worker, $c2);
        $system = \context_system::instance();
        $all = $this->filter($templateid);

        $this->setUser($viewer);
        // Restricted sorts first, as if it had no date; its own date (2025) would put it second.
        $this->assertSame(
            [$sensitive => status::EFFECTIVE_RESTRICTED, $plain => status::STATUS_EXPIRED],
            $this->base_pairs($system, $all)
        );
        $this->assertSame([$sensitive => 0, $plain => 20240101], $this->keys($all, $system));

        // The control: with viewsensitive in the worker's context the same pair reads its status.
        $this->setAdminUser();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('local/evidencehub:viewsensitive', CAP_ALLOW, $roleid, \context_user::instance($worker->id)->id, true);
        role_assign($roleid, $viewer->id, \context_user::instance($worker->id)->id);
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($viewer);
        $this->assertSame(
            [$plain => status::STATUS_EXPIRED, $sensitive => status::STATUS_EXPIRED],
            $this->base_pairs($system, $all)
        );
        $this->assertSame([$plain => 20240101, $sensitive => 20250101], $this->keys($all, $system));
    }

    /**
     * The worker's own sensitive evidence is never restricted to them.
     *
     * @return void
     */
    public function test_the_worker_is_never_restricted_from_their_own_pair(): void {
        [$c1] = $this->competencies(1);
        $templateid = $this->template([$c1]);
        $worker = $this->worker($templateid);
        $this->status_row((int) $worker->id, $c1, status::STATUS_VALID, 20300101, 'aso');
        $this->setAdminUser();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('moodle/competency:userevidenceview', CAP_ALLOW, $roleid, \context_system::instance()->id, true);
        role_assign($roleid, $worker->id, \context_system::instance()->id);
        accesslib_clear_all_caches_for_unit_testing();
        $filter = $this->filter($templateid);

        $this->setUser($worker);
        $pairs = $this->base_pairs(\context_system::instance(), $filter);
        $this->assertSame([$this->pair($worker, $c1) => status::STATUS_VALID], $pairs);
    }

    /**
     * The "valid until" key: a restricted and a missing pair read 0, the most urgent; a dated pair its day; a pair
     * with a status and no expiry (valid with no end) sorts after every day, ascending, and first descending; and the
     * day a reader prints is null for the three without one.
     *
     * Changes that must make it fail: the pair with no expiry keyed 0 (it would sort among the missing ones); a
     * restricted or a missing pair keyed as one with no expiry (they would sort last).
     *
     * @return void
     */
    public function test_a_pair_valid_with_no_end_sorts_as_the_least_urgent(): void {
        [$c1, $c2, $c3, $c4] = $this->competencies(4);
        $templateid = $this->template([$c1, $c2, $c3, $c4]);
        $worker = $this->worker($templateid);
        $this->status_row((int) $worker->id, $c1, status::STATUS_VALID);
        $this->status_row((int) $worker->id, $c2, status::STATUS_VALID, 20990101);
        $this->status_row((int) $worker->id, $c3, status::STATUS_EXPIRED, 20250101, 'aso');
        $viewer = $this->viewer(['local/evidencehub:view', 'moodle/competency:userevidenceview']);
        $system = \context_system::instance();
        // Validated as the administrator: the viewer reads the workers' evidence, not the template.
        $filter = $this->filter($templateid);
        [$noend, $dated, $restricted, $missing] = [
            $this->pair($worker, $c1),
            $this->pair($worker, $c2),
            $this->pair($worker, $c3),
            $this->pair($worker, $c4),
        ];

        $this->setUser($viewer);
        $keys = $this->keys($filter, $system);
        $this->assertSame([$restricted => 0, $missing => 0, $dated => 20990101, $noend => hub_query::NO_EXPIRY], $keys);
        // Descending, the pair valid with no end comes first, and the restricted and missing ones last.
        $descending = array_keys($keys);
        usort($descending, fn(string $a, string $b): int => [$keys[$b], $a] <=> [$keys[$a], $b]);
        $this->assertSame([$noend, $dated, $restricted, $missing], $descending);
        $this->assertSame(
            [$restricted => null, $missing => null, $dated => 20990101, $noend => null],
            array_map(fn(int $key): ?int => hub_query::expiry_day($key), $keys)
        );
    }

    /**
     * The search reads first name, last name and the shown ID number, in any case; the cohort keeps its members.
     *
     * @return void
     */
    public function test_search_and_cohort(): void {
        set_config('showuseridentity', 'email,idnumber');
        [$c1] = $this->competencies(1);
        $templateid = $this->template([$c1]);
        $ana = $this->worker($templateid, ['firstname' => 'Ana', 'lastname' => 'Pereira', 'idnumber' => 'EMP-1']);
        $bia = $this->worker($templateid, ['firstname' => 'Bia', 'lastname' => 'Souza', 'idnumber' => 'EMP-2']);
        $system = \context_system::instance();
        $keys = fn(array $values): array => array_keys($this->base_pairs($system, $this->filter($templateid, $values)));

        $this->assertSame([$this->pair($ana, $c1)], $keys(['search' => 'ana']));
        $this->assertSame([$this->pair($bia, $c1)], $keys(['search' => 'SOUZA']));
        $this->assertSame([$this->pair($bia, $c1)], $keys(['search' => 'emp-2']));
        $this->assertCount(2, $keys(['search' => 'EMP']));
        $this->assertSame([], $keys(['search' => 'nobody']));

        $cohort = $this->getDataGenerator()->create_cohort();
        cohort_add_member($cohort->id, $bia->id);
        $this->assertSame([$this->pair($bia, $c1)], $keys(['cohortid' => $cohort->id]));
    }

    /**
     * The ID number is searched only when the viewer is shown it in the page context.
     *
     * @return void
     */
    public function test_the_search_reads_the_id_number_only_when_shown(): void {
        set_config('showuseridentity', 'email,idnumber');
        [$c1] = $this->competencies(1);
        $templateid = $this->template([$c1]);
        $worker = $this->worker($templateid, ['firstname' => 'Ana', 'lastname' => 'Pereira', 'idnumber' => 'EMP-1']);
        $system = \context_system::instance();
        $byidnumber = $this->filter($templateid, ['search' => 'EMP-1']);
        $byname = $this->filter($templateid, ['search' => 'Pereira']);
        $caps = ['local/evidencehub:view', 'moodle/competency:userevidenceview'];
        $blind = $this->viewer($caps);
        $shown = $this->viewer(array_merge($caps, ['moodle/site:viewuseridentity']));

        $this->setUser($blind);
        $this->assertSame([], \core_user\fields::get_identity_fields($system, false));
        $this->assertSame([], $this->base_pairs($system, $byidnumber));
        $this->assertSame([$this->pair($worker, $c1)], array_keys($this->base_pairs($system, $byname)));
        $this->assertCount(0, $this->base_pairs($system, $byidnumber));

        // The control: a viewer shown the ID number finds the worker by it.
        $this->setUser($shown);
        $this->assertSame([$this->pair($worker, $c1)], array_keys($this->base_pairs($system, $byidnumber)));
        $this->assertCount(1, $this->base_pairs($system, $byidnumber));
    }

    /**
     * From a category only that category's templates and its subcategories' are offered; a template
     * elsewhere is refused, as is a cohort of another category.
     *
     * @return void
     */
    public function test_templates_and_cohorts_follow_the_context(): void {
        $generator = $this->getDataGenerator();
        $category = $generator->create_category();
        $child = $generator->create_category(['parent' => $category->id]);
        $other = $generator->create_category();
        $catcontext = \context_coursecat::instance($category->id);
        [$c1] = $this->competencies(1);
        $own = $this->template([$c1], $catcontext);
        $below = $this->template([$c1], \context_coursecat::instance($child->id));
        $elsewhere = $this->template([$c1], \context_coursecat::instance($other->id));
        $site = $this->template([$c1]);

        $this->assertEqualsCanonicalizing([$own, $below], array_keys(hub_access::templates($catcontext)));
        $everywhere = array_keys(hub_access::templates(\context_system::instance()));
        $this->assertEqualsCanonicalizing([$own, $below, $elsewhere, $site], $everywhere);
        $this->assertSame($own, hub_access::require_template($own, $catcontext)->id);
        try {
            hub_access::require_template($elsewhere, $catcontext);
            $this->fail('A template of another category was accepted.');
        } catch (\moodle_exception $e) {
            $this->assertSame('errorhubtemplate', $e->errorcode);
        }
        $this->expectException(\moodle_exception::class);
        hub_access::require_template($site, $catcontext);
    }

    /**
     * A template the viewer cannot read is neither offered nor accepted, even in their own category.
     *
     * @return void
     */
    public function test_an_unreadable_template_is_refused(): void {
        $category = $this->getDataGenerator()->create_category();
        $catcontext = \context_coursecat::instance($category->id);
        $templateid = $this->template($this->competencies(1), $catcontext);
        $reader = $this->viewer(['local/evidencehub:view', 'moodle/competency:templateview'], $catcontext);
        $blind = $this->viewer(['local/evidencehub:view'], $catcontext);

        // The control: with templateview the same template is offered.
        $this->setUser($reader);
        $this->assertSame([$templateid], array_keys(hub_access::templates($catcontext)));

        $this->setUser($blind);
        $this->assertSame([], hub_access::templates($catcontext));
        $this->expectExceptionMessage(get_string('errorhubtemplate', 'local_evidencehub'));
        hub_access::require_template($templateid, $catcontext);
    }

    /**
     * A cohort of the context or above is accepted; one of another category, or a hidden one without
     * moodle/cohort:view, is refused.
     *
     * @return void
     */
    public function test_cohorts_follow_the_context(): void {
        $generator = $this->getDataGenerator();
        $category = $generator->create_category();
        $other = $generator->create_category();
        $catcontext = \context_coursecat::instance($category->id);
        $own = $generator->create_cohort(['contextid' => $catcontext->id]);
        $site = $generator->create_cohort();
        $elsewhere = $generator->create_cohort(['contextid' => \context_coursecat::instance($other->id)->id]);
        $hidden = $generator->create_cohort(['contextid' => $catcontext->id, 'visible' => 0]);

        $this->assertEqualsCanonicalizing([$own->id, $site->id, $hidden->id], array_keys(hub_access::cohorts($catcontext)));
        $this->assertSame((int) $own->id, hub_access::require_cohort((int) $own->id, $catcontext)->id);
        $this->assertSame((int) $site->id, hub_access::require_cohort((int) $site->id, $catcontext)->id);

        $this->setUser($this->viewer(['local/evidencehub:view'], $catcontext));
        $this->assertEqualsCanonicalizing([$own->id, $site->id], array_keys(hub_access::cohorts($catcontext)));
        foreach ([$elsewhere, $hidden] as $cohort) {
            try {
                hub_access::require_cohort((int) $cohort->id, $catcontext);
                $this->fail('Cohort ' . $cohort->id . ' was accepted.');
            } catch (\moodle_exception $e) {
                $this->assertSame('errorhubcohort', $e->errorcode);
            }
        }
    }

    /**
     * The hub opens on the site and in a category, for holders of view there only.
     *
     * @return void
     */
    public function test_the_hub_opens_in_its_contexts_only(): void {
        $category = $this->getDataGenerator()->create_category();
        $other = $this->getDataGenerator()->create_category();
        $catcontext = \context_coursecat::instance($category->id);
        hub_access::require_view(\context_system::instance());
        hub_access::require_view($catcontext);

        $this->setUser($this->viewer(['local/evidencehub:view'], $catcontext));
        hub_access::require_view($catcontext);
        try {
            hub_access::require_view(\context_coursecat::instance($other->id));
            $this->fail('The hub opened in a category without view.');
        } catch (\required_capability_exception $e) {
            $this->assertStringContainsString(get_string('evidencehub:view', 'local_evidencehub'), $e->getMessage());
        }

        $this->setAdminUser();
        $this->expectException(\moodle_exception::class);
        hub_access::require_view(\context_user::instance(get_admin()->id));
    }

    /**
     * Two requests of the same viewer name the scope's placeholders differently and share its key.
     *
     * @return void
     */
    public function test_the_scope_key_ignores_placeholder_names(): void {
        [$c1] = $this->competencies(1);
        $templateid = $this->template([$c1]);
        $worker = $this->worker($templateid);
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('moodle/competency:userevidenceview', CAP_ALLOW, $roleid, \context_user::instance($worker->id)->id, true);
        $viewer = $this->getDataGenerator()->create_user();
        role_assign($roleid, $viewer->id, \context_user::instance($worker->id)->id);
        accesslib_clear_all_caches_for_unit_testing();

        $first = hub_scope::for_user((int) $viewer->id);
        $second = hub_scope::for_user((int) $viewer->id);
        $this->assertNotSame(array_keys($first->viewparams), array_keys($second->viewparams));
        $this->assertSame($first->key(), $second->key());
        $this->assertNotSame($first->key(), hub_scope::for_user((int) get_admin()->id)->key());
    }

    /**
     * The "valid until" key of every pair of the base set, in the order the list sorts by it: the key, then the pair.
     *
     * @param hub_filter $filter The filter.
     * @param \context $context The context.
     * @return array "userid:competencyid" => eexpiry.
     */
    private function keys(hub_filter $filter, \context $context): array {
        global $DB;
        [$sql, $params] = hub_query::base_sql($filter, hub_scope::for_user(), $context);
        $keys = [];
        $records = $DB->get_recordset_sql("SELECT b.userid, b.competencyid, b.eexpiry
                                             FROM ($sql) b
                                         ORDER BY b.eexpiry, b.userid, b.competencyid", $params);
        foreach ($records as $record) {
            $keys[$record->userid . ':' . $record->competencyid] = (int) $record->eexpiry;
        }
        $records->close();
        return $keys;
    }

    /**
     * How many workers the base set holds, as the report's summary counts them ({@see hub_kpis::readiness_of()}).
     *
     * @param hub_filter $filter The filter.
     * @param \context $context The context.
     * @return int
     */
    private function workers(hub_filter $filter, \context $context): int {
        [$sql, $params] = hub_query::base_sql($filter, hub_scope::for_user(), $context);
        return hub_kpis::readiness_of($sql, $params)[0];
    }
}
