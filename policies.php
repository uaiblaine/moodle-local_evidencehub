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
 * The validity policies of the competency frameworks the user may manage.
 *
 * Without frameworkid it lists those frameworks; with it, the framework's own policy and the policy
 * in force for each of its competencies.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_evidencehub\local\policy;

require_login(null, false);
if (isguestuser()) {
    throw new require_login_exception('Guests are not allowed here.');
}

$frameworkid = optional_param('frameworkid', 0, PARAM_INT);
$title = get_string('policiespage', 'local_evidencehub');
$listurl = new moodle_url('/local/evidencehub/policies.php');
$url = $frameworkid ? new moodle_url($listurl, ['frameworkid' => $frameworkid]) : $listurl;

$PAGE->set_url($url);
// A framework's policies live in the framework's context: the system or a course category.
$PAGE->set_context($frameworkid ? policy::framework_context($frameworkid) : context_system::instance());
$PAGE->set_pagelayout('admin');
$PAGE->set_title($title);
$PAGE->set_heading($title);
// The list's admin entry is already in the breadcrumb when active; a framework's page needs the list as its parent.
if (!$PAGE->settingsnav->find_active_node()) {
    $PAGE->navbar->add($title, $listurl);
}

if (!\core_competency\api::is_enabled()) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('competenciesarenotenabled', 'core_competency'));
    echo $OUTPUT->footer();
    die();
}

if (!$frameworkid) {
    // Each framework is checked in its own context, so no capability is required to open the list.
    echo $OUTPUT->header();
    echo $OUTPUT->heading($title);
    $list = new \local_evidencehub\output\policy_frameworks();
    echo $OUTPUT->render_from_template('local_evidencehub/policy_frameworks', $list->export_for_template($OUTPUT));
    echo $OUTPUT->footer();
    die();
}

policy::require_manage($frameworkid, 0);

if (optional_param('action', '', PARAM_ALPHA) === 'removeoverride') {
    // The page's own form posts it; a link or a prefetched GET never removes a policy.
    \local_evidencehub\local\dossier_export::require_post();
    require_sesskey();
    $competencyid = required_param('competencyid', PARAM_INT);
    if (!$competencyid) {
        throw new moodle_exception('invalidparameter', 'debug');
    }
    policy::delete($frameworkid, $competencyid);
    redirect($url, get_string('overrideremoved', 'local_evidencehub'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$shortname = $DB->get_field('competency_framework', 'shortname', ['id' => $frameworkid], MUST_EXIST);
$name = format_string($shortname, true, ['context' => $PAGE->context]);
$PAGE->navbar->add($name, $url);
$PAGE->requires->js_call_amd('local_evidencehub/policies', 'init');

// Until header() runs, $OUTPUT is core's bootstrap renderer: every export comes after it.
echo $OUTPUT->header();
echo $OUTPUT->heading($name);
$page = new \local_evidencehub\output\framework_policies($frameworkid);
echo $OUTPUT->render_from_template('local_evidencehub/framework_policies', $page->export_for_template($OUTPUT));
echo $OUTPUT->footer();
