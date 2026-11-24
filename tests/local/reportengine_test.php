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
use core_reportbuilder\local\filters\text;
use core_reportbuilder\manager;
use core_reportbuilder\tests\core_reportbuilder_testcase;
use local_evidencehub\event\evidence_details_updated;
use local_evidencehub\reportbuilder\local\filters\validuntil;
use local_evidencehub\reportbuilder\local\systemreports\headless_table;
use local_evidencehub\reportbuilder\local\systemreports\hub_pairs;
use local_evidencehub\tests\hub_scenario;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The pairs report read headlessly: the request facts, the columns, the facet counts and the summary.
 *
 * Three workers listed by three competencies (Ana Souza, Bia Lima, Cid Rocha) and one suspended (Dan Silva).
 * Bia's first pair is decided by a sensitive evidence whose real status (compliant), type, verification, issuer,
 * count and day (within 30 days of the frozen today) all differ from what a reader without viewsensitive is
 * shown, so a figure read from the real value is wrong for that reader. The readers: a manager, a mentor of
 * one cohort (Ana and Bia) through tool_cohortroles without viewsensitive, and a reader of every worker without
 * viewsensitive.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(reportengine::class)]
#[CoversClass(hub_pairs::class)]
#[CoversClass(headless_table::class)]
#[CoversClass(validuntil::class)]
#[CoversClass(hub_kpis::class)]
final class reportengine_test extends core_reportbuilder_testcase {
    use hub_scenario;

    /** @var int 2026-11-23, 12:00 in Perth, 04:00 UTC: the 23rd in either. */
    private const int NOW = 1795406400;

    /** @var array The capabilities of a reader of every worker without viewsensitive. */
    private const array READER = [
        'local/evidencehub:view',
        'moodle/competency:templateview',
        'moodle/competency:userevidenceview',
    ];

    /** @var array The competency ids, C1 to C3. */
    private array $c;

    /** @var int The template. */
    private int $templateid;

    /** @var array The workers by first name. */
    private array $w = [];

    /** @var \stdClass The cohort of Ana and Bia. */
    private \stdClass $cohort;

    /** @var \stdClass The mentor of that cohort. */
    private \stdClass $mentor;

    /** @var \stdClass The reader of every worker without viewsensitive. */
    private \stdClass $reader;

    #[\Override]
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        set_config('enabled', 1, 'core_competency');
        $this->mock_clock_with_frozen(self::NOW);
        $this->setAdminUser();
        $this->c = $this->competencies(3);
        [$c1, $c2, $c3] = $this->c;
        $this->templateid = $this->template($this->c);
        $this->w['ana'] = $this->worker($this->templateid, ['firstname' => 'Ana', 'lastname' => 'Souza']);
        $this->w['bia'] = $this->worker($this->templateid, ['firstname' => 'Bia', 'lastname' => 'Lima']);
        $this->w['cid'] = $this->worker($this->templateid, [
            'firstname' => 'Cid',
            'lastname' => 'Rocha',
            'idnumber' => 'ZXQ123',
            'email' => 'zxq@example.com',
        ]);
        $this->w['dan'] = $this->worker($this->templateid, ['firstname' => 'Dan', 'lastname' => 'Silva', 'suspended' => 1]);

        $DB->set_field('local_evidencehub_type', 'name', 'Zircon permit', ['shortname' => 'aso']);
        $this->status_row($this->id('ana'), $c1, status::STATUS_EXPIRED, 20250101, 'nrcertificate');
        $this->status_row($this->id('ana'), $c2, status::STATUS_VALID, 20300101, 'diploma');
        $this->sensitive_row($this->id('bia'), $c1);
        $this->status_row($this->id('bia'), $c2, status::STATUS_DUE, 20261201, 'diploma');
        $this->status_row($this->id('bia'), $c3, status::STATUS_CRITICAL, 20261130, 'nrcertificate');
        $this->status_row($this->id('cid'), $c1, status::STATUS_VALID, 20300101, 'nrcertificate');
        $this->status_row($this->id('cid'), $c2, status::STATUS_VALID, 20290101, 'diploma');
        $this->status_row($this->id('cid'), $c3, status::STATUS_VALID, 20280101, 'nrcertificate');
        $this->status_row($this->id('dan'), $c1, status::STATUS_EXPIRED, 20250101, 'nrcertificate');

        $this->cohort = $this->getDataGenerator()->create_cohort(['name' => 'Night shift']);
        cohort_add_member($this->cohort->id, $this->id('ana'));
        cohort_add_member($this->cohort->id, $this->id('bia'));
        $this->mentor = $this->viewer(['local/evidencehub:view', 'moodle/competency:templateview']);
        $mentorrole = $this->getDataGenerator()->create_role();
        assign_capability('moodle/competency:userevidenceview', CAP_ALLOW, $mentorrole, \context_system::instance()->id, true);
        set_role_contextlevels($mentorrole, [CONTEXT_USER]);
        \tool_cohortroles\api::create_cohort_role_assignment((object) [
            'userid' => $this->mentor->id,
            'roleid' => $mentorrole,
            'cohortid' => $this->cohort->id,
        ]);
        \tool_cohortroles\api::sync_all_cohort_roles();
        $this->reader = $this->viewer(self::READER);
        $this->setAdminUser();
    }

    /**
     * A worker's id.
     *
     * @param string $name The worker's key.
     * @return int
     */
    private function id(string $name): int {
        return (int) $this->w[$name]->id;
    }

    /**
     * Bia's first pair: compliant until 2026-12-10, decided by a verified sensitive evidence ("Zircon permit",
     * "Quartzline issuer", 37 records), which a reader without viewsensitive is shown as restricted.
     *
     * @param int $userid The worker.
     * @param int $competencyid The competency.
     * @return void
     */
    private function sensitive_row(int $userid, int $competencyid): void {
        global $DB;
        $generator = $this->getDataGenerator()->get_plugin_generator('local_evidencehub');
        $meta = $generator->create_meta([
            'userid' => $userid,
            'typeid' => (int) $DB->get_field('local_evidencehub_type', 'id', ['shortname' => 'aso']),
            'issuer' => 'Quartzline issuer',
            'issuedate' => 20191231,
            'expirydate' => 20261210,
            'verifystatus' => meta::VERIFY_VERIFIED,
        ]);
        $generator->create_status([
            'userid' => $userid,
            'competencyid' => $competencyid,
            'status' => status::STATUS_VALID,
            'effectiveexpiry' => 20261210,
            'evidencecount' => 37,
            'metaid' => $meta->id,
        ]);
    }

    /**
     * The report, built afresh as a request of its own: core keeps one instance per reader and request.
     *
     * @param array $parameters Report parameters.
     * @return hub_pairs
     */
    private function report(array $parameters = []): hub_pairs {
        manager::reset_caches();
        if ($parameters) {
            return hub_pairs::for_template(\context_system::instance(), $this->templateid, $parameters);
        }
        return reportengine::report(\context_system::instance(), $this->templateid);
    }

    /**
     * The rows answer for the current user.
     *
     * @param array $options The engine's options; the scope "all" unless given.
     * @return array
     */
    private function rows(array $options = []): array {
        return reportengine::rows($this->report(), $options + ['scope' => reportengine::SCOPE_ALL, 'perpage' => 100]);
    }

    /**
     * The pairs of a rows answer, as "userid:competencyid".
     *
     * @param array $answer The rows answer.
     * @return array In the answer's order.
     */
    private function keys(array $answer): array {
        return array_map(fn(array $row): string => $this->pair($row['userid'], $row['competencyid']), $answer['rows']);
    }

    /**
     * Store the current user's filter values, replacing the whole set as core's service does.
     *
     * @param array $values The values.
     * @return void
     */
    private function store(array $values): void {
        $this->report()->set_filter_values($values);
    }

    /**
     * The stored values that choose one option of a select filter.
     *
     * @param string $filter The filter's unique identifier.
     * @param string $value The option.
     * @return array
     */
    private function choose(string $filter, string $value): array {
        return [$filter . '_operator' => select::EQUAL_TO, $filter . '_value' => $value];
    }

    /**
     * A facet's counts, by option value.
     *
     * @param array $answer The rows answer.
     * @param string $name The filter's unique identifier.
     * @return array Value => count.
     */
    private function counts(array $answer, string $name): array {
        $filters = array_column($answer['filters'], null, 'name');
        $this->assertArrayHasKey($name, $filters);
        return array_column($filters[$name]['options'], 'count', 'value');
    }

    /**
     * Statements a call sends, as perf_get_queries() counts them.
     *
     * @param callable $call The call.
     * @return int
     */
    private function statements(callable $call): int {
        global $DB;
        $before = $DB->perf_get_queries();
        $call();
        return $DB->perf_get_queries() - $before;
    }

    /**
     * The scope: attention lists every pair but the compliant ones, all lists every pair, and anything else reads
     * as attention.
     *
     * @return void
     */
    public function test_the_scope(): void {
        $this->assertSame(9, $this->rows()['total']);
        $attention = $this->rows(['scope' => reportengine::SCOPE_ATTENTION]);
        $this->assertSame(4, $attention['total']);
        $this->assertSame(reportengine::SCOPE_ATTENTION, $attention['scope']);
        $this->assertNotContains(status::STATUS_VALID, array_column($attention['rows'], 'status'));
        $garbage = $this->rows(['scope' => 'everything']);
        $this->assertSame([reportengine::SCOPE_ATTENTION, 4], [$garbage['scope'], $garbage['total']]);

        // A reader without viewsensitive: the restricted pair needs attention, whatever its real status.
        $this->setUser($this->reader);
        $this->assertSame(5, $this->rows(['scope' => reportengine::SCOPE_ATTENTION])['total']);
        $this->assertContains($this->pair($this->id('bia'), $this->c[0]), $this->keys($this->rows(['scope' => 'attention'])));
    }

    /**
     * The search: the worker's name, case-insensitive, from two characters, at most a hundred; never the ID
     * number or the e-mail, which the name filter does not read.
     *
     * @return void
     */
    public function test_the_search(): void {
        $ana = [$this->pair($this->id('ana'), $this->c[0]), $this->pair($this->id('ana'), $this->c[1]),
            $this->pair($this->id('ana'), $this->c[2])];
        $this->assertEqualsCanonicalizing($ana, $this->keys($this->rows(['search' => 'SOUZA'])));
        $this->assertEqualsCanonicalizing($ana, $this->keys($this->rows(['search' => 'so'])));
        $one = $this->rows(['search' => 's']);
        $this->assertSame(['', 9], [$one['search'], $one['total']]);
        $this->assertSame(0, $this->rows(['search' => 'zxq'])['total']);
        $this->assertSame(0, $this->rows(['search' => 'example.com'])['total']);
        // The control: Cid is found by his name.
        $this->assertSame(3, $this->rows(['search' => 'rocha'])['total']);
        $this->assertSame(100, \core_text::strlen($this->rows(['search' => str_repeat('a', 150)])['search']));
        $this->assertSame('so', reportengine::search("  so\t"));
    }

    /**
     * Suspended accounts are left out unless the reader's preference or the report's parameter includes them.
     *
     * @return void
     */
    public function test_suspended_accounts(): void {
        $dan = $this->pair($this->id('dan'), $this->c[0]);
        $this->assertNotContains($dan, $this->keys($this->rows()));
        reportpreference::write_grid(null, [], true);
        $this->assertSame(12, $this->rows()['total']);
        $this->assertContains($dan, $this->keys($this->rows()));
        $this->assertTrue($this->rows()['preference']['suspended']);

        // The report's own parameter, as a download passes it, overrides the preference both ways.
        $table = headless_table::create((int) $this->report(['suspended' => 0])->get_report_persistent()->get('id'), []);
        $this->assertSame(9, $table->get_total_row_count());
        reportpreference::write_grid(null, [], false);
        $table = headless_table::create((int) $this->report(['suspended' => 1])->get_report_persistent()->get('id'), []);
        $this->assertSame(12, $table->get_total_row_count());
    }

    /**
     * The report's own scope and search parameters can only narrow it: no scope lists every pair, garbage reads
     * as attention, the search follows the engine's.
     *
     * @return void
     */
    public function test_the_report_parameters_only_narrow(): void {
        $count = fn(array $parameters): int => headless_table::create(
            (int) $this->report($parameters)->get_report_persistent()->get('id'),
            []
        )->get_total_row_count();
        $this->assertSame(9, $count([]));
        $this->assertSame(9, $count(['scope' => 'all']));
        $this->assertSame(4, $count(['scope' => 'attention']));
        $this->assertSame(4, $count(['scope' => 'widen']));
        $this->assertSame(3, $count(['search' => 'souza']));
        $this->assertSame(9, $count(['search' => 's']));
    }

    /**
     * Paging is bounded: an unknown page size reads as 50, a negative page as the first, a page past the last as
     * the last.
     *
     * @return void
     */
    public function test_paging_is_bounded(): void {
        $rows = $this->rows(['perpage' => 7]);
        $this->assertSame([50, 9], [$rows['perpage'], count($rows['rows'])]);
        $rows = $this->rows(['perpage' => 30, 'page' => -3]);
        $this->assertSame([0, 9], [$rows['page'], count($rows['rows'])]);
        $rows = $this->rows(['perpage' => 30, 'page' => 7]);
        $this->assertSame([0, 9, 9], [$rows['page'], $rows['total'], count($rows['rows'])]);

        reportpreference::write_grid(null, [], true);
        for ($i = 0; $i < 9; $i++) {
            $this->worker($this->templateid, ['firstname' => 'Extra' . $i]);
        }
        // 39 pairs: the last page of 30 is the second, with nine.
        $rows = $this->rows(['perpage' => 30, 'page' => 5]);
        $this->assertSame([1, 39, 9], [$rows['page'], $rows['total'], count($rows['rows'])]);
    }

    /**
     * The sort names a column key the report shows and can sort by; anything else comes back empty and leaves
     * the report's own sort.
     *
     * @return void
     */
    public function test_the_sort(): void {
        $rows = $this->rows(['sort' => 'status', 'direction' => 'desc']);
        $this->assertSame(['status', 'desc'], [$rows['sort'], $rows['direction']]);
        $statuses = array_column($rows['rows'], 'status');
        $sorted = $statuses;
        rsort($sorted);
        $this->assertSame($sorted, $statuses);

        $default = $this->keys($this->rows());
        foreach (['changed', 'nope', 'identity:idnumber', ''] as $sort) {
            $rows = $this->rows(['sort' => $sort, 'direction' => 'desc']);
            $this->assertSame(['', ''], [$rows['sort'], $rows['direction']], $sort);
            $this->assertSame($default, $this->keys($rows), $sort);
        }
        // The control: "changed" sorts once the reader shows it.
        reportpreference::write_grid(['changed'], [], null);
        $this->assertSame('changed', $this->rows(['sort' => 'changed'])['sort']);
    }

    /**
     * The columns: the default set with no preference; the catalogue's keys a preference names, in catalogue order,
     * a stale or forged key adding nothing; the whole catalogue listed with what is active; the cells follow.
     *
     * @return void
     */
    public function test_the_columns_follow_the_catalogue(): void {
        $fixed = ['worker', 'identity:email', 'competency'];
        $defaults = ['status', 'expiry', 'count', 'type', 'issuer'];
        $rows = $this->rows();
        $this->assertSame(array_merge($fixed, $defaults), array_column($rows['rows'][0]['cells'], 'key'));
        $this->assertTrue($rows['preference']['usedefault']);
        $columns = array_column($rows['columns'], null, 'key');
        $this->assertSame(
            array_merge($fixed, $defaults, ['competencyidnumber', 'framework', 'issuedate', 'expirydate', 'verification',
                'changed']),
            array_keys($columns)
        );
        $this->assertTrue($columns['worker']['fixed']);
        $this->assertFalse($columns['changed']['active']);
        $this->assertFalse($columns['changed']['default']);
        $this->assertTrue($columns['type']['active']);

        reportpreference::write_grid(['changed', 'status', 'bogus', 'worker', 'identity:email'], [], null);
        $rows = $this->rows();
        $this->assertSame(array_merge($fixed, ['status', 'changed']), array_column($rows['rows'][0]['cells'], 'key'));
        $this->assertSame(['changed', 'status', 'bogus', 'worker', 'identity:email'], $rows['preference']['columns']);
        $this->assertTrue(array_column($rows['columns'], null, 'key')['changed']['active']);
        $this->assertArrayNotHasKey('bogus', array_column($rows['columns'], null, 'key'));

        // The status and the restricted flag come with every row, whatever the columns; a reader without
        // moodle/site:viewuseridentity has no identity column.
        $this->setUser($this->reader);
        reportpreference::write_grid([], [], null);
        $rows = $this->rows()['rows'];
        $this->assertSame(['worker', 'competency'], array_column($rows[0]['cells'], 'key'));
        $bia = array_values(array_filter(
            $rows,
            fn(array $row): bool => $row['userid'] === $this->id('bia') && $row['competencyid'] === $this->c[0]
        ))[0];
        $this->assertSame([status::EFFECTIVE_RESTRICTED, true], [$bia['status'], $bia['restricted']]);
    }

    /**
     * A preset is in force when the reader's columns are its keys; the default set is no preset.
     *
     * @return void
     */
    public function test_the_presets(): void {
        $active = fn(): array => array_column(
            array_filter($this->rows()['presets'], fn(array $preset): bool => $preset['active']),
            'name'
        );
        $this->assertSame(['validity', 'evidence'], array_column($this->rows()['presets'], 'name'));
        $this->assertSame([], $active());
        reportpreference::write_grid(reportengine::PRESETS['validity'], [], null);
        $this->assertSame(['validity'], $active());
        reportpreference::write_grid(array_reverse(reportengine::PRESETS['evidence']), [], null);
        $this->assertSame(['evidence'], $active());
        reportpreference::write_grid(['status', 'type'], [], null);
        $this->assertSame([], $active());
    }

    /**
     * Hidden: the template's workers the reader may not view, suspended included, never cut by a filter or the
     * search.
     *
     * @return void
     */
    public function test_hidden_workers(): void {
        $this->assertSame(0, $this->rows()['hidden']);
        $this->setUser($this->mentor);
        $this->assertSame(2, $this->rows()['hidden']);
        $this->store($this->choose('user:cohort', (string) $this->cohort->id));
        $this->assertSame(2, $this->rows(['search' => 'souza'])['hidden']);
        $this->assertSame(2, reportengine::summary($this->report(), ['search' => 'souza'])['hidden']);
        // The control: the mentor's rows are their cohort's.
        $this->assertSame(3, $this->rows(['search' => 'souza'])['total']);
    }

    /**
     * Every count of every facet is what the rows list once that option is chosen, for a manager and for the
     * mentor, under the scope and with another filter in force.
     *
     * @return void
     */
    public function test_facet_counts_are_what_choosing_lists(): void {
        $facets = ['validity:status', 'user:cohort', 'competency:competency', 'evidence_details:type',
            'evidence_details:verifystatus', 'validity:validuntil'];
        $cases = [
            'manager' => [null, ['scope' => 'all'], []],
            'mentor' => [$this->mentor, ['scope' => 'all'], []],
            'attention' => [null, ['scope' => 'attention'], []],
            'other filter' => [$this->reader, ['scope' => 'all'], $this->choose('competency:competency', (string) $this->c[0])],
        ];
        foreach ($cases as $case => [$user, $options, $others]) {
            $user === null ? $this->setAdminUser() : $this->setUser($user);
            $this->store($others);
            $answer = $this->rows($options);
            $tested = 0;
            foreach ($facets as $facet) {
                $counts = $this->counts($answer, $facet);
                foreach ($counts as $value => $count) {
                    $this->store($this->choose($facet, (string) $value) + $others);
                    $this->assertSame($count, $this->rows($options)['total'], "{$case}: {$facet} = {$value}");
                    $tested++;
                }
                $this->store($others);
            }
            $this->assertGreaterThan(5, $tested, $case);
        }
    }

    /**
     * Hand counts for the mentor, who sees Bia's first pair restricted: every facet reads the masked value, so the
     * pair is counted as restricted, with no type, verification or day.
     *
     * @return void
     */
    public function test_facet_counts_read_the_masked_values(): void {
        global $DB;
        $aso = (string) $DB->get_field('local_evidencehub_type', 'id', ['shortname' => 'aso']);
        $this->setUser($this->mentor);
        $answer = $this->rows();
        $this->assertSame(6, $answer['total']);
        $this->assertSame([
            (string) status::EFFECTIVE_MISSING => 1,
            (string) status::STATUS_EXPIRED => 1,
            (string) status::STATUS_CRITICAL => 1,
            (string) status::STATUS_DUE => 1,
            (string) status::STATUS_VALID => 1,
            (string) status::EFFECTIVE_RESTRICTED => 1,
        ], $this->counts($answer, 'validity:status'));
        $this->assertArrayNotHasKey($aso, $this->counts($answer, 'evidence_details:type'));
        $this->assertSame([(string) meta::VERIFY_PENDING => 4], $this->counts($answer, 'evidence_details:verifystatus'));
        $this->assertSame(
            [validuntil::EXPIRED => 1, 'within30' => 2, 'within60' => 2, 'within90' => 2],
            $this->counts($answer, 'validity:validuntil')
        );
        $this->assertSame([(string) $this->cohort->id => 6], $this->counts($answer, 'user:cohort'));

        // The control: the manager is counted the real values.
        $this->setAdminUser();
        $answer = $this->rows();
        $this->assertSame(1, $this->counts($answer, 'evidence_details:type')[$aso]);
        $this->assertSame(1, $this->counts($answer, 'evidence_details:verifystatus')[(string) meta::VERIFY_VERIFIED]);
        $this->assertSame(3, $this->counts($answer, 'validity:validuntil')['within30']);
        $this->assertArrayNotHasKey((string) status::EFFECTIVE_RESTRICTED, $this->counts($answer, 'validity:status'));
    }

    /**
     * A facet is counted without its own value, under the others; an option no row would list is left out unless
     * chosen; and a read without facets counts nothing.
     *
     * @return void
     */
    public function test_a_facet_is_counted_without_its_own_value(): void {
        $this->store($this->choose('validity:status', (string) status::STATUS_EXPIRED));
        $answer = $this->rows();
        $this->assertSame(1, $answer['total']);
        // Its own facet sees every status; another facet sees the chosen status's pairs alone.
        $this->assertSame(9, array_sum($this->counts($answer, 'validity:status')));
        $this->assertSame([(string) $this->c[0] => 1], $this->counts($answer, 'competency:competency'));

        // A status no pair holds under the scope is left out, unless it is the one chosen.
        $this->store($this->choose('validity:status', (string) status::STATUS_VALID));
        $counts = $this->counts($this->rows(['scope' => 'attention']), 'validity:status');
        $this->assertSame(0, $counts[(string) status::STATUS_VALID]);
        $this->assertArrayNotHasKey((string) status::STATUS_UNVERIFIED, $counts);

        $answer = $this->rows(['facets' => false]);
        $this->assertFalse($answer['counted']);
        $status = array_column($answer['filters'], null, 'name')['validity:status'];
        $this->assertCount(count(status::effective_codes()), $status['options']);
        $this->assertSame([-1], array_values(array_unique(array_column($status['options'], 'count'))));
        $this->assertTrue($status['applied']);
        $this->assertSame((string) status::STATUS_VALID, json_decode($status['values'], true)['validity:status_value']);
    }

    /**
     * "Valid until" filters by the masked day against today: expired before today, a window from today on.
     *
     * @return void
     */
    public function test_valid_until(): void {
        $keys = function (string $choice): array {
            $this->store($this->choose('validity:validuntil', $choice));
            return $this->keys($this->rows());
        };
        $bia = fn(int $index): string => $this->pair($this->id('bia'), $this->c[$index]);
        $this->assertEqualsCanonicalizing([$this->pair($this->id('ana'), $this->c[0])], $keys(validuntil::EXPIRED));
        $this->assertEqualsCanonicalizing([$bia(0), $bia(1), $bia(2)], $keys('within30'));
        $this->assertSame(9, count($keys('nonsense')));

        // A reader who sees Bia's first pair restricted does not find it by its real day.
        $this->setUser($this->reader);
        $this->assertEqualsCanonicalizing([$bia(1), $bia(2)], $keys('within30'));

        // Today moves the window: on 2026-12-05 only Bia's first pair is within 30 days.
        $this->setAdminUser();
        $this->mock_clock_with_frozen(self::NOW + 12 * DAYSECS);
        $this->assertSame([$bia(0)], $keys('within30'));
    }

    /**
     * The summary's figures against hand counts, for a manager and for a reader without viewsensitive.
     *
     * @return void
     */
    public function test_the_summary(): void {
        $summary = reportengine::summary($this->report(), []);
        $counts = array_column($summary['distribution'], 'count', 'status');
        $this->assertSame([
            status::EFFECTIVE_MISSING => 1,
            status::STATUS_UNVERIFIED => 0,
            status::STATUS_EXPIRED => 1,
            status::STATUS_CRITICAL => 1,
            status::STATUS_DUE => 1,
            status::STATUS_VALID => 5,
            status::EFFECTIVE_RESTRICTED => 0,
        ], $counts);
        $this->assertSame(
            [3, 2, 0.6667, 9, 4, 3, 0],
            [$summary['workers'], $summary['ready'], $summary['rate'], $summary['pairs'], $summary['attention'],
                $summary['expiring'], $summary['hidden']]
        );
        $this->assertSame([$this->c[2], $this->c[0], $this->c[1]], array_column($summary['worst'], 'competencyid'));
        $this->assertSame([2, 1, 1], array_column($summary['worst'], 'count'));
        $this->assertSame(['C3', 'C1', 'C2'], array_column($summary['worst'], 'name'));

        $this->setUser($this->reader);
        $summary = reportengine::summary($this->report(), []);
        $this->assertSame(
            [3, 1, 9, 5, 2],
            [$summary['workers'], $summary['ready'], $summary['pairs'], $summary['attention'], $summary['expiring']]
        );
        $this->assertSame(1, array_column($summary['distribution'], 'count', 'status')[status::EFFECTIVE_RESTRICTED]);
        $this->assertSame($summary['pairs'], array_sum(array_column($summary['distribution'], 'count')));
        $this->assertSame([$this->c[0], $this->c[2], $this->c[1]], array_column($summary['worst'], 'competencyid'));

        // The search narrows every figure but hidden.
        $summary = reportengine::summary($this->report(), ['search' => 'lima']);
        $this->assertSame([1, 0, 3, 3, 'lima'], [$summary['workers'], $summary['ready'], $summary['pairs'],
            $summary['attention'], $summary['search']]);
    }

    /**
     * The workers and ready figures (and the band's other figures) ignore the scope and the status filter; the
     * control: a cohort filter changes them.
     *
     * @return void
     */
    public function test_the_summary_ignores_the_scope_and_the_status_filter(): void {
        // Each call computed afresh: the cache's key leaves the scope and the status filter out on purpose.
        $figures = function (array $options): array {
            hub_kpis::bump_generation();
            return array_intersect_key(
                reportengine::summary($this->report(), $options),
                array_flip(['workers', 'ready', 'pairs', 'attention', 'expiring', 'distribution', 'worst'])
            );
        };
        $all = $figures(['scope' => 'all']);
        $this->assertSame($all, $figures(['scope' => 'attention']));
        $this->store($this->choose('validity:status', (string) status::STATUS_EXPIRED));
        $this->assertSame($all, $figures(['scope' => 'attention']));
        // The control: the cohort of Ana and Bia narrows them.
        $this->store($this->choose('user:cohort', (string) $this->cohort->id));
        $narrowed = $figures([]);
        $this->assertSame([2, 1, 6], [$narrowed['workers'], $narrowed['ready'], $narrowed['pairs']]);
    }

    /**
     * The competencies with most pairs needing attention: five at most, the most first, ties by competency.
     *
     * @return void
     */
    public function test_the_worst_competencies(): void {
        $competencies = $this->competencies(7);
        $templateid = $this->template($competencies);
        $this->worker($templateid);
        $second = $this->worker($templateid);
        $this->status_row((int) $second->id, $competencies[6], status::STATUS_EXPIRED, 20250101);
        $this->status_row((int) $second->id, $competencies[5], status::STATUS_EXPIRED, 20250101);
        $this->status_row((int) $second->id, $competencies[0], status::STATUS_VALID, 20300101);
        manager::reset_caches();
        $worst = reportengine::summary(reportengine::report(\context_system::instance(), $templateid), [])['worst'];
        // Two pairs each for C2 to C7, one for C1 (whose second pair is compliant): the first five by id.
        $this->assertSame(array_slice($competencies, 1, 5), array_column($worst, 'competencyid'));
        $this->assertSame([2, 2, 2, 2, 2], array_column($worst, 'count'));
    }

    /**
     * A second summary call is served by the cache; a status change retires it; a search is never cached.
     *
     * @return void
     */
    public function test_the_summary_cache(): void {
        $first = $this->statements(fn() => reportengine::summary($this->report(), []));
        $cached = $this->statements(fn() => reportengine::summary($this->report(), []));
        $this->assertLessThan($first, $cached);
        $this->assertSame(reportengine::summary($this->report(), []), reportengine::summary($this->report(), []));

        // A status written changes the generation, and so the figures.
        $this->status_row($this->id('ana'), $this->c[2], status::STATUS_VALID, 20300101);
        $summary = reportengine::summary($this->report(), []);
        $this->assertSame(0, array_column($summary['distribution'], 'count', 'status')[status::EFFECTIVE_MISSING]);

        // A stored filter is part of the key: the cohort's figures are not the whole template's.
        $this->store($this->choose('user:cohort', (string) $this->cohort->id));
        $this->assertSame(2, reportengine::summary($this->report(), [])['workers']);
        // The suspended flag too.
        $this->store([]);
        reportpreference::write_grid(null, [], true);
        $this->assertSame(4, reportengine::summary($this->report(), [])['workers']);

        // A search reads the database every time.
        reportengine::summary($this->report(), ['search' => 'souza']);
        $this->assertGreaterThan(
            $cached,
            $this->statements(fn() => reportengine::summary($this->report(), ['search' => 'souza']))
        );
    }

    /**
     * The cached summary follows today: on 2026-12-05 only Bia's first pair expires within 30 days.
     *
     * @return void
     */
    public function test_the_summary_follows_today(): void {
        $this->assertSame(3, reportengine::summary($this->report(), [])['expiring']);
        $this->mock_clock_with_frozen(self::NOW + 12 * DAYSECS);
        $this->assertSame(1, reportengine::summary($this->report(), [])['expiring']);
    }

    /**
     * A competency named with a "<" and an "&" reaches the drawer's title and the Inspect action's name in the
     * plain spelling, which the client prints as text: neither entity-escaped nor doubly escaped.
     *
     * Changes that must make it fail: the competency's name passed to the strings as format_string() returns it.
     *
     * @return void
     */
    public function test_the_titles_carry_a_competency_name_in_the_plain_spelling(): void {
        global $DB;
        $DB->set_field('competency', 'shortname', 'A < B & C', ['id' => $this->c[0]]);
        $rows = array_values(array_filter(
            $this->rows()['rows'],
            fn(array $row): bool => $row['userid'] === $this->id('ana') && $row['competencyid'] === $this->c[0]
        ));
        $this->assertCount(1, $rows);
        $this->assertSame('Ana Souza: A < B & C', $rows[0]['drawertitle']);
        $this->assertSame('Inspect Ana Souza: A < B & C', $rows[0]['inspectlabel']);
        // The control: the cells keep core's escaped spelling, which the client inserts as HTML.
        $this->assertStringContainsString('A &lt; B &amp; C', implode('', array_column($rows[0]['cells'], 'html')));
    }

    /**
     * The inspection drawer is offered where its own fragment would open: a reader who may not read the
     * competency's framework is not offered it.
     *
     * @return void
     */
    public function test_inspect_follows_the_drawer(): void {
        global $DB;
        // Every authenticated user reads competencies by default: this site withholds it.
        $userrole = (int) $DB->get_field('role', 'id', ['shortname' => 'user'], MUST_EXIST);
        unassign_capability('moodle/competency:competencyview', $userrole);
        accesslib_clear_all_caches_for_unit_testing();
        $this->assertSame([true], array_values(array_unique(array_column($this->rows()['rows'], 'inspect'))));
        $this->setUser($this->reader);
        $rows = $this->rows()['rows'];
        $this->assertSame([false], array_values(array_unique(array_column($rows, 'inspect'))));
        $this->assertSame(
            get_string('drawertitle', 'local_evidencehub', (object) ['worker' => 'Ana Souza', 'competency' => 'C1']),
            array_values(array_filter($rows, fn(array $row): bool => $row['userid'] === $this->id('ana')
                && $row['competencyid'] === $this->c[0]))[0]['drawertitle']
        );
        $this->assertStringEndsWith('/local/evidencehub/evidence.php?userid=' . $rows[0]['userid'], $rows[0]['evidenceurl']);

        // The control: the same reader with competencyview in the framework's context.
        $this->setUser($this->viewer(array_merge(self::READER, ['moodle/competency:competencyview'])));
        $this->assertSame([true], array_values(array_unique(array_column($this->rows()['rows'], 'inspect'))));
    }

    /**
     * The statements each case of a reader's session sends, after a call of other criteria warmed the request.
     *
     * The first page with its facets is the cold case (the generation just moved); the same call again, the next
     * page, a sort and a call without facets read the same criteria; the first page without facets is also measured
     * cold, and the summary cold and cached.
     *
     * @return array Case => statements.
     */
    private function statement_cases(): array {
        $first = ['scope' => reportengine::SCOPE_ALL, 'perpage' => 30];
        $this->rows(['scope' => reportengine::SCOPE_ATTENTION, 'facets' => true]);
        reportengine::summary($this->report(), ['scope' => reportengine::SCOPE_ATTENTION]);
        hub_kpis::bump_generation();
        $cases = [
            'rows' => $this->statements(fn() => $this->rows($first + ['facets' => true])),
            'rowsagain' => $this->statements(fn() => $this->rows($first + ['facets' => true])),
            'nextpage' => $this->statements(fn() => $this->rows($first + ['page' => 1, 'facets' => false])),
            'sort' => $this->statements(fn() => $this->rows($first + ['sort' => 'status', 'facets' => false])),
        ];
        hub_kpis::bump_generation();
        $cases['rowsnofacets'] = $this->statements(fn() => $this->rows($first + ['facets' => false]));
        $cases['search'] = $this->statements(fn() => $this->rows($first + ['search' => 'more', 'facets' => true]));
        $cases['summary'] = $this->statements(function (): void {
            hub_kpis::bump_report_generation();
            reportengine::summary($this->report(), []);
        });
        $cases['summarycached'] = $this->statements(fn() => reportengine::summary($this->report(), []));
        return $cases;
    }

    /**
     * The statements a call sends do not grow with the rows, stay under their bounds, and drop once the criteria are
     * known: a second call, a page turn and a sort send the page's statements and none of the counts.
     *
     * @return void
     */
    public function test_the_statements_are_bounded(): void {
        $made = 0;
        $grow = function (int $workers) use (&$made): void {
            for ($i = 0; $i < $workers; $i++, $made++) {
                $worker = $this->worker($this->templateid, ['firstname' => 'More' . $made]);
                $this->status_row((int) $worker->id, $this->c[$made % 3], status::STATUS_DUE, 20261201, 'diploma');
            }
        };
        $bounds = [];
        foreach (['manager' => null, 'reader' => $this->reader] as $name => $user) {
            $this->setAdminUser();
            $grow(9);
            $user === null ? $this->setAdminUser() : $this->setUser($user);
            $small = $this->statement_cases();
            $this->setAdminUser();
            $grow(12);
            $user === null ? $this->setAdminUser() : $this->setUser($user);
            $large = $this->statement_cases();
            $this->assertSame($small, $large, $name);

            // A known total and known facets: the same criteria again send no count and no facet statement.
            $this->assertLessThan($large['rows'], $large['rowsagain'], $name);
            $this->assertSame($large['rowsagain'], $large['nextpage'], $name);
            $this->assertSame($large['nextpage'], $large['sort'], $name);
            // Cold without facets: the count and the hidden workers more than a page turn.
            $this->assertSame($large['nextpage'] + 2, $large['rowsnofacets'], $name);
            // A search is counted every time.
            $this->assertGreaterThan($large['rowsagain'], $large['search'], $name);
            $this->assertLessThan($large['summary'], $large['summarycached'], $name);
            $bounds[$name] = $large;
        }
        // The counts a manager's calls send: one statement more is a decision to make, not a drift. Every build reads
        // the template's framework contexts and competencies; every call without a search reads the template's
        // signature for the cache key; the summary reads the names of its listed competencies on every call.
        $manager = $bounds['manager'];
        $this->assertLessThanOrEqual(16, $manager['rows'], json_encode($manager));
        $this->assertLessThanOrEqual(13, $manager['rowsagain'], json_encode($manager));
        $this->assertLessThanOrEqual(15, $manager['rowsnofacets'], json_encode($manager));
        $this->assertLessThanOrEqual(15, $manager['search'], json_encode($manager));
        $this->assertLessThanOrEqual(15, $manager['summary'], json_encode($manager));
        $this->assertLessThanOrEqual(8, $manager['summarycached'], json_encode($manager));
        // A reader whose scope is worked out from role assignments pays for it in each build, the same in every case.
        $this->assertSame(
            array_map(fn(int $count): int => $count - $manager['rowsagain'], $manager),
            array_map(fn(int $count): int => $count - $bounds['reader']['rowsagain'], $bounds['reader'])
        );
    }

    /**
     * The rows' total and facet counts are kept per reader: another reader, or the same reader under other rights, is
     * counted afresh, never answered another scope's numbers. The control: the same reader again is served from the
     * cache.
     *
     * @return void
     */
    public function test_cached_counts_are_never_served_to_another_scope(): void {
        $status = fn(array $answer): array => $this->counts($answer, 'validity:status');
        hub_kpis::bump_generation();
        $admin = [];
        $cold = $this->statements(function () use (&$admin): void {
            $admin = $this->rows(['facets' => true]);
        });
        $this->assertSame(9, $admin['total']);
        $this->assertLessThan($cold, $this->statements(fn() => $this->rows(['facets' => true])), 'The control: the cache.');

        $this->setUser($this->mentor);
        $mentor = $this->rows(['facets' => true]);
        $this->assertSame(6, $mentor['total']);
        $this->assertSame(1, $status($mentor)[(string) status::EFFECTIVE_RESTRICTED]);

        // The same reader granted viewsensitive, with no generation moved: counted their new rights' numbers.
        $this->setUser($this->reader);
        $this->assertSame(1, $status($this->rows(['facets' => true]))[(string) status::EFFECTIVE_RESTRICTED]);
        $this->setAdminUser();
        $role = $this->getDataGenerator()->create_role();
        $system = \context_system::instance();
        assign_capability('local/evidencehub:viewsensitive', CAP_ALLOW, $role, $system->id, true);
        role_assign($role, $this->reader->id, $system->id);
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($this->reader);
        $generation = hub_kpis::report_generation();
        $seeing = $this->rows(['facets' => true]);
        $this->assertSame($generation, hub_kpis::report_generation());
        $this->assertArrayNotHasKey((string) status::EFFECTIVE_RESTRICTED, $status($seeing));
        $this->assertEquals($status($admin), $status($seeing));
    }

    /**
     * The cached total and facets follow every criterion that cuts the rows: the scope and the status filter (which
     * the summary's key leaves out) are both in the key. The page, its size and the sort are not criteria.
     *
     * @return void
     */
    public function test_cached_counts_follow_the_criteria(): void {
        $this->assertSame(9, $this->rows(['scope' => reportengine::SCOPE_ALL])['total']);
        $this->assertSame(4, $this->rows(['scope' => reportengine::SCOPE_ATTENTION])['total']);
        $this->assertSame(9, $this->rows(['scope' => reportengine::SCOPE_ALL])['total']);

        $this->store($this->choose('validity:status', (string) status::STATUS_VALID));
        $this->assertSame(5, $this->rows()['total']);
        $this->store($this->choose('validity:status', (string) status::STATUS_EXPIRED));
        $answer = $this->rows();
        $this->assertSame(1, $answer['total']);
        $this->assertSame([(string) $this->c[0] => 1], $this->counts($answer, 'competency:competency'));

        // A page turn and a sort answer the same total and facets as the first page.
        $this->store([]);
        $first = $this->rows(['perpage' => 30]);
        $turned = $this->rows(['perpage' => 50, 'page' => 0, 'sort' => 'status', 'direction' => 'desc']);
        $this->assertSame([9, $first['filters']], [$turned['total'], $turned['filters']]);
    }

    /**
     * A search is counted on every call, total and facets: free text is never a key.
     *
     * @return void
     */
    public function test_a_search_is_never_cached(): void {
        $souza = $this->rows(['search' => 'souza']);
        $lima = $this->rows(['search' => 'lima']);
        $this->assertEquals([
            (string) status::EFFECTIVE_MISSING => 1,
            (string) status::STATUS_EXPIRED => 1,
            (string) status::STATUS_VALID => 1,
        ], $this->counts($souza, 'validity:status'));
        $this->assertEquals([
            (string) status::STATUS_VALID => 1,
            (string) status::STATUS_DUE => 1,
            (string) status::STATUS_CRITICAL => 1,
        ], $this->counts($lima, 'validity:status'));
        $this->assertSame(
            $this->statements(fn() => $this->rows(['search' => 'souza'])),
            $this->statements(fn() => $this->rows(['search' => 'souza']))
        );
        // The control: without a search the second call is served from the cache.
        $first = $this->statements(fn() => $this->rows());
        $this->assertLessThan($first, $this->statements(fn() => $this->rows()));
    }

    /**
     * A status written retires the cached counts, through the report generation.
     *
     * @return void
     */
    public function test_a_status_change_retires_the_cached_counts(): void {
        $missing = (string) status::EFFECTIVE_MISSING;
        $this->assertSame(1, $this->counts($this->rows(), 'validity:status')[$missing]);
        $this->status_row($this->id('ana'), $this->c[2], status::STATUS_VALID, 20300101);
        $answer = $this->rows();
        $this->assertArrayNotHasKey($missing, $this->counts($answer, 'validity:status'));
        $this->assertSame(6, $this->counts($answer, 'validity:status')[(string) status::STATUS_VALID]);
    }

    /**
     * The cached facet counts follow today: on 2026-12-05 only Bia's first pair is valid within 30 days.
     *
     * @return void
     */
    public function test_cached_counts_follow_today(): void {
        $this->assertSame(3, $this->counts($this->rows(), 'validity:validuntil')['within30']);
        $this->mock_clock_with_frozen(self::NOW + 12 * DAYSECS);
        $this->assertSame(1, $this->counts($this->rows(), 'validity:validuntil')['within30']);
    }

    /**
     * A stored cohort value applies only while the report offers the cohort: hidden from a reader without
     * moodle/cohort:view, it stops applying, and the cached total follows with no generation moved.
     *
     * @return void
     */
    public function test_a_cached_total_follows_the_cohorts_offered(): void {
        $this->setUser($this->reader);
        $this->store($this->choose('user:cohort', (string) $this->cohort->id));
        $this->assertSame(6, $this->rows()['total']);
        $this->setAdminUser();
        $this->cohort->visible = 0;
        cohort_update_cohort($this->cohort);
        $this->setUser($this->reader);
        $generation = hub_kpis::report_generation();
        $answer = $this->rows();
        $this->assertSame($generation, hub_kpis::report_generation());
        $this->assertSame(9, $answer['total']);
        $this->assertFalse(array_column($answer['filters'], null, 'name')['user:cohort']['applied']);
    }

    /**
     * An empty page counts again: rows gone in a way the generation does not follow (plans deleted with no event)
     * leave a stale cached total and stale facets, which the empty page replaces, in the response too.
     *
     * @return void
     */
    public function test_an_empty_page_counts_again(): void {
        global $DB;
        $this->assertSame(9, $this->rows()['total']);

        // Ana's plan is gone with no event: the cached total and facets still count her. A page past the last is
        // empty, counts again, and the response carries the fresh facets, not the cached ones.
        $DB->delete_records('competency_plan', ['templateid' => $this->templateid, 'userid' => $this->id('ana')]);
        $answer = $this->rows(['page' => 1, 'perpage' => 30]);
        $this->assertSame([6, 0], [$answer['total'], $answer['page']]);
        $this->assertSame(array_sum($this->counts($answer, 'validity:status')), $answer['total']);
        hub_kpis::bump_generation();
        $this->assertEquals($this->rows(['perpage' => 30])['filters'], $answer['filters']);

        $DB->delete_records('competency_plan', ['templateid' => $this->templateid]);
        $answer = $this->rows();
        $this->assertSame([0, 0, []], [$answer['total'], $answer['page'], $answer['rows']]);
    }

    /**
     * The LEFT joins to the competency and its framework list the same pairs as inner joins: the total is the count
     * of an inner-joined reference before and after core is asked to delete a template's competency and its
     * framework, which it refuses; every row carries its competency's name.
     *
     * @return void
     */
    public function test_the_competency_joins_list_the_same_pairs(): void {
        global $DB;
        $reference = fn(): int => (int) $DB->count_records_sql(
            "SELECT COUNT(1)
               FROM {competency_plan} p
               JOIN {user} u ON u.id = p.userid AND u.deleted = 0 AND u.suspended = 0
               JOIN {competency_templatecomp} tc ON tc.templateid = p.templateid
               JOIN {competency} c ON c.id = tc.competencyid
               JOIN {competency_framework} f ON f.id = c.competencyframeworkid
              WHERE p.templateid = :templateid AND p.status = :active",
            ['templateid' => $this->templateid, 'active' => \core_competency\plan::STATUS_ACTIVE]
        );
        $this->assertSame([9, 9], [$reference(), $this->rows()['total']]);

        $frameworkid = (int) $DB->get_field('competency', 'competencyframeworkid', ['id' => $this->c[0]], MUST_EXIST);
        $this->assertFalse(\core_competency\api::delete_competency($this->c[0]));
        $this->assertFalse(\core_competency\api::delete_framework($frameworkid));
        hub_kpis::bump_generation();
        $answer = $this->rows();
        $this->assertSame([9, 9], [$reference(), $answer['total']]);
        foreach ($answer['rows'] as $row) {
            $cells = array_column($row['cells'], 'html', 'key');
            $this->assertNotSame('', $cells['competency']);
        }
    }

    /**
     * A reader who holds viewsensitive over every worker reads the status table once: nothing is restricted to them,
     * so the report joins no second status row; a reader without it keeps the join.
     *
     * @return void
     */
    public function test_a_reader_of_every_sensitive_pair_reads_the_status_once(): void {
        $joined = fn(): int => substr_count(
            headless_table::create((int) $this->report()->get_report_persistent()->get('id'), [])->sql->from,
            '{' . status_repository::TABLE . '}'
        );
        $this->assertTrue(hub_scope::for_user()->sees_every_sensitive());
        $this->assertSame(1, $joined());
        $this->setUser($this->reader);
        $this->assertFalse(hub_scope::for_user()->sees_every_sensitive());
        $this->assertSame(2, $joined());
    }

    /**
     * A technical policy of a competency whose alerts leave the 30-day window of the summary unaltered by a status.
     *
     * @param int $competencyid The competency.
     * @return void
     */
    private function short_alerts(int $competencyid): void {
        global $DB;
        policy::save((object) [
            'frameworkid' => (int) $DB->get_field('competency', 'competencyframeworkid', ['id' => $competencyid], MUST_EXIST),
            'competencyid' => $competencyid,
            'kind' => policy::KIND_TECHNICAL,
            'requireverified' => 0,
            'requireproficiency' => 0,
            'minhours' => null,
            'alert1' => 10,
            'alert2' => 5,
            'gracedays' => 0,
            'cyclemonths' => 0,
            'onexpiry' => policy::ONEXPIRY_LOG,
        ]);
    }

    /**
     * A status row whose expiry moves into the 30-day window with the same status, written by the real writer,
     * changes the cached "expiring" figure. The control: before the writer runs, the figure is served from the
     * cache, stale.
     *
     * @return void
     */
    public function test_an_expiry_into_the_window_changes_the_cached_summary(): void {
        global $DB;
        $this->short_alerts($this->c[0]);
        $worker = $this->worker($this->templateid);
        $core = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $evidence = $core->create_user_evidence(['userid' => $worker->id]);
        $core->create_user_evidence_competency(['userevidenceid' => $evidence->get('id'), 'competencyid' => $this->c[0]]);
        $meta = $this->getDataGenerator()->get_plugin_generator('local_evidencehub')->create_meta([
            'userid' => $worker->id,
            'userevidenceid' => $evidence->get('id'),
            'issuedate' => 20260101,
            'expirydate' => 20300101,
        ]);
        status_repository::recompute((int) $worker->id, $this->c[0]);
        $row = fn() => $DB->get_record(status_repository::TABLE, ['userid' => $worker->id, 'competencyid' => $this->c[0]]);
        $this->assertSame(status::STATUS_VALID, (int) $row()->status);
        $first = reportengine::summary($this->report(), []);

        $DB->set_field(details::TABLE, 'expirydate', 20261215, ['id' => $meta->id]);
        // The control: nothing has told the cache, so the second call is its answer.
        $stale = 0;
        $cached = $this->statements(function () use (&$stale): void {
            $stale = reportengine::summary($this->report(), [])['expiring'];
        });
        $this->assertSame($first['expiring'], $stale);
        $this->assertLessThan($this->statements(function (): void {
            hub_kpis::bump_generation();
            reportengine::summary($this->report(), []);
        }), $cached);

        $kpis = hub_kpis::generation();
        status_repository::recompute((int) $worker->id, $this->c[0]);
        $this->assertSame([status::STATUS_VALID, 20261215], [(int) $row()->status, (int) $row()->effectiveexpiry]);
        $this->assertSame($kpis, hub_kpis::generation());
        $this->assertSame($first['expiring'] + 1, reportengine::summary($this->report(), [])['expiring']);
    }

    /**
     * A change of the deciding evidence's type under a stored type filter changes the cached figures: the event
     * of the details retires them, the row itself being unchanged. The control: until the event, the cache answers.
     *
     * @return void
     */
    public function test_a_change_of_the_deciding_type_changes_the_cached_summary(): void {
        global $DB;
        $diploma = (string) $DB->get_field('local_evidencehub_type', 'id', ['shortname' => 'diploma']);
        $certificate = (int) $DB->get_field('local_evidencehub_type', 'id', ['shortname' => 'nrcertificate']);
        $this->store($this->choose('evidence_details:type', $diploma));
        $first = reportengine::summary($this->report(), []);
        $this->assertSame([3, 3], [$first['pairs'], $first['workers']]);

        $metaid = (int) $DB->get_field(status_repository::TABLE, 'metaid', [
            'userid' => $this->id('ana'),
            'competencyid' => $this->c[1],
        ]);
        $DB->set_field(details::TABLE, 'typeid', $certificate, ['id' => $metaid]);
        $this->assertSame($first, reportengine::summary($this->report(), []), 'The control: the cache answers.');

        evidence_details_updated::create_from_meta($DB->get_record(details::TABLE, ['id' => $metaid], '*', MUST_EXIST))->trigger();
        $this->assertSame(2, reportengine::summary($this->report(), [])['pairs']);
    }

    /**
     * The cached summary carries codes and counts, so a reader gets the status labels of the language in force on
     * a call the cache serves.
     *
     * @return void
     */
    public function test_the_cached_summary_speaks_the_language_in_force(): void {
        global $CFG;
        // A Brazilian Portuguese language pack, so the plugin's own lang/pt_br strings load.
        $folder = $CFG->dataroot . '/lang/pt_br';
        check_dir_exists($folder);
        file_put_contents($folder . '/langconfig.php', "<?php\n\$string['thislanguage'] = 'Português - Brasil';\n");
        get_string_manager()->reset_caches(true);
        $label = static fn(array $summary): string => array_column(
            $summary['distribution'],
            'label',
            'status'
        )[status::STATUS_VALID];

        hub_kpis::bump_generation();
        $english = [];
        $first = $this->statements(function () use (&$english): void {
            $english = reportengine::summary($this->report(), []);
        });
        $this->assertSame('Compliant', $label($english));
        $previous = force_current_language('pt_br');
        try {
            $portuguese = [];
            $cached = $this->statements(function () use (&$portuguese): void {
                $portuguese = reportengine::summary($this->report(), []);
            });
        } finally {
            force_current_language($previous);
        }
        $this->assertLessThan($first, $cached, 'The call was not served from the cache.');
        $this->assertSame('Conforme', $label($portuguese));
        $this->assertSame($english['pairs'], $portuguese['pairs']);
        $this->assertSame('en', current_language());
    }

    /**
     * A renamed competency shows its new name on a call the cache serves: the cache holds the competency's id.
     *
     * @return void
     */
    public function test_a_renamed_competency_shows_its_new_name_on_a_cached_call(): void {
        global $DB;
        $first = reportengine::summary($this->report(), []);
        $this->assertSame(['C3', 'C1', 'C2'], array_column($first['worst'], 'name'));
        $DB->set_field('competency', 'shortname', 'Renamed', ['id' => $this->c[2]]);
        $second = reportengine::summary($this->report(), []);
        $this->assertSame(['Renamed', 'C1', 'C2'], array_column($second['worst'], 'name'));
        $this->assertSame(array_column($first['worst'], 'count'), array_column($second['worst'], 'count'));
    }

    /**
     * The framework column is offered only to a reader who may read every framework of the template, as the
     * drawer is; the control: the capability in the framework's context offers it.
     *
     * @return void
     */
    public function test_the_framework_column_follows_the_competency_capability(): void {
        global $DB;
        $this->setUser($this->reader);
        $this->assertArrayHasKey('framework', $this->report()->column_catalogue());

        // Every authenticated user reads competencies by default: this site withholds it.
        $userrole = (int) $DB->get_field('role', 'id', ['shortname' => 'user'], MUST_EXIST);
        unassign_capability('moodle/competency:competencyview', $userrole);
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($this->reader);
        $catalogue = $this->report()->column_catalogue();
        $this->assertArrayNotHasKey('framework', $catalogue);
        $this->assertArrayHasKey('competencyidnumber', $catalogue);
        $this->assertNotContains('framework', array_column($this->rows()['columns'], 'key'));

        $this->setUser($this->viewer(array_merge(self::READER, ['moodle/competency:competencyview'])));
        $this->assertArrayHasKey('framework', $this->report()->column_catalogue());
        $this->setAdminUser();
        $this->assertArrayHasKey('framework', $this->report()->column_catalogue());
    }

    /**
     * A stored preference naming the framework column lists none for a reader without the capability, and the
     * same preference lists it for one who holds it.
     *
     * @return void
     */
    public function test_a_preference_naming_framework_lists_no_column_without_the_capability(): void {
        global $DB;
        $userrole = (int) $DB->get_field('role', 'id', ['shortname' => 'user'], MUST_EXIST);
        unassign_capability('moodle/competency:competencyview', $userrole);
        accesslib_clear_all_caches_for_unit_testing();
        $keys = function (\stdClass $user): array {
            $this->setUser($user);
            reportpreference::write_grid(['status', 'framework'], [], null);
            return array_column($this->rows()['rows'][0]['cells'], 'key');
        };
        $blind = $keys($this->reader);
        $this->assertContains('status', $blind);
        $this->assertNotContains('framework', $blind);
        $this->assertContains('framework', $keys($this->viewer(array_merge(self::READER, ['moodle/competency:competencyview']))));
    }

    /**
     * What a reader without viewsensitive is answered does not depend on the real values of a restricted pair:
     * the rows (with their facets) and the summary are the same structures before and after the pair's status,
     * expiry, deciding evidence's type and verification and evidence count change; the control: a reader with
     * viewsensitive is answered differently.
     *
     * @return void
     */
    public function test_a_restricted_pair_does_not_reveal_its_changes(): void {
        global $DB;
        $this->preventResetByRollback();
        $sink = $this->redirectMessages();
        $seeing = $this->viewer(array_merge(self::READER, ['local/evidencehub:viewsensitive']));
        $everything = ['status', 'expiry', 'count', 'type', 'issuer', 'competencyidnumber', 'framework', 'issuedate',
            'expirydate', 'verification', 'changed'];
        $snapshot = function (\stdClass $user) use ($everything): array {
            $this->setUser($user);
            reportpreference::write_grid($everything, [], true);
            return [
                'rows' => $this->rows(['facets' => true]),
                'summary' => reportengine::summary($this->report(), []),
            ];
        };
        $blindbefore = $snapshot($this->reader);
        $seeingbefore = $snapshot($seeing);

        // Real values of Bia's first pair change, through the real writer, and it stays restricted.
        $this->setAdminUser();
        $metaid = (int) $DB->get_field(status_repository::TABLE, 'metaid', [
            'userid' => $this->id('bia'),
            'competencyid' => $this->c[0],
        ]);
        $evidenceid = (int) $DB->get_field(details::TABLE, 'userevidenceid', ['id' => $metaid]);
        $core = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $core->create_user_evidence_competency(['userevidenceid' => $evidenceid, 'competencyid' => $this->c[0]]);
        $other = $DB->get_record('local_evidencehub_type', ['shortname' => 'aso'], '*', MUST_EXIST);
        unset($other->id);
        $other->shortname = 'asotwo';
        $other->name = 'Second sensitive type';
        $typeid = $DB->insert_record('local_evidencehub_type', $other);
        $DB->update_record(details::TABLE, (object) [
            'id' => $metaid,
            'typeid' => $typeid,
            'issuer' => 'Another issuer',
            'issuedate' => 20180101,
            'expirydate' => 20200101,
            'verifystatus' => meta::VERIFY_PENDING,
        ]);
        $result = status_repository::recompute($this->id('bia'), $this->c[0]);
        $this->assertSame(status::STATUS_EXPIRED, $result->status);
        $this->assertSame([1, 20200101], [$result->evidencecount, $result->effectiveexpiry]);

        // Core numbers every action link it renders: the one thing allowed to differ between two requests.
        $plain = static fn(array $answer): string => preg_replace('/action_link[0-9a-f]+/', 'action_link', json_encode($answer));
        $this->assertSame($plain($blindbefore), $plain($snapshot($this->reader)));
        $this->assertNotSame($plain($seeingbefore), $plain($snapshot($seeing)));
        $sink->close();
    }

    /**
     * A report parameter that is not a scalar never builds a report, whoever asks: the parameters are read while the
     * report is initialised, before core's access check, so the reader who may view and the one who may not meet
     * the same refusal of an array where a scalar is read.
     *
     * @return void
     */
    public function test_a_non_scalar_report_parameter_builds_no_report(): void {
        $build = function (string $name): string {
            manager::reset_caches();
            try {
                hub_pairs::for_template(\context_system::instance(), $this->templateid, [$name => ['a']]);
                return 'built';
            } catch (\Throwable $e) {
                return get_class($e);
            }
        };
        foreach (['scope', 'search', 'suspended'] as $name) {
            $this->setAdminUser();
            $viewer = $build($name);
            $this->setUser($this->viewer([]));
            $blind = $build($name);
            $this->assertSame(\core\exception\coding_exception::class, $viewer, $name);
            $this->assertSame($viewer, $blind, $name);
        }
    }

    /**
     * A page far beyond the last answers the last page, however large the request.
     *
     * @return void
     */
    public function test_a_huge_page_answers_the_last_page(): void {
        $last = $this->rows(['perpage' => 30, 'page' => 0]);
        $this->assertSame(9, $last['total']);
        foreach ([PHP_INT_MAX, reportengine::MAX_PAGE + 1] as $page) {
            $answer = $this->rows(['perpage' => 30, 'page' => $page]);
            $this->assertSame(0, $answer['page']);
            $this->assertSame($last['total'], $answer['total']);
            $this->assertCount($last['total'], $answer['rows']);
        }
    }

    /**
     * A stored name filter with a value is free text like a search: neither the rows' total and facets nor the
     * summary are cached under it, so each value a reader types mints no entry. The control: with no value stored
     * the second call is served from the cache.
     *
     * @return void
     */
    public function test_a_stored_name_filter_is_never_cached(): void {
        $this->store(['user:fullname_operator' => text::CONTAINS, 'user:fullname_value' => 'souza']);
        $this->assertSame(3, $this->rows()['total']);
        $rows = [$this->statements(fn() => $this->rows()), $this->statements(fn() => $this->rows())];
        $summary = [
            $this->statements(fn() => reportengine::summary($this->report(), [])),
            $this->statements(fn() => reportengine::summary($this->report(), [])),
        ];
        $this->assertSame($rows[0], $rows[1]);
        $this->assertSame($summary[0], $summary[1]);
        $this->assertSame(3, reportengine::summary($this->report(), [])['pairs']);

        $this->store([]);
        $this->rows();
        reportengine::summary($this->report(), []);
        $this->assertLessThan($rows[0], $this->statements(fn() => $this->rows()));
        $this->assertLessThan($summary[0], $this->statements(fn() => reportengine::summary($this->report(), [])));
    }

    /**
     * Two workers of the template with a sensitive pair each, and a reader who is a worker with a sensitive pair of
     * their own, for the readers whose viewsensitive differs from one worker to the next.
     *
     * @param array $capabilities The reader's capabilities at the system level.
     * @return \stdClass The reader.
     */
    private function sensitive_world(array $capabilities): \stdClass {
        $this->setAdminUser();
        $this->w['eve'] = $this->worker($this->templateid, ['firstname' => 'Eve', 'lastname' => 'Alves']);
        $this->sensitive_row($this->id('eve'), $this->c[0]);
        $this->sensitive_row($this->id('ana'), $this->c[2]);
        $reader = $this->viewer($capabilities);
        $this->getDataGenerator()->get_plugin_generator('local_evidencehub')->create_template_plan([
            'userid' => $reader->id,
            'templateid' => $this->templateid,
        ]);
        $this->sensitive_row((int) $reader->id, $this->c[0]);
        $this->w['reader'] = $reader;
        return $reader;
    }

    /**
     * Assign a role holding one capability in a worker's user context, as the only place it applies.
     *
     * @param \stdClass $reader The reader.
     * @param string $worker The worker's key.
     * @param int $permission CAP_ALLOW or CAP_PROHIBIT.
     * @return void
     */
    private function role_over(\stdClass $reader, string $worker, int $permission): void {
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('local/evidencehub:viewsensitive', $permission, $roleid, \context_system::instance()->id, true);
        set_role_contextlevels($roleid, [CONTEXT_USER]);
        role_assign($roleid, $reader->id, \context_user::instance($this->id($worker))->id);
        accesslib_clear_all_caches_for_unit_testing();
    }

    /**
     * What the report shows a reader of the sensitive pairs, row by row, in the facet and in the summary.
     *
     * @param \stdClass $reader The reader.
     * @param string $form A pattern the reader's viewsensitive fragment must match, so the fixture cannot degrade to
     *     "everyone" or "no one".
     * @param array $whole The sensitive pairs read whole, as [worker key, competency index].
     * @param array $restricted The sensitive pairs read restricted.
     * @return void
     */
    private function assert_sensitive_pairs(\stdClass $reader, string $form, array $whole, array $restricted): void {
        $this->setUser($reader);
        $scope = hub_scope::for_user();
        $this->assertFalse($scope->sees_every_sensitive());
        $this->assertMatchesRegularExpression($form, $scope->sensitivesql);
        $this->assertNotSame([], $scope->sensitiveparams);

        reportpreference::write_grid(['status', 'type', 'issuer', 'issuedate', 'expirydate'], [], true);
        $answer = $this->rows(['facets' => true]);
        $rows = [];
        foreach ($answer['rows'] as $row) {
            $rows[$this->pair($row['userid'], $row['competencyid'])] = $row;
        }
        foreach ($restricted as [$worker, $index]) {
            $row = $rows[$this->pair($this->id($worker), $this->c[$index])];
            $this->assertSame([status::EFFECTIVE_RESTRICTED, true], [$row['status'], $row['restricted']], $worker);
            $cells = array_column($row['cells'], 'html', 'key');
            foreach (['type', 'issuer', 'issuedate', 'expirydate'] as $key) {
                $this->assertSame('', $cells[$key], "{$worker}: {$key}");
            }
        }
        foreach ($whole as [$worker, $index]) {
            $row = $rows[$this->pair($this->id($worker), $this->c[$index])];
            $this->assertSame([status::STATUS_VALID, false], [$row['status'], $row['restricted']], $worker);
            $cells = array_column($row['cells'], 'html', 'key');
            $this->assertNotSame('', $cells['type'], $worker);
            $this->assertNotSame('', $cells['issuer'], $worker);
        }
        $counted = $this->counts($answer, 'validity:status')[(string) status::EFFECTIVE_RESTRICTED] ?? 0;
        $this->assertSame(count($restricted), $counted);
        $summary = reportengine::summary($this->report(), []);
        $distribution = array_column($summary['distribution'], 'count', 'status');
        $this->assertSame(count($restricted), $distribution[status::EFFECTIVE_RESTRICTED]);
    }

    /**
     * A reader who holds viewsensitive at the system but is prohibited over one worker reads that worker's sensitive
     * pair as restricted and the others whole, own pair included: the report keeps the restricted rule for any
     * fragment but core's "everyone".
     *
     * @return void
     */
    public function test_a_reader_prohibited_over_one_worker_reads_that_workers_pair_restricted(): void {
        $reader = $this->sensitive_world(array_merge(self::READER, ['local/evidencehub:viewsensitive']));
        $this->role_over($reader, 'bia', CAP_PROHIBIT);
        $this->assert_sensitive_pairs(
            $reader,
            '/^(<>|NOT IN)/',
            [['ana', 2], ['eve', 0], ['reader', 0]],
            [['bia', 0]]
        );
    }

    /**
     * A reader who holds viewsensitive in some workers' user contexts only reads those workers' sensitive pairs
     * whole and the others restricted; their own pair is whole either way.
     *
     * @return void
     */
    public function test_a_reader_allowed_over_some_workers_reads_the_others_restricted(): void {
        $reader = $this->sensitive_world(self::READER);
        $this->role_over($reader, 'ana', CAP_ALLOW);
        $this->assert_sensitive_pairs(
            $reader,
            '/^(=|IN)\s/',
            [['ana', 2], ['reader', 0]],
            [['bia', 0], ['eve', 0]]
        );
    }

    /**
     * A page of rows with its total and facets as the reader gets them, under the scope "all".
     *
     * @return array
     */
    private function listed(): array {
        return $this->rows(['perpage' => 100]);
    }

    /**
     * Retiring the rows' cached total and facets: the control first, a change no event follows is served stale from
     * the cache, then the event, which is answered fresh.
     *
     * @param int $stale The total the cache holds.
     * @param callable $silent Changes the base set with no event.
     * @param callable $event Changes it through the call that fires the event.
     * @param int $fresh The total once both are made.
     * @return void
     */
    private function assert_event_retires_the_rows(int $stale, callable $silent, callable $event, int $fresh): void {
        $cold = $this->statements(fn() => $this->listed());
        $this->assertSame($stale, $this->listed()['total']);
        $this->assertLessThan($cold, $this->statements(fn() => $this->listed()), 'The control: the cache.');
        $silent();
        $this->assertSame($stale, $this->listed()['total'], 'No event: the cached total stands.');
        $event();
        $answer = $this->listed();
        $this->assertSame($fresh, $answer['total']);
        $this->assertSame($fresh, array_sum($this->counts($answer, 'validity:status')));
    }

    /**
     * A plan created from the template retires the cached total and facets.
     *
     * @return void
     */
    public function test_a_plan_created_retires_the_cached_rows(): void {
        $generator = $this->getDataGenerator();
        $this->assert_event_retires_the_rows(
            9,
            fn() => $generator->get_plugin_generator('local_evidencehub')->create_template_plan([
                'userid' => $generator->create_user()->id,
                'templateid' => $this->templateid,
            ]),
            fn() => \core_competency\api::create_plan_from_template($this->templateid, (int) $generator->create_user()->id),
            15
        );
    }

    /**
     * A cohort member added under a stored cohort filter retires the cached total and facets.
     *
     * @return void
     */
    public function test_a_cohort_member_added_retires_the_cached_rows(): void {
        global $DB;
        $this->store($this->choose('user:cohort', (string) $this->cohort->id));
        $this->assert_event_retires_the_rows(
            6,
            fn() => $DB->insert_record('cohort_members', (object) [
                'cohortid' => $this->cohort->id,
                'userid' => $this->id('dan'),
                'timeadded' => time(),
            ]),
            fn() => cohort_add_member($this->cohort->id, $this->id('cid')),
            9
        );
    }

    /**
     * A worker deleted retires the cached total and facets.
     *
     * @return void
     */
    public function test_a_worker_deleted_retires_the_cached_rows(): void {
        global $DB;
        $this->assert_event_retires_the_rows(
            9,
            fn() => $DB->set_field('user', 'deleted', 1, ['id' => $this->id('ana')]),
            fn() => delete_user($this->w['bia']),
            3
        );
    }

    /**
     * A template competency whose row is gone (a damaged database: core refuses to delete one a template uses) does
     * not abort the page: its pairs keep their rows without a name or a drawer, the others are answered as before,
     * and the summary lists as many competencies as the template has left.
     *
     * @return void
     */
    public function test_a_missing_competency_row_keeps_the_page(): void {
        global $DB;
        $competencies = $this->competencies(7);
        $templateid = $this->template($competencies);
        $worker = $this->worker($templateid);
        foreach ($competencies as $competencyid) {
            $this->status_row((int) $worker->id, $competencyid, status::STATUS_EXPIRED, 20250101);
        }
        $DB->delete_records('competency', ['id' => $competencies[0]]);
        manager::reset_caches();
        $report = reportengine::report(\context_system::instance(), $templateid);

        $answer = reportengine::rows($report, ['scope' => reportengine::SCOPE_ALL, 'perpage' => 100]);
        $this->assertSame(7, $answer['total']);
        $rows = array_column($answer['rows'], null, 'competencyid');
        $this->assertCount(7, $rows);
        $this->assertFalse($rows[$competencies[0]]['inspect']);
        $this->assertStringEndsWith(': ', $rows[$competencies[0]]['drawertitle']);
        $this->assertTrue($rows[$competencies[1]]['inspect']);
        $this->assertStringEndsWith('C2', $rows[$competencies[1]]['drawertitle']);

        manager::reset_caches();
        $summary = reportengine::summary(reportengine::report(\context_system::instance(), $templateid), []);
        $this->assertSame(7, $summary['pairs']);
        $this->assertSame(array_slice($competencies, 1, 5), array_column($summary['worst'], 'competencyid'));
        $this->assertCount(reportengine::WORST, $summary['worst']);
    }
}
