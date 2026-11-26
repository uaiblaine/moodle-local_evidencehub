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

namespace local_evidencehub\form;

use local_evidencehub\local\details;
use local_evidencehub\local\meta;

/**
 * Verify or reject the validity details of one evidence.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class verify extends \core_form\dynamic_form {
    /** @var \stdClass|null The meta row, read once. */
    private ?\stdClass $meta = null;

    /**
     * The meta row being decided on.
     *
     * @return \stdClass
     */
    private function get_meta(): \stdClass {
        global $DB;
        if ($this->meta === null) {
            $id = (int) $this->optional_param('id', 0, PARAM_INT);
            $this->meta = $DB->get_record(details::TABLE, ['id' => $id], '*', MUST_EXIST);
        }
        return $this->meta;
    }

    /**
     * The form fields.
     *
     * @return void
     */
    #[\Override]
    protected function definition(): void {
        $mform = $this->_form;
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $choices = [];
        foreach ([meta::VERIFY_VERIFIED => 'decisionverify', meta::VERIFY_REJECTED => 'decisionreject'] as $value => $stringid) {
            $choices[] = $mform->createElement('radio', 'decision', '', get_string($stringid, 'local_evidencehub'), $value);
        }
        $mform->addGroup($choices, 'decisiongroup', get_string('decision', 'local_evidencehub'), [' '], false);
        $mform->setType('decision', PARAM_INT);
        $mform->setDefault('decision', meta::VERIFY_VERIFIED);
        $mform->addElement('textarea', 'note', get_string('note', 'local_evidencehub'), ['rows' => 4]);
        $mform->setType('note', PARAM_TEXT);
    }

    /**
     * The API's rules on their fields.
     *
     * @param array $data The submitted values.
     * @param array $files The submitted files.
     * @return array Field name => error.
     */
    #[\Override]
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        $decision = (int) ($data['decision'] ?? 0);
        if (!in_array($decision, [meta::VERIFY_VERIFIED, meta::VERIFY_REJECTED], true)) {
            $errors['decisiongroup'] = get_string('required');
        } else if ($decision === (int) $this->get_meta()->verifystatus) {
            $stringid = $decision === meta::VERIFY_VERIFIED ? 'alreadyverified' : 'alreadyrejected';
            $errors['decisiongroup'] = get_string($stringid, 'local_evidencehub');
        } else if ($decision === meta::VERIFY_REJECTED && trim((string) ($data['note'] ?? '')) === '') {
            $errors['note'] = get_string('rejectneedsnote', 'local_evidencehub');
        }
        return $errors;
    }

    /**
     * The worker's user context.
     *
     * @return \context
     */
    #[\Override]
    protected function get_context_for_dynamic_submission(): \context {
        return \context_user::instance((int) $this->get_meta()->userid);
    }

    /**
     * Verification rights in the worker's context, never over one's own evidence.
     *
     * @return void
     * @throws \required_capability_exception Without local/evidencehub:verify or the right to read the evidence.
     * @throws \moodle_exception With competencies disabled, on the user's own evidence, or details restricted for the
     *     user.
     */
    #[\Override]
    protected function check_access_for_dynamic_submission(): void {
        global $USER;
        \core_competency\api::require_enabled();
        $context = $this->get_context_for_dynamic_submission();
        $ownerid = (int) $this->get_meta()->userid;
        require_capability('local/evidencehub:verify', $context);
        if (!\core_competency\user_evidence::can_read_user($ownerid)) {
            throw new \required_capability_exception($context, 'moodle/competency:userevidenceview', 'nopermissions', '');
        }
        if ($ownerid === (int) $USER->id) {
            throw new \moodle_exception('cannotverifyown', 'local_evidencehub');
        }
        if (details::is_restricted($this->get_meta())) {
            throw new \moodle_exception('errorrestricted', 'local_evidencehub');
        }
    }

    /**
     * Record the decision through the API, which checks the same rules again.
     *
     * @return int The meta row id.
     */
    #[\Override]
    public function process_dynamic_submission(): int {
        $data = $this->get_data();
        $id = (int) $this->get_meta()->id;
        if ((int) $data->decision === meta::VERIFY_REJECTED) {
            details::reject($id, (string) $data->note);
        } else {
            details::verify($id, (string) $data->note);
        }
        return $id;
    }

    /**
     * Start from the current decision's opposite, with no note.
     *
     * @return void
     */
    #[\Override]
    public function set_data_for_dynamic_submission(): void {
        $meta = $this->get_meta();
        $decision = (int) $meta->verifystatus === meta::VERIFY_VERIFIED ? meta::VERIFY_REJECTED : meta::VERIFY_VERIFIED;
        $this->set_data(['id' => (int) $meta->id, 'decision' => $decision]);
    }

    /**
     * The worker's evidence page.
     *
     * @return \moodle_url
     */
    #[\Override]
    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        return new \moodle_url('/local/evidencehub/evidence.php', ['userid' => (int) $this->get_meta()->userid]);
    }
}
