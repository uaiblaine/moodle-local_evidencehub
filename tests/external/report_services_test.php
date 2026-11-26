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

namespace local_evidencehub\external;

use core_reportbuilder\local\filters\select;
use core_reportbuilder\manager;
use local_evidencehub\local\meta;
use local_evidencehub\local\reportengine;
use local_evidencehub\local\reportpreference;
use local_evidencehub\local\status;
use local_evidencehub\reportbuilder\local\systemreports\hub_pairs;
use local_evidencehub\tests\hub_scenario;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The pairs report's three web services, called as a client calls them, through call_external_function().
 *
 * What they add to the engine (tests/local/reportengine_test.php) is the door: the context and the template both
 * checked with one refusal, guests refused, the capability, the report's own can_view(), and the returns cleaned
 * against their declared allowlist.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(report_rows::class)]
#[CoversClass(report_summary::class)]
#[CoversClass(set_report_preference::class)]
#[CoversClass(preference_structure::class)]
#[CoversClass(reportengine::class)]
final class report_services_test extends \advanced_testcase {
    use hub_scenario;

    /** @var array The capabilities of a reader of every worker, sensitive evidence included. */
    private const array READER = [
        'local/evidencehub:view',
        'moodle/competency:templateview',
        'moodle/competency:userevidenceview',
    ];

    /** @var array The three functions, each with the arguments it needs beyond the context and the template. */
    private const array FUNCTIONS = [
        'local_evidencehub_report_rows' => [],
        'local_evidencehub_report_summary' => [],
        'local_evidencehub_set_report_preference' => ['columns' => ['status']],
    ];

    /** @var string The preference's writer. */
    private const string PREFERENCE = 'local_evidencehub_set_report_preference';

    /** @var int The template, offered on the site. */
    private int $templateid;

    /** @var array The competencies. */
    private array $competencies;

    /** @var \stdClass The worker whose pair is decided by a sensitive evidence. */
    private \stdClass $worker;

    #[\Override]
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        set_config('enabled', 1, 'core_competency');
        $this->setAdminUser();
        $this->competencies = $this->competencies(2);
        $this->templateid = $this->template($this->competencies);
        $this->worker = $this->worker($this->templateid, ['firstname' => 'Ana', 'lastname' => 'Souza']);
        $other = $this->worker($this->templateid, ['firstname' => 'Bia', 'lastname' => 'Lima']);
        // Written beside core's user API, which would clean the name: a stored name may still hold these.
        $DB->set_field('user', 'lastname', 'Lima & <b>', ['id' => $other->id]);
        $this->status_row((int) $other->id, $this->competencies[0], status::STATUS_DUE, 20261201, 'diploma');

        // The sensitive pair: every real value is distinctive, to be searched for in the response.
        $DB->set_field('local_evidencehub_type', 'name', 'Zircon permit', ['shortname' => 'aso']);
        $generator = $this->getDataGenerator()->get_plugin_generator('local_evidencehub');
        $meta = $generator->create_meta([
            'userid' => $this->worker->id,
            'typeid' => (int) $DB->get_field('local_evidencehub_type', 'id', ['shortname' => 'aso']),
            'issuer' => 'Quartzline issuer',
            'issuedate' => 20171113,
            'expirydate' => 20991231,
            'verifystatus' => meta::VERIFY_VERIFIED,
        ]);
        $generator->create_status([
            'userid' => $this->worker->id,
            'competencyid' => $this->competencies[0],
            'status' => status::STATUS_VALID,
            'effectiveexpiry' => 20991231,
            'evidencecount' => 4321,
            'metaid' => $meta->id,
        ]);
    }

    /**
     * Call a function as a client does, as a request of its own.
     *
     * @param string $function The function.
     * @param array $args Its arguments.
     * @return array The response: error, data or exception.
     */
    private function call(string $function, array $args): array {
        manager::reset_caches();
        $_POST['sesskey'] = sesskey();
        return \core_external\external_api::call_external_function($function, $args, true);
    }

    /**
     * The arguments of a function on the site's template.
     *
     * @param string $function The function.
     * @param array $args More arguments.
     * @return array
     */
    private function args(string $function, array $args = []): array {
        return $args + ['contextid' => \context_system::instance()->id, 'templateid' => $this->templateid]
            + self::FUNCTIONS[$function];
    }

    /**
     * A manager reads the rows, the summary and stores a preference; each answer has its declared shape.
     *
     * @return void
     */
    public function test_a_manager_reads_the_allowlisted_answers(): void {
        $rows = 'local_evidencehub_report_rows';
        $response = $this->call($rows, $this->args($rows, ['scope' => 'all']));
        $this->assertFalse($response['error'], json_encode($response['exception'] ?? null));
        $data = $response['data'];
        $this->assertSame(4, $data['total']);
        $this->assertSame(
            ['scope', 'search', 'sort', 'direction', 'page', 'perpage', 'total', 'hidden', 'candecide', 'columns', 'presets',
                'rows', 'counted', 'filters', 'preference'],
            array_keys($data)
        );
        $this->assertSame(
            ['userid', 'competencyid', 'status', 'restricted', 'evidenceurl', 'inspect', 'selectable', 'drawertitle',
                'inspectlabel', 'cells'],
            array_keys($data['rows'][0])
        );
        // A name with a bare "<" and an "&" survives the return cleaning, plain in the title, escaped in the cell.
        $titles = array_column($data['rows'], 'drawertitle');
        $this->assertContains('Bia Lima & <b>: C1', $titles, json_encode($titles));
        $html = implode('', array_column(array_merge(...array_column($data['rows'], 'cells')), 'html'));
        $this->assertStringContainsString('Bia Lima &amp; &lt;b&gt;', $html);
        $this->assertStringNotContainsString('<b>', $html);
        $this->assertTrue($data['counted']);
        $this->assertSame(['validity', 'evidence'], array_column($data['presets'], 'name'));
        $this->assertSame(
            ['usedefault' => true, 'columns' => [], 'widths' => [], 'suspended' => false],
            $data['preference']
        );
        // The template is remembered as the reader's last on the site.
        $this->assertSame($this->templateid, reportpreference::last_template(\context_system::instance()->id));

        $response = $this->call('local_evidencehub_report_summary', $this->args('local_evidencehub_report_summary'));
        $this->assertFalse($response['error'], json_encode($response['exception'] ?? null));
        $this->assertSame(
            ['scope', 'search', 'workers', 'ready', 'rate', 'pairs', 'attention', 'expiring', 'distribution', 'worst', 'hidden'],
            array_keys($response['data'])
        );
        $this->assertSame([2, 4], [$response['data']['workers'], $response['data']['pairs']]);

        $preference = 'local_evidencehub_set_report_preference';
        $response = $this->call($preference, $this->args($preference));
        $this->assertFalse($response['error'], json_encode($response['exception'] ?? null));
        $this->assertSame(
            ['usedefault' => false, 'columns' => ['status'], 'widths' => [], 'suspended' => false],
            $response['data']
        );
    }

    /**
     * Every service refuses a guest, a reader without view, a course context, a template not offered there (with
     * the same error as one that does not exist) and a template id of 0; the control: a manager is answered.
     *
     * @return void
     */
    public function test_each_service_refuses_who_the_report_refuses(): void {
        global $DB;
        $category = $this->getDataGenerator()->create_category();
        $catcontext = \context_coursecat::instance($category->id);
        $course = \context_course::instance($this->getDataGenerator()->create_course(['category' => $category->id])->id);
        $elsewhere = $this->template($this->competencies, $catcontext);
        $blind = $this->viewer(['moodle/competency:templateview', 'moodle/competency:userevidenceview']);
        $guestrole = (int) $DB->get_field('role', 'id', ['shortname' => 'guest'], MUST_EXIST);
        foreach (self::READER as $capability) {
            assign_capability($capability, CAP_ALLOW, $guestrole, \context_system::instance()->id, true);
        }
        accesslib_clear_all_caches_for_unit_testing();
        $missing = (int) $DB->get_field_sql('SELECT MAX(id) FROM {competency_template}') + 100;
        $reader = $this->viewer(self::READER, $catcontext);

        foreach (array_keys(self::FUNCTIONS) as $function) {
            $refusals = [
                'blind' => [$blind, []],
                'course context' => [null, ['contextid' => $course->id]],
                'zero template' => [null, ['templateid' => 0]],
                'no context' => [null, ['contextid' => 0]],
            ];
            foreach ($refusals as $case => [$user, $args]) {
                $user === null ? $this->setAdminUser() : $this->setUser($user);
                $response = $this->call($function, $this->args($function, $args));
                $this->assertTrue($response['error'], "{$function}: {$case}");
            }
            $this->setGuestUser();
            $this->assertTrue($this->call($function, $this->args($function))['error'], "{$function}: guest");

            // A template of another category and a template that does not exist: one error, the same one.
            $this->setUser($reader);
            $notoffered = $this->call($function, $this->args($function, ['contextid' => $catcontext->id]));
            $nonexistent = $this->call(
                $function,
                $this->args($function, ['contextid' => $catcontext->id, 'templateid' => $missing])
            );
            $this->assertTrue($notoffered['error'], "{$function}: not offered");
            $this->assertSame($notoffered['exception']->errorcode, $nonexistent['exception']->errorcode, $function);
            $this->assertSame($notoffered['exception']->message, $nonexistent['exception']->message, $function);
            // The control: the same reader on the category's own template.
            $own = $this->call($function, $this->args($function, ['contextid' => $catcontext->id, 'templateid' => $elsewhere]));
            $this->assertFalse($own['error'], "{$function}: own template");

            $this->setAdminUser();
            $this->assertFalse($this->call($function, $this->args($function))['error'], "{$function}: manager");
        }
        $this->assertNull(get_user_preferences(reportpreference::NAME, null, $blind->id));
    }

    /**
     * With competencies disabled every service refuses, the preference's writer included, which builds no report;
     * the control: enabled again, each answers.
     *
     * @return void
     */
    public function test_each_service_refuses_when_competencies_are_disabled(): void {
        set_config('enabled', 0, 'core_competency');
        foreach (array_keys(self::FUNCTIONS) as $function) {
            $this->assertTrue($this->call($function, $this->args($function))['error'], $function);
        }
        $this->assertNull(get_user_preferences(reportpreference::NAME));
        set_config('enabled', 1, 'core_competency');
        foreach (array_keys(self::FUNCTIONS) as $function) {
            $this->assertFalse($this->call($function, $this->args($function))['error'], $function);
        }
    }

    /**
     * The selection variant: a manager holding exportdossier may select every row; a reader with view alone may
     * select none, and the page says so.
     *
     * @return void
     */
    public function test_the_selection_follows_exportdossier(): void {
        $rows = 'local_evidencehub_report_rows';
        $data = $this->call($rows, $this->args($rows, ['scope' => 'all']))['data'];
        $this->assertTrue($data['candecide']);
        $this->assertCount(4, $data['rows']);
        $this->assertSame([true], array_values(array_unique(array_column($data['rows'], 'selectable'))));

        $this->setUser($this->viewer(self::READER));
        $response = $this->call($rows, $this->args($rows, ['scope' => 'all']));
        $this->assertFalse($response['error'], json_encode($response['exception'] ?? null));
        $this->assertFalse($response['data']['candecide']);
        $this->assertCount(4, $response['data']['rows']);
        $this->assertSame([false], array_values(array_unique(array_column($response['data']['rows'], 'selectable'))));

        // The control: the same rights and exportdossier.
        $this->setUser($this->viewer(array_merge(self::READER, ['local/evidencehub:exportdossier'])));
        $this->assertTrue($this->call($rows, $this->args($rows, ['scope' => 'all']))['data']['candecide']);
    }

    /**
     * Forged values fall back to what the engine allows: a page size, a sort, a direction, a scope and a page
     * outside their vocabularies.
     *
     * @return void
     */
    public function test_forged_request_values_fall_back(): void {
        $response = $this->call('local_evidencehub_report_rows', $this->args('local_evidencehub_report_rows', [
            'perpage' => 7,
            'sort' => 'DROP TABLE',
            'direction' => 'sideways',
            'scope' => 'everything',
            'page' => -4,
        ]));
        $this->assertFalse($response['error'], json_encode($response['exception'] ?? null));
        $data = $response['data'];
        $this->assertSame(
            [reportengine::DEFAULT_PERPAGE, '', '', reportengine::SCOPE_ATTENTION, 0],
            [$data['perpage'], $data['sort'], $data['direction'], $data['scope'], $data['page']]
        );
        $response = $this->call('local_evidencehub_report_summary', $this->args('local_evidencehub_report_summary', [
            'scope' => 'everything',
            'search' => 'x',
        ]));
        $this->assertSame([reportengine::SCOPE_ATTENTION, ''], [$response['data']['scope'], $response['data']['search']]);
    }

    /**
     * The preference is stored cleaned: unknown keys and a list of 300 entries are bounded, widths clamped, the flag
     * kept unless given, and "use the default" forgets the columns and widths; the rows read it back.
     *
     * @return void
     */
    public function test_the_preference_is_stored_cleaned(): void {
        $columns = array_merge(['changed', 'Bad Key', '<x>', 'nosuchcolumn'], array_fill(0, 300, 'status'));
        $response = $this->call(self::PREFERENCE, $this->args(self::PREFERENCE, [
            'columns' => $columns,
            'widths' => [
                ['key' => 'status', 'width' => 99999],
                ['key' => 'changed', 'width' => -5],
                ['key' => '<x>', 'width' => 100],
            ],
            'suspended' => 1,
        ]));
        $this->assertFalse($response['error'], json_encode($response['exception'] ?? null));
        $this->assertSame([
            'usedefault' => false,
            'columns' => ['changed', 'nosuchcolumn', 'status'],
            'widths' => [
                ['key' => 'status', 'width' => reportpreference::MAX_WIDTH],
                ['key' => 'changed', 'width' => reportpreference::MIN_WIDTH],
            ],
            'suspended' => true,
        ], $response['data']);

        // The rows show the catalogue's keys alone, in catalogue order.
        $rows = $this->call('local_evidencehub_report_rows', $this->args('local_evidencehub_report_rows'))['data'];
        $this->assertSame(
            ['worker', 'identity:email', 'competency', 'status', 'changed'],
            array_column($rows['rows'][0]['cells'], 'key')
        );

        // The flag is kept when not given, then "use the default" forgets the grid and keeps the flag.
        $response = $this->call(self::PREFERENCE, $this->args(self::PREFERENCE));
        $this->assertTrue($response['data']['suspended']);
        $response = $this->call(self::PREFERENCE, $this->args(self::PREFERENCE, [
            'usedefault' => true,
            'widths' => [['key' => 'status', 'width' => 200]],
        ]));
        $this->assertSame(['usedefault' => true, 'columns' => [], 'widths' => [], 'suspended' => true], $response['data']);
        $response = $this->call(self::PREFERENCE, $this->args(self::PREFERENCE, [
            'usedefault' => true,
            'suspended' => 0,
        ]));
        $this->assertFalse($response['data']['suspended']);
    }

    /**
     * A restricted pair's row and the whole answer carry none of its real values: type, issuer, dates, count or
     * verification. The control: a reader with viewsensitive gets them.
     *
     * @return void
     */
    public function test_a_restricted_pair_reveals_nothing(): void {
        $all = ['status', 'expiry', 'count', 'type', 'issuer', 'competencyidnumber', 'framework', 'issuedate', 'expirydate',
            'verification', 'changed'];
        $secrets = ['Zircon', 'Quartzline', '2099', '20991231', '2017', '20171113', '4321'];
        $answer = function () use ($all): string {
            reportpreference::write_grid($all, [], null);
            $rows = $this->call('local_evidencehub_report_rows', $this->args('local_evidencehub_report_rows', ['scope' => 'all']));
            $this->assertFalse($rows['error'], json_encode($rows['exception'] ?? null));
            $summary = $this->call('local_evidencehub_report_summary', $this->args('local_evidencehub_report_summary'));
            $this->assertFalse($summary['error'], json_encode($summary['exception'] ?? null));
            return json_encode([$rows['data'], $summary['data']]);
        };

        $this->setUser($this->viewer(self::READER));
        $json = $answer();
        foreach ($secrets as $secret) {
            $this->assertStringNotContainsString($secret, $json, $secret);
        }
        $this->assertStringNotContainsString(meta::verify_label(meta::VERIFY_VERIFIED), $json);
        $restricted = array_values(array_filter(
            json_decode($json, true)[0]['rows'],
            fn(array $row): bool => $row['userid'] === (int) $this->worker->id && $row['competencyid'] === $this->competencies[0]
        ))[0];
        $this->assertSame([status::EFFECTIVE_RESTRICTED, true], [$restricted['status'], $restricted['restricted']]);

        // The control: viewsensitive shows every real value.
        $this->setUser($this->viewer(array_merge(self::READER, ['local/evidencehub:viewsensitive'])));
        $json = $answer();
        foreach (['Zircon', 'Quartzline', '2099-12-31', '2017-11-13', '4321'] as $secret) {
            $this->assertStringContainsString($secret, $json, $secret);
        }
    }

    /**
     * The preference's writer checks the report's rule without building the report: the rows after it, in the
     * same request, show the new columns.
     *
     * @return void
     */
    public function test_the_preference_writer_does_not_build_the_report(): void {
        manager::reset_caches();
        $_POST['sesskey'] = sesskey();
        $write = \core_external\external_api::call_external_function(
            'local_evidencehub_set_report_preference',
            $this->args('local_evidencehub_set_report_preference', ['columns' => ['changed']]),
            true
        );
        $this->assertFalse($write['error']);
        $rows = \core_external\external_api::call_external_function(
            'local_evidencehub_report_rows',
            $this->args('local_evidencehub_report_rows'),
            true
        );
        $this->assertSame(
            ['worker', 'identity:email', 'competency', 'changed'],
            array_column($rows['data']['rows'][0]['cells'], 'key')
        );
        $this->assertTrue(hub_pairs::may_view(\context_system::instance(), $this->templateid));
        // The stored filters are core's: a status stored narrows the next rows.
        manager::reset_caches();
        hub_pairs::for_template(\context_system::instance(), $this->templateid)->set_filter_values([
            'validity:status_operator' => select::EQUAL_TO,
            'validity:status_value' => status::STATUS_DUE,
        ]);
        $rows = $this->call('local_evidencehub_report_rows', $this->args('local_evidencehub_report_rows', ['scope' => 'all']));
        $this->assertSame(1, $rows['data']['total']);
    }

    /**
     * A negative context id, a negative template id, a user context and a module context get the refusal the
     * other refusals get: the same error code and message, from every service.
     *
     * @return void
     */
    public function test_a_forged_id_gets_the_one_refusal(): void {
        $course = $this->getDataGenerator()->create_course();
        $module = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $forged = [
            'negative context' => ['contextid' => -1],
            'negative template' => ['templateid' => -1],
            'user context' => ['contextid' => \context_user::instance((int) $this->worker->id)->id],
            'module context' => ['contextid' => \context_module::instance((int) $module->cmid)->id],
        ];
        foreach (array_keys(self::FUNCTIONS) as $function) {
            $reference = $this->call($function, $this->args($function, ['templateid' => 0]));
            $this->assertTrue($reference['error'], $function);
            foreach ($forged as $case => $args) {
                $response = $this->call($function, $this->args($function, $args));
                $this->assertTrue($response['error'], "{$function}: {$case}");
                $this->assertSame($reference['exception']->errorcode, $response['exception']->errorcode, "{$function}: {$case}");
                $this->assertSame($reference['exception']->message, $response['exception']->message, "{$function}: {$case}");
            }
        }
    }

    /**
     * The numeric masking holds through the services: for a reader without viewsensitive the facet counts and the
     * summary's distribution count the sensitive pair as restricted, with no type of its own; the control: a reader
     * with viewsensitive is counted the real values.
     *
     * @return void
     */
    public function test_the_numeric_masking_reaches_both_services(): void {
        global $DB;
        $rows = 'local_evidencehub_report_rows';
        $summary = 'local_evidencehub_report_summary';
        $aso = (string) $DB->get_field('local_evidencehub_type', 'id', ['shortname' => 'aso']);
        $diploma = (string) $DB->get_field('local_evidencehub_type', 'id', ['shortname' => 'diploma']);
        $answer = function () use ($rows, $summary): array {
            $r = $this->call($rows, $this->args($rows, ['scope' => 'all']));
            $s = $this->call($summary, $this->args($summary));
            $this->assertFalse($r['error'], json_encode($r['exception'] ?? null));
            $this->assertFalse($s['error'], json_encode($s['exception'] ?? null));
            $facets = fn(string $name): array => array_column(
                array_column($r['data']['filters'], null, 'name')[$name]['options'],
                'count',
                'value'
            );
            return [
                $facets('validity:status'),
                $facets('evidence_details:type'),
                array_column($s['data']['distribution'], 'count', 'status'),
            ];
        };
        $restricted = (string) status::EFFECTIVE_RESTRICTED;
        $valid = (string) status::STATUS_VALID;

        $this->setUser($this->viewer(self::READER));
        [$statuses, $types, $distribution] = $answer();
        $this->assertSame(1, $statuses[$restricted]);
        $this->assertArrayNotHasKey($valid, $statuses);
        $this->assertArrayNotHasKey($aso, $types);
        $this->assertSame(1, $types[$diploma]);
        $this->assertSame([1, 0], [$distribution[status::EFFECTIVE_RESTRICTED], $distribution[status::STATUS_VALID]]);

        $this->setUser($this->viewer(array_merge(self::READER, ['local/evidencehub:viewsensitive'])));
        [$statuses, $types, $distribution] = $answer();
        $this->assertSame(1, $statuses[$valid]);
        $this->assertArrayNotHasKey($restricted, $statuses);
        $this->assertSame(1, $types[$aso]);
        $this->assertSame([0, 1], [$distribution[status::EFFECTIVE_RESTRICTED], $distribution[status::STATUS_VALID]]);
    }

    /**
     * A page of the largest integer answers the last page, with no type error.
     *
     * @return void
     */
    public function test_the_largest_page_answers_the_last_page(): void {
        $rows = 'local_evidencehub_report_rows';
        $response = $this->call($rows, $this->args($rows, ['scope' => 'all', 'page' => PHP_INT_MAX]));
        $this->assertFalse($response['error'], json_encode($response['exception'] ?? null));
        $this->assertSame([0, 4], [$response['data']['page'], $response['data']['total']]);
        $this->assertCount(4, $response['data']['rows']);
    }

    /**
     * The rows service writes (the reader's last template, a user preference), so it is declared a write: a
     * service declared a read promises a site and its tokens that it changes nothing. The summary, which writes
     * nothing, stays a read (the control).
     *
     * @return void
     */
    public function test_the_rows_service_is_declared_a_write(): void {
        global $CFG;
        $functions = [];
        require($CFG->dirroot . '/local/evidencehub/db/services.php');
        $this->assertSame('write', $functions['local_evidencehub_report_rows']['type']);
        $this->assertSame('read', $functions['local_evidencehub_report_summary']['type']);
    }
}
