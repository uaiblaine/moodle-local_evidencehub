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

use core_reportbuilder\local\filters\select;
use core_reportbuilder\manager;
use core_reportbuilder\table\system_report_table;
use local_evidencehub\output\hub_matrix_page;
use local_evidencehub\reportbuilder\local\systemreports\hub_pairs;
use local_evidencehub\tests\hub_scenario;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The hub's readiness matrix: its workers are the pairs report's under the reader's stored filters, the scope and the
 * suspended choice; each shows every competency; the order, paging, the columns, the restricted cell and the count.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(hub_matrix::class)]
#[CoversClass(hub_matrix_page::class)]
#[CoversClass(reportengine::class)]
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
     * The pairs report of a template for the current user, built afresh with a scope, its stored filters set first.
     *
     * @param int $templateid The template.
     * @param string $scope The scope.
     * @param array $filters Stored filter values, as core's store holds them; empty clears them.
     * @param \context|null $context The hub context; the system when null.
     * @return hub_pairs
     */
    private function report(
        int $templateid,
        string $scope = reportengine::SCOPE_ALL,
        array $filters = [],
        ?\context $context = null
    ): hub_pairs {
        $context ??= \context_system::instance();
        manager::reset_caches();
        $report = hub_pairs::for_template($context, $templateid);
        $report->set_filter_values($filters);
        manager::reset_caches();
        return hub_pairs::for_template($context, $templateid, ['scope' => $scope]);
    }

    /**
     * The status filter's stored values for one effective code.
     *
     * @param int $code The code.
     * @return array
     */
    private static function status_is(int $code): array {
        return ['validity:status_operator' => select::EQUAL_TO, 'validity:status_value' => $code];
    }

    /**
     * The matrix of a report, as the matrix page builds it.
     *
     * @param hub_pairs $report The report.
     * @return hub_matrix
     */
    private function matrix(hub_pairs $report): hub_matrix {
        return hub_matrix::for_report($report);
    }

    /**
     * Every worker of a matrix, page after page of 25, in order.
     *
     * @param hub_matrix $matrix The matrix.
     * @return array User ids.
     */
    private function all_workers(hub_matrix $matrix): array {
        $userids = [];
        for ($page = 0; $page < 100; $page++) {
            $rows = $matrix->workers($page, 25);
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
     * Every cell of a matrix's workers, as "userid:competencyid" => effective status.
     *
     * @param hub_matrix $matrix The matrix.
     * @return array
     */
    private function all_cells(hub_matrix $matrix): array {
        return array_map(fn(\stdClass $cell): int => $cell->estatus, $matrix->cells($this->all_workers($matrix))['cells']);
    }

    /**
     * The workers of the rows core's own table of a report lists, the list's rows read whole.
     *
     * @param hub_pairs $report The report.
     * @return array User ids, ascending.
     */
    private function listed_workers(hub_pairs $report): array {
        global $SESSION;
        unset($SESSION->flextable);
        $table = system_report_table::create((int) $report->get_report_persistent()->get('id'), $report->get_parameters());
        $table->define_baseurl(new \moodle_url('/local/evidencehub/index.php'));
        $table->setup();
        $table->query_db(1000, false);
        $userids = [];
        foreach ($table->rawdata as $record) {
            $userids[(int) $record->userid] = (int) $record->userid;
        }
        $table->close_recordset();
        sort($userids);
        return array_values($userids);
    }

    /**
     * A category manager without the user-context capability has no row and no cell, even for a worker named
     * outright; given it over a cohort through tool_cohortroles, exactly the cohort's workers.
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
        $both = [(int) $team->id, (int) $outsider->id];

        $this->setUser($manager);
        $matrix = $this->matrix($this->report($templateid, reportengine::SCOPE_ALL, [], $catcontext));
        $this->assertSame([], $this->all_workers($matrix));
        $this->assertSame(['workers' => [], 'cells' => []], $matrix->cells($both));
        $this->assertSame(0, $matrix->worker_count());

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
        $matrix = $this->matrix($this->report($templateid, reportengine::SCOPE_ALL, [], $catcontext));
        $this->assertSame([(int) $team->id], $this->all_workers($matrix));
        $cells = $matrix->cells($both);
        $this->assertSame([$this->pair($team, $c1)], array_keys($cells['cells']));
        $this->assertSame([(int) $team->id], array_keys($cells['workers']));
        $this->assertSame(1, $matrix->worker_count());

        // The control: the site administrator sees both.
        $this->setAdminUser();
        $matrix = $this->matrix($this->report($templateid, reportengine::SCOPE_ALL, [], $catcontext));
        $this->assertEqualsCanonicalizing($both, $this->all_workers($matrix));
        $this->assertCount(2, $matrix->cells($both)['cells']);
        $this->assertSame(2, $matrix->worker_count());
    }

    /**
     * A stored status filter keeps a worker with one pair in that status, whose row still shows every competency,
     * and drops a worker with none: the workers the list's rows name under the same filter.
     *
     * Changes that must make it fail: the matching set read without the reader's stored filters; the cells cut by them.
     *
     * @return void
     */
    public function test_the_stored_status_filter_keeps_workers_with_one_matching_pair(): void {
        [$c1, $c2] = $this->competencies(2);
        $templateid = $this->template([$c1, $c2]);
        $late = $this->worker($templateid);
        $this->status_row((int) $late->id, $c1, status::STATUS_EXPIRED, 20250101);
        $this->status_row((int) $late->id, $c2, status::STATUS_VALID, 20300101);
        $ready = $this->worker($templateid);
        $this->status_row((int) $ready->id, $c1, status::STATUS_VALID, 20300101);
        $this->status_row((int) $ready->id, $c2, status::STATUS_VALID, 20300101);

        $report = $this->report($templateid, reportengine::SCOPE_ALL, self::status_is(status::STATUS_EXPIRED));
        $matrix = $this->matrix($report);
        $this->assertSame([(int) $late->id], $this->all_workers($matrix));
        $this->assertSame($this->listed_workers($report), $this->all_workers($matrix));
        $this->assertSame(
            [$this->pair($late, $c1) => status::STATUS_EXPIRED, $this->pair($late, $c2) => status::STATUS_VALID],
            $this->all_cells($matrix)
        );
        $this->assertSame(1, $matrix->worker_count());

        // The control: a status both workers have keeps both.
        $report = $this->report($templateid, reportengine::SCOPE_ALL, self::status_is(status::STATUS_VALID));
        $matrix = $this->matrix($report);
        $this->assertEqualsCanonicalizing([(int) $late->id, (int) $ready->id], $this->all_workers($matrix));
        $this->assertSame($this->listed_workers($report), $this->sorted($this->all_workers($matrix)));
        $this->assertSame(2, $matrix->worker_count());
    }

    /**
     * The scope and a stored cohort filter narrow the matrix as they narrow the list: "Needs attention" keeps the
     * workers with a pair not compliant, "All" every worker; a cohort keeps its members.
     *
     * Changes that must make it fail: the scope or the cohort filter left out of the matching set.
     *
     * @return void
     */
    public function test_the_scope_and_a_stored_cohort_follow_the_list(): void {
        [$c1] = $this->competencies(1);
        $templateid = $this->template([$c1]);
        $late = $this->worker($templateid);
        $this->status_row((int) $late->id, $c1, status::STATUS_EXPIRED, 20250101);
        $ready = $this->worker($templateid);
        $this->status_row((int) $ready->id, $c1, status::STATUS_VALID, 20300101);
        $cohort = $this->getDataGenerator()->create_cohort();
        cohort_add_member($cohort->id, $ready->id);
        $incohort = ['user:cohort_operator' => select::EQUAL_TO, 'user:cohort_value' => $cohort->id];

        $attention = $this->report($templateid, reportengine::SCOPE_ATTENTION);
        $this->assertSame([(int) $late->id], $this->all_workers($this->matrix($attention)));
        $this->assertSame($this->listed_workers($attention), $this->all_workers($this->matrix($attention)));
        $all = $this->report($templateid, reportengine::SCOPE_ALL);
        $this->assertSame(
            $this->sorted([(int) $late->id, (int) $ready->id]),
            $this->sorted($this->all_workers($this->matrix($all)))
        );

        $report = $this->report($templateid, reportengine::SCOPE_ALL, $incohort);
        $this->assertSame([(int) $ready->id], $this->all_workers($this->matrix($report)));
        $this->assertSame($this->listed_workers($report), $this->all_workers($this->matrix($report)));
        // The control: the cohort's only member has no pair needing attention, so both cuts together list nobody.
        $both = $this->report($templateid, reportengine::SCOPE_ATTENTION, $incohort);
        $this->assertSame([], $this->all_workers($this->matrix($both)));
    }

    /**
     * A suspended worker is in the matrix only while the reader's preference lists suspended accounts, as in the list.
     *
     * Changes that must make it fail: the matching set or the cells read without the report's suspended choice.
     *
     * @return void
     */
    public function test_suspended_accounts_follow_the_preference(): void {
        [$c1] = $this->competencies(1);
        $templateid = $this->template([$c1]);
        $active = $this->worker($templateid);
        $suspended = $this->worker($templateid, ['suspended' => 1]);

        $matrix = $this->matrix($this->report($templateid));
        $this->assertSame([(int) $active->id], $this->all_workers($matrix));
        $this->assertSame([], $matrix->cells([(int) $suspended->id])['cells']);

        // The control: with suspended accounts listed, the suspended worker is a row with their cells.
        reportpreference::write_grid(null, [], true);
        $matrix = $this->matrix($this->report($templateid));
        $this->assertEqualsCanonicalizing([(int) $active->id, (int) $suspended->id], $this->all_workers($matrix));
        $this->assertSame([$this->pair($suspended, $c1)], array_keys($matrix->cells([(int) $suspended->id])['cells']));
    }

    /**
     * By urgency: a missing pair first, then the lowest "valid until" key among the pairs not compliant, a pair valid
     * with no end after every day, the compliant workers last; ties by name, then by id.
     *
     * @return void
     */
    public function test_the_order_and_its_ties(): void {
        [$c1, $c2] = $this->competencies(2);
        $templateid = $this->template([$c1, $c2]);
        $workers = [];
        $shapes = [
            'a' => ['Alves', 'Ana', [status::STATUS_VALID, 20300101]],
            'b' => ['Braga', 'Bia', [status::STATUS_EXPIRED, 20250101]],
            'c' => ['Costa', 'Caio', null],
            'd' => ['Dias', 'Duda', [status::STATUS_DUE, 20261201]],
            'e' => ['Elias', 'Eva', [status::STATUS_UNVERIFIED, null]],
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
        $ids = fn(array $keys): array => array_map(fn(string $key): int => $workers[$key], $keys);

        $this->assertSame(
            $ids(['c', 'b', 'd', 'a', 'e', 't1', 't2']),
            $this->all_workers($this->matrix($this->report($templateid)))
        );
    }

    /**
     * A worker whose only pair not compliant is restricted to the viewer ranks as urgent as one with a missing pair,
     * then by name: the stored expiry of the restricted pair never orders the rows. With viewsensitive it does.
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
        $viewer = $this->viewer([
            'local/evidencehub:view',
            'moodle/competency:templateview',
            'moodle/competency:userevidenceview',
        ]);
        $ids = fn(\stdClass ...$workers): array => array_map(fn(\stdClass $worker): int => (int) $worker->id, $workers);

        $this->setUser($viewer);
        $this->assertSame($ids($restricted, $missing, $expired), $this->all_workers($this->matrix($this->report($templateid))));

        // The control: with viewsensitive in the worker's context the stored 2024 date orders the worker.
        $this->setAdminUser();
        $roleid = $this->getDataGenerator()->create_role();
        $usercontext = \context_user::instance($restricted->id);
        assign_capability('local/evidencehub:viewsensitive', CAP_ALLOW, $roleid, $usercontext->id, true);
        role_assign($roleid, $viewer->id, $usercontext->id);
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($viewer);
        $this->assertSame($ids($missing, $restricted, $expired), $this->all_workers($this->matrix($this->report($templateid))));
    }

    /**
     * Pages of 25 add up to one page of 50, each worker once, with every worker tied on name and urgency: by id.
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
        // the primary key, so this changes nothing there: test_the_order_ends_in_the_worker_id covers it.
        $later = time() + 60;
        foreach (array_slice($created, 0, 13) as $userid) {
            $DB->set_field('user', 'timemodified', $later, ['id' => $userid]);
            $DB->set_field('competency_plan', 'timemodified', $later, ['userid' => $userid]);
        }
        $matrix = $this->matrix($this->report($templateid));

        $paged = $this->all_workers($matrix);
        $this->assertCount(25, $matrix->workers(0, 25));
        $this->assertSame($created, $paged);
        $this->assertSame($paged, $matrix->workers(0, 50));
    }

    /**
     * Step 1's ORDER BY ends in the worker's id, the one unique key of a row: ties never fall to the order a database
     * happens to read rows in.
     *
     * @return void
     */
    public function test_the_order_ends_in_the_worker_id(): void {
        [$sql] = $this->matrix($this->report($this->template($this->competencies(1))))->workers_sql();
        $this->assertMatchesRegularExpression('/\bORDER BY\b/', $sql);
        $orderby = trim(preg_replace('/^.*\bORDER BY\b/s', '', $sql));
        $keys = array_map('trim', explode(',', $orderby));
        $this->assertSame('w.userid', end($keys), $orderby);
        // The control: the urgency and the name come before the tie-break.
        $this->assertSame('w.urgency', $keys[0], $orderby);
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
        $viewer = $this->viewer([
            'local/evidencehub:view',
            'moodle/competency:templateview',
            'moodle/competency:userevidenceview',
        ]);
        $today = 20260601;
        $workers = fn(int $code): array => $this->all_workers(
            $this->matrix($this->report($templateid, reportengine::SCOPE_ALL, self::status_is($code)))
        );

        $this->setUser($viewer);
        $cell = $this->matrix($this->report($templateid))->cells([(int) $worker->id])['cells'][$this->pair($worker, $c1)];
        $this->assertSame([status::EFFECTIVE_RESTRICTED, 0], [$cell->estatus, $cell->eexpiry]);
        $this->assertSame('', hub_matrix_page::cell_text($cell->estatus, $cell->eexpiry, $today));
        $this->assertSame([], $workers(status::STATUS_EXPIRED));
        $this->assertSame([(int) $worker->id], $workers(status::EFFECTIVE_RESTRICTED));

        // The control: with viewsensitive in the worker's context the cell reads its status and its days.
        $this->setAdminUser();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('local/evidencehub:viewsensitive', CAP_ALLOW, $roleid, \context_user::instance($worker->id)->id, true);
        role_assign($roleid, $viewer->id, \context_user::instance($worker->id)->id);
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($viewer);
        $cell = $this->matrix($this->report($templateid))->cells([(int) $worker->id])['cells'][$this->pair($worker, $c1)];
        $this->assertSame([status::STATUS_EXPIRED, 20250101], [$cell->estatus, $cell->eexpiry]);
        $this->assertSame(
            get_string('hubmatrixdayssince', 'local_evidencehub', 516),
            hub_matrix_page::cell_text($cell->estatus, $cell->eexpiry, $today)
        );
        $this->assertSame([(int) $worker->id], $workers(status::STATUS_EXPIRED));
        $this->assertSame([], $workers(status::EFFECTIVE_RESTRICTED));
    }

    /**
     * The short text of each cell: days left while due or critical, days since once expired, a dash when
     * missing, nothing when compliant, awaiting verification or without a date (the "valid until" key of a pair
     * valid with no end included).
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
        // A pair valid with no end carries the high "valid until" key, never printed as days.
        $this->assertSame('', hub_matrix_page::cell_text(status::STATUS_VALID, hub_query::NO_EXPIRY, $today));
        $this->assertSame('', hub_matrix_page::cell_text(status::STATUS_DUE, hub_query::NO_EXPIRY, $today));
    }

    /**
     * The matrix and the list never disagree on a pair: the cells of every worker are the hub's pair set, statuses
     * included, and the workers are those the report's rows name.
     *
     * @return void
     */
    public function test_the_cells_equal_the_pair_set(): void {
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
        $viewer = $this->viewer([
            'local/evidencehub:view',
            'moodle/competency:templateview',
            'moodle/competency:userevidenceview',
        ]);
        $system = \context_system::instance();
        $this->setUser($viewer);

        $report = $this->report($templateid);
        $list = $this->base_pairs($system, $this->filter($templateid));
        $this->assertContains(status::EFFECTIVE_RESTRICTED, $list);
        $this->assertContains(status::EFFECTIVE_MISSING, $list);
        $matrix = $this->all_cells($this->matrix($report));
        ksort($list);
        ksort($matrix);
        $this->assertSame($list, $matrix);
        $this->assertSame($this->listed_workers($report), $this->sorted($this->all_workers($this->matrix($report))));
    }

    /**
     * The worker count is the matching set's, counted on each load in a statement of its own, and follows the
     * criteria as the rows do.
     *
     * @return void
     */
    public function test_the_worker_count_is_the_matching_sets(): void {
        global $DB;
        [$c1, $c2] = $this->competencies(2);
        $templateid = $this->template([$c1, $c2]);
        for ($i = 0; $i < 3; $i++) {
            $worker = $this->worker($templateid);
            $this->status_row((int) $worker->id, $c1, $i ? status::STATUS_VALID : status::STATUS_EXPIRED, 20250101);
        }
        $matrix = $this->matrix($this->report($templateid));
        $reads = $DB->perf_get_reads();
        $this->assertSame(3, $matrix->worker_count());
        $this->assertSame($reads + 1, $DB->perf_get_reads());
        $this->assertCount(3, $this->all_workers($matrix));

        // The control: a stored filter narrows the rows, and the count with them.
        $matrix = $this->matrix($this->report($templateid, reportengine::SCOPE_ALL, self::status_is(status::STATUS_EXPIRED)));
        $this->assertSame(1, $matrix->worker_count());
        $this->assertCount(1, $this->all_workers($matrix));
    }

    /**
     * A worker outside the reader's cut (no userevidenceview on them) stays out of the rows, the cells and the count
     * although a stored filter and the "all" scope would otherwise list them.
     *
     * Changes that must make it fail: the matching set or the cells read without the reader's cut.
     *
     * @return void
     */
    public function test_a_worker_outside_the_readers_cut_stays_out_under_a_stored_filter(): void {
        [$c1] = $this->competencies(1);
        $templateid = $this->template([$c1]);
        $inside = $this->worker($templateid);
        $outside = $this->worker($templateid);
        foreach ([$inside, $outside] as $worker) {
            $this->status_row((int) $worker->id, $c1, status::STATUS_VALID, 20990101);
        }
        $both = [(int) $inside->id, (int) $outside->id];
        $viewer = $this->viewer(['local/evidencehub:view', 'moodle/competency:templateview']);
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('moodle/competency:userevidenceview', CAP_ALLOW, $roleid, \context_user::instance($inside->id)->id, true);
        role_assign($roleid, $viewer->id, \context_user::instance($inside->id)->id);
        accesslib_clear_all_caches_for_unit_testing();
        $report = fn(): hub_pairs => $this->report($templateid, reportengine::SCOPE_ALL, self::status_is(status::STATUS_VALID));

        $this->setUser($viewer);
        $matrix = $this->matrix($report());
        $this->assertSame([(int) $inside->id], $this->all_workers($matrix));
        $this->assertSame([(int) $inside->id], array_keys($matrix->cells($both)['workers']));
        $this->assertSame(1, $matrix->worker_count());

        // The control: the administrator lists both under the same filter and scope.
        $this->setAdminUser();
        $matrix = $this->matrix($report());
        $this->assertEqualsCanonicalizing($both, $this->all_workers($matrix));
        $this->assertSame(2, $matrix->worker_count());
    }

    /**
     * A request that names core's dynamic table service leaves the matrix's matching set and the list's rows as they are
     * without it.
     *
     * Changes that must make it fail: the table built without its report under that parameter (the read then fails).
     *
     * @return void
     */
    public function test_the_dynamic_table_parameter_does_not_unload_the_report(): void {
        [$c1] = $this->competencies(1);
        $templateid = $this->template([$c1]);
        $worker = $this->worker($templateid);
        $this->status_row((int) $worker->id, $c1, status::STATUS_EXPIRED, 20250101);
        $without = $this->all_workers($this->matrix($this->report($templateid)));
        $this->assertSame([(int) $worker->id], $without);

        $_GET['info'] = 'core_table_get_dynamic_table_content';
        try {
            $this->assertSame($without, $this->all_workers($this->matrix($this->report($templateid))));
            $this->assertSame(1, $this->matrix($this->report($templateid))->worker_count());
        } finally {
            unset($_GET['info']);
        }
    }

    /**
     * Parameter arrays of one statement are joined only when they share no name, and the real statements' sets are
     * disjoint.
     *
     * Changes that must make it fail: a union that keeps the left value of a repeated name.
     *
     * @return void
     */
    public function test_statement_parameters_never_share_a_name(): void {
        $this->assertSame(['a' => 1, 'b' => 2], hub_matrix::union_params(['a' => 1], ['b' => 2]));
        $this->assertSame(['a' => 1], hub_matrix::union_params(['a' => 1], []));
        try {
            hub_matrix::union_params(['a' => 1, 'b' => 2], ['b' => 3]);
            $this->fail('A shared name was accepted.');
        } catch (\coding_exception $e) {
            $this->assertStringContainsString('b', $e->getMessage());
        }

        [$c1] = $this->competencies(1);
        $templateid = $this->template([$c1]);
        $worker = $this->worker($templateid);
        $this->status_row((int) $worker->id, $c1, status::STATUS_EXPIRED, 20250101);
        $matrix = $this->matrix($this->report($templateid));
        [, $params] = $matrix->workers_sql();
        [, $cellparams] = $matrix->cells_sql([(int) $worker->id]);
        // Joined without a throw, and nothing lost: every parameter of each set is in the statement's.
        $this->assertNotSame([], $params);
        $this->assertNotSame([], $cellparams);
        $report = $this->report($templateid);
        [$basesql, $baseparams] = hub_query::base_sql(
            hub_filter::from_values(
                ['templateid' => $templateid, 'suspended' => $report->includes_suspended()],
                $report->get_context()
            ),
            $report->scope(),
            $report->get_context()
        );
        $this->assertNotSame('', $basesql);
        [, $matching] = reportengine::workers_sql($report);
        $this->assertSame([], array_intersect_key($baseparams, $matching));
        $this->assertCount(count($baseparams) + count($matching), $params);
    }

    /**
     * Some user ids in ascending order.
     *
     * @param array $userids The ids.
     * @return array
     */
    private function sorted(array $userids): array {
        sort($userids);
        return $userids;
    }
}
