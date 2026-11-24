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
 * The evidence hub: the (worker, competency) pairs a learning plan template requires, with their validity.
 *
 * Opens in the system context (Site administration > Competencies) or in a course category (the
 * category's More menu), locked to that category's templates.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_evidencehub\local\dossier_builder;
use local_evidencehub\local\dossier_export;
use local_evidencehub\local\hub_access;
use local_evidencehub\local\hub_scope;
use local_evidencehub\local\report_download;
use local_evidencehub\local\reportengine;
use local_evidencehub\output\hub_choose_page;
use local_evidencehub\output\hub_matrix_page;
use local_evidencehub\output\hub_report_view;
use local_evidencehub\reportbuilder\local\systemreports\hub_pairs;

$contextid = optional_param('contextid', context_system::instance()->id, PARAM_INT);

// Before the context is looked up: a visitor not logged in must not tell an existing id from a missing one.
require_login(null, false);
if (isguestuser()) {
    throw new require_login_exception('Guests are not allowed here.');
}
$context = context::instance_by_id($contextid);
hub_access::require_view($context);

$title = get_string('hubpage', 'local_evidencehub');
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/evidencehub/index.php', ['contextid' => $context->id]));
$PAGE->set_pagelayout('admin');
$PAGE->set_title($title);
if ($context->contextlevel == CONTEXT_COURSECAT) {
    \core_course_category::page_setup();
} else {
    $PAGE->set_heading($SITE->fullname);
}
// Core already puts this page in the breadcrumb when its node is active (the admin tree's entry, or the
// category More menu's): adding it again would print it twice.
if (!$PAGE->settingsnav->find_active_node()) {
    $PAGE->navbar->add($title, $PAGE->url);
}

if (!\core_competency\api::is_enabled()) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('competenciesarenotenabled', 'core_competency'));
    echo $OUTPUT->footer();
    die();
}

$templateid = optional_param('templateid', 0, PARAM_INT);
$view = optional_param('view', '', PARAM_ALPHA);

// The dossiers of the workers selected in the list (pairs[]) or in the matrix (workers[]). The selection only names
// workers: the server keeps those of the template's pair set this reader may view, and nothing else
// (dossier_export::selection()), then returns to the page the post came from.
if (optional_param('action', '', PARAM_ALPHA) === 'exportdossiers') {
    dossier_export::require_post();
    require_sesskey();
    dossier_builder::require_export($context);
    hub_access::require_template($templateid, $context);
    $returnurl = $view === hub_matrix_page::VIEW
        ? hub_matrix_page::url($context, $templateid, reportengine::scope(optional_param('scope', '', PARAM_ALPHA)))
        : hub_report_view::url($context, $templateid);
    try {
        $userids = dossier_export::selection($context, $templateid, hub_scope::for_user(), dossier_export::posted_workers());
    } catch (moodle_exception $e) {
        redirect($returnurl, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
    $plan = dossier_export::start($context, $templateid, $userids);
    if ($plan === null) {
        redirect(
            $returnurl,
            get_string('dossierqueued', 'local_evidencehub', count($userids)),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
    dossier_export::stream($context, $templateid, $plan);
    die();
}

// The list's download: a link of the bar's Download menu, a GET with the sesskey as core's own download link. The
// file is the pairs report's own table under the criteria in force, the only door to it, and the one that logs it.
$format = optional_param('download', '', PARAM_ALPHANUMEXT);
if ($format !== '') {
    require_sesskey();
    hub_access::require_template($templateid, $context);
    report_download::open($context, $templateid, $format, [
        'scope' => optional_param('scope', '', PARAM_ALPHA),
        'search' => optional_param('search', '', PARAM_TEXT),
        'sort' => optional_param('sort', '', PARAM_ALPHANUMEXT),
        'direction' => optional_param('dir', '', PARAM_ALPHA),
    ])->send();
    die();
}

// Core's table of the pairs: another rendering of the list's rows, with core's own filter form.
if ($view === hub_pairs::VIEW && $templateid > 0) {
    hub_access::require_template($templateid, $context);
    $report = hub_pairs::for_template($context, $templateid);
    $PAGE->set_url(new moodle_url('/local/evidencehub/index.php', [
        'contextid' => $context->id,
        'templateid' => $templateid,
        'view' => hub_pairs::VIEW,
    ]));
    echo $OUTPUT->header();
    echo $OUTPUT->heading($title);
    echo $OUTPUT->container(
        $OUTPUT->action_link(hub_report_view::url($context, $templateid), get_string('hubbacktolist', 'local_evidencehub')),
        'mb-3'
    );
    echo $report->output();
    echo $OUTPUT->footer();
    return;
}

// The matrix: the workers of the list's rows under the reader's stored filters and the scope, server-rendered.
if ($view === hub_matrix_page::VIEW && $templateid > 0) {
    hub_access::require_template($templateid, $context);
    $scope = reportengine::scope(optional_param('scope', '', PARAM_ALPHA));
    $report = hub_pairs::for_template($context, $templateid, ['scope' => $scope]);
    $page = new hub_matrix_page(
        $context,
        $report,
        $scope,
        optional_param('page', 0, PARAM_INT),
        optional_param('perpage', 0, PARAM_INT)
    );
    $PAGE->set_url($page->page_url());
    $PAGE->requires->js_call_amd('local_evidencehub/hub_page', 'init');
    // Until header() runs, $OUTPUT is core's bootstrap renderer: the page is exported after it.
    echo $OUTPUT->header();
    echo $OUTPUT->render_from_template('local_evidencehub/hub_matrix', $page->export_for_template($OUTPUT));
    echo $OUTPUT->footer();
    return;
}

// The list of a template is the data report: the template the request names, else the reader's last one in this
// context while it is still offered; with neither, the page asks for one.
$listtemplateid = $templateid > 0 ? $templateid : hub_report_view::last_template($context);
if ($listtemplateid > 0) {
    hub_access::require_template($listtemplateid, $context);
    $report = hub_pairs::for_template($context, $listtemplateid);
    $PAGE->set_url(hub_report_view::url($context, $listtemplateid));
    // Until header() runs, $OUTPUT is core's bootstrap renderer: the shell is exported after it.
    echo $OUTPUT->header();
    echo $OUTPUT->render_from_template(
        'local_evidencehub/hub_report_view',
        (new hub_report_view($context, $report))->export_for_template($OUTPUT)
    );
    echo $OUTPUT->footer();
    return;
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_evidencehub/hub_choose', (new hub_choose_page($context))->export_for_template($OUTPUT));
echo $OUTPUT->footer();
