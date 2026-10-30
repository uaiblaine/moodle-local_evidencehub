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

use local_evidencehub\event\hub_downloaded;
use local_evidencehub\local\hub_access;
use local_evidencehub\local\hub_download;
use local_evidencehub\local\hub_filter;
use local_evidencehub\local\hub_kpis;
use local_evidencehub\local\hub_query;
use local_evidencehub\local\hub_scope;

$contextid = optional_param('contextid', context_system::instance()->id, PARAM_INT);
$context = context::instance_by_id($contextid);

require_login(null, false);
if (isguestuser()) {
    throw new require_login_exception('Guests are not allowed here.');
}
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
$PAGE->navbar->add($title, $PAGE->url);

if (!\core_competency\api::is_enabled()) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('competenciesarenotenabled', 'core_competency'));
    echo $OUTPUT->footer();
    die();
}

// A request naming no filter restores the last one stored; one naming a filter replaces it once the grid rendered.
$fromrequest = hub_filter::has_request_params();
$filter = $fromrequest ? hub_filter::from_request($context) : hub_filter::from_preference($context);
$scope = hub_scope::for_user();

$format = optional_param('download', '', PARAM_ALPHANUMEXT);
if ($format !== '' && $filter !== null) {
    require_sesskey();
    if (!array_key_exists($format, \core\plugininfo\dataformat::get_enabled_plugins())) {
        throw new moodle_exception('invalidparameter', 'debug');
    }
    $posted = optional_param_array('pairs', [], PARAM_RAW_TRIMMED);
    $pairs = $posted ? hub_download::parse_pairs($posted) : null;
    $download = new hub_download($context, new hub_query($context, $scope));
    $rows = $download->count($filter, $pairs, hub_kpis::get($filter, $scope, $context));
    hub_downloaded::create_from_download($context, $filter->templateid, $rows)->trigger();
    \core\dataformat::download_data(
        clean_filename(get_string('hubdownloadname', 'local_evidencehub', $filter->templateid)),
        $format,
        $download->columns(),
        $download->rows($filter, $pairs)
    );
    die();
}

$page = new \local_evidencehub\output\hub_page($context, $filter, $scope);
$PAGE->set_url($page->url($filter));
$PAGE->requires->js_call_amd('local_evidencehub/hub_page', 'init');

// Until header() runs, $OUTPUT is core's bootstrap renderer: every export comes after it.
echo $OUTPUT->header();
echo $OUTPUT->heading($title);
echo $OUTPUT->render_from_template('local_evidencehub/hub_page', $page->export_for_template($OUTPUT));
// Only a filter that validated and rendered is stored; a download never stores one.
if ($fromrequest && $filter !== null) {
    $filter->save_preference();
}
echo $OUTPUT->footer();
