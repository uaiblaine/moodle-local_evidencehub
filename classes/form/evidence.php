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

use core_competency\api;
use core_competency\user_evidence;
use local_evidencehub\local\details;
use local_evidencehub\local\meta;

/**
 * One core evidence of prior learning and its validity details, created or edited in one submit.
 *
 * The core fields mirror tool_lp's own form ({@see \tool_lp\form\user_evidence}) and are saved
 * through core_competency\api, never written directly. Without an id it creates; with the id of
 * an evidence that has no details yet it is the "complete details" form.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class evidence extends \core_form\dynamic_form {
    /** @var array The file manager options of tool_lp's user evidence form. */
    private const array FILEAREA_OPTIONS = ['subdirs' => false];

    /**
     * The worker whose evidence this is.
     *
     * @return int
     */
    private function get_userid(): int {
        return (int) $this->optional_param('userid', 0, PARAM_INT);
    }

    /**
     * The core evidence being edited, 0 when creating.
     *
     * @return int
     */
    private function get_evidenceid(): int {
        return (int) $this->optional_param('id', 0, PARAM_INT);
    }

    /**
     * The form fields.
     *
     * @return void
     */
    #[\Override]
    protected function definition(): void {
        global $DB;
        $mform = $this->_form;

        $mform->addElement('hidden', 'userid');
        $mform->setType('userid', PARAM_INT);
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('text', 'name', get_string('userevidencename', 'tool_lp'), 'maxlength="100"');
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 100), 'maxlength', 100, 'client');
        $mform->addElement('editor', 'description', get_string('userevidencedescription', 'tool_lp'), ['rows' => 10]);
        $mform->setType('description', PARAM_CLEANHTML);
        $mform->addElement('url', 'url', get_string('userevidenceurl', 'tool_lp'), ['size' => '60'], ['usefilepicker' => false]);
        // Core's own choice: PARAM_URL silently turns a bad URL into an empty one.
        $mform->setType('url', PARAM_RAW_TRIMMED);
        $mform->addHelpButton('url', 'userevidenceurl', 'tool_lp');
        $mform->addElement('filemanager', 'files', get_string('userevidencefiles', 'tool_lp'), [], self::FILEAREA_OPTIONS);

        $mform->addElement('header', 'validityhdr', get_string('validitydetails', 'local_evidencehub'));
        $mform->setExpanded('validityhdr');
        $types = [];
        foreach ($DB->get_records('local_evidencehub_type', null, 'sortorder, id', 'id, name') as $type) {
            $types[$type->id] = format_string($type->name, true, ['context' => \context_system::instance()]);
        }
        $mform->addElement('select', 'typeid', get_string('type', 'local_evidencehub'), $types);
        $mform->addRule('typeid', null, 'required', null, 'client');
        $mform->addElement('date_selector', 'issuedate', get_string('issuedate', 'local_evidencehub'));
        $mform->addElement('advcheckbox', 'noexpiry', get_string('noexpiry', 'local_evidencehub'));
        $mform->addElement('date_selector', 'expirydate', get_string('expirydate', 'local_evidencehub'), ['optional' => true]);
        $mform->addHelpButton('expirydate', 'expirydate', 'local_evidencehub');
        $mform->hideIf('expirydate', 'noexpiry', 'checked');
        $mform->addElement('text', 'issuer', get_string('issuer', 'local_evidencehub'), ['maxlength' => details::ISSUER_MAXLENGTH]);
        $mform->setType('issuer', PARAM_TEXT);
        $mform->addElement('text', 'authcode', get_string('authcode', 'local_evidencehub'), [
            'maxlength' => details::AUTHCODE_MAXLENGTH,
        ]);
        $mform->setType('authcode', PARAM_TEXT);
        $mform->addElement('float', 'hours', get_string('hours', 'local_evidencehub'));

        $mform->setDisableShortforms();
    }

    /**
     * The API's rules, shown on their fields rather than as an exception.
     *
     * @param array $data The submitted values.
     * @param array $files The submitted files.
     * @return array Field name => error.
     */
    #[\Override]
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        // The persistent's own rule (user_evidence::validate_url()), which tool_lp's persistent form shows on the field.
        $url = (string) ($data['url'] ?? '');
        if ($url !== '' && !preg_match('@^https?://.+@', $url)) {
            $errors['url'] = get_string('invalidurl', 'core_competency');
        }
        foreach (details::problems(self::details_from((object) $data)) as $field => $stringid) {
            $errors[$field] = get_string($stringid, 'local_evidencehub');
        }
        return $errors;
    }

    /**
     * The details out of the submitted values, dates turned into calendar days.
     *
     * @param \stdClass $data The submitted values.
     * @return \stdClass The details as details::save() reads them.
     */
    private static function details_from(\stdClass $data): \stdClass {
        return (object) [
            'typeid' => (int) ($data->typeid ?? 0),
            'issuedate' => empty($data->issuedate) ? 0 : meta::from_timestamp((int) $data->issuedate),
            'expirydate' => empty($data->expirydate) ? null : meta::from_timestamp((int) $data->expirydate),
            'noexpiry' => (int) !empty($data->noexpiry),
            'issuer' => $data->issuer ?? null,
            'authcode' => $data->authcode ?? null,
            'hours' => $data->hours ?? null,
        ];
    }

    /**
     * The worker's user context.
     *
     * @return \context
     */
    #[\Override]
    protected function get_context_for_dynamic_submission(): \context {
        return \context_user::instance($this->get_userid());
    }

    /**
     * Core's own rights over the evidence, which must belong to the worker named and whose details
     * the user may read.
     *
     * @return void
     * @throws \required_capability_exception Without the right to manage the worker's evidence.
     * @throws \moodle_exception When the evidence belongs to another user, or its details are restricted for the user.
     */
    #[\Override]
    protected function check_access_for_dynamic_submission(): void {
        api::require_enabled();
        $userid = $this->get_userid();
        $id = $this->get_evidenceid();
        if ($id) {
            $ue = new user_evidence($id);
            if ((int) $ue->get('userid') !== $userid) {
                throw new \moodle_exception('errorwronguser', 'local_evidencehub');
            }
            $allowed = $ue->can_manage();
            // The form would load details the user may not read; set_data never runs for them.
            $meta = details::get($id);
            if ($allowed && $meta !== null && details::is_restricted($meta)) {
                throw new \moodle_exception('errorrestricted', 'local_evidencehub');
            }
        } else {
            $allowed = user_evidence::can_manage_user($userid);
        }
        if (!$allowed) {
            throw new \required_capability_exception(
                $this->get_context_for_dynamic_submission(),
                'moodle/competency:userevidencemanage',
                'nopermissions',
                ''
            );
        }
    }

    /**
     * Save the core evidence through core's API and then its details, both or neither.
     *
     * @return int The core user evidence id.
     */
    #[\Override]
    public function process_dynamic_submission(): int {
        global $DB;
        $data = $this->get_data();
        $record = (object) [
            'userid' => $this->get_userid(),
            'name' => $data->name,
            'description' => $data->description['text'],
            'descriptionformat' => $data->description['format'],
            'url' => $data->url,
        ];
        $transaction = $DB->start_delegated_transaction();
        try {
            if ($this->get_evidenceid()) {
                $record->id = $this->get_evidenceid();
                $ue = api::update_user_evidence($record, $data->files);
            } else {
                $ue = api::create_user_evidence($record, $data->files);
            }
            details::save($ue, self::details_from($data));
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            $transaction->rollback($e);
        }
        return (int) $ue->get('id');
    }

    /**
     * Load core's evidence and its details, when editing.
     *
     * @return void
     */
    #[\Override]
    public function set_data_for_dynamic_submission(): void {
        $id = $this->get_evidenceid();
        $context = $this->get_context_for_dynamic_submission();
        $data = (object) [
            'userid' => $this->get_userid(),
            'id' => $id,
            'issuedate' => time(),
        ];
        $draftitemid = 0;
        file_prepare_draft_area(
            $draftitemid,
            $context->id,
            'core_competency',
            'userevidence',
            $id ?: null,
            self::FILEAREA_OPTIONS
        );
        $data->files = $draftitemid;
        if ($id) {
            $ue = new user_evidence($id);
            $data->name = $ue->get('name');
            $data->description = ['text' => $ue->get('description'), 'format' => $ue->get('descriptionformat')];
            $data->url = $ue->get('url');
            $meta = details::get($id);
            if ($meta) {
                $data->typeid = (int) $meta->typeid;
                $data->issuedate = meta::to_timestamp((int) $meta->issuedate);
                $data->noexpiry = (int) $meta->noexpiry;
                $data->expirydate = $meta->expirydate === null ? 0 : meta::to_timestamp((int) $meta->expirydate);
                $data->issuer = $meta->issuer;
                $data->authcode = $meta->authcode;
                $data->hours = $meta->hours;
            }
        }
        $this->set_data($data);
    }

    /**
     * The worker's evidence page.
     *
     * @return \moodle_url
     */
    #[\Override]
    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        return new \moodle_url('/local/evidencehub/evidence.php', ['userid' => $this->get_userid()]);
    }
}
