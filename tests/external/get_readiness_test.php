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

/**
 * Tests for the readiness web service.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_evidencehub\external;

use core_competency\plan;
use core_external\external_api;
use local_evidencehub\local\dossier_config;
use local_evidencehub\local\policy;
use local_evidencehub\local\status;
use local_evidencehub\tests\hub_scenario;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The function returns its allowlist; each access check refuses, and passes once its right is held; a
 * restricted competency hides its status while the readiness stays the real one.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(get_readiness::class)]
final class get_readiness_test extends \advanced_testcase {
    use hub_scenario;

    /** @var array The competencies C1 (regulatory) and C2 (technical). */
    private array $competencyids;

    /** @var int The template requiring them. */
    private int $templateid;

    /** @var \stdClass The worker, ID number W-1. */
    private \stdClass $worker;

    /** @var array The capabilities a caller needs. */
    private const array CALLER = [
        'local/evidencehub:view',
        'moodle/competency:userevidenceview',
        'moodle/competency:templateview',
        'moodle/site:viewuseridentity',
    ];

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('enabled', 1, 'core_competency');
        // ID numbers are among the identity fields shown, to holders of moodle/site:viewuseridentity.
        set_config('showuseridentity', 'email,idnumber');
        $this->setAdminUser();
        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $generator->create_framework();
        $this->competencyids = [];
        foreach (['C1', 'C2'] as $name) {
            $this->competencyids[] = (int) $generator->create_competency([
                'competencyframeworkid' => $framework->get('id'),
                'shortname' => $name,
                'idnumber' => $name . '-ID',
            ])->get('id');
        }
        policy::save((object) [
            'frameworkid' => (int) $framework->get('id'),
            'competencyid' => $this->competencyids[0],
            'kind' => policy::KIND_REGULATORY,
            'requireverified' => 1,
            'requireproficiency' => 0,
            'minhours' => null,
            'alert1' => 60,
            'alert2' => 30,
            'gracedays' => 0,
            'cyclemonths' => 0,
            'onexpiry' => policy::ONEXPIRY_LOG,
        ]);
        $this->templateid = $this->template($this->competencyids);
        $this->worker = $this->worker($this->templateid, ['idnumber' => 'W-1']);
    }

    /**
     * Call the function as the current user.
     *
     * @param array $args The arguments; the template is added when absent.
     * @return array call_external_function()'s response.
     */
    private function call(array $args): array {
        $_POST['sesskey'] = sesskey();
        return external_api::call_external_function(
            'local_evidencehub_get_readiness',
            $args + ['templateid' => $this->templateid]
        );
    }

    /**
     * The data of a successful call.
     *
     * @param array $args The arguments.
     * @return array
     */
    private function data(array $args): array {
        $response = $this->call($args);
        $this->assertFalse($response['error'], $response['exception']->message ?? '');
        return $response['data'];
    }

    /**
     * The error code of a refused call.
     *
     * @param array $args The arguments.
     * @return string
     */
    private function refusal(array $args): string {
        $response = $this->call($args);
        $this->assertTrue($response['error'], 'The call was not refused.');
        return $response['exception']->errorcode;
    }

    /**
     * The returns are exactly the allowlist, with each competency's effective status and expiry.
     *
     * @return void
     */
    public function test_the_returns_are_the_allowlist(): void {
        [$c1, $c2] = $this->competencyids;
        $this->status_row((int) $this->worker->id, $c1, status::STATUS_VALID, 20280101);
        $this->status_row((int) $this->worker->id, $c2, status::STATUS_EXPIRED, 20260101);
        $this->setUser($this->viewer(self::CALLER));

        $data = $this->data(['userid' => $this->worker->id]);
        $this->assertSame(['ready', 'computed', 'competencies'], array_keys($data));
        $this->assertTrue($data['ready'], 'An unmet technical competency made the worker not ready.');
        $this->assertIsInt($data['computed']);
        $expected = [
            [
                'id' => $c1,
                'shortname' => 'C1',
                'idnumber' => 'C1-ID',
                'status' => status::STATUS_VALID,
                'expiry' => '2028-01-01',
                'blocking' => false,
            ],
            [
                'id' => $c2,
                'shortname' => 'C2',
                'idnumber' => 'C2-ID',
                'status' => status::STATUS_EXPIRED,
                'expiry' => '2026-01-01',
                'blocking' => false,
            ],
        ];
        $this->assertSame($expected, $data['competencies']);
    }

    /**
     * An unmet regulatory competency blocks; a missing one too, with no status row and no computed time.
     *
     * @return void
     */
    public function test_an_unmet_regulatory_competency_blocks(): void {
        $this->setUser($this->viewer(self::CALLER));
        $data = $this->data(['userid' => $this->worker->id]);
        $this->assertFalse($data['ready']);
        $this->assertNull($data['computed']);
        $this->assertSame([status::EFFECTIVE_MISSING, status::EFFECTIVE_MISSING], array_column($data['competencies'], 'status'));
        $this->assertSame([true, false], array_column($data['competencies'], 'blocking'));
    }

    /**
     * The worker's id and ID number give the same answer; both or neither are refused.
     *
     * @return void
     */
    public function test_userid_and_useridnumber_agree(): void {
        $this->status_row((int) $this->worker->id, $this->competencyids[0], status::STATUS_DUE, 20260801);
        $this->setUser($this->viewer(self::CALLER));
        $this->assertSame(
            $this->data(['userid' => $this->worker->id]),
            $this->data(['useridnumber' => 'W-1'])
        );
        $this->assertSame('invalidparameter', $this->refusal(['userid' => $this->worker->id, 'useridnumber' => 'W-1']));
        $this->assertSame('invalidparameter', $this->refusal([]));
    }

    /**
     * Without local/evidencehub:view in the template's context the call is refused; with it, answered.
     *
     * @return void
     */
    public function test_the_caller_needs_view_in_the_template_context(): void {
        $this->setUser($this->viewer(['moodle/competency:userevidenceview', 'moodle/competency:templateview']));
        $this->assertSame('nopermissions', $this->refusal(['userid' => $this->worker->id]));
        $this->setUser($this->viewer(self::CALLER));
        $this->data(['userid' => $this->worker->id]);
    }

    /**
     * A template the caller may not read is refused like an unknown one.
     *
     * @return void
     */
    public function test_an_unreadable_template_is_refused_like_an_unknown_one(): void {
        $this->setUser($this->viewer(['local/evidencehub:view', 'moodle/competency:userevidenceview']));
        $this->assertSame('errorhubtemplate', $this->refusal(['userid' => $this->worker->id]));
        $unknown = ['userid' => $this->worker->id, 'templateid' => $this->templateid + 99];
        $this->assertSame('errorhubtemplate', $this->refusal($unknown));
    }

    /**
     * A guest is refused.
     *
     * @return void
     */
    public function test_a_guest_is_refused(): void {
        $this->setGuestUser();
        $this->assertSame('noguest', $this->refusal(['userid' => $this->worker->id]));
    }

    /**
     * An unknown or shared ID number, a worker the caller may not read, a deleted worker and a worker without
     * an active plan from the template all give the same error; the readable worker with the plan is answered.
     *
     * @return void
     */
    public function test_every_worker_not_found_reads_the_same(): void {
        $this->setUser($this->viewer(['local/evidencehub:view', 'moodle/competency:templateview']));
        $unreadable = $this->call(['userid' => $this->worker->id]);

        $this->setUser($this->viewer(self::CALLER));
        $unknown = $this->call(['useridnumber' => 'nobody']);
        $draft = $this->worker($this->templateid, ['idnumber' => 'W-2'], plan::STATUS_DRAFT);
        $noplan = $this->call(['userid' => $draft->id]);
        $gone = $this->worker($this->templateid);
        delete_user($gone);
        $deleted = $this->call(['userid' => $gone->id]);

        // An ID number two workers share names neither.
        $this->worker($this->templateid, ['idnumber' => 'SHARED']);
        $this->worker($this->templateid, ['idnumber' => 'SHARED']);
        $shared = $this->call(['useridnumber' => 'SHARED']);

        foreach ([$unreadable, $unknown, $noplan, $deleted, $shared] as $response) {
            $this->assertTrue($response['error']);
            $this->assertSame('errorreadinessnotfound', $response['exception']->errorcode);
            $this->assertSame($unknown['exception']->message, $response['exception']->message);
        }
        $this->data(['useridnumber' => 'W-1']);
    }

    /**
     * A competency decided by a sensitive evidence shows no status or expiry to a caller without
     * viewsensitive, and does to one with it; the readiness is the real one for both.
     *
     * @return void
     */
    public function test_a_restricted_competency_hides_its_status_but_not_the_readiness(): void {
        [$c1, $c2] = $this->competencyids;
        $this->status_row((int) $this->worker->id, $c1, status::STATUS_EXPIRED, 20260101, 'aso');
        $this->status_row((int) $this->worker->id, $c2, status::STATUS_VALID, 20280101);

        $this->setUser($this->viewer(self::CALLER));
        $restricted = $this->data(['userid' => $this->worker->id]);
        $this->assertSame(status::EFFECTIVE_RESTRICTED, $restricted['competencies'][0]['status']);
        $this->assertNull($restricted['competencies'][0]['expiry']);
        $this->assertFalse($restricted['ready'], 'The readiness was not the real one.');

        $this->setUser($this->viewer(array_merge(self::CALLER, ['local/evidencehub:viewsensitive'])));
        $shown = $this->data(['userid' => $this->worker->id]);
        $this->assertSame(status::STATUS_EXPIRED, $shown['competencies'][0]['status']);
        $this->assertSame('2026-01-01', $shown['competencies'][0]['expiry']);
        $this->assertFalse($shown['ready']);

        // The real readiness again: the sensitive competency met, still restricted to the first caller.
        global $DB;
        $DB->set_field('local_evidencehub_status', 'status', status::STATUS_VALID, [
            'userid' => $this->worker->id,
            'competencyid' => $c1,
        ]);
        $this->setUser($this->viewer(self::CALLER));
        $met = $this->data(['userid' => $this->worker->id]);
        $this->assertSame(status::EFFECTIVE_RESTRICTED, $met['competencies'][0]['status']);
        $this->assertTrue($met['ready']);
    }

    /**
     * Under the "shown" basis the flag counts a competency restricted to the caller as not met; a caller
     * shown the competency gets the real verdict, and the default basis ignores the restriction.
     *
     * @return void
     */
    public function test_the_ready_flag_follows_the_readiness_basis(): void {
        [$c1, $c2] = $this->competencyids;
        $this->status_row((int) $this->worker->id, $c1, status::STATUS_VALID, 20280101, 'aso');
        $this->status_row((int) $this->worker->id, $c2, status::STATUS_VALID, 20280101);
        $plain = $this->viewer(self::CALLER);
        $sensitive = $this->viewer(array_merge(self::CALLER, ['local/evidencehub:viewsensitive']));

        // Real, the default.
        $this->setUser($plain);
        $data = $this->data(['userid' => $this->worker->id]);
        $this->assertSame(status::EFFECTIVE_RESTRICTED, $data['competencies'][0]['status'], 'Control: C1 is restricted.');
        $this->assertTrue($data['ready']);

        set_config('readinessbasis', dossier_config::READINESS_SHOWN, 'local_evidencehub');
        $data = $this->data(['userid' => $this->worker->id]);
        $this->assertFalse($data['ready']);
        $this->assertTrue($data['competencies'][0]['blocking']);

        // Control: shown the competency, the same basis says ready.
        $this->setUser($sensitive);
        $data = $this->data(['userid' => $this->worker->id]);
        $this->assertSame(status::STATUS_VALID, $data['competencies'][0]['status']);
        $this->assertTrue($data['ready']);
    }

    /**
     * A caller without view anywhere gets the same refusal for an unknown template as for a real one, so
     * the service tells no one whether a template exists; with view, the real one is answered.
     *
     * @return void
     */
    public function test_an_unknown_template_reads_like_one_out_of_reach(): void {
        $this->setUser($this->viewer(['moodle/competency:userevidenceview', 'moodle/competency:templateview']));
        $real = $this->call(['userid' => $this->worker->id]);
        $unknown = $this->call(['userid' => $this->worker->id, 'templateid' => $this->templateid + 99]);
        foreach ([$real, $unknown] as $response) {
            $this->assertTrue($response['error']);
            $this->assertSame('nopermissions', $response['exception']->errorcode);
        }
        $this->assertSame($real['exception']->message, $unknown['exception']->message);

        $this->setUser($this->viewer(self::CALLER));
        $this->data(['userid' => $this->worker->id]);
    }

    /**
     * An ID number finds the worker only for a caller shown ID numbers in the template's context; for
     * anyone else it reads as an unknown worker, while the user id still answers.
     *
     * @return void
     */
    public function test_an_idnumber_needs_the_identity_field(): void {
        $this->setUser($this->viewer(array_diff(self::CALLER, ['moodle/site:viewuseridentity'])));
        $hidden = $this->call(['useridnumber' => 'W-1']);
        $unknown = $this->call(['useridnumber' => 'nobody']);
        $this->assertTrue($hidden['error']);
        $this->assertSame('errorreadinessnotfound', $hidden['exception']->errorcode);
        $this->assertSame($unknown['exception']->message, $hidden['exception']->message);
        $this->data(['userid' => $this->worker->id]);

        $this->setUser($this->viewer(self::CALLER));
        $this->data(['useridnumber' => 'W-1']);
    }

    /**
     * A suspended worker is not found, by id or by ID number, with the error of an unknown one; unsuspended, the
     * same calls are answered (the control).
     *
     * @return void
     */
    public function test_a_suspended_worker_is_not_found(): void {
        global $DB;
        $this->setUser($this->viewer(self::CALLER));
        $unknown = $this->call(['useridnumber' => 'nobody']);
        $DB->set_field('user', 'suspended', 1, ['id' => $this->worker->id]);
        foreach ([['userid' => $this->worker->id], ['useridnumber' => 'W-1']] as $args) {
            $response = $this->call($args);
            $this->assertTrue($response['error'], json_encode($args));
            $this->assertSame('errorreadinessnotfound', $response['exception']->errorcode);
            $this->assertSame($unknown['exception']->message, $response['exception']->message);
        }

        $DB->set_field('user', 'suspended', 0, ['id' => $this->worker->id]);
        $this->data(['userid' => $this->worker->id]);
        $this->data(['useridnumber' => 'W-1']);
    }

    /**
     * With competencies disabled on the site the call is refused; enabled, it is answered.
     *
     * @return void
     */
    public function test_competencies_must_be_enabled(): void {
        $this->setUser($this->viewer(self::CALLER));
        set_config('enabled', 0, 'core_competency');
        $this->assertSame('competenciesarenotenabled', $this->refusal(['userid' => $this->worker->id]));
        set_config('enabled', 1, 'core_competency');
        $this->data(['userid' => $this->worker->id]);
    }
}
