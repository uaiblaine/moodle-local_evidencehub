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
 * A worker's evidence of prior learning with its validity details.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login(null, false);
if (isguestuser()) {
    throw new require_login_exception('Guests are not allowed here.');
}

$userid = optional_param('userid', $USER->id, PARAM_INT);
$user = core_user::get_user($userid);
if (!$user || !core_user::is_real_user($userid)) {
    throw new moodle_exception('invaliduser', 'error');
}
$context = context_user::instance($userid);
$title = get_string('evidencepage', 'local_evidencehub');

$PAGE->set_url(new moodle_url('/local/evidencehub/evidence.php', ['userid' => $userid]));
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title($title);

if (!\core_competency\api::is_enabled()) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('competenciesarenotenabled', 'core_competency'));
    echo $OUTPUT->footer();
    die();
}
if (!\core_competency\user_evidence::can_read_user($userid)) {
    throw new required_capability_exception($context, 'moodle/competency:userevidenceview', 'nopermissions', '');
}

// Only now, for a reader of the worker's evidence, does the page name the worker.
if ($userid != $USER->id) {
    $PAGE->navigation->extend_for_user($user);
    $PAGE->set_heading(fullname($user));
}

$page = new \local_evidencehub\output\evidence_page($userid);
$PAGE->requires->js_call_amd('local_evidencehub/evidence_page', 'init');

echo $OUTPUT->header();
echo $OUTPUT->heading($title);
echo $OUTPUT->render_from_template('local_evidencehub/evidence_page', $page->export_for_template($OUTPUT));
echo $OUTPUT->footer();
