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

use local_evidencehub\tests\hub_scenario;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The KPI bar: the counts equal the grid's, readiness, and the cache retired by its generation.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(hub_kpis::class)]
final class hub_kpis_test extends \advanced_testcase {
    use hub_scenario;

    /** @var array The competency ids. */
    private array $competencies;

    /** @var int The template. */
    private int $templateid;

    /** @var array The workers. */
    private array $workers;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('enabled', 1, 'core_competency');
        $this->setAdminUser();
        $this->competencies = $this->competencies(3);
        [$c1, $c2, $c3] = $this->competencies;
        $this->templateid = $this->template($this->competencies);
        $ready = $this->worker($this->templateid);
        $this->status_row((int) $ready->id, $c1, status::STATUS_VALID, 20300101);
        $this->status_row((int) $ready->id, $c2, status::STATUS_DUE, 20261201);
        $this->status_row((int) $ready->id, $c3, status::STATUS_CRITICAL, 20261101);
        $mixed = $this->worker($this->templateid);
        $this->status_row((int) $mixed->id, $c1, status::STATUS_EXPIRED, 20250101);
        $this->status_row((int) $mixed->id, $c2, status::STATUS_UNVERIFIED);
        $sensitive = $this->worker($this->templateid);
        $this->status_row((int) $sensitive->id, $c1, status::STATUS_VALID, 20300101, 'aso');
        $this->status_row((int) $sensitive->id, $c2, status::STATUS_VALID, 20300101);
        $this->status_row((int) $sensitive->id, $c3, status::STATUS_VALID, 20300101);
        $this->workers = [$ready, $mixed, $sensitive];
    }

    /**
     * Each tile counts what the grid shows under that status alone, the pager count is the sum of the
     * shown statuses, and "needs attention" holds every pair but the compliant ones.
     *
     * @return void
     */
    public function test_the_tiles_count_what_the_grid_shows(): void {
        $system = \context_system::instance();
        $all = $this->filter($this->templateid);
        $filters = [];
        foreach (status::effective_codes() as $code) {
            $filters[$code] = $this->filter($this->templateid, ['statuses' => [$code], 'perpage' => 25]);
        }
        $attention = hub_filter::from_values(['templateid' => $this->templateid], $system);
        $this->setUser($this->viewer(['local/evidencehub:view', 'moodle/competency:userevidenceview']));
        $scope = hub_scope::for_user();
        $kpis = hub_kpis::get($all, $scope, \context_system::instance());
        $this->assertSame(9, $kpis['pairs']);
        foreach ($filters as $code => $filter) {
            $this->assertCount($kpis['counts'][$code], $this->all_pages($system, $filter), 'Status ' . $code);
            $this->assertSame($kpis['counts'][$code], hub_kpis::pager_count($kpis, $filter));
        }
        $this->assertSame([
            status::EFFECTIVE_MISSING => 1,
            status::STATUS_UNVERIFIED => 1,
            status::STATUS_EXPIRED => 1,
            status::STATUS_CRITICAL => 1,
            status::STATUS_DUE => 1,
            status::STATUS_VALID => 3,
            status::EFFECTIVE_RESTRICTED => 1,
        ], $kpis['counts']);

        $this->assertSame(hub_filter::needs_attention(), $attention->statuses);
        $this->assertSame(6, hub_kpis::pager_count($kpis, $attention));
        $this->assertCount(6, $this->all_pages($system, $attention));
        $this->assertSame(9, hub_kpis::pager_count($kpis, $all));
    }

    /**
     * A worker is ready when every pair is compliant, due or critical; a restricted pair is not.
     *
     * @return void
     */
    public function test_readiness(): void {
        $filter = $this->filter($this->templateid);
        $this->setUser($this->viewer(['local/evidencehub:view', 'moodle/competency:userevidenceview']));
        $kpis = hub_kpis::compute($filter, hub_scope::for_user(), \context_system::instance());
        $this->assertSame([3, 1], [$kpis['workers'], $kpis['ready']]);

        // The control: a viewer who may see the sensitive evidence finds that worker ready too.
        $this->setAdminUser();
        $kpis = hub_kpis::compute($filter, hub_scope::for_user(), \context_system::instance());
        $this->assertSame([3, 2], [$kpis['workers'], $kpis['ready']]);
        $this->assertSame(0, $kpis['counts'][status::EFFECTIVE_RESTRICTED]);
        $this->assertSame(4, $kpis['counts'][status::STATUS_VALID]);
    }

    /**
     * A second call reads only the template's signature: the grouped statements come from the cache.
     *
     * @return void
     */
    public function test_the_cache_serves_a_second_call(): void {
        $filter = $this->filter($this->templateid);
        $scope = hub_scope::for_user();
        $reads = $this->reads(fn() => hub_kpis::get($filter, $scope, \context_system::instance()));
        $this->assertGreaterThanOrEqual(4, $reads);
        $this->assertSame(1, $this->reads(fn() => hub_kpis::get($filter, $scope, \context_system::instance())));

        // The control: after a bump the statements run again.
        hub_kpis::bump_generation();
        $this->assertSame($reads, $this->reads(fn() => hub_kpis::get($filter, $scope, \context_system::instance())));
    }

    /**
     * A status written by the repository changes the generation, and so the numbers.
     *
     * @return void
     */
    public function test_a_status_write_retires_the_counts(): void {
        $filter = $this->filter($this->templateid);
        $scope = hub_scope::for_user();
        $system = \context_system::instance();
        $missing = hub_kpis::get($filter, $scope, $system)['counts'][status::EFFECTIVE_MISSING];
        $c3 = $this->competencies[2];
        $worker = $this->workers[1];
        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $ue = $generator->create_user_evidence(['userid' => $worker->id]);
        $generator->create_user_evidence_competency(['userevidenceid' => $ue->get('id'), 'competencyid' => $c3]);
        $this->getDataGenerator()->get_plugin_generator('local_evidencehub')->create_meta([
            'userid' => $worker->id,
            'userevidenceid' => $ue->get('id'),
            'issuedate' => 20200101,
            'expirydate' => 20990101,
        ]);
        // The details are written directly, without a status: the cached counts still stand.
        $generation = hub_kpis::generation();
        $this->assertSame($missing, hub_kpis::get($filter, $scope, $system)['counts'][status::EFFECTIVE_MISSING]);

        status_repository::recompute((int) $worker->id, $c3);
        $this->assertNotSame($generation, hub_kpis::generation());
        $this->assertSame($missing - 1, hub_kpis::get($filter, $scope, $system)['counts'][status::EFFECTIVE_MISSING]);
    }

    /**
     * A plan created from the template changes the generation, and so the numbers.
     *
     * @return void
     */
    public function test_a_plan_created_retires_the_counts(): void {
        $filter = $this->filter($this->templateid);
        $scope = hub_scope::for_user();
        $this->assertSame(3, hub_kpis::get($filter, $scope, \context_system::instance())['workers']);
        $generation = hub_kpis::generation();

        \core_competency\api::create_plan_from_template($this->templateid, (int) $this->getDataGenerator()->create_user()->id);
        $this->assertNotSame($generation, hub_kpis::generation());
        $this->assertSame(4, hub_kpis::get($filter, $scope, \context_system::instance())['workers']);
    }

    /**
     * A cohort member added changes the generation, and so the numbers of a cohort filter.
     *
     * @return void
     */
    public function test_a_cohort_member_added_retires_the_counts(): void {
        $cohort = $this->getDataGenerator()->create_cohort();
        cohort_add_member($cohort->id, $this->workers[0]->id);
        $filter = $this->filter($this->templateid, ['cohortid' => $cohort->id]);
        $scope = hub_scope::for_user();
        $this->assertSame(1, hub_kpis::get($filter, $scope, \context_system::instance())['workers']);
        $generation = hub_kpis::generation();

        cohort_add_member($cohort->id, $this->workers[1]->id);
        $this->assertNotSame($generation, hub_kpis::generation());
        $this->assertSame(2, hub_kpis::get($filter, $scope, \context_system::instance())['workers']);
    }

    /**
     * A user deleted changes the generation, and so the numbers.
     *
     * @return void
     */
    public function test_a_user_deleted_retires_the_counts(): void {
        $filter = $this->filter($this->templateid);
        $scope = hub_scope::for_user();
        $this->assertSame(3, hub_kpis::get($filter, $scope, \context_system::instance())['workers']);
        $generation = hub_kpis::generation();

        delete_user($this->workers[0]);
        $this->assertNotSame($generation, hub_kpis::generation());
        $this->assertSame(2, hub_kpis::get($filter, $scope, \context_system::instance())['workers']);
    }

    /**
     * A competency added to the template changes the numbers although core fires no event for it.
     *
     * @return void
     */
    public function test_a_template_competency_added_changes_the_counts(): void {
        $filter = $this->filter($this->templateid);
        $scope = hub_scope::for_user();
        $this->assertSame(9, hub_kpis::get($filter, $scope, \context_system::instance())['pairs']);
        [$extra] = $this->competencies(1);
        $this->getDataGenerator()->get_plugin_generator('core_competency')->create_template_competency([
            'templateid' => $this->templateid,
            'competencyid' => $extra,
        ]);
        $this->assertSame(12, hub_kpis::get($filter, $scope, \context_system::instance())['pairs']);
    }

    /**
     * A searched call counts every time; the same filter without a search is served from the cache.
     *
     * @return void
     */
    public function test_a_search_is_never_cached(): void {
        $system = \context_system::instance();
        $scope = hub_scope::for_user();
        $searched = $this->filter($this->templateid, ['search' => 'a']);
        $first = $this->reads(fn() => hub_kpis::get($searched, $scope, $system));
        $this->assertGreaterThanOrEqual(3, $first);
        $this->assertSame($first, $this->reads(fn() => hub_kpis::get($searched, $scope, $system)));

        // The control: without the search the second call reads only the template's signature.
        $plain = $this->filter($this->templateid);
        hub_kpis::get($plain, $scope, $system);
        $this->assertSame(1, $this->reads(fn() => hub_kpis::get($plain, $scope, $system)));
    }

    /**
     * The hidden count is the template's hidden workers whatever the cohort, suspended or search filter.
     *
     * @return void
     */
    public function test_the_hidden_count_ignores_the_filter_cuts(): void {
        global $DB;
        $system = \context_system::instance();
        $DB->set_field('user', 'suspended', 1, ['id' => $this->workers[2]->id]);
        $cohort = $this->getDataGenerator()->create_cohort();
        cohort_add_member($cohort->id, $this->workers[0]->id);
        $filters = [
            $this->filter($this->templateid),
            $this->filter($this->templateid, ['cohortid' => $cohort->id]),
            $this->filter($this->templateid, ['suspended' => 1]),
            $this->filter($this->templateid, ['search' => 'nobody at all']),
        ];
        $this->setUser($this->viewer(['local/evidencehub:view']));
        $scope = hub_scope::for_user();
        foreach ($filters as $index => $filter) {
            $this->assertSame(3, hub_kpis::compute($filter, $scope, $system)['hidden'], 'Filter ' . $index);
        }
        // The control: the visible counts do follow the cuts, for a viewer who sees everyone.
        $this->setAdminUser();
        $scope = hub_scope::for_user();
        $this->assertSame([2, 1, 3], [
            hub_kpis::compute($filters[0], $scope, $system)['workers'],
            hub_kpis::compute($filters[1], $scope, $system)['workers'],
            hub_kpis::compute($filters[2], $scope, $system)['workers'],
        ]);
    }

    /**
     * The same viewer, filter and generation give other counts once the viewer's scope changes: the key
     * holds both capability fragments.
     *
     * @return void
     */
    public function test_the_cache_follows_the_scope(): void {
        $system = \context_system::instance();
        $filter = $this->filter($this->templateid);
        $viewer = $this->viewer(['local/evidencehub:view']);
        $sensitive = $this->workers[2];
        $this->setUser($viewer);
        $generation = hub_kpis::generation();
        $this->assertSame(0, hub_kpis::get($filter, hub_scope::for_user(), $system)['workers']);

        // Granted userevidenceview over one worker: the view fragment changes.
        $this->grant_in($viewer, 'moodle/competency:userevidenceview', $sensitive);
        $kpis = hub_kpis::get($filter, hub_scope::for_user(), $system);
        $this->assertSame([1, 1], [$kpis['workers'], $kpis['counts'][status::EFFECTIVE_RESTRICTED]]);

        // Granted viewsensitive over the same worker: the sensitive fragment changes.
        $this->grant_in($viewer, 'local/evidencehub:viewsensitive', $sensitive);
        $kpis = hub_kpis::get($filter, hub_scope::for_user(), $system);
        $this->assertSame(0, $kpis['counts'][status::EFFECTIVE_RESTRICTED]);
        $this->assertSame($generation, hub_kpis::generation());
    }

    /**
     * A generation replaced inside a transaction is replaced once more when the request's transactions are over: a
     * reader arriving after the replacement and before the commit stores pre-commit figures under the new one. The
     * replacement inside is made at once, so a reader inside the transaction sees fresh keys; outside a transaction
     * nothing is deferred.
     *
     * @return void
     */
    public function test_a_generation_replaced_in_a_transaction_is_replaced_again_after_it(): void {
        global $DB;
        // PostgreSQL wraps each test in a transaction, which would make "outside" a lie.
        $this->preventResetByRollback();
        hub_kpis::replace_after_transaction();
        $this->assertFalse($DB->is_transaction_started());

        hub_kpis::bump_generation();
        hub_kpis::bump_report_generation();
        $this->assertFalse(hub_kpis::has_deferred_replacement());

        $bumps = [
            'both' => fn() => hub_kpis::bump_generation(),
            'report' => fn() => hub_kpis::bump_report_generation(),
        ];
        foreach ($bumps as $name => $bump) {
            $transaction = $DB->start_delegated_transaction();
            $before = [hub_kpis::generation(), hub_kpis::report_generation()];
            $bump();
            $this->assertNotSame($before[1], hub_kpis::report_generation(), $name);
            $this->assertTrue(hub_kpis::has_deferred_replacement(), $name);
            $transaction->allow_commit();

            // What the shutdown runs once the transaction has committed.
            $inside = [hub_kpis::generation(), hub_kpis::report_generation()];
            hub_kpis::replace_after_transaction();
            $this->assertFalse(hub_kpis::has_deferred_replacement(), $name);
            $this->assertNotSame($inside[0], hub_kpis::generation(), $name);
            $this->assertNotSame($inside[1], hub_kpis::report_generation(), $name);
        }
    }

    /**
     * Give a user a capability in one worker's user context.
     *
     * @param \stdClass $user The user.
     * @param string $capability The capability.
     * @param \stdClass $worker The worker.
     * @return void
     */
    private function grant_in(\stdClass $user, string $capability, \stdClass $worker): void {
        $this->setAdminUser();
        $usercontext = \context_user::instance($worker->id);
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability($capability, CAP_ALLOW, $roleid, $usercontext->id, true);
        role_assign($roleid, $user->id, $usercontext->id);
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($user);
    }

    /**
     * How many statements a call sends.
     *
     * @param callable $call The call.
     * @return int
     */
    private function reads(callable $call): int {
        global $DB;
        $before = $DB->perf_get_reads();
        $call();
        return $DB->perf_get_reads() - $before;
    }
}
