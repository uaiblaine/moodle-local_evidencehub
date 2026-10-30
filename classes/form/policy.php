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

use local_evidencehub\local\policy as policy_api;

/**
 * The validity policy of a competency framework, or of one competency overriding it.
 *
 * Custom data / ajax args: frameworkid, and competencyid (0 for the framework's own policy).
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class policy extends \core_form\dynamic_form {
    /** @var array The policy fields the form edits, as policy_api::save() reads them. */
    private const array FIELDS = [
        'kind', 'requireverified', 'requireproficiency', 'minhours', 'alert1', 'alert2', 'gracedays', 'cyclemonths', 'onexpiry',
    ];

    /**
     * The framework whose policy this is.
     *
     * @return int
     */
    private function get_frameworkid(): int {
        return (int) $this->optional_param('frameworkid', 0, PARAM_INT);
    }

    /**
     * The competency whose own policy this is, 0 for the framework's.
     *
     * @return int
     */
    private function get_competencyid(): int {
        return (int) $this->optional_param('competencyid', 0, PARAM_INT);
    }

    /**
     * The form fields.
     *
     * @return void
     */
    #[\Override]
    protected function definition(): void {
        $mform = $this->_form;
        $mform->addElement('hidden', 'frameworkid');
        $mform->setType('frameworkid', PARAM_INT);
        $mform->addElement('hidden', 'competencyid');
        $mform->setType('competencyid', PARAM_INT);

        $kinds = [];
        foreach ([policy_api::KIND_REGULATORY, policy_api::KIND_TECHNICAL, policy_api::KIND_BEHAVIOURAL] as $kind) {
            $kinds[$kind] = policy_api::kind_label($kind);
        }
        $mform->addElement('select', 'kind', get_string('kind', 'local_evidencehub'), $kinds);
        $mform->addHelpButton('kind', 'kind', 'local_evidencehub');
        $mform->addElement('advcheckbox', 'requireverified', get_string('requireverified', 'local_evidencehub'));
        $mform->addElement('advcheckbox', 'requireproficiency', get_string('requireproficiency', 'local_evidencehub'));
        $mform->hideIf('requireproficiency', 'kind', 'neq', policy_api::KIND_TECHNICAL);
        $mform->addElement('float', 'minhours', get_string('minhours', 'local_evidencehub'));
        foreach (['alert1', 'alert2', 'gracedays', 'cyclemonths'] as $field) {
            $mform->addElement('text', $field, get_string($field, 'local_evidencehub'), ['size' => 5]);
            $mform->setType($field, PARAM_INT);
        }
        // Only the alerts are always shown. A field hidden by hideIf is also disabled, so the browser
        // never submits it, and a required rule on it would refuse every save of that kind.
        $mform->addRule('alert1', null, 'required', null, 'client');
        $mform->addRule('alert2', null, 'required', null, 'client');
        $mform->addHelpButton('gracedays', 'gracedays', 'local_evidencehub');
        $mform->hideIf('gracedays', 'kind', 'neq', policy_api::KIND_TECHNICAL);
        $mform->addHelpButton('cyclemonths', 'cyclemonths', 'local_evidencehub');
        $mform->hideIf('cyclemonths', 'kind', 'eq', policy_api::KIND_REGULATORY);
        $mform->addElement('select', 'onexpiry', get_string('onexpiry', 'local_evidencehub'), [
            policy_api::ONEXPIRY_LOG => policy_api::onexpiry_label(policy_api::ONEXPIRY_LOG),
            policy_api::ONEXPIRY_REVIEW => policy_api::onexpiry_label(policy_api::ONEXPIRY_REVIEW),
        ]);
        $mform->addHelpButton('onexpiry', 'onexpiry', 'local_evidencehub');
        $mform->setDisableShortforms();
    }

    /**
     * The API's rules, shown on their fields.
     *
     * @param array $data The submitted values.
     * @param array $files The submitted files.
     * @return array Field name => error.
     */
    #[\Override]
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        foreach (policy_api::problems((object) $data) as $field => $stringid) {
            $errors[$field] = get_string($stringid, 'local_evidencehub');
        }
        return $errors;
    }

    /**
     * The framework's context, where its policies are managed.
     *
     * @return \context
     */
    #[\Override]
    protected function get_context_for_dynamic_submission(): \context {
        return policy_api::framework_context($this->get_frameworkid());
    }

    /**
     * local/evidencehub:managepolicies in the framework's context, over a competency of that framework.
     *
     * @return void
     * @throws \required_capability_exception Without the capability.
     * @throws \moodle_exception When the competency belongs to another framework.
     */
    #[\Override]
    protected function check_access_for_dynamic_submission(): void {
        \core_competency\api::require_enabled();
        policy_api::require_manage($this->get_frameworkid(), $this->get_competencyid());
    }

    /**
     * Save through the API, which checks the same rules again.
     *
     * @return int The policy row id.
     */
    #[\Override]
    public function process_dynamic_submission(): int {
        $data = $this->get_data();
        $policy = (object) [
            'frameworkid' => $this->get_frameworkid(),
            'competencyid' => $this->get_competencyid(),
        ];
        foreach (self::FIELDS as $field) {
            // A field its kind hides is not submitted: the column's own default, 0, applies.
            $policy->$field = $data->$field ?? ($field === 'minhours' ? null : 0);
        }
        return (int) policy_api::save($policy)->id;
    }

    /**
     * Start from the row being edited, or for a new one from the policy in force, with the
     * defaults of its kind (a regulatory policy counts verified evidence only).
     *
     * @return void
     */
    #[\Override]
    public function set_data_for_dynamic_submission(): void {
        global $DB;
        $frameworkid = $this->get_frameworkid();
        $competencyid = $this->get_competencyid();
        $current = $DB->get_record(policy_api::TABLE, ['frameworkid' => $frameworkid, 'competencyid' => $competencyid]);
        if ($current) {
            $values = (array) $current;
        } else {
            // A competency's new override starts from the framework's policy; a framework's from the default.
            $inherited = $competencyid ? policy_api::resolve($competencyid, $frameworkid) : policy_api::defaults();
            $values = (array) $inherited;
            $values = array_merge($values, (array) policy_api::kind_defaults((int) $inherited->kind));
        }
        $data = ['frameworkid' => $frameworkid, 'competencyid' => $competencyid];
        foreach (self::FIELDS as $field) {
            $data[$field] = $values[$field] ?? null;
        }
        if ($data['minhours'] !== null) {
            $data['minhours'] = (float) $data['minhours'];
        }
        $this->set_data($data);
    }

    /**
     * The framework's policies page.
     *
     * @return \moodle_url
     */
    #[\Override]
    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        return new \moodle_url('/local/evidencehub/policies.php', ['frameworkid' => $this->get_frameworkid()]);
    }
}
