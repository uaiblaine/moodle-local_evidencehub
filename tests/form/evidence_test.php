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
 * Tests for the evidence form.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_evidencehub\form;

use local_evidencehub\local\audit;
use local_evidencehub\local\details;
use local_evidencehub\local\meta;
use local_evidencehub\tests\scenario;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The core evidence and its details are saved together, through core's API, by who may manage them.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(evidence::class)]
final class evidence_test extends \advanced_testcase {
    use scenario;

    /** @var \stdClass The worker. */
    private \stdClass $worker;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('enabled', 1, 'core_competency');
        $this->worker = $this->user_with();
    }

    /**
     * What the modal posts, with some values replaced.
     *
     * @param int $userid The worker argument.
     * @param int $id The core evidence id argument, 0 to create.
     * @param array $overrides Field => value.
     * @return array
     */
    private function submission(int $userid, int $id = 0, array $overrides = []): array {
        return $overrides + [
            'userid' => $userid,
            'id' => $id,
            'name' => 'First aid certificate',
            'description' => ['text' => '<p>Course of 2026</p>', 'format' => FORMAT_HTML, 'itemid' => 0],
            'url' => '',
            'files' => file_get_unused_draft_itemid(),
            'typeid' => $this->type_id('nrcertificate'),
            'issuedate' => ['day' => 15, 'month' => 1, 'year' => 2026],
            'noexpiry' => 0,
            'expirydate' => ['enabled' => 1, 'day' => 15, 'month' => 1, 'year' => 2028],
            'issuer' => 'Training centre',
            'authcode' => '',
            'hours' => '8',
        ];
    }

    /**
     * The form as the modal's web service builds it for a submission.
     *
     * @param array $submission The posted values.
     * @return evidence
     */
    private function submitted(array $submission): evidence {
        return new evidence(null, null, 'post', '', [], true, evidence::mock_ajax_submit($submission), true);
    }

    /**
     * The errors of a form that did not validate.
     *
     * @param evidence $form The form.
     * @return array Field => error.
     */
    private function errors(evidence $form): array {
        $mform = (new \ReflectionProperty(\moodleform::class, '_form'))->getValue($form);
        return $mform->_errors;
    }

    /**
     * A worker creates an evidence: core's row through core's API (its event, its files) and the
     * details beside it.
     *
     * @return void
     */
    public function test_a_worker_creates_evidence_with_details(): void {
        global $DB, $USER;
        $this->setUser($this->worker);
        $submission = $this->submission((int) $this->worker->id);
        get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance($USER->id)->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => $submission['files'],
            'filepath' => '/',
            'filename' => 'certificate.pdf',
        ], 'A certificate');

        $sink = $this->redirectEvents();
        $form = $this->submitted($submission);
        $this->assertTrue($form->is_validated(), json_encode($this->errors($form)));
        $id = $form->process_dynamic_submission();
        $events = array_map(fn($event): string => get_class($event), $sink->get_events());
        $sink->close();

        $ue = new \core_competency\user_evidence($id);
        $this->assertSame((int) $this->worker->id, (int) $ue->get('userid'));
        $this->assertSame('First aid certificate', $ue->get('name'));
        $this->assertContains(\core\event\competency_user_evidence_created::class, $events);
        $this->assertContains(\local_evidencehub\event\evidence_details_updated::class, $events);
        $files = array_map(fn(\stored_file $file): string => $file->get_filename(), $ue->get_files());
        $this->assertSame(['certificate.pdf'], array_values($files));

        $meta = details::get($id);
        $this->assertSame(20260115, (int) $meta->issuedate);
        $this->assertSame(20280115, (int) $meta->expirydate);
        $this->assertSame('Training centre', $meta->issuer);
        $this->assertEqualsWithDelta(8.0, (float) $meta->hours, 0.001);
        $this->assertFalse($DB->record_exists('local_evidencehub_audit', ['metaid' => $meta->id, 'action' => audit::ACTION_RESET]));
    }

    /**
     * A user who cannot manage the worker's evidence never reaches the form.
     *
     * @return void
     */
    public function test_creating_for_another_user_needs_manage_rights(): void {
        $this->setUser($this->user_with(['moodle/competency:userevidenceview']));
        $this->expectException(\required_capability_exception::class);
        $this->submitted($this->submission((int) $this->worker->id));
    }

    /**
     * An evidence of one user is never edited through another user's page, even by a manager of both.
     *
     * @return void
     */
    public function test_an_evidence_is_edited_only_under_its_owner(): void {
        $other = $this->user_with();
        $ue = $this->evidence_of((int) $this->worker->id);
        $this->setUser($this->user_with(['moodle/competency:userevidencemanage']));

        // Control: under its owner the same manager reaches the form.
        $form = $this->submitted($this->submission((int) $this->worker->id, (int) $ue->get('id')));
        $this->assertInstanceOf(evidence::class, $form);

        try {
            $this->submitted($this->submission((int) $other->id, (int) $ue->get('id')));
            $this->fail('The evidence was edited through another user\'s page.');
        } catch (\moodle_exception $e) {
            $this->assertSame('errorwronguser', $e->errorcode);
        }
    }

    /**
     * When the details are refused after core created its row, core's row goes too.
     *
     * @return void
     */
    public function test_a_refused_details_save_rolls_the_core_row_back(): void {
        global $DB;
        // On PostgreSQL the test itself runs inside a transaction, which would swallow the rollback.
        $this->preventResetByRollback();
        $this->setUser($this->worker);
        $form = $this->submitted($this->submission((int) $this->worker->id));
        $this->assertTrue($form->is_validated());
        // The type disappears between validation and save, so only details::save() can refuse.
        $DB->delete_records('local_evidencehub_type', ['id' => $this->type_id('nrcertificate')]);
        try {
            $form->process_dynamic_submission();
            $this->fail('The details were saved with a deleted type.');
        } catch (\moodle_exception $e) {
            $this->assertSame('errorunknowntype', $e->errorcode);
        }
        $this->assertSame(0, $DB->count_records('competency_userevidence', ['userid' => $this->worker->id]));
        $this->assertSame(0, $DB->count_records('local_evidencehub_meta'));
    }

    /**
     * The API's rules show on their fields, and the core URL rule on the URL.
     *
     * @return void
     */
    public function test_rules_show_on_their_fields(): void {
        $this->setUser($this->worker);
        $form = $this->submitted($this->submission((int) $this->worker->id, 0, [
            'typeid' => $this->type_id('diploma'),
            'expirydate' => ['day' => 1, 'month' => 1, 'year' => 2030],
            'url' => 'not a url',
            'hours' => '-1',
        ]));
        $this->assertFalse($form->is_validated());
        $errors = $this->errors($form);
        $this->assertSame(get_string('errorexpiryrequired', 'local_evidencehub'), $errors['expirydate']);
        $this->assertSame(get_string('errorhours', 'local_evidencehub'), $errors['hours']);
        $this->assertSame(get_string('invalidurl', 'core_competency'), $errors['url']);

        // Control: the same submission with the fields fixed validates.
        $form = $this->submitted($this->submission((int) $this->worker->id, 0, [
            'typeid' => $this->type_id('diploma'),
            'noexpiry' => 1,
        ]));
        $this->assertTrue($form->is_validated(), json_encode($this->errors($form)));
    }

    /**
     * "Complete details" is the same form on an evidence without details: core's fields prefilled,
     * the details saved beside it.
     *
     * @return void
     */
    public function test_completing_the_details_of_a_tool_lp_evidence(): void {
        $ue = $this->evidence_of((int) $this->worker->id);
        $this->setUser($this->worker);
        $args = ['userid' => (int) $this->worker->id, 'id' => (int) $ue->get('id')];
        $form = new evidence(null, null, 'post', '', [], true, $args, true);
        $form->set_data_for_dynamic_submission();
        $mform = (new \ReflectionProperty(\moodleform::class, '_form'))->getValue($form);
        $this->assertSame($ue->get('name'), $mform->getElement('name')->getValue());
        $this->assertNull(details::get((int) $ue->get('id')));

        $form = $this->submitted($this->submission((int) $this->worker->id, (int) $ue->get('id'), ['name' => $ue->get('name')]));
        $this->assertTrue($form->is_validated(), json_encode($this->errors($form)));
        $form->process_dynamic_submission();
        $meta = details::get((int) $ue->get('id'));
        $this->assertSame(20260115, (int) $meta->issuedate);
        $this->assertSame([[audit::ACTION_CREATED, (int) $this->worker->id]], $this->trail((int) $meta->id));
    }

    /**
     * The owner's edit of a verified evidence resets it once: the core observer resets, the
     * details save that follows finds it pending.
     *
     * @return void
     */
    public function test_an_edit_after_verification_resets_once(): void {
        $ue = $this->evidence_of((int) $this->worker->id);
        $this->setUser($this->worker);
        $meta = details::save($ue, $this->details_data());
        $this->setUser($this->user_with(['local/evidencehub:verify', 'moodle/competency:userevidenceview']));
        details::verify((int) $meta->id);

        $this->setUser($this->worker);
        $form = $this->submitted($this->submission((int) $this->worker->id, (int) $ue->get('id'), ['issuer' => 'Edited']));
        $this->assertTrue($form->is_validated(), json_encode($this->errors($form)));
        $form->process_dynamic_submission();

        $saved = details::get((int) $ue->get('id'));
        $this->assertSame(meta::VERIFY_PENDING, (int) $saved->verifystatus);
        $this->assertSame('Edited', $saved->issuer);
        $actions = array_column($this->trail((int) $meta->id), 0);
        $this->assertSame([audit::ACTION_CREATED, audit::ACTION_VERIFIED, audit::ACTION_RESET, audit::ACTION_DETAILS], $actions);
    }

    /**
     * A manager without viewsensitive never opens the edit form of sensitive details; with it they
     * do, and so does the owner.
     *
     * @return void
     */
    public function test_the_edit_form_of_sensitive_details_needs_viewsensitive(): void {
        $ue = $this->evidence_of((int) $this->worker->id);
        $this->setUser($this->worker);
        details::save($ue, $this->details_data(['typeid' => $this->type_id('aso'), 'expirydate' => 20270115]));
        $args = ['userid' => (int) $this->worker->id, 'id' => (int) $ue->get('id')];

        $manager = $this->user_with(['moodle/competency:userevidencemanage']);
        $this->setUser($manager);
        try {
            new evidence(null, null, 'post', '', [], true, $args, true);
            $this->fail('A manager without viewsensitive opened the edit form of sensitive details.');
        } catch (\moodle_exception $e) {
            $this->assertSame('errorrestricted', $e->errorcode);
        }

        $this->grant($manager, ['local/evidencehub:viewsensitive']);
        $form = new evidence(null, null, 'post', '', [], true, $args, true);
        $form->set_data_for_dynamic_submission();
        $mform = (new \ReflectionProperty(\moodleform::class, '_form'))->getValue($form);
        $this->assertSame('Training centre', $mform->getElement('issuer')->getValue());

        $this->setUser($this->worker);
        $this->assertInstanceOf(evidence::class, new evidence(null, null, 'post', '', [], true, $args, true));
    }

    /**
     * A manager without viewsensitive still creates a new evidence of a sensitive type.
     *
     * @return void
     */
    public function test_a_manager_without_viewsensitive_creates_sensitive_evidence(): void {
        $this->setUser($this->user_with(['moodle/competency:userevidencemanage']));
        $form = $this->submitted($this->submission((int) $this->worker->id, 0, [
            'typeid' => $this->type_id('aso'),
            'expirydate' => ['enabled' => 1, 'day' => 15, 'month' => 1, 'year' => 2027],
        ]));
        $this->assertTrue($form->is_validated(), json_encode($this->errors($form)));
        $id = $form->process_dynamic_submission();
        $this->assertSame($this->type_id('aso'), (int) details::get($id)->typeid);
    }
}
