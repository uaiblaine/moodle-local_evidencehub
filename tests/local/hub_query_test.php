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
 * The hub's grid: the base set, the access cut, the effective restricted status, sorting and paging.
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

        $pairs = $this->all_pages(\context_system::instance(), $this->filter($templateid));
        $this->assertEqualsCanonicalizing([$this->pair($active, $c1), $this->pair($active, $c2)], array_keys($pairs));
        $this->assertSame([status::EFFECTIVE_MISSING, status::EFFECTIVE_MISSING], array_values($pairs));

        $pairs = $this->all_pages(\context_system::instance(), $this->filter($templateid, ['suspended' => 1]));
        $this->assertEqualsCanonicalizing([
            $this->pair($active, $c1),
            $this->pair($active, $c2),
            $this->pair($suspended, $c1),
            $this->pair($suspended, $c2),
        ], array_keys($pairs));

        // The control: the other template's worker is in its own grid, with its own competencies.
        $pairs = $this->all_pages(\context_system::instance(), $this->filter($other));
        $this->assertEqualsCanonicalizing([$this->pair($elsewhere, $c1), $this->pair($elsewhere, $c3)], array_keys($pairs));
        $this->assertNotContains((int) $draft->id, array_map('intval', array_keys($pairs)));
        $this->assertNotContains((int) $complete->id, array_map('intval', array_keys($pairs)));
    }

    /**
     * A stored status shows as itself, with its expiry and the deciding evidence's type and issuer.
     *
     * @return void
     */
    public function test_a_page_carries_the_details_in_step_one_order(): void {
        [$c1, $c2] = $this->competencies(2);
        $templateid = $this->template([$c1, $c2]);
        $worker = $this->worker($templateid, ['firstname' => 'Ana', 'lastname' => 'Souza']);
        $this->status_row((int) $worker->id, $c1, status::STATUS_EXPIRED, 20260101, 'nrcertificate');
        $this->status_row((int) $worker->id, $c2, status::STATUS_DUE, 20261201);

        $query = new hub_query(\context_system::instance(), hub_scope::for_user());
        $rows = $query->page($this->filter($templateid));
        $this->assertCount(2, $rows);
        // By expiry: the expired one first.
        $this->assertSame([$c1, $c2], array_column($rows, 'competencyid'));
        $this->assertSame([status::STATUS_EXPIRED, status::STATUS_DUE], array_column($rows, 'estatus'));
        $this->assertSame('Souza', $rows[0]->lastname);
        $this->assertSame('C1', $rows[0]->competencyname);
        $this->assertSame('Issuer nrcertificate', $rows[0]->issuer);
        $this->assertSame(20260101, (int) $rows[0]->effectiveexpiry);
        $this->assertNull($rows[1]->issuer);
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
        $this->assertSame([], $this->all_pages($catcontext, $filter));
        $kpis = hub_kpis::compute($filter, hub_scope::for_user(), $catcontext);
        $this->assertSame(2, $kpis['hidden']);
        $this->assertSame(0, $kpis['workers']);

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
        $this->assertSame([$this->pair($team, $c1)], array_keys($this->all_pages($catcontext, $filter)));
        $kpis = hub_kpis::compute($filter, hub_scope::for_user(), $catcontext);
        $this->assertSame(1, $kpis['hidden']);
        $this->assertSame(1, $kpis['workers']);

        $this->setAdminUser();
        $this->assertEqualsCanonicalizing(
            [$this->pair($team, $c1), $this->pair($outsider, $c1)],
            array_keys($this->all_pages($catcontext, $filter))
        );
        $this->assertSame(0, hub_kpis::compute($filter, hub_scope::for_user(), $catcontext)['hidden']);
    }

    /**
     * A pair decided by a sensitive evidence reads "restricted" for a viewer without viewsensitive: in the
     * grid, under the status filter and in the sort; with viewsensitive it reads its own status.
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
        $expired = $this->filter($templateid, ['statuses' => [status::STATUS_EXPIRED]]);

        $this->setUser($viewer);
        $this->assertSame(
            [$sensitive => status::EFFECTIVE_RESTRICTED, $plain => status::STATUS_EXPIRED],
            $this->all_pages($system, $all)
        );
        $this->assertSame([$plain], array_keys($this->all_pages($system, $expired)));
        $rows = (new hub_query($system, hub_scope::for_user()))->page($all);
        // Restricted sorts first, as if it had no date; its own date (2025) would put it second.
        $this->assertSame([$c1, $c2], array_column($rows, 'competencyid'));
        $this->assertSame(0, $rows[0]->eexpiry);

        // The control: with viewsensitive in the worker's context the same pair reads its status.
        $this->setAdminUser();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('local/evidencehub:viewsensitive', CAP_ALLOW, $roleid, \context_user::instance($worker->id)->id, true);
        role_assign($roleid, $viewer->id, \context_user::instance($worker->id)->id);
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($viewer);
        $this->assertEqualsCanonicalizing([$sensitive, $plain], array_keys($this->all_pages($system, $expired)));
        $rows = (new hub_query($system, hub_scope::for_user()))->page($all);
        $this->assertSame([$c2, $c1], array_column($rows, 'competencyid'));
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
        $pairs = $this->all_pages(\context_system::instance(), $filter);
        $this->assertSame([$this->pair($worker, $c1) => status::STATUS_VALID], $pairs);
    }

    /**
     * Paging returns every pair once across pages, even when every sort value ties.
     *
     * @return void
     */
    public function test_paging_is_stable(): void {
        [$c1, $c2] = $this->competencies(2);
        // The template lists the higher id first, so a join order that is not the key's shows.
        $templateid = $this->template([$c2, $c1]);
        $expected = [];
        for ($i = 0; $i < 30; $i++) {
            $worker = $this->worker($templateid, ['lastname' => 'Same', 'firstname' => 'Name']);
            $expected[] = $this->pair($worker, $c1);
            $expected[] = $this->pair($worker, $c2);
        }
        foreach (hub_filter::SORTS as $sort) {
            $filter = $this->filter($templateid, ['sort' => $sort, 'perpage' => 25]);
            $pairs = $this->all_pages(\context_system::instance(), $filter);
            $this->assertCount(60, $pairs, $sort);
            $this->assertEqualsCanonicalizing($expected, array_keys($pairs), $sort);
            // Ties end in the unique key: the order is the same on every run.
            $this->assertSame(array_keys($pairs), array_keys($this->all_pages(\context_system::instance(), $filter)), $sort);
        }
        $first = array_keys($this->all_pages(\context_system::instance(), $this->filter($templateid, ['perpage' => 25])));
        $sorted = $first;
        $key = fn(string $pair): array => array_map('intval', explode(':', $pair));
        usort($sorted, fn(string $a, string $b): int => $key($a) <=> $key($b));
        $this->assertSame($sorted, $first);
    }

    /**
     * Each sort orders by its value, then by the pair's key; desc reverses the value only.
     *
     * @return void
     */
    public function test_each_sort(): void {
        [$c1, $c2] = $this->competencies(2);
        $templateid = $this->template([$c2, $c1]);
        $zeta = $this->worker($templateid, ['lastname' => 'Zeta', 'firstname' => 'Ana']);
        $alfa = $this->worker($templateid, ['lastname' => 'Alfa', 'firstname' => 'Bia']);
        $this->status_row((int) $zeta->id, $c1, status::STATUS_DUE, 20261001);
        $this->status_row((int) $zeta->id, $c2, status::STATUS_VALID, 20290101);
        $this->status_row((int) $alfa->id, $c1, status::STATUS_EXPIRED, 20250101);
        $system = \context_system::instance();
        $keys = fn(array $values): array => array_keys($this->all_pages($system, $this->filter($templateid, $values)));

        $this->assertSame([
            $this->pair($alfa, $c2),
            $this->pair($alfa, $c1),
            $this->pair($zeta, $c1),
            $this->pair($zeta, $c2),
        ], $keys(['sort' => 'expiry']));
        $this->assertSame([
            $this->pair($alfa, $c1),
            $this->pair($alfa, $c2),
            $this->pair($zeta, $c1),
            $this->pair($zeta, $c2),
        ], $keys(['sort' => 'worker']));
        $this->assertSame([
            $this->pair($zeta, $c1),
            $this->pair($zeta, $c2),
            $this->pair($alfa, $c1),
            $this->pair($alfa, $c2),
        ], $keys(['sort' => 'worker', 'dir' => 'desc']));
        // Zeta was created first, so the lower user id comes first within a competency.
        $this->assertSame([
            $this->pair($zeta, $c1),
            $this->pair($alfa, $c1),
            $this->pair($zeta, $c2),
            $this->pair($alfa, $c2),
        ], $keys(['sort' => 'competency']));
        // By status code: missing, then compliant, due and expired.
        $this->assertSame([
            $this->pair($alfa, $c2),
            $this->pair($zeta, $c2),
            $this->pair($zeta, $c1),
            $this->pair($alfa, $c1),
        ], $keys(['sort' => 'status']));
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
        $keys = fn(array $values): array => array_keys($this->all_pages($system, $this->filter($templateid, $values)));

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
        $this->assertSame([], $this->all_pages($system, $byidnumber));
        $this->assertSame([$this->pair($worker, $c1)], array_keys($this->all_pages($system, $byname)));
        $this->assertSame(0, hub_kpis::compute($byidnumber, hub_scope::for_user(), $system)['pairs']);

        // The control: a viewer shown the ID number finds the worker by it.
        $this->setUser($shown);
        $this->assertSame([$this->pair($worker, $c1)], array_keys($this->all_pages($system, $byidnumber)));
        $this->assertSame(1, hub_kpis::compute($byidnumber, hub_scope::for_user(), $system)['pairs']);
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
}
