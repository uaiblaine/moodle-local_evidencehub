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
 * Tests for the details API.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_evidencehub\local;

use local_evidencehub\event\evidence_details_updated;
use local_evidencehub\event\evidence_rejected;
use local_evidencehub\event\evidence_verified;
use local_evidencehub\tests\scenario;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Who may save, verify and reject, the rules of each type, and the reset after an edit.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(details::class)]
#[CoversClass(evidence_details_updated::class)]
#[CoversClass(evidence_verified::class)]
#[CoversClass(evidence_rejected::class)]
final class details_test extends \advanced_testcase {
    use scenario;

    /** @var \stdClass The worker. */
    private \stdClass $worker;

    /** @var \stdClass A holder of local/evidencehub:verify who can read the worker's evidence. */
    private \stdClass $verifier;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('enabled', 1, 'core_competency');
        $this->worker = $this->user_with();
        $this->verifier = $this->user_with(['local/evidencehub:verify', 'moodle/competency:userevidenceview']);
    }

    /**
     * Details of the worker's new evidence, saved by the worker.
     *
     * @param array $overrides Detail fields to replace.
     * @return \stdClass The meta row.
     */
    private function saved_by_worker(array $overrides = []): \stdClass {
        $ue = $this->evidence_of((int) $this->worker->id);
        $this->setUser($this->worker);
        return details::save($ue, $this->details_data($overrides));
    }

    /**
     * The owner saves through moodle/competency:userevidencemanageown; the row, its owner and its
     * first audit row are written.
     *
     * @return void
     */
    public function test_the_owner_saves_their_own_details(): void {
        $meta = $this->saved_by_worker();
        $this->assertSame((int) $this->worker->id, (int) $meta->userid);
        $this->assertSame(meta::VERIFY_PENDING, (int) $meta->verifystatus);
        $this->assertSame(20280115, (int) $meta->expirydate);
        $this->assertSame([[audit::ACTION_CREATED, (int) $this->worker->id]], $this->trail((int) $meta->id));
    }

    /**
     * A holder of moodle/competency:userevidencemanage saves another user's details.
     *
     * @return void
     */
    public function test_a_manager_saves_another_users_details(): void {
        $manager = $this->user_with(['moodle/competency:userevidencemanage']);
        $ue = $this->evidence_of((int) $this->worker->id);
        $this->setUser($manager);
        $meta = details::save($ue, $this->details_data());
        $this->assertSame((int) $this->worker->id, (int) $meta->userid);
        $this->assertSame([[audit::ACTION_CREATED, (int) $manager->id]], $this->trail((int) $meta->id));
    }

    /**
     * Without either right the save is refused and nothing is written.
     *
     * @return void
     */
    public function test_saving_without_manage_rights_is_refused(): void {
        global $DB;
        $stranger = $this->user_with(['moodle/competency:userevidenceview']);
        $ue = $this->evidence_of((int) $this->worker->id);
        $this->setUser($stranger);
        try {
            details::save($ue, $this->details_data());
            $this->fail('A user without manage rights saved details.');
        } catch (\required_capability_exception $e) {
            $this->assertStringContainsString(get_capability_string('moodle/competency:userevidencemanage'), $e->getMessage());
        }
        $this->assertFalse($DB->record_exists('local_evidencehub_meta', ['userevidenceid' => $ue->get('id')]));
    }

    /**
     * Each rule refused, beside its passing neighbour.
     *
     * @return array Case name => [type shortname, overrides, expected error code or null].
     */
    public static function rules(): array {
        return [
            'unknown type' => [null, ['typeid' => 0], 'errorunknowntype'],
            'impossible issue date' => ['nrcertificate', ['issuedate' => 20260230], 'errorissuedate'],
            'expiry before issue' => ['nrcertificate', ['expirydate' => 20260114], 'errorexpirybeforeissue'],
            'expiry on the issue day' => ['nrcertificate', ['expirydate' => 20260115], null],
            'impossible expiry' => ['nrcertificate', ['expirydate' => 20271301], 'errorexpirydate'],
            'never expires where expiry is required' => ['nrcertificate', ['noexpiry' => 1, 'expirydate' => null], 'errornoexpiry'],
            'never expires where it may' => ['diploma', ['noexpiry' => 1, 'expirydate' => null], null],
            'no expiry and no default validity' => ['diploma', ['expirydate' => null], 'errorexpiryrequired'],
            'no expiry with a default validity' => ['nrcertificate', ['expirydate' => null], null],
            'no code where one is required' => ['artrrt', ['noexpiry' => 1, 'expirydate' => null], 'errorauthcoderequired'],
            'a code where one is required' => ['artrrt', ['noexpiry' => 1, 'expirydate' => null, 'authcode' => 'X-1'], null],
            'negative hours' => ['nrcertificate', ['hours' => -0.5], 'errorhours'],
            'zero hours' => ['nrcertificate', ['hours' => 0], null],
            'no hours' => ['nrcertificate', ['hours' => ''], null],
            'issuer too long' => ['nrcertificate', ['issuer' => str_repeat('a', 256)], 'errorissuertoolong'],
            'code too long' => ['nrcertificate', ['authcode' => str_repeat('a', 101)], 'errorauthcodetoolong'],
        ];
    }

    /**
     * The API refuses details that break a rule of their type, on its own.
     *
     * @param string|null $type The type's shortname, or null to keep the override's typeid.
     * @param array $overrides Detail fields to replace.
     * @param string|null $expected The error code, or null when the save must pass.
     * @return void
     */
    #[DataProvider('rules')]
    public function test_each_rule_of_a_type(?string $type, array $overrides, ?string $expected): void {
        global $DB;
        if ($type !== null) {
            $overrides['typeid'] = $this->type_id($type);
        }
        $ue = $this->evidence_of((int) $this->worker->id);
        $this->setUser($this->worker);
        $problems = details::problems($this->details_data($overrides));
        if ($expected === null) {
            $this->assertSame([], $problems);
        } else {
            $this->assertContains($expected, $problems);
        }
        try {
            $meta = details::save($ue, $this->details_data($overrides));
            $this->assertNull($expected, 'The save passed a rule it should refuse.');
            $this->assertSame((int) $ue->get('id'), (int) $meta->userevidenceid);
        } catch (\moodle_exception $e) {
            $this->assertSame($expected, $e->errorcode);
            $this->assertFalse($DB->record_exists('local_evidencehub_meta', ['userevidenceid' => $ue->get('id')]));
        }
    }

    /**
     * Without an expiry the type's default validity fills it, clamped to the month end.
     *
     * @return void
     */
    public function test_the_default_validity_fills_the_expiry(): void {
        $meta = $this->saved_by_worker(['expirydate' => null]);
        $this->assertSame(20280115, (int) $meta->expirydate);
        $leap = $this->saved_by_worker(['typeid' => $this->type_id('aso'), 'issuedate' => 20280229, 'expirydate' => null]);
        $this->assertSame(20290228, (int) $leap->expirydate);
        $never = $this->saved_by_worker(['typeid' => $this->type_id('diploma'), 'noexpiry' => 1, 'expirydate' => 20300101]);
        $this->assertNull($never->expirydate);
        $this->assertSame(1, (int) $never->noexpiry);
    }

    /**
     * Insert and update each fire the event and write their audit row.
     *
     * @return void
     */
    public function test_insert_and_update_fire_the_event_and_write_the_trail(): void {
        $ue = $this->evidence_of((int) $this->worker->id);
        $this->setUser($this->worker);
        $sink = $this->redirectEvents();
        $meta = details::save($ue, $this->details_data());
        $updated = details::save($ue, $this->details_data(['issuer' => 'Another centre']));
        $events = array_values(array_filter(
            $sink->get_events(),
            fn($event): bool => $event instanceof evidence_details_updated
        ));
        $sink->close();

        $this->assertSame((int) $meta->id, (int) $updated->id);
        $this->assertSame('Another centre', $updated->issuer);
        $this->assertCount(2, $events);
        foreach ($events as $event) {
            $this->assertSame((int) $meta->id, (int) $event->objectid);
            $this->assertSame((int) $this->worker->id, (int) $event->relateduserid);
            $this->assertSame((int) $ue->get('id'), (int) $event->other['userevidenceid']);
            $this->assertSame(\context_user::instance($this->worker->id)->id, (int) $event->contextid);
        }
        $this->assertSame(
            [[audit::ACTION_CREATED, (int) $this->worker->id], [audit::ACTION_DETAILS, (int) $this->worker->id]],
            $this->trail((int) $meta->id)
        );
    }

    /**
     * A verifier verifies: state, actor, time, audit row and event.
     *
     * @return void
     */
    public function test_a_verifier_verifies(): void {
        $meta = $this->saved_by_worker();
        $this->setUser($this->verifier);
        $sink = $this->redirectEvents();
        $verified = details::verify((int) $meta->id, '');
        $events = $sink->get_events();
        $sink->close();

        $this->assertSame(meta::VERIFY_VERIFIED, (int) $verified->verifystatus);
        $this->assertSame((int) $this->verifier->id, (int) $verified->verifiedby);
        $this->assertNotEmpty($verified->timeverified);
        $this->assertNull($verified->verifynote);
        $this->assertCount(1, $events);
        $this->assertInstanceOf(evidence_verified::class, $events[0]);
        $this->assertSame([audit::ACTION_VERIFIED, (int) $this->verifier->id], $this->trail((int) $meta->id)[1]);
    }

    /**
     * Without local/evidencehub:verify, or without the right to read the evidence, nothing is decided.
     *
     * @return array Case name => [capabilities, expected capability in the message].
     */
    public static function missing_rights(): array {
        return [
            'reader without verify' => [['moodle/competency:userevidenceview'], 'local/evidencehub:verify'],
            'verify without reading' => [['local/evidencehub:verify'], 'moodle/competency:userevidenceview'],
        ];
    }

    /**
     * Verification needs both rights; the verifier of setUp() holding both is the control.
     *
     * @param array $capabilities What the actor holds.
     * @param string $missing The capability the refusal names.
     * @return void
     */
    #[DataProvider('missing_rights')]
    public function test_verifying_needs_both_rights(array $capabilities, string $missing): void {
        $meta = $this->saved_by_worker();
        $this->setUser($this->user_with($capabilities));
        foreach (['verify', 'reject'] as $method) {
            try {
                details::$method((int) $meta->id, 'A note');
                $this->fail("$method passed without $missing.");
            } catch (\required_capability_exception $e) {
                $this->assertStringContainsString(get_capability_string($missing), $e->getMessage());
            }
        }
        $this->assertSame(meta::VERIFY_PENDING, (int) details::get((int) $meta->userevidenceid)->verifystatus);

        $this->setUser($this->verifier);
        details::reject((int) $meta->id, 'A note');
        $this->assertSame(meta::VERIFY_REJECTED, (int) details::get((int) $meta->userevidenceid)->verifystatus);
    }

    /**
     * The owner never decides on their own evidence, whatever they hold; another verifier does.
     *
     * @return void
     */
    public function test_nobody_verifies_their_own_evidence(): void {
        $meta = $this->saved_by_worker();
        $this->grant($this->worker, ['local/evidencehub:verify', 'moodle/competency:userevidenceview']);
        $this->setUser($this->worker);
        foreach (['verify', 'reject'] as $method) {
            try {
                details::$method((int) $meta->id, 'A note');
                $this->fail("The owner could $method their own evidence.");
            } catch (\moodle_exception $e) {
                $this->assertSame('cannotverifyown', $e->errorcode);
            }
        }
        $this->assertSame(meta::VERIFY_PENDING, (int) details::get((int) $meta->userevidenceid)->verifystatus);
        $this->assertFalse(details::can_verify((int) $this->worker->id));

        $this->setUser($this->verifier);
        $this->assertTrue(details::can_verify((int) $this->worker->id));
        details::verify((int) $meta->id);
        $this->assertSame(meta::VERIFY_VERIFIED, (int) details::get((int) $meta->userevidenceid)->verifystatus);
    }

    /**
     * A rejection needs a note; a blank one does not count.
     *
     * @return void
     */
    public function test_a_rejection_needs_a_note(): void {
        $meta = $this->saved_by_worker();
        $this->setUser($this->verifier);
        try {
            details::reject((int) $meta->id, "  \n ");
            $this->fail('A rejection without a note passed.');
        } catch (\moodle_exception $e) {
            $this->assertSame('rejectneedsnote', $e->errorcode);
        }
        $this->assertSame(meta::VERIFY_PENDING, (int) details::get((int) $meta->userevidenceid)->verifystatus);

        $rejected = details::reject((int) $meta->id, ' The file is unreadable. ');
        $this->assertSame(meta::VERIFY_REJECTED, (int) $rejected->verifystatus);
        $this->assertSame('The file is unreadable.', $rejected->verifynote);
        $trail = $this->trail((int) $meta->id);
        $this->assertSame([audit::ACTION_REJECTED, (int) $this->verifier->id], end($trail));
    }

    /**
     * The same decision twice is refused; the other decision is a second look, with its own row.
     *
     * @return void
     */
    public function test_the_same_decision_twice_is_refused(): void {
        $meta = $this->saved_by_worker();
        $this->setUser($this->verifier);
        details::verify((int) $meta->id);
        try {
            details::verify((int) $meta->id);
            $this->fail('A verified evidence was verified again.');
        } catch (\moodle_exception $e) {
            $this->assertSame('alreadyverified', $e->errorcode);
        }
        details::reject((int) $meta->id, 'On a second look, expired.');
        try {
            details::reject((int) $meta->id, 'Again.');
            $this->fail('A rejected evidence was rejected again.');
        } catch (\moodle_exception $e) {
            $this->assertSame('alreadyrejected', $e->errorcode);
        }
        details::verify((int) $meta->id, 'A newer file.');
        $actions = array_column($this->trail((int) $meta->id), 0);
        $expected = [audit::ACTION_CREATED, audit::ACTION_VERIFIED, audit::ACTION_REJECTED, audit::ACTION_VERIFIED];
        $this->assertSame($expected, $actions);
    }

    /**
     * Who edits a decided evidence, and whether it goes back to pending.
     *
     * @return array Case name => [actor, starting decision, whether it resets].
     */
    public static function editors(): array {
        return [
            'owner without verify, verified' => ['owner', meta::VERIFY_VERIFIED, true],
            'owner without verify, rejected' => ['owner', meta::VERIFY_REJECTED, true],
            'owner with verify' => ['ownerverifier', meta::VERIFY_VERIFIED, true],
            'another verifier, verified' => ['verifier', meta::VERIFY_VERIFIED, false],
            'another verifier, rejected' => ['verifier', meta::VERIFY_REJECTED, false],
            'manager without verify' => ['manager', meta::VERIFY_VERIFIED, true],
        ];
    }

    /**
     * An edit sends a decided evidence back to pending unless a verifier other than the owner made it.
     *
     * @param string $actor Who edits.
     * @param int $decision The decision before the edit.
     * @param bool $resets Whether the details go back to pending.
     * @return void
     */
    #[DataProvider('editors')]
    public function test_the_reset_rule(string $actor, int $decision, bool $resets): void {
        $meta = $this->saved_by_worker();
        $this->setUser($this->verifier);
        if ($decision === meta::VERIFY_VERIFIED) {
            details::verify((int) $meta->id);
        } else {
            details::reject((int) $meta->id, 'Unreadable.');
        }

        $editor = match ($actor) {
            'owner' => $this->worker,
            'ownerverifier' => $this->worker,
            'verifier' => $this->verifier,
            'manager' => $this->user_with(['moodle/competency:userevidencemanage']),
        };
        if ($actor === 'ownerverifier') {
            $this->grant($this->worker, ['local/evidencehub:verify', 'moodle/competency:userevidenceview']);
        }
        if ($actor === 'verifier') {
            $this->grant($this->verifier, ['moodle/competency:userevidencemanage']);
        }
        $this->setUser($editor);
        $ue = new \core_competency\user_evidence((int) $meta->userevidenceid);
        $saved = details::save($ue, $this->details_data(['issuer' => 'Edited']));

        $last = array_slice($this->trail((int) $meta->id), -1)[0];
        if ($resets) {
            $this->assertSame(meta::VERIFY_PENDING, (int) $saved->verifystatus);
            $this->assertNull($saved->verifiedby);
            $this->assertSame([audit::ACTION_RESET, (int) $editor->id], $last);
        } else {
            $this->assertSame($decision, (int) $saved->verifystatus);
            $this->assertSame((int) $this->verifier->id, (int) $saved->verifiedby);
            $this->assertSame([audit::ACTION_DETAILS, (int) $editor->id], $last);
        }
        $this->assertSame('Edited', $saved->issuer);
    }

    /**
     * A pending row never resets, so a second call writes no second reset row.
     *
     * @return void
     */
    public function test_a_pending_row_does_not_reset(): void {
        $meta = $this->saved_by_worker();
        $this->assertFalse(details::apply_edit_rule($meta, (int) $this->worker->id));
        $this->assertSame([[audit::ACTION_CREATED, (int) $this->worker->id]], $this->trail((int) $meta->id));

        $this->setUser($this->verifier);
        details::verify((int) $meta->id);
        $verified = details::get((int) $meta->userevidenceid);
        $this->assertTrue(details::apply_edit_rule($verified, (int) $this->worker->id));
        $this->assertFalse(details::apply_edit_rule($verified, (int) $this->worker->id));
        $resets = array_filter($this->trail((int) $meta->id), fn(array $row): bool => $row[0] === audit::ACTION_RESET);
        $this->assertCount(1, $resets);
    }

    /**
     * Details of a sensitive type of the worker's evidence, saved by the worker.
     *
     * @return \stdClass The meta row.
     */
    private function sensitive_by_worker(): \stdClass {
        return $this->saved_by_worker(['typeid' => $this->type_id('aso'), 'expirydate' => 20270115]);
    }

    /**
     * A manager who may not read sensitive details cannot save over them, nor unmask them by changing
     * the type; with viewsensitive they can, and the owner always can.
     *
     * @return void
     */
    public function test_saving_over_sensitive_details_needs_viewsensitive(): void {
        $meta = $this->sensitive_by_worker();
        $ue = new \core_competency\user_evidence((int) $meta->userevidenceid);
        $manager = $this->user_with(['moodle/competency:userevidencemanage']);
        $this->setUser($manager);
        $attempts = [
            ['typeid' => $this->type_id('aso'), 'expirydate' => 20270115, 'issuer' => 'Changed'],
            ['issuer' => 'Unmasked'],
        ];
        foreach ($attempts as $attempt) {
            try {
                details::save($ue, $this->details_data($attempt));
                $this->fail('A manager without viewsensitive saved over sensitive details.');
            } catch (\moodle_exception $e) {
                $this->assertSame('errorrestricted', $e->errorcode);
            }
        }
        $stored = details::get((int) $meta->userevidenceid);
        $this->assertSame('Training centre', $stored->issuer);
        $this->assertSame($this->type_id('aso'), (int) $stored->typeid);

        $this->grant($manager, ['local/evidencehub:viewsensitive']);
        $saved = details::save($ue, $this->details_data(['typeid' => $this->type_id('aso'), 'issuer' => 'Changed']));
        $this->assertSame('Changed', $saved->issuer);

        $this->setUser($this->worker);
        $saved = details::save($ue, $this->details_data(['typeid' => $this->type_id('aso'), 'issuer' => 'By the owner']));
        $this->assertSame('By the owner', $saved->issuer);
    }

    /**
     * Creating new details of a sensitive type needs no viewsensitive: the manager is typing them.
     *
     * @return void
     */
    public function test_a_manager_without_viewsensitive_creates_sensitive_details(): void {
        $ue = $this->evidence_of((int) $this->worker->id);
        $this->setUser($this->user_with(['moodle/competency:userevidencemanage']));
        $meta = details::save($ue, $this->details_data(['typeid' => $this->type_id('aso'), 'expirydate' => 20270115]));
        $this->assertSame($this->type_id('aso'), (int) $meta->typeid);
        $this->assertTrue(details::is_restricted($meta));
    }

    /**
     * A verifier who may not read sensitive details cannot decide on them; with viewsensitive they can.
     *
     * @return void
     */
    public function test_deciding_on_sensitive_details_needs_viewsensitive(): void {
        $meta = $this->sensitive_by_worker();
        $this->setUser($this->verifier);
        foreach (['verify', 'reject'] as $method) {
            try {
                details::$method((int) $meta->id, 'A note');
                $this->fail("A verifier without viewsensitive could $method sensitive details.");
            } catch (\moodle_exception $e) {
                $this->assertSame('errorrestricted', $e->errorcode);
            }
        }
        $this->assertSame(meta::VERIFY_PENDING, (int) details::get((int) $meta->userevidenceid)->verifystatus);

        $this->grant($this->verifier, ['local/evidencehub:viewsensitive']);
        details::verify((int) $meta->id);
        $this->assertSame(meta::VERIFY_VERIFIED, (int) details::get((int) $meta->userevidenceid)->verifystatus);
    }

    /**
     * Only the owner, or a holder of viewsensitive, sees sensitive details.
     *
     * @return void
     */
    public function test_who_sees_sensitive_details(): void {
        $sensitive = $this->sensitive_by_worker();
        $plain = $this->saved_by_worker();
        $this->assertFalse(details::is_restricted($sensitive), 'The owner was kept from their own details.');
        $this->setUser($this->verifier);
        $this->assertTrue(details::is_restricted($sensitive));
        $this->assertFalse(details::is_restricted($plain));
        $this->grant($this->verifier, ['local/evidencehub:viewsensitive']);
        $this->assertTrue(details::can_see_sensitive((int) $this->worker->id));
        $this->assertFalse(details::is_restricted($sensitive));
    }

    /**
     * A decision writes its own columns only: details changed after the verifier's page loaded survive.
     *
     * @return void
     */
    public function test_a_decision_writes_only_the_decision_columns(): void {
        global $DB;
        $loaded = $this->saved_by_worker();
        $DB->set_field('local_evidencehub_meta', 'issuer', 'Changed after the page loaded', ['id' => $loaded->id]);
        $this->setUser($this->verifier);
        $decided = details::verify((int) $loaded->id);
        $this->assertSame('Changed after the page loaded', $decided->issuer);
        $this->assertSame(meta::VERIFY_VERIFIED, (int) $decided->verifystatus);
    }

    /**
     * A reset from a row read before a concurrent edit keeps the newer details and updates the row
     * it was given.
     *
     * @return void
     */
    public function test_a_reset_from_a_stale_row_keeps_newer_details(): void {
        global $DB;
        $meta = $this->saved_by_worker();
        $this->setUser($this->verifier);
        details::verify((int) $meta->id);
        $stale = details::get((int) $meta->userevidenceid);
        $DB->set_field('local_evidencehub_meta', 'issuer', 'Newer', ['id' => $meta->id]);

        $this->assertTrue(details::apply_edit_rule($stale, (int) $this->worker->id));
        $stored = details::get((int) $meta->userevidenceid);
        $this->assertSame('Newer', $stored->issuer);
        $this->assertSame(meta::VERIFY_PENDING, (int) $stored->verifystatus);
        $this->assertSame(meta::VERIFY_PENDING, (int) $stale->verifystatus);
        $this->assertNull($stale->verifiedby);
    }
}
