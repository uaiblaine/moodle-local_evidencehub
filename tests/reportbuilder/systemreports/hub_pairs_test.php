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

namespace local_evidencehub\reportbuilder\systemreports;

use core_competency\plan;
use core_reportbuilder\exception\report_access_exception;
use core_reportbuilder\local\filters\select;
use core_reportbuilder\local\filters\text;
use core_reportbuilder\manager;
use core_reportbuilder\table\system_report_table;
use core_reportbuilder\tests\core_reportbuilder_testcase;
use local_evidencehub\local\hub_access;
use local_evidencehub\local\hub_filter;
use local_evidencehub\local\hub_query;
use local_evidencehub\local\hub_scope;
use local_evidencehub\local\meta;
use local_evidencehub\local\pairset;
use local_evidencehub\local\status;
use local_evidencehub\reportbuilder\datasource\validity as validity_datasource;
use local_evidencehub\reportbuilder\local\entities\evidence_details;
use local_evidencehub\reportbuilder\local\entities\validity;
use local_evidencehub\reportbuilder\local\filters\cohort;
use local_evidencehub\reportbuilder\local\filters\reader_select;
use local_evidencehub\reportbuilder\local\systemreports\hub_pairs;
use local_evidencehub\tests\hub_scenario;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The hub's pairs as core's system report: the same rows, values and order as the hub's list, its access,
 * its restriction, its filters and its cuts.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(hub_pairs::class)]
#[CoversClass(validity::class)]
#[CoversClass(evidence_details::class)]
#[CoversClass(cohort::class)]
#[CoversClass(reader_select::class)]
#[CoversClass(pairset::class)]
#[CoversClass(hub_access::class)]
final class hub_pairs_test extends core_reportbuilder_testcase {
    use hub_scenario;

    /** @var array The cells the hub's list and the report both show, in column order. */
    private const array COMPARED = [
        'validity:status',
        'validity:effectiveexpiry',
        'validity:evidencecount',
        'evidence_details:type',
        'evidence_details:issuer',
    ];

    /** @var array The capabilities of a reader of every worker without viewsensitive. */
    private const array READER = [
        'local/evidencehub:view',
        'moodle/competency:templateview',
        'moodle/competency:userevidenceview',
    ];

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('enabled', 1, 'core_competency');
        $this->setAdminUser();
    }

    /**
     * The report of a template for the current user, built afresh: core keeps one instance per report and
     * user for the request, so a reader after another would read the first one's.
     *
     * @param int $templateid The template.
     * @param \context|null $context The hub's context; the system when null.
     * @return hub_pairs
     */
    private function report(int $templateid, ?\context $context = null): hub_pairs {
        manager::reset_caches();
        return hub_pairs::for_template($context ?? \context_system::instance(), $templateid);
    }

    /**
     * The report's records as the database returns them, before any column's callback, by pair.
     *
     * @param hub_pairs $report The report.
     * @return array "userid:competencyid" => the record's fields by SQL alias, as strings.
     */
    private function raw_records(hub_pairs $report): array {
        global $SESSION;
        unset($SESSION->flextable);
        $table = system_report_table::create((int) $report->get_report_persistent()->get('id'), []);
        $table->define_baseurl(new \moodle_url('/local/evidencehub/index.php'));
        $table->setup();
        $table->query_db(1000, false);
        $records = [];
        foreach ($table->rawdata as $record) {
            $records[$record->userid . ':' . $record->competencyid] = array_map('strval', (array) $record);
        }
        $table->close_recordset();
        return $records;
    }

    /**
     * The values of the fields a column's alias ends with, in one raw record.
     *
     * @param array $record A record from {@see self::raw_records()}.
     * @param string $alias The field's own alias in its column, such as effectiveexpiry.
     * @return array The values of every field of the record named c<index>_<alias>; never empty.
     */
    private function raw_fields(array $record, string $alias): array {
        $values = array_values(array_filter(
            $record,
            static fn(string $name): bool => (bool) preg_match('/^c\d+_' . $alias . '$/', $name),
            ARRAY_FILTER_USE_KEY
        ));
        $this->assertNotSame([], $values, "The record has a field $alias.");
        return $values;
    }

    /**
     * The report's rows as core's table reads them, by pair, in the table's order.
     *
     * @param hub_pairs $report The report.
     * @param string|null $sortby A column's unique identifier to sort by; the report's own sort when null.
     * @param int $sortorder SORT_ASC or SORT_DESC.
     * @return array "userid:competencyid" => formatted cells by column unique identifier.
     */
    private function rows(hub_pairs $report, ?string $sortby = null, int $sortorder = SORT_ASC): array {
        global $SESSION;
        // The table keeps its last sort in the session.
        unset($SESSION->flextable);
        $table = system_report_table::create((int) $report->get_report_persistent()->get('id'), []);
        $table->define_baseurl(new \moodle_url('/local/evidencehub/index.php'));
        $aliases = [];
        foreach ($report->get_active_columns_by_alias() as $alias => $column) {
            $aliases[$column->get_unique_identifier()] = $alias;
        }
        if ($sortby !== null) {
            $table->set_sortdata([['sortby' => $aliases[$sortby], 'sortorder' => $sortorder]]);
        }
        $table->setup();
        // The raw data is a recordset, which out() would consume.
        $table->query_db(1000, false);
        $rows = [];
        foreach ($table->rawdata as $record) {
            $formatted = $table->format_row($record);
            $cells = [];
            foreach ($aliases as $identifier => $alias) {
                $cells[$identifier] = (string) $formatted[$alias];
            }
            $rows[$record->userid . ':' . $record->competencyid] = $cells;
        }
        $table->close_recordset();
        return $rows;
    }

    /**
     * The compared cells of the report's rows.
     *
     * @param array $rows As rows() returns them.
     * @return array "userid:competencyid" => cells, in COMPARED order.
     */
    private function compared(array $rows): array {
        return array_map(fn(array $cells): array => array_intersect_key($cells, array_flip(self::COMPARED)), $rows);
    }

    /**
     * The hub's pair set of a template for the current user ({@see hub_query::base_sql()}, which the matrix, the
     * dossier selection, the digest and the readiness service read): every pair with its status as the report labels
     * it and its day, in the order of the "valid until" key, then the pair.
     *
     * @param int $templateid The template.
     * @return array "userid:competencyid" => [status label, valid until as the report prints it].
     */
    private function hub_rows(int $templateid): array {
        global $DB;
        [$sql, $params] = hub_query::base_sql(
            hub_filter::from_values(['templateid' => $templateid], \context_system::instance()),
            hub_scope::for_user(),
            \context_system::instance()
        );
        $rows = [];
        $rs = $DB->get_recordset_sql("SELECT b.userid, b.competencyid, b.estatus, b.eexpiry
                                        FROM ($sql) b
                                    ORDER BY b.eexpiry, b.userid, b.competencyid", $params);
        foreach ($rs as $record) {
            $day = hub_query::expiry_day((int) $record->eexpiry);
            $rows[$record->userid . ':' . $record->competencyid] = [
                status::effective_label((int) $record->estatus),
                $day === null ? '' : meta::iso_date($day),
            ];
        }
        $rs->close();
        return $rows;
    }

    /**
     * Set one select filter of the report for the current user, every other filter cleared.
     *
     * @param hub_pairs $report The report.
     * @param string $filter The filter's unique identifier.
     * @param int|string $value The value.
     * @param int $operator The select operator.
     * @return void
     */
    private function select(hub_pairs $report, string $filter, int|string $value, int $operator = select::EQUAL_TO): void {
        $report->set_filter_values([$filter . '_operator' => $operator, $filter . '_value' => $value]);
    }

    /**
     * For a manager, a reader of one cohort's workers through tool_cohortroles and a reader without
     * viewsensitive, the report lists the hub's pair set with its statuses and days in its order.
     *
     * @return void
     */
    public function test_the_report_lists_what_the_hub_lists(): void {
        [$c1, $c2] = $this->competencies(2);
        $templateid = $this->template([$c1, $c2]);
        $ana = $this->worker($templateid, ['firstname' => 'Ana', 'lastname' => 'Souza']);
        $bia = $this->worker($templateid, ['firstname' => 'Bia', 'lastname' => 'Lima']);
        $cid = $this->worker($templateid, ['firstname' => 'Cid', 'lastname' => 'Rocha']);
        $this->worker($templateid, ['suspended' => 1]);
        $this->status_row((int) $ana->id, $c1, status::STATUS_EXPIRED, 20250101, 'nrcertificate');
        $this->status_row((int) $bia->id, $c1, status::STATUS_VALID, 20300101, 'aso');
        $this->status_row((int) $bia->id, $c2, status::STATUS_DUE, 20261201);
        $cohort = $this->getDataGenerator()->create_cohort();
        cohort_add_member($cohort->id, $ana->id);
        cohort_add_member($cohort->id, $bia->id);

        $mentor = $this->viewer(['local/evidencehub:view', 'moodle/competency:templateview']);
        $mentorrole = $this->getDataGenerator()->create_role();
        assign_capability('moodle/competency:userevidenceview', CAP_ALLOW, $mentorrole, \context_system::instance()->id, true);
        set_role_contextlevels($mentorrole, [CONTEXT_USER]);
        \tool_cohortroles\api::create_cohort_role_assignment((object) [
            'userid' => $mentor->id,
            'roleid' => $mentorrole,
            'cohortid' => $cohort->id,
        ]);
        \tool_cohortroles\api::sync_all_cohort_roles();
        accesslib_clear_all_caches_for_unit_testing();
        $reader = $this->viewer(self::READER);

        $restricted = status::effective_label(status::EFFECTIVE_RESTRICTED);
        $missing = status::effective_label(status::EFFECTIVE_MISSING);
        $readers = ['manager' => null, 'mentor' => $mentor, 'reader' => $reader];
        foreach ($readers as $name => $user) {
            $user === null ? $this->setAdminUser() : $this->setUser($user);
            $hub = $this->hub_rows($templateid);
            $report = array_map(
                fn(array $cells): array => [$cells['validity:status'], $cells['validity:effectiveexpiry']],
                $this->rows($this->report($templateid))
            );
            $this->assertSame($hub, $report, $name);
            // The control: the fixture holds missing pairs, and a restricted one for a reader without viewsensitive.
            $statuses = array_column($hub, 0);
            $this->assertContains($missing, $statuses, $name);
            $this->assertSame($name !== 'manager', in_array($restricted, $statuses, true), $name);
        }
        // The cuts the comparison went through: the mentor reads the cohort, the others every active worker.
        $this->setUser($mentor);
        $this->assertEqualsCanonicalizing(
            [$this->pair($ana, $c1), $this->pair($ana, $c2), $this->pair($bia, $c1), $this->pair($bia, $c2)],
            array_keys($this->rows($this->report($templateid)))
        );
        $this->setUser($reader);
        $this->assertCount(6, $this->rows($this->report($templateid)));
        $this->assertArrayHasKey($this->pair($cid, $c2), $this->rows($this->report($templateid)));
    }

    /**
     * The columns: the worker linked to their evidence, the identity fields the reader may see, the competency,
     * then the validity; each cell carries its heading.
     *
     * @return void
     */
    public function test_the_columns(): void {
        [$c1] = $this->competencies(1);
        $templateid = $this->template([$c1]);
        $ana = $this->worker($templateid, ['firstname' => 'Ana', 'lastname' => 'Souza']);
        $validity = ['validity:status', 'validity:effectiveexpiry', 'validity:evidencecount'];
        $details = ['evidence_details:type', 'evidence_details:issuer'];

        $report = $this->report($templateid);
        $this->assertSame(
            array_merge(['user:worker', 'user:email', 'competency:name'], $validity, $details),
            array_keys($report->get_active_columns())
        );
        $this->assertSame(
            get_string('status', 'local_evidencehub'),
            $report->get_column('validity:status')->get_attributes()['data-cardtitle']
        );
        $row = $this->rows($report)[$this->pair($ana, $c1)];
        $this->assertStringContainsString('/local/evidencehub/evidence.php?userid=' . $ana->id, $row['user:worker']);
        $this->assertStringContainsString('Ana Souza', $row['user:worker']);
        $this->assertSame('C1', $row['competency:name']);

        // A reader without moodle/site:viewuseridentity gets no identity column at all.
        $this->setUser($this->viewer(self::READER));
        $this->assertSame(
            array_merge(['user:worker', 'competency:name'], $validity, $details),
            array_keys($this->report($templateid)->get_active_columns())
        );
    }

    /**
     * The report opens where the hub opens, for whom it opens, on a template the hub offers there.
     *
     * @return void
     */
    public function test_who_may_view(): void {
        $category = $this->getDataGenerator()->create_category();
        $catcontext = \context_coursecat::instance($category->id);
        [$c1] = $this->competencies(1);
        $own = $this->template([$c1], $catcontext);
        $site = $this->template([$c1]);
        $course = \context_course::instance($this->getDataGenerator()->create_course(['category' => $category->id])->id);
        $reader = $this->viewer(['local/evidencehub:view', 'moodle/competency:templateview'], $catcontext);
        $blind = $this->viewer(['moodle/competency:templateview'], $catcontext);

        // The control: a holder of view, on a template of the category.
        $this->setUser($reader);
        $this->assertTrue(hub_pairs::may_view($catcontext, $own));
        $this->assertInstanceOf(hub_pairs::class, $this->report($own, $catcontext));

        $this->assertFalse(hub_pairs::may_view($course, $own));
        $this->assertFalse(hub_pairs::may_view($catcontext, $site));
        set_config('enabled', 0, 'core_competency');
        $this->assertFalse(hub_pairs::may_view($catcontext, $own));
        set_config('enabled', 1, 'core_competency');
        $this->setUser($blind);
        $this->assertFalse(hub_pairs::may_view($catcontext, $own));

        $refused = [[$reader, $course, $own], [$reader, $catcontext, $site], [$blind, $catcontext, $own]];
        foreach ($refused as $index => [$user, $context, $templateid]) {
            $this->setUser($user);
            try {
                $this->report($templateid, $context);
                $this->fail('Report ' . $index . ' was built.');
            } catch (report_access_exception $e) {
                $this->assertSame('errorreportview', $e->errorcode);
            }
        }
    }

    /**
     * What a restricted pair's record carries, before any callback: the real day, status, count and issuer never leave
     * the database, so a column's callback is not what hides them. The controls: a manager's record of the same pair
     * holds the real values, and so does the same reader's record of a pair that is not restricted.
     *
     * Changes that must make it fail: the "valid until" column's value, or the count's, read unmasked.
     *
     * @return void
     */
    public function test_a_restricted_pairs_raw_fields_hold_the_masked_values(): void {
        [$c1, $c2] = $this->competencies(2);
        $templateid = $this->template([$c1, $c2]);
        $worker = $this->worker($templateid);
        $this->status_row((int) $worker->id, $c1, status::STATUS_VALID, 20300101, 'aso');
        $this->status_row((int) $worker->id, $c2, status::STATUS_EXPIRED, 20250101, 'nrcertificate');
        [$sensitive, $plain] = [$this->pair($worker, $c1), $this->pair($worker, $c2)];
        // Each alias, with the value a manager's record holds for the sensitive pair and the one a reader's does.
        $fields = [
            'effectiveexpiry' => ['20300101', '0'],
            'expirysort' => ['20300101', '0'],
            'status' => [(string) status::STATUS_VALID, (string) status::EFFECTIVE_RESTRICTED],
            'evidencecount' => ['1', '0'],
            'issuer' => ['Issuer aso', ''],
        ];

        $this->setAdminUser();
        $manager = $this->raw_records($this->report($templateid));
        $this->setUser($this->viewer(self::READER));
        $reader = $this->raw_records($this->report($templateid));
        $this->assertSame([$sensitive, $plain], array_keys($reader));
        foreach ($fields as $alias => [$real, $masked]) {
            $this->assertSame([$real], array_unique($this->raw_fields($manager[$sensitive], $alias)), "Manager, $alias");
            $this->assertSame([$masked], array_unique($this->raw_fields($reader[$sensitive], $alias)), "Reader, $alias");
        }
        $this->assertSame(['20250101'], array_unique($this->raw_fields($reader[$plain], 'effectiveexpiry')));
        $this->assertSame(['Issuer nrcertificate'], array_unique($this->raw_fields($reader[$plain], 'issuer')));
    }

    /**
     * A pair restricted to the reader is restricted in its cells, the status and type filters and the sort by
     * valid until; the controls: a manager reads it whole, and so does the worker themself.
     *
     * @return void
     */
    public function test_a_restricted_pair_stays_restricted(): void {
        global $DB;
        [$c1, $c2] = $this->competencies(2);
        $templateid = $this->template([$c1, $c2]);
        $worker = $this->worker($templateid);
        $this->status_row((int) $worker->id, $c1, status::STATUS_VALID, 20300101, 'aso');
        $this->status_row((int) $worker->id, $c2, status::STATUS_EXPIRED, 20250101, 'nrcertificate');
        $sensitive = $this->pair($worker, $c1);
        $plain = $this->pair($worker, $c2);
        $aso = (int) $DB->get_field('local_evidencehub_type', 'id', ['shortname' => 'aso']);
        $reader = $this->viewer(self::READER);

        $this->setUser($reader);
        $report = $this->report($templateid);
        $rows = $this->rows($report);
        // Restricted sorts first, as if it had no day; its own day (2030) would put it last.
        $this->assertSame([$sensitive, $plain], array_keys($rows));
        $this->assertSame([$plain, $sensitive], array_keys($this->rows($report, 'validity:effectiveexpiry', SORT_DESC)));
        $this->assertSame(
            [status::effective_label(status::EFFECTIVE_RESTRICTED), '', '', '', ''],
            array_values($this->compared($rows)[$sensitive])
        );
        $this->select($report, 'validity:status', status::STATUS_VALID);
        $this->assertSame([], $this->rows($report));
        $this->select($report, 'validity:status', status::EFFECTIVE_RESTRICTED);
        $this->assertSame([$sensitive], array_keys($this->rows($report)));
        $this->select($report, 'evidence_details:type', $aso);
        $this->assertSame([], $this->rows($report));

        // The control: a manager reads the pair whole, by every filter and in its real place.
        $this->setAdminUser();
        $report = $this->report($templateid);
        $rows = $this->rows($report);
        $this->assertSame([$plain, $sensitive], array_keys($rows));
        $this->assertSame('2030-01-01', $rows[$sensitive]['validity:effectiveexpiry']);
        $this->assertSame('1', $rows[$sensitive]['validity:evidencecount']);
        $this->select($report, 'validity:status', status::STATUS_VALID);
        $this->assertSame([$sensitive], array_keys($this->rows($report)));
        $this->select($report, 'evidence_details:type', $aso);
        $this->assertSame([$sensitive], array_keys($this->rows($report)));
    }

    /**
     * The worker reads their own sensitive pair whole; another reader without viewsensitive does not.
     *
     * @return void
     */
    public function test_the_worker_is_never_restricted_from_their_own_pair(): void {
        [$c1] = $this->competencies(1);
        $templateid = $this->template([$c1]);
        $worker = $this->worker($templateid);
        $this->status_row((int) $worker->id, $c1, status::STATUS_VALID, 20300101, 'aso');
        $roleid = $this->getDataGenerator()->create_role();
        foreach (self::READER as $capability) {
            assign_capability($capability, CAP_ALLOW, $roleid, \context_system::instance()->id, true);
        }
        role_assign($roleid, $worker->id, \context_system::instance()->id);
        accesslib_clear_all_caches_for_unit_testing();
        $pair = $this->pair($worker, $c1);

        $this->setUser($worker);
        $this->assertSame(
            [status::label(status::STATUS_VALID), '2030-01-01'],
            array_slice(array_values($this->compared($this->rows($this->report($templateid)))[$pair]), 0, 2)
        );

        // The control: the same pair is restricted to another reader of the same rights.
        $this->setUser($this->viewer(self::READER));
        $this->assertSame(
            status::effective_label(status::EFFECTIVE_RESTRICTED),
            $this->rows($this->report($templateid))[$pair]['validity:status']
        );
    }

    /**
     * Each filter narrows the rows: the status (missing included), the cohort, the type and the worker's name;
     * the cohort filter offers the hub's cohorts only, and a cohort outside them filters nothing.
     *
     * @return void
     */
    public function test_each_filter_narrows(): void {
        global $DB;
        [$c1, $c2] = $this->competencies(2);
        $templateid = $this->template([$c1, $c2]);
        $ana = $this->worker($templateid, ['firstname' => 'Ana', 'lastname' => 'Souza']);
        $bia = $this->worker($templateid, ['firstname' => 'Bia', 'lastname' => 'Lima']);
        $this->status_row((int) $ana->id, $c1, status::STATUS_EXPIRED, 20250101, 'nrcertificate');
        $this->status_row((int) $bia->id, $c1, status::STATUS_VALID, 20300101, 'diploma');
        $this->status_row((int) $bia->id, $c2, status::STATUS_DUE, 20261201, 'diploma');
        $team = $this->getDataGenerator()->create_cohort();
        cohort_add_member($team->id, $bia->id);
        $category = $this->getDataGenerator()->create_category();
        $elsewhere = $this->getDataGenerator()->create_cohort(['contextid' => \context_coursecat::instance($category->id)->id]);
        cohort_add_member($elsewhere->id, $ana->id);
        $nrcertificate = (int) $DB->get_field('local_evidencehub_type', 'id', ['shortname' => 'nrcertificate']);
        $all = [$this->pair($ana, $c1), $this->pair($ana, $c2), $this->pair($bia, $c1), $this->pair($bia, $c2)];
        $report = $this->report($templateid);
        $keys = fn(): array => array_keys($this->rows($report));

        // The control: no filter, every pair.
        $this->assertEqualsCanonicalizing($all, $keys());
        $this->select($report, 'validity:status', status::EFFECTIVE_MISSING);
        $this->assertSame([$this->pair($ana, $c2)], $keys());
        $this->select($report, 'validity:status', status::STATUS_EXPIRED);
        $this->assertSame([$this->pair($ana, $c1)], $keys());
        $this->select($report, 'evidence_details:type', $nrcertificate);
        $this->assertSame([$this->pair($ana, $c1)], $keys());

        $this->assertSame(
            array_keys(hub_access::cohorts(\context_system::instance())),
            array_keys($report->get_filter('user:cohort')->get_options())
        );
        $this->assertSame([(int) $team->id], array_keys($report->get_filter('user:cohort')->get_options()));
        $this->select($report, 'user:cohort', (int) $team->id);
        $this->assertEqualsCanonicalizing([$this->pair($bia, $c1), $this->pair($bia, $c2)], $keys());
        $this->select($report, 'user:cohort', (int) $team->id, select::NOT_EQUAL_TO);
        $this->assertEqualsCanonicalizing([$this->pair($ana, $c1), $this->pair($ana, $c2)], $keys());
        $this->select($report, 'user:cohort', (int) $elsewhere->id);
        $this->assertEqualsCanonicalizing($all, $keys());

        $report->set_filter_values(['user:fullname_operator' => text::CONTAINS, 'user:fullname_value' => 'Souza']);
        $this->assertEqualsCanonicalizing([$this->pair($ana, $c1), $this->pair($ana, $c2)], $keys());

        // In a category, the category's own cohorts and the site's, as the hub offers them there.
        $catcontext = \context_coursecat::instance($category->id);
        $options = $this->report($this->template([$c1], $catcontext), $catcontext)->get_filter('user:cohort')->get_options();
        $this->assertEqualsCanonicalizing([(int) $team->id, (int) $elsewhere->id], array_keys($options));
    }

    /**
     * The status filter offers "missing" over the pair set and not in the datasource, whose rows are status rows.
     *
     * @return void
     */
    public function test_missing_is_offered_over_the_pair_set_only(): void {
        $templateid = $this->template($this->competencies(1));
        $options = $this->report($templateid)->get_filter('validity:status')->get_options();
        $this->assertArrayHasKey(status::EFFECTIVE_MISSING, $options);
        $this->assertArrayHasKey(status::EFFECTIVE_RESTRICTED, $options);

        $generator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
        $persistent = $generator->create_report(['name' => 'Validity', 'source' => validity_datasource::class, 'default' => 0]);
        $options = manager::get_report_from_persistent($persistent)->get_filter('validity:status')->get_options();
        $this->assertArrayNotHasKey(status::EFFECTIVE_MISSING, $options);
        // The control: the datasource offers the stored statuses.
        $this->assertArrayHasKey(status::STATUS_VALID, $options);
    }

    /**
     * Only active plans of the template are listed, and of workers neither suspended nor deleted.
     *
     * @return void
     */
    public function test_only_active_plans_of_active_workers_are_listed(): void {
        global $DB;
        [$c1] = $this->competencies(1);
        $templateid = $this->template([$c1]);
        $other = $this->template([$c1]);
        $active = $this->worker($templateid);
        $elsewhere = $this->worker($other);
        $this->worker($templateid, [], plan::STATUS_DRAFT);
        $this->worker($templateid, ['suspended' => 1]);
        $deleted = $this->worker($templateid);
        $DB->set_field('user', 'deleted', 1, ['id' => $deleted->id]);

        $this->assertSame([$this->pair($active, $c1)], array_keys($this->rows($this->report($templateid))));
        // The control: the other template's worker is in its own report.
        $this->assertSame([$this->pair($elsewhere, $c1)], array_keys($this->rows($this->report($other))));
    }

    /**
     * A guest account is refused even when a role override gives it the hub's capabilities, and so is a
     * visitor who is not logged in; the control: a real user holding the same capabilities is accepted.
     *
     * @return void
     */
    public function test_a_guest_is_refused(): void {
        global $DB;
        [$c1] = $this->competencies(1);
        $templateid = $this->template([$c1]);
        $system = \context_system::instance();
        $capabilities = ['local/evidencehub:view', 'moodle/competency:templateview'];
        $guestrole = (int) $DB->get_field('role', 'id', ['shortname' => 'guest'], MUST_EXIST);
        foreach ($capabilities as $capability) {
            assign_capability($capability, CAP_ALLOW, $guestrole, $system->id, true);
        }
        accesslib_clear_all_caches_for_unit_testing();

        $this->setUser($this->viewer($capabilities));
        $this->assertTrue(hub_pairs::may_view($system, $templateid));
        $this->assertInstanceOf(hub_pairs::class, $this->report($templateid));

        $this->setGuestUser();
        // The override is in force: only the guest clause stands between the guest and the report.
        $this->assertTrue(isguestuser());
        $this->assertTrue(has_capability('local/evidencehub:view', $system));
        $this->assertFalse(hub_pairs::may_view($system, $templateid));
        try {
            $this->report($templateid);
            $this->fail('The guest was given the report.');
        } catch (report_access_exception $e) {
            $this->assertSame('errorreportview', $e->errorcode);
        }

        $this->setUser(0);
        $this->assertFalse(hub_pairs::may_view($system, $templateid));
    }

    /**
     * A report built with a download parameter is refused, as core's dynamic table builds it; the same call
     * without the parameter builds.
     *
     * @return void
     */
    public function test_a_download_parameter_is_refused(): void {
        $templateid = $this->template($this->competencies(1));
        $system = \context_system::instance();

        manager::reset_caches();
        $report = hub_pairs::for_template($system, $templateid);
        $this->assertInstanceOf(hub_pairs::class, $report);
        manager::reset_caches();
        $this->assertInstanceOf(hub_pairs::class, hub_pairs::for_template($system, $templateid, ['other' => 'x']));

        manager::reset_caches();
        try {
            hub_pairs::for_template($system, $templateid, ['download' => 'csv']);
            $this->fail('A download report was built.');
        } catch (report_access_exception $e) {
            $this->assertSame('errorreportview', $e->errorcode);
        }

        // The path core's table takes: the report is rebuilt from its persistent with the caller's parameters.
        manager::reset_caches();
        try {
            system_report_table::create((int) $report->get_report_persistent()->get('id'), ['download' => 'csv']);
            $this->fail('A download table was built.');
        } catch (report_access_exception $e) {
            $this->assertSame('errorreportview', $e->errorcode);
        }
    }

    /**
     * The identity columns are the hub's own: the user table's fields the reader may see, never a custom
     * profile field named in showuseridentity; a reader without moodle/site:viewuseridentity sees none.
     *
     * @return void
     */
    public function test_identity_columns_are_the_hubs_own(): void {
        $this->getDataGenerator()->create_custom_profile_field(['datatype' => 'text', 'shortname' => 'cpf', 'name' => 'CPF']);
        set_config('showuseridentity', 'email,profile_field_cpf');
        $templateid = $this->template($this->competencies(1));
        $profile = fn(hub_pairs $report): array => array_filter(
            array_keys($report->get_active_columns()),
            fn(string $identifier): bool => str_starts_with($identifier, 'user:profilefield')
        );

        $reader = $this->viewer(array_merge(self::READER, ['moodle/site:viewuseridentity']));
        $this->setUser($reader);
        // The control: core's own helper would add the profile field's column for this reader.
        $core = array_map(
            fn($column): string => $column->get_unique_identifier(),
            (new \core_reportbuilder\local\entities\user())->initialise()->get_identity_columns(\context_system::instance())
        );
        $this->assertContains('user:profilefield_cpf', $core);
        $report = $this->report($templateid);
        $this->assertArrayHasKey('user:email', $report->get_active_columns());
        $this->assertSame([], $profile($report));

        $this->setUser($this->viewer(self::READER));
        $report = $this->report($templateid);
        $this->assertArrayNotHasKey('user:email', $report->get_active_columns());
        $this->assertSame([], $profile($report));
    }

    /**
     * Each sortable column of a restricted pair sorts by its masked value, whose real one would put the pair
     * last; a reader holding viewsensitive sees it where its real value puts it.
     *
     * @return void
     */
    public function test_every_sort_key_of_a_restricted_pair_is_masked(): void {
        global $DB;
        [$c1, $c2, $c3] = $this->competencies(3);
        $templateid = $this->template([$c1, $c2, $c3]);
        $worker = $this->worker($templateid);
        $generator = $this->getDataGenerator()->get_plugin_generator('local_evidencehub');
        // Type names and issuers that sort in the order of the pairs; the restricted pair's sort last.
        $names = ['nrcertificate' => 'Alpha', 'diploma' => 'Beta', 'aso' => 'Zulu'];
        $pairs = [
            [$c1, 'nrcertificate', status::STATUS_VALID, 20260601, 1],
            [$c2, 'diploma', status::STATUS_DUE, 20270101, 2],
            [$c3, 'aso', status::STATUS_EXPIRED, 20990101, 5],
        ];
        foreach ($pairs as [$competencyid, $shortname, $code, $expiry, $count]) {
            $DB->set_field('local_evidencehub_type', 'name', $names[$shortname], ['shortname' => $shortname]);
            $typeid = (int) $DB->get_field('local_evidencehub_type', 'id', ['shortname' => $shortname]);
            $meta = $generator->create_meta([
                'userid' => $worker->id,
                'typeid' => $typeid,
                'issuer' => $names[$shortname] . ' issuer',
            ]);
            $generator->create_status([
                'userid' => $worker->id,
                'competencyid' => $competencyid,
                'status' => $code,
                'effectiveexpiry' => $expiry,
                'evidencecount' => $count,
                'metaid' => $meta->id,
            ]);
        }
        [$first, $second, $restricted] = [$this->pair($worker, $c1), $this->pair($worker, $c2), $this->pair($worker, $c3)];
        $keys = [
            'validity:status',
            'validity:effectiveexpiry',
            'validity:evidencecount',
            'evidence_details:type',
            'evidence_details:issuer',
        ];

        $readers = [
            // The masked key (-1, 0, 0, '', '') sorts the restricted pair first.
            'masked' => [$this->viewer(self::READER), [$restricted, $first, $second]],
            // The real key sorts it last.
            'real' => [
                $this->viewer(array_merge(self::READER, ['local/evidencehub:viewsensitive'])),
                [$first, $second, $restricted],
            ],
        ];
        foreach ($readers as $name => [$user, $ascending]) {
            $this->setUser($user);
            $report = $this->report($templateid);
            foreach ($keys as $key) {
                $this->assertSame($ascending, array_keys($this->rows($report, $key, SORT_ASC)), "$name ascending by $key");
                $this->assertSame(
                    array_reverse($ascending),
                    array_keys($this->rows($report, $key, SORT_DESC)),
                    "$name descending by $key"
                );
            }
        }
    }

    /**
     * The status filter reads the effective status: a forged real status of a restricted pair finds nothing,
     * restricted finds it, and missing lists the missing pairs only.
     *
     * @return void
     */
    public function test_the_status_filter_cannot_be_forged(): void {
        [$c1, $c2, $c3] = $this->competencies(3);
        $templateid = $this->template([$c1, $c2, $c3]);
        $worker = $this->worker($templateid);
        $this->status_row((int) $worker->id, $c1, status::STATUS_VALID, 20300101, 'aso');
        $this->status_row((int) $worker->id, $c2, status::STATUS_EXPIRED, 20250101, 'nrcertificate');
        $this->setUser($this->viewer(self::READER));
        $report = $this->report($templateid);

        $this->select($report, 'validity:status', status::STATUS_VALID);
        $this->assertSame([], array_keys($this->rows($report)));
        $this->select($report, 'validity:status', status::EFFECTIVE_RESTRICTED);
        $this->assertSame([$this->pair($worker, $c1)], array_keys($this->rows($report)));
        $this->select($report, 'validity:status', status::EFFECTIVE_MISSING);
        $this->assertSame([$this->pair($worker, $c3)], array_keys($this->rows($report)));
    }

    /**
     * A cohort the hub does not offer the reader is not an option and, forged as the filter's value, filters
     * nothing; the control: made visible, it is offered and narrows.
     *
     * @return void
     */
    public function test_a_hidden_cohort_is_not_offered_and_filters_nothing(): void {
        global $DB;
        [$c1] = $this->competencies(1);
        $templateid = $this->template([$c1]);
        $ana = $this->worker($templateid);
        $bia = $this->worker($templateid);
        $cohort = $this->getDataGenerator()->create_cohort(['visible' => 0]);
        cohort_add_member($cohort->id, $bia->id);
        $this->setUser($this->viewer(self::READER));
        $both = [$this->pair($ana, $c1), $this->pair($bia, $c1)];

        $report = $this->report($templateid);
        $this->assertArrayNotHasKey((int) $cohort->id, $report->get_filter('user:cohort')->get_options());
        $this->select($report, 'user:cohort', (int) $cohort->id);
        $this->assertEqualsCanonicalizing($both, array_keys($this->rows($report)));

        $DB->set_field('cohort', 'visible', 1, ['id' => $cohort->id]);
        $report = $this->report($templateid);
        $this->assertArrayHasKey((int) $cohort->id, $report->get_filter('user:cohort')->get_options());
        $this->select($report, 'user:cohort', (int) $cohort->id);
        $this->assertSame([$this->pair($bia, $c1)], array_keys($this->rows($report)));
    }

    /**
     * The report's "valid until" sort puts a pair valid with no end after every dated pair ascending, and first
     * descending, while a restricted and a missing pair sort as the most urgent; the cell of the pair with no end is
     * empty (the high key is the sort's only: the column's value and the "valid until" filter read 0 for it).
     *
     * Changes that must make it fail: the sort key of a pair with no expiry left 0 (it sorts among the missing ones);
     * a restricted or missing pair keyed high; the high key put in the column's value (the cell would print it).
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
        [$noend, $dated, $restricted, $missing] = [
            $this->pair($worker, $c1),
            $this->pair($worker, $c2),
            $this->pair($worker, $c3),
            $this->pair($worker, $c4),
        ];

        $this->setUser($this->viewer(self::READER));
        $report = $this->report($templateid);
        $ascending = $this->rows($report, 'validity:effectiveexpiry', SORT_ASC);
        $this->assertSame([$restricted, $missing, $dated, $noend], array_keys($ascending));
        $descending = $this->rows($report, 'validity:effectiveexpiry', SORT_DESC);
        // The tie of the restricted and the missing pair goes by the pair's key, in the sort's direction too.
        $this->assertSame([$noend, $dated, $missing, $restricted], array_keys($descending));
        $this->assertSame('', $ascending[$noend]['validity:effectiveexpiry']);
        // The report's own order, its default sort, is the same as the pair set's.
        $this->assertSame(array_keys($this->hub_rows($templateid)), array_keys($this->rows($report)));
    }
}
