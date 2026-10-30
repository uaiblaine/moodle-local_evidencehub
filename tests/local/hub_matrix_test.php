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

use local_evidencehub\output\hub_matrix_page;
use local_evidencehub\tests\hub_scenario;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The hub's readiness matrix: its workers, the status filter, the sorts, paging, the columns, the
 * restricted cell, the worker count, and its agreement with the list.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(hub_matrix::class)]
#[CoversClass(hub_matrix_page::class)]
final class hub_matrix_test extends \advanced_testcase {
    use hub_scenario;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('enabled', 1, 'core_competency');
        $this->setAdminUser();
    }

    /**
     * Every worker of a filter, page after page, in order, for the current user.
     *
     * @param \context $context The page context.
     * @param hub_filter $filter The filter; its page is replaced.
     * @return array User ids.
     */
    private function all_workers(\context $context, hub_filter $filter): array {
        $matrix = new hub_matrix($context, hub_scope::for_user());
        $userids = [];
        for ($page = 0; $page < 100; $page++) {
            $rows = $matrix->workers($filter->with(['page' => $page]));
            if (!$rows) {
                break;
            }
            foreach ($rows as $userid) {
                $this->assertNotContains($userid, $userids, 'A worker came twice.');
                $userids[] = $userid;
            }
        }
        return $userids;
    }

    /**
     * Every cell of a filter's workers, as "userid:competencyid" => effective status.
     *
     * @param \context $context The page context.
     * @param hub_filter $filter The filter.
     * @return array
     */
    private function all_cells(\context $context, hub_filter $filter): array {
        $matrix = new hub_matrix($context, hub_scope::for_user());
        $cells = $matrix->cells($this->all_workers($context, $filter), $filter)['cells'];
        return array_map(fn(\stdClass $cell): int => $cell->estatus, $cells);
    }

    /**
     * One cell, as the current user reads it.
     *
     * @param \context $context The page context.
     * @param hub_filter $filter The filter.
     * @param int $userid The worker.
     * @param int $competencyid The competency.
     * @return \stdClass
     */
    private function cell(\context $context, hub_filter $filter, int $userid, int $competencyid): \stdClass {
        $cells = (new hub_matrix($context, hub_scope::for_user()))->cells([$userid], $filter)['cells'];
        return $cells[$userid . ':' . $competencyid];
    }

    /**
     * A category manager without the user-context capability has no row and no cell, even for a worker
     * named outright; given it over a cohort through tool_cohortroles, exactly the cohort's workers.
     *
     * @return void
     */
    public function test_rows_are_exactly_the_visible_workers(): void {
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
        $missing = $filter->with(['statuses' => [status::EFFECTIVE_MISSING]]);
        $both = [(int) $team->id, (int) $outsider->id];

        $this->setUser($manager);
        $matrix = new hub_matrix($catcontext, hub_scope::for_user());
        $this->assertSame([], $this->all_workers($catcontext, $filter));
        $this->assertSame(['workers' => [], 'cells' => []], $matrix->cells($both, $filter));
        $this->assertSame(0, $matrix->worker_count($missing, []));

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
        $matrix = new hub_matrix($catcontext, hub_scope::for_user());
        $this->assertSame([(int) $team->id], $this->all_workers($catcontext, $filter));
        $cells = $matrix->cells($both, $filter);
        $this->assertSame([$this->pair($team, $c1)], array_keys($cells['cells']));
        $this->assertSame([(int) $team->id], array_keys($cells['workers']));
        $this->assertSame(1, $matrix->worker_count($missing, []));

        // The control: the site administrator sees both.
        $this->setAdminUser();
        $matrix = new hub_matrix($catcontext, hub_scope::for_user());
        $this->assertEqualsCanonicalizing($both, $this->all_workers($catcontext, $filter));
        $this->assertCount(2, $matrix->cells($both, $filter)['cells']);
        $this->assertSame(2, $matrix->worker_count($missing, []));
    }

    /**
     * The status chips keep a worker with one pair in the chosen statuses, whose row still shows every
     * competency, and drop a worker with none.
     *
     * @return void
     */
    public function test_the_status_filter_keeps_workers_with_one_matching_pair(): void {
        [$c1, $c2] = $this->competencies(2);
        $templateid = $this->template([$c1, $c2]);
        $late = $this->worker($templateid);
        $this->status_row((int) $late->id, $c1, status::STATUS_EXPIRED, 20250101);
        $this->status_row((int) $late->id, $c2, status::STATUS_VALID, 20300101);
        $ready = $this->worker($templateid);
        $this->status_row((int) $ready->id, $c1, status::STATUS_VALID, 20300101);
        $this->status_row((int) $ready->id, $c2, status::STATUS_VALID, 20300101);
        $system = \context_system::instance();
        $expired = $this->filter($templateid, ['statuses' => [status::STATUS_EXPIRED]]);

        $this->assertSame([(int) $late->id], $this->all_workers($system, $expired));
        $this->assertSame(
            [$this->pair($late, $c1) => status::STATUS_EXPIRED, $this->pair($late, $c2) => status::STATUS_VALID],
            $this->all_cells($system, $expired)
        );
        $this->assertSame(1, (new hub_matrix($system, hub_scope::for_user()))->worker_count($expired, []));

        // The control: a status both workers have keeps both.
        $valid = $expired->with(['statuses' => [status::STATUS_VALID]]);
        $this->assertEqualsCanonicalizing([(int) $late->id, (int) $ready->id], $this->all_workers($system, $valid));
        $this->assertSame(2, (new hub_matrix($system, hub_scope::for_user()))->worker_count($valid, []));
    }

    /**
     * By name (last, first, id) either way; by urgency, a missing pair first, then the lowest expiry
     * among the pairs not compliant, the compliant workers last, ties by name then id. The list's other
     * sorts read as urgency.
     *
     * @return void
     */
    public function test_each_sort_and_its_ties(): void {
        [$c1, $c2] = $this->competencies(2);
        $templateid = $this->template([$c1, $c2]);
        $workers = [];
        $shapes = [
            'a' => ['Alves', 'Ana', [status::STATUS_VALID, 20300101]],
            'b' => ['Braga', 'Bia', [status::STATUS_EXPIRED, 20250101]],
            'c' => ['Costa', 'Caio', null],
            'd' => ['Dias', 'Duda', [status::STATUS_DUE, 20261201]],
            't1' => ['Teles', 'Tom', [status::STATUS_VALID, 20300101]],
            't2' => ['Teles', 'Tom', [status::STATUS_VALID, 20300101]],
        ];
        foreach ($shapes as $key => [$lastname, $firstname, $first]) {
            $worker = $this->worker($templateid, ['lastname' => $lastname, 'firstname' => $firstname]);
            if ($first !== null) {
                $this->status_row((int) $worker->id, $c1, $first[0], $first[1]);
            }
            // A compliant pair with the earliest date of all: compliant pairs never count for urgency.
            $this->status_row((int) $worker->id, $c2, status::STATUS_VALID, 20240101);
            $workers[$key] = (int) $worker->id;
        }
        $system = \context_system::instance();
        $ids = fn(array $keys): array => array_map(fn(string $key): int => $workers[$key], $keys);
        $filter = $this->filter($templateid);

        $this->assertSame(
            $ids(['a', 'b', 'c', 'd', 't1', 't2']),
            $this->all_workers($system, $filter->with(['sort' => hub_filter::SORT_WORKER]))
        );
        $this->assertSame(
            $ids(['t1', 't2', 'd', 'c', 'b', 'a']),
            $this->all_workers($system, $filter->with(['sort' => hub_filter::SORT_WORKER, 'dir' => 'desc']))
        );
        $this->assertSame(
            $ids(['c', 'b', 'd', 'a', 't1', 't2']),
            $this->all_workers($system, $filter->with(['sort' => hub_filter::SORT_EXPIRY]))
        );
        $this->assertSame(
            $ids(['a', 't1', 't2', 'd', 'b', 'c']),
            $this->all_workers($system, $filter->with(['sort' => hub_filter::SORT_EXPIRY, 'dir' => 'desc']))
        );
        $this->assertSame(
            $ids(['c', 'b', 'd', 'a', 't1', 't2']),
            $this->all_workers($system, $filter->with(['sort' => hub_filter::SORT_STATUS]))
        );
    }

    /**
     * A worker whose only pair not compliant is restricted to the viewer ranks as urgent as one with a
     * missing pair, then by name: the stored expiry of the restricted pair never orders the rows. With
     * viewsensitive that stored expiry orders them.
     *
     * @return void
     */
    public function test_a_restricted_pair_is_as_urgent_as_a_missing_one(): void {
        [$c1, $c2] = $this->competencies(2);
        $templateid = $this->template([$c1, $c2]);
        // By name: the restricted worker first, so an order by its stored date would differ.
        $restricted = $this->worker($templateid, ['lastname' => 'Alves', 'firstname' => 'Ana']);
        $this->status_row((int) $restricted->id, $c1, status::STATUS_EXPIRED, 20240101, 'aso');
        $missing = $this->worker($templateid, ['lastname' => 'Mota', 'firstname' => 'Mia']);
        $expired = $this->worker($templateid, ['lastname' => 'Bento', 'firstname' => 'Bia']);
        $this->status_row((int) $expired->id, $c1, status::STATUS_EXPIRED, 20250101, 'nrcertificate');
        foreach ([$restricted, $missing, $expired] as $worker) {
            $this->status_row((int) $worker->id, $c2, status::STATUS_VALID, 20300101);
        }
        $viewer = $this->viewer(['local/evidencehub:view', 'moodle/competency:userevidenceview']);
        $system = \context_system::instance();
        $filter = $this->filter($templateid, ['sort' => hub_filter::SORT_EXPIRY]);
        $ids = fn(\stdClass ...$workers): array => array_map(fn(\stdClass $worker): int => (int) $worker->id, $workers);

        $this->setUser($viewer);
        $this->assertSame($ids($restricted, $missing, $expired), $this->all_workers($system, $filter));
        $this->assertSame(
            $ids($expired, $restricted, $missing),
            $this->all_workers($system, $filter->with(['dir' => 'desc']))
        );

        // The control: with viewsensitive in the worker's context the stored 2024 date orders the worker.
        $this->setAdminUser();
        $roleid = $this->getDataGenerator()->create_role();
        $usercontext = \context_user::instance($restricted->id);
        assign_capability('local/evidencehub:viewsensitive', CAP_ALLOW, $roleid, $usercontext->id, true);
        role_assign($roleid, $viewer->id, $usercontext->id);
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($viewer);
        $this->assertSame($ids($missing, $restricted, $expired), $this->all_workers($system, $filter));
    }

    /**
     * Pages of 25 add up to one page of 50, each worker once, with every worker tied on name and urgency:
     * by id, in either sort and direction.
     *
     * @return void
     */
    public function test_paging_is_stable(): void {
        global $DB;
        [$c1] = $this->competencies(1);
        $templateid = $this->template([$c1]);
        $created = [];
        for ($i = 0; $i < 27; $i++) {
            $created[] = (int) $this->worker($templateid, ['lastname' => 'Same', 'firstname' => 'Name'])->id;
        }
        // Rewrite the lowest ids' rows: PostgreSQL puts the new tuples at the end of the heap, so a scan no
        // longer meets the workers in id order and only the tie-break restores it. MariaDB clusters rows on
        // the primary key, so this changes nothing there: test_every_sort_ends_in_the_worker_id covers it.
        $later = time() + 60;
        foreach (array_slice($created, 0, 13) as $userid) {
            $DB->set_field('user', 'timemodified', $later, ['id' => $userid]);
            $DB->set_field('competency_plan', 'timemodified', $later, ['userid' => $userid]);
        }
        $system = \context_system::instance();
        $filter = $this->filter($templateid, ['perpage' => 25]);
        $matrix = new hub_matrix($system, hub_scope::for_user());

        $paged = $this->all_workers($system, $filter);
        $this->assertCount(25, $matrix->workers($filter));
        $this->assertSame($created, $paged);
        $this->assertSame($paged, $matrix->workers($filter->with(['perpage' => 50])));
        $byname = $filter->with(['sort' => hub_filter::SORT_WORKER, 'dir' => 'desc']);
        $this->assertSame($created, $this->all_workers($system, $byname));
    }

    /**
     * Every ORDER BY of step 1, by name and by urgency in either direction, ends in the worker's id, the
     * one unique key of a row: ties never fall to the order a database happens to read rows in.
     *
     * @return void
     */
    public function test_every_sort_ends_in_the_worker_id(): void {
        $filter = $this->filter($this->template($this->competencies(1)));
        foreach ([hub_filter::SORT_WORKER, hub_filter::SORT_EXPIRY] as $sort) {
            foreach (['asc', 'desc'] as $dir) {
                [$sql] = hub_matrix::workers_sql(
                    $filter->with(['sort' => $sort, 'dir' => $dir]),
                    hub_scope::for_user(),
                    \context_system::instance()
                );
                $this->assertMatchesRegularExpression('/\bORDER BY\b/', $sql);
                $orderby = trim(preg_replace('/^.*\bORDER BY\b/s', '', $sql));
                $keys = array_map('trim', explode(',', $orderby));
                $this->assertSame('w.userid', end($keys), "Sort $sort $dir: $orderby");
                // The control: the name, or the urgency, comes before the tie-break.
                $this->assertGreaterThan(1, count($keys), "Sort $sort $dir: $orderby");
            }
        }
    }

    /**
     * The columns follow the template's own order, not the competencies' ids or names, each with the kind
     * of its policy: the competency's own, else its framework's, else the default.
     *
     * @return void
     */
    public function test_columns_follow_the_template_order_with_their_kind(): void {
        global $DB;
        [$c1, $c2] = $this->competencies(2);
        [$c3] = $this->competencies(1);
        $templateid = $this->template([$c1, $c2, $c3]);
        foreach ([$c2 => 0, $c3 => 1, $c1 => 2] as $competencyid => $sortorder) {
            $DB->set_field('competency_templatecomp', 'sortorder', $sortorder, [
                'templateid' => $templateid,
                'competencyid' => $competencyid,
            ]);
        }
        $frameworkid = (int) $DB->get_field('competency', 'competencyframeworkid', ['id' => $c1]);
        $now = time();
        foreach ([0 => policy::KIND_REGULATORY, $c2 => policy::KIND_BEHAVIOURAL] as $competencyid => $kind) {
            $DB->insert_record(policy::TABLE, [
                'frameworkid' => $frameworkid,
                'competencyid' => $competencyid,
                'kind' => $kind,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
        }

        $columns = hub_matrix::columns($this->filter($templateid));
        $this->assertSame([$c2, $c3, $c1], array_column($columns, 'id'));
        $this->assertSame(
            [policy::KIND_BEHAVIOURAL, policy::KIND_TECHNICAL, policy::KIND_REGULATORY],
            array_column($columns, 'kind')
        );
        $this->assertSame(['C2', 'C1', 'C1'], array_column($columns, 'shortname'));
        $this->assertSame([], hub_matrix::columns($this->filter($this->template([]))));
        // Only a validated filter names the template: no caller passes a raw id.
        $type = (new \ReflectionMethod(hub_matrix::class, 'columns'))->getParameters()[0]->getType();
        $this->assertSame(hub_filter::class, $type->getName());
    }

    /**
     * A pair decided by a sensitive evidence shows the restricted code and no number to a viewer without
     * viewsensitive, and does not keep its worker under its stored status; with viewsensitive it shows both.
     *
     * @return void
     */
    public function test_a_restricted_cell_has_no_number(): void {
        [$c1] = $this->competencies(1);
        $templateid = $this->template([$c1]);
        $worker = $this->worker($templateid);
        $this->status_row((int) $worker->id, $c1, status::STATUS_EXPIRED, 20250101, 'aso');
        $viewer = $this->viewer(['local/evidencehub:view', 'moodle/competency:userevidenceview']);
        $system = \context_system::instance();
        $filter = $this->filter($templateid);
        $expired = $filter->with(['statuses' => [status::STATUS_EXPIRED]]);
        $restricted = $filter->with(['statuses' => [status::EFFECTIVE_RESTRICTED]]);
        $today = 20260601;

        $this->setUser($viewer);
        $cell = $this->cell($system, $filter, (int) $worker->id, $c1);
        $this->assertSame([status::EFFECTIVE_RESTRICTED, 0], [$cell->estatus, $cell->eexpiry]);
        $this->assertSame('', hub_matrix_page::cell_text($cell->estatus, $cell->eexpiry, $today));
        $this->assertSame([], $this->all_workers($system, $expired));
        $this->assertSame([(int) $worker->id], $this->all_workers($system, $restricted));

        // The control: with viewsensitive in the worker's context the cell reads its status and its days.
        $this->setAdminUser();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('local/evidencehub:viewsensitive', CAP_ALLOW, $roleid, \context_user::instance($worker->id)->id, true);
        role_assign($roleid, $viewer->id, \context_user::instance($worker->id)->id);
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($viewer);
        $cell = $this->cell($system, $filter, (int) $worker->id, $c1);
        $this->assertSame([status::STATUS_EXPIRED, 20250101], [$cell->estatus, $cell->eexpiry]);
        $this->assertSame(
            get_string('hubmatrixdayssince', 'local_evidencehub', 516),
            hub_matrix_page::cell_text($cell->estatus, $cell->eexpiry, $today)
        );
        $this->assertSame([(int) $worker->id], $this->all_workers($system, $expired));
        $this->assertSame([], $this->all_workers($system, $restricted));
    }

    /**
     * The short text of each cell: days left while due or critical, days since once expired, a dash when
     * missing, nothing when compliant, awaiting verification or without a date.
     *
     * @return void
     */
    public function test_the_cell_text(): void {
        $today = 20261030;
        $left = fn(int $days): string => get_string('hubmatrixdaysleft', 'local_evidencehub', $days);
        $this->assertSame($left(32), hub_matrix_page::cell_text(status::STATUS_DUE, 20261201, $today));
        $this->assertSame($left(2), hub_matrix_page::cell_text(status::STATUS_CRITICAL, 20261101, $today));
        $this->assertSame(
            get_string('hubmatrixdayssince', 'local_evidencehub', 29),
            hub_matrix_page::cell_text(status::STATUS_EXPIRED, 20261001, $today)
        );
        $this->assertSame(
            get_string('hubmatrixmissing', 'local_evidencehub'),
            hub_matrix_page::cell_text(status::EFFECTIVE_MISSING, 0, $today)
        );
        $this->assertSame('', hub_matrix_page::cell_text(status::STATUS_VALID, 20300101, $today));
        $this->assertSame('', hub_matrix_page::cell_text(status::STATUS_UNVERIFIED, 20300101, $today));
        $this->assertSame('', hub_matrix_page::cell_text(status::STATUS_DUE, 0, $today));
        $this->assertSame('', hub_matrix_page::cell_text(status::EFFECTIVE_RESTRICTED, 0, $today));
    }

    /**
     * The matrix and the list never disagree on a pair: with every status they hold the same pairs with
     * the same statuses; under a status filter every pair the list keeps is in the matrix, and every worker
     * the matrix keeps has one in the list.
     *
     * @return void
     */
    public function test_the_cells_equal_the_list(): void {
        [$c1, $c2, $c3] = $this->competencies(3);
        $templateid = $this->template([$c1, $c2, $c3]);
        $codes = [status::STATUS_VALID, status::STATUS_DUE, status::STATUS_EXPIRED, status::STATUS_UNVERIFIED];
        for ($i = 0; $i < 6; $i++) {
            $worker = $this->worker($templateid);
            foreach ([$c1, $c2, $c3] as $index => $competencyid) {
                if (($i + $index) % 5 === 4) {
                    continue;
                }
                $type = ($i + $index) % 3 === 0 ? 'aso' : 'nrcertificate';
                $this->status_row((int) $worker->id, $competencyid, $codes[($i + $index) % 4], 20260101 + $i, $type);
            }
        }
        $viewer = $this->viewer(['local/evidencehub:view', 'moodle/competency:userevidenceview']);
        $system = \context_system::instance();
        // Validated as the administrator: the viewer reads the workers' evidence, not the template.
        $all = $this->filter($templateid, ['perpage' => 25]);
        $this->setUser($viewer);

        $list = $this->all_pages($system, $all);
        $this->assertContains(status::EFFECTIVE_RESTRICTED, $list);
        $this->assertContains(status::EFFECTIVE_MISSING, $list);
        $matrix = $this->all_cells($system, $all);
        ksort($list);
        ksort($matrix);
        $this->assertSame($list, $matrix);

        $narrow = $all->with(['statuses' => [status::STATUS_EXPIRED, status::EFFECTIVE_MISSING]]);
        $list = $this->all_pages($system, $narrow);
        $matrix = $this->all_cells($system, $narrow);
        $this->assertNotEmpty($list);
        $kept = array_intersect_key($matrix, $list);
        ksort($list);
        ksort($kept);
        $this->assertSame($list, $kept);
        $workers = array_unique(array_map('intval', array_keys($list)));
        $this->assertEqualsCanonicalizing($workers, $this->all_workers($system, $narrow));
    }

    /**
     * Without a status chip the pager counts the KPIs' workers and sends no statement; with one it counts
     * the kept workers in a statement of its own, whatever the KPIs say.
     *
     * @return void
     */
    public function test_the_worker_count_comes_from_the_kpis_without_a_chip(): void {
        global $DB;
        [$c1, $c2] = $this->competencies(2);
        $templateid = $this->template([$c1, $c2]);
        for ($i = 0; $i < 3; $i++) {
            $worker = $this->worker($templateid);
            $this->status_row((int) $worker->id, $c1, $i ? status::STATUS_VALID : status::STATUS_EXPIRED, 20250101);
        }
        $system = \context_system::instance();
        $scope = hub_scope::for_user();
        $matrix = new hub_matrix($system, $scope);
        $all = $this->filter($templateid);
        $kpis = hub_kpis::compute($all, $scope, $system);
        $this->assertSame(3, $kpis['workers']);
        $this->assertSame(3, $matrix->worker_count($all, $kpis));
        $this->assertCount(3, $this->all_workers($system, $all));

        $reads = $DB->perf_get_reads();
        $this->assertSame(99, $matrix->worker_count($all, ['workers' => 99] + $kpis));
        $this->assertSame($reads, $DB->perf_get_reads());

        // The control: a chip narrows the rows, and the count reads them, not the KPIs.
        $expired = $all->with(['statuses' => [status::STATUS_EXPIRED]]);
        $this->assertSame(1, $matrix->worker_count($expired, ['workers' => 99] + $kpis));
        $this->assertGreaterThan($reads, $DB->perf_get_reads());
        $this->assertCount(1, $this->all_workers($system, $expired));
    }
}
