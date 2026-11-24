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
 * The report's figures outside its rows: readiness under the restricted rule, the hidden workers, the cache keys and
 * their generations.
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
     * A worker is ready when every pair is compliant, due or critical; a restricted pair is not.
     *
     * @return void
     */
    public function test_readiness(): void {
        $filter = $this->filter($this->templateid);
        $this->setUser($this->viewer(['local/evidencehub:view', 'moodle/competency:userevidenceview']));
        $this->assertSame([3, 1], $this->readiness($filter));

        // The control: a viewer who may see the sensitive evidence finds that worker ready too.
        $this->setAdminUser();
        $this->assertSame([3, 2], $this->readiness($filter));
    }

    /**
     * The hidden count is the template's hidden workers, whatever cohort, suspended account or name the reader's
     * criteria would cut by: it takes the template alone.
     *
     * Changes that must make it fail: the count cut by suspended accounts or by cohort membership.
     *
     * @return void
     */
    public function test_the_hidden_count_ignores_the_filter_cuts(): void {
        global $DB;
        $DB->set_field('user', 'suspended', 1, ['id' => $this->workers[2]->id]);
        $cohort = $this->getDataGenerator()->create_cohort();
        cohort_add_member($cohort->id, $this->workers[0]->id);
        $this->setUser($this->viewer(['local/evidencehub:view']));
        $this->assertSame(3, hub_kpis::hidden_workers($this->templateid, hub_scope::for_user()));

        // The control: the visible counts do follow the cuts, for a viewer who sees everyone.
        $this->setAdminUser();
        $this->assertSame(0, hub_kpis::hidden_workers($this->templateid, hub_scope::for_user()));
        $this->assertSame([2, 1, 3], [
            $this->readiness($this->filter($this->templateid))[0],
            $this->readiness($this->filter($this->templateid, ['cohortid' => $cohort->id]))[0],
            $this->readiness($this->filter($this->templateid, ['suspended' => 1]))[0],
        ]);
    }

    /**
     * A competency added to the template changes the report's cache keys: core fires no event for it, so the key
     * reads the template's signature.
     *
     * Changes that must make it fail: the signature left out of the keys.
     *
     * @return void
     */
    public function test_a_template_competency_added_changes_the_keys(): void {
        $system = \context_system::instance();
        $scope = hub_scope::for_user();
        $rows = fn(): string => hub_kpis::report_rows_key($system, $this->templateid, $scope, [], false, 'all', 20260101, []);
        $summary = fn(): string => hub_kpis::report_summary_key($system, $this->templateid, $scope, [], false, 20260101);
        [$before, $beforesummary] = [$rows(), $summary()];
        // The control: nothing changed, the same keys.
        $this->assertSame($before, $rows());
        $this->assertSame($beforesummary, $summary());

        [$added] = $this->competencies(1);
        $this->getDataGenerator()->get_plugin_generator('core_competency')->create_template_competency([
            'templateid' => $this->templateid,
            'competencyid' => $added,
        ]);
        $this->assertNotSame($before, $rows());
        $this->assertNotSame($beforesummary, $summary());
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
     * Workers and ready workers of the base set under a filter, as the report's summary counts them.
     *
     * @param hub_filter $filter The filter.
     * @return array [workers, ready].
     */
    private function readiness(hub_filter $filter): array {
        [$sql, $params] = hub_query::base_sql($filter, hub_scope::for_user(), \context_system::instance());
        return hub_kpis::readiness_of($sql, $params);
    }
}
