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

namespace local_evidencehub\reportbuilder\datasource;

use core_reportbuilder\local\filters\select;
use core_reportbuilder\manager;
use core_reportbuilder\tests\core_reportbuilder_testcase;
use local_evidencehub\local\status;
use local_evidencehub\reportbuilder\local\entities\evidence_details;
use local_evidencehub\reportbuilder\local\entities\validity as validity_entity;
use local_evidencehub\tests\hub_scenario;
use local_evidencehub\tests\scenario;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The validity datasource: who may use it, the per-worker cut, and the per-worker sensitive rule.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(validity::class)]
#[CoversClass(validity_entity::class)]
#[CoversClass(evidence_details::class)]
final class validity_test extends core_reportbuilder_testcase {
    use hub_scenario;
    use scenario;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('enabled', 1, 'core_competency');
        $this->setAdminUser();
    }

    /**
     * Available to holders of view at system level only; view in a category is not enough.
     *
     * @return void
     */
    public function test_availability(): void {
        $listed = fn(): array => array_merge([], ...array_values(manager::get_report_datasources()));
        $this->assertTrue(validity::is_available());
        $this->assertArrayHasKey(validity::class, $listed());
        $this->setUser($this->viewer(['local/evidencehub:view']));
        $this->assertTrue(validity::is_available());
        $category = $this->getDataGenerator()->create_category();
        $this->setUser($this->viewer(['local/evidencehub:view'], \context_coursecat::instance($category->id)));
        $this->assertFalse(validity::is_available());
        $this->assertArrayNotHasKey(validity::class, $listed());
    }

    /**
     * A report with every column of the plugin's entities, for the current user.
     *
     * @return array The rows, by competency name, cells in column order: competency, status, expiry, count,
     *     changed, type, issuer, issue date, expiry date, verification.
     */
    private function content(): array {
        $generator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
        $report = $generator->create_report(['name' => 'Validity', 'source' => validity::class, 'default' => 0]);
        $columns = [
            'competency:name',
            'validity:status',
            'validity:effectiveexpiry',
            'validity:evidencecount',
            'validity:timechanged',
            'evidence_details:type',
            'evidence_details:issuer',
            'evidence_details:issuedate',
            'evidence_details:expirydate',
            'evidence_details:verifystatus',
        ];
        foreach ($columns as $column) {
            $generator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => $column, 'sortenabled' => 0]);
        }
        $rows = array_map('array_values', $this->get_custom_report_content($report->get('id')));
        usort($rows, fn(array $a, array $b): int => strcmp($a[0], $b[0]));
        return $rows;
    }

    /**
     * A user with a role holding capabilities in one worker's user context.
     *
     * @param \stdClass $user The user.
     * @param array $capabilities Capability names.
     * @param \stdClass $worker The worker.
     * @return void
     */
    private function grant_over(\stdClass $user, array $capabilities, \stdClass $worker): void {
        $usercontext = \context_user::instance($worker->id);
        $roleid = $this->getDataGenerator()->create_role();
        foreach ($capabilities as $capability) {
            assign_capability($capability, CAP_ALLOW, $roleid, $usercontext->id, true);
        }
        role_assign($roleid, $user->id, $usercontext->id);
        accesslib_clear_all_caches_for_unit_testing();
    }

    /**
     * A pair decided by a sensitive evidence reads "Restricted" in every column, type and count included,
     * for a user without viewsensitive in that worker's user context; another pair reads its values.
     * The controls: with viewsensitive over the worker, and for the worker themself, everything shows.
     *
     * @return void
     */
    public function test_a_restricted_pair_reads_restricted(): void {
        [$c1, $c2] = $this->competencies(2);
        $templateid = $this->template([$c1, $c2]);
        $worker = $this->worker($templateid);
        $this->status_row((int) $worker->id, $c1, status::STATUS_EXPIRED, 20250101, 'aso');
        $this->status_row((int) $worker->id, $c2, status::STATUS_DUE, 20261201, 'nrcertificate');
        $restricted = get_string('validityrestricted', 'local_evidencehub');
        $caps = ['local/evidencehub:view', 'moodle/reportbuilder:view', 'moodle/competency:userevidenceview'];
        $viewer = $this->viewer($caps);
        $this->grant($worker, $caps);

        $this->setUser($viewer);
        $rows = $this->content();
        $this->assertCount(2, $rows);
        $this->assertSame(['C1'], array_slice($rows[0], 0, 1));
        $this->assertSame(array_fill(0, 9, $restricted), array_map('strval', array_slice($rows[0], 1)));
        $this->assertSame([status::label(status::STATUS_DUE), '2026-12-01', '1'], array_map('strval', array_slice($rows[1], 1, 3)));
        $this->assertSame(get_string('type_nrcertificate', 'local_evidencehub'), $rows[1][5]);
        $this->assertSame('Issuer nrcertificate', $rows[1][6]);

        // The control: viewsensitive over this worker shows the pair in full.
        $this->setAdminUser();
        $this->grant_over($viewer, ['local/evidencehub:viewsensitive'], $worker);
        $this->setUser($viewer);
        $this->assert_in_full($this->content()[0]);

        // The other control: the worker reads their own sensitive pair.
        $this->setUser($worker);
        $this->assert_in_full($this->content()[0]);
    }

    /**
     * The sensitive pair's row as a user who may see it reads it.
     *
     * @param array $row The C1 row of content().
     * @return void
     */
    private function assert_in_full(array $row): void {
        $this->assertSame([status::label(status::STATUS_EXPIRED), '2025-01-01', '1'], array_map('strval', array_slice($row, 1, 3)));
        $this->assertSame(get_string('type_aso', 'local_evidencehub'), $row[5]);
        $this->assertSame('Issuer aso', $row[6]);
        $this->assertSame(['2026-01-01', '2028-01-01'], [$row[7], $row[8]]);
    }

    /**
     * Rows exist only for workers whose user context grants the user userevidenceview: none for a
     * holder of view alone, exactly the cohort's once tool_cohortroles grants it over the cohort.
     *
     * @return void
     */
    public function test_the_rows_follow_the_user_context_cut(): void {
        [$c1] = $this->competencies(1);
        $templateid = $this->template([$c1]);
        $team = $this->worker($templateid);
        $outsider = $this->worker($templateid);
        $this->status_row((int) $team->id, $c1, status::STATUS_VALID, 20300101);
        $this->status_row((int) $outsider->id, $c1, status::STATUS_VALID, 20300101);
        $cohort = $this->getDataGenerator()->create_cohort();
        cohort_add_member($cohort->id, $team->id);
        $manager = $this->viewer(['local/evidencehub:view', 'moodle/reportbuilder:view']);
        $generator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
        $report = $generator->create_report(['name' => 'Validity', 'source' => validity::class, 'default' => 0]);
        $generator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'user:username']);

        $this->setUser($manager);
        $this->assertSame([], $this->get_custom_report_content($report->get('id')));

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
        manager::reset_caches();

        $this->setUser($manager);
        $this->assertSame([[$team->username]], array_map('array_values', $this->get_custom_report_content($report->get('id'))));
        $this->setAdminUser();
        $this->assertCount(2, $this->get_custom_report_content($report->get('id')));
    }

    /**
     * The status and type filters read the restricted values: neither picks the restricted pair out.
     *
     * @return void
     */
    public function test_the_filters_read_the_restricted_values(): void {
        global $DB;
        [$c1, $c2] = $this->competencies(2);
        $templateid = $this->template([$c1, $c2]);
        $worker = $this->worker($templateid);
        $this->status_row((int) $worker->id, $c1, status::STATUS_EXPIRED, 20250101, 'aso');
        $this->status_row((int) $worker->id, $c2, status::STATUS_EXPIRED, 20250101, 'nrcertificate');
        $generator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
        $report = $generator->create_report(['name' => 'Validity', 'source' => validity::class, 'default' => 0]);
        $generator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'competency:name']);
        $generator->create_filter(['reportid' => $report->get('id'), 'uniqueidentifier' => 'validity:status']);
        $generator->create_filter(['reportid' => $report->get('id'), 'uniqueidentifier' => 'evidence_details:type']);
        $expired = [
            'validity:status_operator' => select::EQUAL_TO,
            'validity:status_value' => status::STATUS_EXPIRED,
        ];
        $aso = [
            'evidence_details:type_operator' => select::EQUAL_TO,
            'evidence_details:type_value' => (int) $DB->get_field('local_evidencehub_type', 'id', ['shortname' => 'aso']),
        ];
        $content = fn(array $values): array => array_map(
            'array_values',
            $this->get_custom_report_content($report->get('id'), 30, $values)
        );

        $caps = ['local/evidencehub:view', 'moodle/reportbuilder:view', 'moodle/competency:userevidenceview'];
        $this->setUser($this->viewer($caps));
        $this->assertSame([['C2']], $content($expired));
        $this->assertSame([], $content($aso));

        // The control: the admin finds the sensitive pair by either filter.
        $this->setAdminUser();
        $this->assertCount(2, $content($expired));
        $this->assertSame([['C1']], $content($aso));
    }

    /**
     * A new report from the datasource lists the default columns.
     *
     * @return void
     */
    public function test_the_default_report(): void {
        [$c1] = $this->competencies(1);
        $worker = $this->worker($this->template([$c1]), ['firstname' => 'Ana', 'lastname' => 'Souza']);
        $this->status_row((int) $worker->id, $c1, status::STATUS_VALID, 20300101, 'nrcertificate');
        $generator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
        $report = $generator->create_report(['name' => 'Validity', 'source' => validity::class, 'default' => 1]);
        $content = array_map('array_values', $this->get_custom_report_content($report->get('id')));
        $this->assertSame([[
            'Ana Souza',
            'C1',
            status::label(status::STATUS_VALID),
            '2030-01-01',
            get_string('type_nrcertificate', 'local_evidencehub'),
        ]], $content);
    }
}
