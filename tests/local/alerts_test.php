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
 * Tests for the immediate alerts.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_evidencehub\local;

use core_competency\api;
use local_evidencehub\observer;
use local_evidencehub\task\send_verification_request;
use local_evidencehub\tests\scenario;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The worker is told on a change into due, critical or expired, in their language; the verifiers assigned in
 * the worker's user context are asked when an evidence enters pending under a policy requiring verification.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(alerts::class)]
#[CoversClass(observer::class)]
#[CoversClass(send_verification_request::class)]
final class alerts_test extends \advanced_testcase {
    use scenario;

    /** @var \core_competency\competency_framework The framework. */
    private $framework;

    /** @var \core_competency\competency The competency. */
    private $competency;

    /** @var \phpunit_message_sink The message sink. */
    private \phpunit_message_sink $sink;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        // PostgreSQL runs each test inside a transaction, which holds external observers back until it ends.
        $this->preventResetByRollback();
        set_config('enabled', 1, 'core_competency');
        $this->setTimezone('UTC', 'UTC');
        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $this->framework = $generator->create_framework();
        $this->competency = $generator->create_competency([
            'competencyframeworkid' => $this->framework->get('id'),
            'shortname' => 'Working at height',
        ]);
        $this->freeze('2026-06-15 12:00');
        $this->setAdminUser();
        $this->sink = $this->redirectMessages();
    }

    /**
     * Freeze the clock at a UTC time.
     *
     * @param string $utc The time, read in UTC.
     * @return void
     */
    private function freeze(string $utc): void {
        $this->mock_clock_with_frozen((new \DateTimeImmutable($utc, new \DateTimeZone('UTC')))->getTimestamp());
    }

    /**
     * A worker's evidence of the competency with details expiring on a day, written without events.
     *
     * @param \stdClass $worker The worker.
     * @param int $expiry The expiry, YYYYMMDD.
     * @return void
     */
    private function evidence_expiring(\stdClass $worker, int $expiry): void {
        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $ue = $generator->create_user_evidence(['userid' => $worker->id]);
        $generator->create_user_evidence_competency([
            'userevidenceid' => $ue->get('id'),
            'competencyid' => $this->competency->get('id'),
        ]);
        $this->getDataGenerator()->get_plugin_generator('local_evidencehub')->create_meta([
            'userid' => $worker->id,
            'userevidenceid' => $ue->get('id'),
            'issuedate' => 20250101,
            'expirydate' => $expiry,
        ]);
    }

    /**
     * Recompute a worker's pair of the competency.
     *
     * @param \stdClass $worker The worker.
     * @return void
     */
    private function recompute(\stdClass $worker): void {
        status_repository::recompute((int) $worker->id, (int) $this->competency->get('id'));
    }

    /**
     * The messages sent since the last call, and empty the sink.
     *
     * @return array The sink's messages.
     */
    private function sent(): array {
        $messages = $this->sink->get_messages();
        $this->sink->clear();
        return $messages;
    }

    /**
     * Due, then critical, then expired each tell the worker once, with a link to their evidence page; a
     * recomputation that leaves the status as it was tells nobody.
     *
     * @return void
     */
    public function test_each_change_into_an_alerting_status_tells_the_worker_once(): void {
        $worker = $this->user_with();
        // Under the default policy, 47 days before the expiry is due (60), 16 critical (30).
        $this->evidence_expiring($worker, 20260801);
        $this->recompute($worker);
        $due = $this->sent();
        $this->assertCount(1, $due);
        $this->assertSame('validitydue', $due[0]->eventtype);
        $this->assertSame('local_evidencehub', $due[0]->component);
        $this->assertSame((int) $worker->id, (int) $due[0]->useridto);
        $this->assertSame('Evidence for Working at height is due for renewal', $due[0]->subject);
        $this->assertStringContainsString('2026-08-01', $due[0]->fullmessage);
        $url = new \moodle_url('/local/evidencehub/evidence.php', ['userid' => $worker->id]);
        $this->assertSame($url->out(false), $due[0]->contexturl);

        $this->freeze('2026-06-16 12:00');
        $this->recompute($worker);
        $this->assertSame([], $this->sent(), 'A recomputation that kept the status sent a message.');

        $this->freeze('2026-07-16 12:00');
        $this->recompute($worker);
        $critical = $this->sent();
        $this->assertCount(1, $critical);
        $this->assertSame('validitydue', $critical[0]->eventtype);
        $this->assertSame('Evidence for Working at height expires on 2026-08-01', $critical[0]->subject);

        $this->freeze('2026-08-02 12:00');
        $this->recompute($worker);
        $expired = $this->sent();
        $this->assertCount(1, $expired);
        $this->assertSame('validityexpired', $expired[0]->eventtype);
        $this->assertSame('Evidence for Working at height has expired', $expired[0]->subject);
    }

    /**
     * A change into compliant or awaiting verification tells nobody.
     *
     * @return void
     */
    public function test_other_statuses_alert_nobody(): void {
        $worker = $this->user_with();
        $this->evidence_expiring($worker, 20280101);
        $this->recompute($worker);
        $this->assertSame(status::STATUS_VALID, (int) $this->stored($worker));
        $this->assertSame([], $this->sent());
        $this->assertNull(alerts::provider(status::STATUS_DUE, status::STATUS_DUE));
        $this->assertSame(alerts::VALIDITY_DUE, alerts::provider(status::STATUS_DUE, status::STATUS_CRITICAL));
    }

    /**
     * A change inside a transaction that rolls back tells nobody; the same change committed tells the worker.
     *
     * @return void
     */
    public function test_a_change_that_rolls_back_alerts_nobody(): void {
        global $DB;
        $worker = $this->user_with();
        $this->evidence_expiring($worker, 20260801);
        $transaction = $DB->start_delegated_transaction();
        $this->recompute($worker);
        try {
            $transaction->rollback(new \moodle_exception('error'));
        } catch (\moodle_exception $e) {
            $this->assertSame('error', $e->errorcode);
        }
        $this->assertFalse($this->stored($worker));
        $this->assertSame([], $this->sent(), 'A change that rolled back was announced.');

        $this->recompute($worker);
        $this->assertCount(1, $this->sent());
    }

    /**
     * Two changes of one pair in one transaction reach the observer after the commit: a due superseded by
     * compliant, or a due whose expiry moved while it stayed due, alerts nobody; a due alone alerts once.
     *
     * @return void
     */
    public function test_a_change_superseded_in_the_same_transaction_alerts_nobody(): void {
        global $DB;
        $renewed = $this->user_with();
        $moved = $this->user_with();
        $due = $this->user_with();
        foreach ([$renewed, $moved, $due] as $worker) {
            $this->evidence_expiring($worker, 20260801);
        }
        $transaction = $DB->start_delegated_transaction();
        $this->recompute($renewed);
        $DB->set_field(details::TABLE, 'expirydate', 20280101, ['userid' => $renewed->id]);
        $this->recompute($renewed);
        $this->recompute($moved);
        $DB->set_field(details::TABLE, 'expirydate', 20260805, ['userid' => $moved->id]);
        $this->recompute($moved);
        $this->recompute($due);
        $transaction->allow_commit();

        $this->assertSame(status::STATUS_VALID, (int) $this->stored($renewed));
        $this->assertSame(status::STATUS_DUE, (int) $this->stored($moved));
        $sent = $this->sent();
        $this->assertSame([(int) $due->id], array_map(fn($message): int => (int) $message->useridto, $sent));
    }

    /**
     * A status overtaken in the same transaction with the same expiry alerts only for the status it ended in.
     *
     * Due, then expired once the day passes: the expiry is the same in both rows, so only the status check
     * keeps the stale due alert from going out.
     *
     * @return void
     */
    public function test_a_status_overtaken_with_the_same_expiry_alerts_only_its_last(): void {
        global $DB;
        $worker = $this->user_with();
        $this->evidence_expiring($worker, 20260801);
        $transaction = $DB->start_delegated_transaction();
        $this->recompute($worker);
        $this->assertSame(status::STATUS_DUE, (int) $this->stored($worker));
        $this->freeze('2026-08-02 12:00');
        $this->recompute($worker);
        $transaction->allow_commit();

        $this->assertSame(status::STATUS_EXPIRED, (int) $this->stored($worker));
        $sent = $this->sent();
        $this->assertCount(1, $sent);
        $this->assertSame((int) $worker->id, (int) $sent[0]->useridto);
        $this->assertSame('validityexpired', $sent[0]->eventtype);
    }

    /**
     * A suspended or unconfirmed worker is told nothing; an active one, on the same change, is.
     *
     * @return void
     */
    public function test_suspended_and_unconfirmed_workers_are_not_told(): void {
        $active = $this->user_with();
        $suspended = $this->getDataGenerator()->create_user(['suspended' => 1]);
        $unconfirmed = $this->getDataGenerator()->create_user(['confirmed' => 0]);
        foreach ([$active, $suspended, $unconfirmed] as $worker) {
            $this->evidence_expiring($worker, 20260801);
            $this->recompute($worker);
            $this->assertSame(status::STATUS_DUE, (int) $this->stored($worker));
        }
        $sent = $this->sent();
        $this->assertSame([(int) $active->id], array_map(fn($message): int => (int) $message->useridto, $sent));
    }

    /**
     * The message is composed in the worker's language, and the current language comes back afterwards.
     *
     * @return void
     */
    public function test_the_message_is_in_the_workers_language(): void {
        global $CFG;
        // A Brazilian Portuguese language pack, so the plugin's own lang/pt_br strings load.
        $folder = $CFG->dataroot . '/lang/pt_br';
        check_dir_exists($folder);
        file_put_contents($folder . '/langconfig.php', "<?php\n\$string['thislanguage'] = 'Português - Brasil';\n");
        get_string_manager()->reset_caches(true);

        $english = $this->user_with();
        $portuguese = $this->getDataGenerator()->create_user(['lang' => 'pt_br']);
        foreach ([$english, $portuguese] as $worker) {
            $this->evidence_expiring($worker, 20260801);
            $this->recompute($worker);
        }
        $subjects = [];
        foreach ($this->sent() as $message) {
            $subjects[(int) $message->useridto] = $message->subject;
        }
        $this->assertSame('Evidence for Working at height is due for renewal', $subjects[(int) $english->id]);
        $this->assertSame('A evidência de Working at height está a vencer', $subjects[(int) $portuguese->id]);
        $this->assertSame('en', current_language());
    }

    /**
     * The stored status of a worker's pair.
     *
     * @param \stdClass $worker The worker.
     * @return int|false
     */
    private function stored(\stdClass $worker) {
        global $DB;
        return $DB->get_field(status_repository::TABLE, 'status', [
            'userid' => $worker->id,
            'competencyid' => $this->competency->get('id'),
        ]);
    }

    /**
     * Require verified evidence for the framework.
     *
     * @return void
     */
    private function regulatory_policy(): void {
        policy::save((object) [
            'frameworkid' => (int) $this->framework->get('id'),
            'competencyid' => 0,
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
    }

    /**
     * A user given a role in a worker's user context, the way tool_cohortroles assigns a mentor.
     *
     * @param \stdClass $worker The worker.
     * @param array $capabilities The role's capabilities.
     * @param \stdClass|null $user The user; a new one when null.
     * @return \stdClass The user.
     */
    private function assigned_over(\stdClass $worker, array $capabilities, ?\stdClass $user = null): \stdClass {
        $user ??= $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        set_role_contextlevels($roleid, [CONTEXT_USER]);
        foreach ($capabilities as $capability) {
            assign_capability($capability, CAP_ALLOW, $roleid, \context_system::instance()->id, true);
        }
        role_assign($roleid, $user->id, \context_user::instance((int) $worker->id)->id);
        accesslib_clear_all_caches_for_unit_testing();
        return $user;
    }

    /**
     * The worker creates an evidence linked to a competency and saves its details.
     *
     * @param \stdClass $worker The worker.
     * @param int $competencyid The competency.
     * @return array [user_evidence, meta row].
     */
    private function evidence_with_details(\stdClass $worker, int $competencyid): array {
        $this->setUser($worker);
        $ue = api::create_user_evidence((object) ['userid' => $worker->id, 'name' => 'Harness training']);
        api::create_user_evidence_competency($ue, $competencyid);
        $meta = details::save($ue, $this->details_data());
        $this->setAdminUser();
        return [$ue, $meta];
    }

    /**
     * Run the queued verification requests.
     *
     * @return array The messages they sent.
     */
    private function requests(): array {
        ob_start();
        $this->runAdhocTasks(send_verification_request::class);
        ob_end_clean();
        return array_values(array_filter(
            $this->sent(),
            fn($message): bool => $message->eventtype === alerts::VERIFICATION_REQUESTED
        ));
    }

    /**
     * Only the verifier assigned in the worker's user context is asked: not a system manager holding
     * the same capabilities, not the worker, even when the worker holds verify over themselves.
     *
     * @return void
     */
    public function test_only_verifiers_assigned_over_the_worker_are_asked(): void {
        $this->regulatory_policy();
        $worker = $this->user_with();
        $verifier = $this->assigned_over($worker, ['local/evidencehub:verify', 'moodle/competency:userevidenceview']);
        $this->assigned_over($worker, ['local/evidencehub:verify', 'moodle/competency:userevidenceview'], $worker);
        $manager = $this->user_with(['local/evidencehub:verify', 'moodle/competency:userevidenceview']);
        $this->assertTrue(has_capability('local/evidencehub:verify', \context_user::instance((int) $worker->id), $manager));

        $this->evidence_with_details($worker, (int) $this->competency->get('id'));
        $sent = $this->requests();
        $this->assertSame([(int) $verifier->id], array_map(fn($message): int => (int) $message->useridto, $sent));
        $this->assertSame(alerts::VERIFICATION_REQUESTED, $sent[0]->eventtype);
        $this->assertStringContainsString('Harness training', $sent[0]->fullmessage);
        $this->assertStringContainsString('Working at height', $sent[0]->fullmessage);
        $url = new \moodle_url('/local/evidencehub/evidence.php', ['userid' => $worker->id]);
        $this->assertSame($url->out(false), $sent[0]->contexturl);
    }

    /**
     * A verifier who may not read the worker's evidence is not asked; one who may, is.
     *
     * @return void
     */
    public function test_a_verifier_who_cannot_read_the_evidence_is_not_asked(): void {
        $this->regulatory_policy();
        $worker = $this->user_with();
        $reader = $this->assigned_over($worker, ['local/evidencehub:verify', 'moodle/competency:userevidenceview']);
        $this->assigned_over($worker, ['local/evidencehub:verify']);
        $this->evidence_with_details($worker, (int) $this->competency->get('id'));
        $this->assertSame([(int) $reader->id], array_map(fn($message): int => (int) $message->useridto, $this->requests()));
    }

    /**
     * A technical policy that does not require verification asks nobody; the regulatory one does.
     *
     * @return void
     */
    public function test_only_a_policy_requiring_verification_asks(): void {
        $worker = $this->user_with();
        $verifier = $this->assigned_over($worker, ['local/evidencehub:verify', 'moodle/competency:userevidenceview']);
        $this->evidence_with_details($worker, (int) $this->competency->get('id'));
        $this->assertSame([], $this->requests(), 'A technical policy asked for verification.');

        $this->regulatory_policy();
        $this->evidence_with_details($worker, (int) $this->competency->get('id'));
        $this->assertSame([(int) $verifier->id], array_map(fn($message): int => (int) $message->useridto, $this->requests()));
    }

    /**
     * A decision asks nobody; the owner's edit that sends the evidence back to pending asks again, once,
     * whether it comes through core's evidence alone or through the plugin's form (core's update, then the details).
     *
     * @return void
     */
    public function test_only_entering_pending_asks_and_once_per_change(): void {
        $this->regulatory_policy();
        $worker = $this->user_with();
        $verifier = $this->assigned_over($worker, ['local/evidencehub:verify', 'moodle/competency:userevidenceview']);
        [$ue, $meta] = $this->evidence_with_details($worker, (int) $this->competency->get('id'));
        $this->assertCount(1, $this->requests());

        $this->setUser($verifier);
        details::verify((int) $meta->id);
        $this->assertSame([], $this->requests(), 'A verification asked for verification.');

        // The owner changes core's evidence only: the reset asks again.
        $this->setUser($worker);
        api::update_user_evidence((object) ['id' => $ue->get('id'), 'name' => 'Harness training 2026']);
        $this->assertSame(meta::VERIFY_PENDING, (int) details::get((int) $ue->get('id'))->verifystatus);
        $this->assertCount(1, $this->requests());

        // Verified again, then the form's save: core's update resets, the details save; one request.
        $this->setUser($verifier);
        details::verify((int) $meta->id);
        $this->setUser($worker);
        $ue = api::update_user_evidence((object) ['id' => $ue->get('id'), 'name' => 'Harness training 2027']);
        details::save($ue, $this->details_data(['issuer' => 'Another centre']));
        $this->assertCount(1, $this->requests());
    }

    /**
     * An evidence of a sensitive type is announced only to verifiers who may read sensitive evidence.
     *
     * @return void
     */
    public function test_a_sensitive_evidence_asks_only_who_may_read_it(): void {
        $this->regulatory_policy();
        $worker = $this->user_with();
        $this->assigned_over($worker, ['local/evidencehub:verify', 'moodle/competency:userevidenceview']);
        $sensitive = $this->assigned_over(
            $worker,
            ['local/evidencehub:verify', 'moodle/competency:userevidenceview', 'local/evidencehub:viewsensitive']
        );
        $this->setUser($worker);
        $ue = api::create_user_evidence((object) ['userid' => $worker->id, 'name' => 'Occupational health']);
        api::create_user_evidence_competency($ue, (int) $this->competency->get('id'));
        details::save($ue, $this->details_data(['typeid' => $this->type_id('aso')]));
        $this->setAdminUser();
        $this->assertSame([(int) $sensitive->id], array_map(fn($message): int => (int) $message->useridto, $this->requests()));
    }

    /**
     * A verifier's own edit of a decided evidence leaves it decided and queues nothing; a request queued
     * anyway for a decided evidence sends nothing, while one for a pending evidence does.
     *
     * @return void
     */
    public function test_a_decided_evidence_asks_nobody(): void {
        $this->regulatory_policy();
        $worker = $this->user_with();
        $verifier = $this->assigned_over(
            $worker,
            ['local/evidencehub:verify', 'moodle/competency:userevidenceview', 'moodle/competency:userevidencemanage']
        );
        [$ue, $meta] = $this->evidence_with_details($worker, (int) $this->competency->get('id'));
        $this->assertCount(1, $this->requests());

        $this->setUser($verifier);
        details::verify((int) $meta->id);
        details::save($ue, $this->details_data(['issuer' => 'Corrected centre']));
        $this->assertSame(meta::VERIFY_VERIFIED, (int) details::get((int) $ue->get('id'))->verifystatus);
        $this->assertSame([], \core\task\manager::get_adhoc_tasks(send_verification_request::class));

        alerts::request_verification((int) $meta->id);
        $this->assertSame([], $this->requests(), 'A decided evidence was announced.');
        $this->assertSame(0, alerts::verification_requested((int) $meta->id));
    }

    /**
     * A recipient whose message fails is traced and skipped; the other verifier is told exactly once and the
     * request completes.
     *
     * @return void
     */
    public function test_one_failing_recipient_does_not_stop_the_others(): void {
        $this->regulatory_policy();
        $worker = $this->user_with();
        $capabilities = ['local/evidencehub:verify', 'moodle/competency:userevidenceview'];
        $failing = $this->assigned_over($worker, $capabilities);
        $told = $this->assigned_over($worker, $capabilities);
        [, $meta] = $this->evidence_with_details($worker, (int) $this->competency->get('id'));

        $send = function (
            string $provider,
            \stdClass $recipient,
            string $subject,
            string $body,
            string $html,
            ?\moodle_url $url = null
        ) use ($failing): bool {
            if ((int) $recipient->id === (int) $failing->id) {
                throw new \moodle_exception('error');
            }
            return alerts::send($provider, $recipient, $subject, $body, $html, $url);
        };
        ob_start();
        $sent = alerts::verification_requested((int) $meta->id, $send);
        $output = ob_get_clean();
        $this->assertSame(1, $sent);
        $this->assertStringContainsString("to user {$failing->id} failed", $output);
        $this->assertSame([(int) $told->id], array_map(fn($message): int => (int) $message->useridto, $this->sent()));
    }
}
