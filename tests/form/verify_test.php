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
 * Tests for the verification form.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_evidencehub\form;

use local_evidencehub\local\details;
use local_evidencehub\local\meta;
use local_evidencehub\tests\scenario;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Only a verifier other than the owner reaches the form, and a rejection needs its note.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(verify::class)]
final class verify_test extends \advanced_testcase {
    use scenario;

    /** @var \stdClass The worker. */
    private \stdClass $worker;

    /** @var \stdClass A verifier. */
    private \stdClass $verifier;

    /** @var \stdClass The worker's details. */
    private \stdClass $meta;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('enabled', 1, 'core_competency');
        $this->worker = $this->user_with();
        $this->verifier = $this->user_with(['local/evidencehub:verify', 'moodle/competency:userevidenceview']);
        $ue = $this->evidence_of((int) $this->worker->id);
        $this->setUser($this->worker);
        $this->meta = details::save($ue, $this->details_data());
    }

    /**
     * The form as the modal's web service builds it for a submission.
     *
     * @param int $decision A meta::VERIFY_* decision.
     * @param string $note The note.
     * @param int|null $metaid The meta row; the worker's plain details when null.
     * @return verify
     */
    private function submitted(int $decision, string $note = '', ?int $metaid = null): verify {
        $metaid ??= (int) $this->meta->id;
        $submission = verify::mock_ajax_submit(['id' => $metaid, 'decision' => $decision, 'note' => $note]);
        return new verify(null, null, 'post', '', [], true, $submission, true);
    }

    /**
     * The errors of a form that did not validate.
     *
     * @param verify $form The form.
     * @return array Field => error.
     */
    private function errors(verify $form): array {
        return (new \ReflectionProperty(\moodleform::class, '_form'))->getValue($form)->_errors;
    }

    /**
     * The current verification state of the worker's details.
     *
     * @return int
     */
    private function state(): int {
        return (int) details::get((int) $this->meta->userevidenceid)->verifystatus;
    }

    /**
     * A verifier verifies through the form.
     *
     * @return void
     */
    public function test_a_verifier_verifies(): void {
        $this->setUser($this->verifier);
        $form = $this->submitted(meta::VERIFY_VERIFIED);
        $this->assertTrue($form->is_validated(), json_encode($this->errors($form)));
        $this->assertSame((int) $this->meta->id, $form->process_dynamic_submission());
        $this->assertSame(meta::VERIFY_VERIFIED, $this->state());
    }

    /**
     * A rejection without a note shows the error on the note; with one it is recorded.
     *
     * @return void
     */
    public function test_a_rejection_needs_its_note(): void {
        $this->setUser($this->verifier);
        $form = $this->submitted(meta::VERIFY_REJECTED, ' ');
        $this->assertFalse($form->is_validated());
        $this->assertSame(get_string('rejectneedsnote', 'local_evidencehub'), $this->errors($form)['note']);
        $this->assertSame(meta::VERIFY_PENDING, $this->state());

        $form = $this->submitted(meta::VERIFY_REJECTED, 'Unreadable.');
        $this->assertTrue($form->is_validated(), json_encode($this->errors($form)));
        $form->process_dynamic_submission();
        $this->assertSame(meta::VERIFY_REJECTED, $this->state());
    }

    /**
     * The decision already taken is refused on the field.
     *
     * @return void
     */
    public function test_the_same_decision_again_shows_on_the_field(): void {
        $this->setUser($this->verifier);
        details::verify((int) $this->meta->id);
        $form = $this->submitted(meta::VERIFY_VERIFIED);
        $this->assertFalse($form->is_validated());
        $this->assertSame(get_string('alreadyverified', 'local_evidencehub'), $this->errors($form)['decisiongroup']);
    }

    /**
     * The owner never reaches the form, even holding the capability; the verifier does.
     *
     * @return void
     */
    public function test_the_owner_never_reaches_the_form(): void {
        $this->grant($this->worker, ['local/evidencehub:verify', 'moodle/competency:userevidenceview']);
        $this->setUser($this->worker);
        try {
            $this->submitted(meta::VERIFY_VERIFIED);
            $this->fail('The owner reached the verification of their own evidence.');
        } catch (\moodle_exception $e) {
            $this->assertSame('cannotverifyown', $e->errorcode);
        }
        $this->setUser($this->verifier);
        $this->assertInstanceOf(verify::class, $this->submitted(meta::VERIFY_VERIFIED));
    }

    /**
     * Without local/evidencehub:verify, or without reading the evidence, the form is refused.
     *
     * @return void
     */
    public function test_the_form_needs_both_rights(): void {
        foreach ([['moodle/competency:userevidenceview'], ['local/evidencehub:verify']] as $capabilities) {
            $this->setUser($this->user_with($capabilities));
            try {
                $this->submitted(meta::VERIFY_VERIFIED);
                $this->fail('The form was reached with only ' . implode(', ', $capabilities) . '.');
            } catch (\required_capability_exception $e) {
                $this->assertSame('nopermissions', $e->errorcode);
            }
        }
    }

    /**
     * A verifier without viewsensitive never reaches the form on sensitive details; with it they do.
     *
     * @return void
     */
    public function test_the_form_on_sensitive_details_needs_viewsensitive(): void {
        $ue = $this->evidence_of((int) $this->worker->id);
        $this->setUser($this->worker);
        $meta = details::save($ue, $this->details_data(['typeid' => $this->type_id('aso'), 'expirydate' => 20270115]));
        $this->setUser($this->verifier);
        try {
            $this->submitted(meta::VERIFY_VERIFIED, '', (int) $meta->id);
            $this->fail('A verifier without viewsensitive reached the form on sensitive details.');
        } catch (\moodle_exception $e) {
            $this->assertSame('errorrestricted', $e->errorcode);
        }
        $this->grant($this->verifier, ['local/evidencehub:viewsensitive']);
        $form = $this->submitted(meta::VERIFY_VERIFIED, '', (int) $meta->id);
        $this->assertTrue($form->is_validated(), json_encode($this->errors($form)));
        $form->process_dynamic_submission();
        $this->assertSame(meta::VERIFY_VERIFIED, (int) details::get((int) $ue->get('id'))->verifystatus);
    }

    /**
     * With competencies disabled on the site the form is refused to a verifier; enabled, it is reached.
     *
     * @return void
     */
    public function test_the_form_needs_competencies_enabled(): void {
        $this->setUser($this->verifier);
        set_config('enabled', 0, 'core_competency');
        try {
            $this->submitted(meta::VERIFY_VERIFIED);
            $this->fail('The form was reached with competencies disabled.');
        } catch (\moodle_exception $e) {
            $this->assertSame('competenciesarenotenabled', $e->errorcode);
        }
        $this->assertSame(meta::VERIFY_PENDING, $this->state());
        set_config('enabled', 1, 'core_competency');
        $this->assertInstanceOf(verify::class, $this->submitted(meta::VERIFY_VERIFIED));
    }
}
