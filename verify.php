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
 * The public verification page of a readiness dossier, opened from its QR code.
 *
 * Never calls require_login(): it must work under forcelogin and for a contractor without an account.
 * It shows the dossier's snapshot as issued, never the worker's identifier, full name or files, and
 * neither logs nor triggers anything: the page is anonymous and the code is the dossier's only secret.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:ignore moodle.Files.RequireLogin.Missing -- The page is public on purpose.
require_once(__DIR__ . '/../../config.php');

// Raw, so a dash or a space is not stripped into a valid shape: find_by_code() accepts 32 hex characters only.
$code = optional_param('code', '', PARAM_RAW_TRIMMED);
$title = get_string('verifypage', 'local_evidencehub');

$PAGE->set_context(context_system::instance());
// The code stays out of the page's URL, so no link the page prints carries it.
$PAGE->set_url(new moodle_url('/local/evidencehub/verify.php'));
$PAGE->set_pagelayout('embedded');
$PAGE->set_title($title);
// As core_renderer does for the login page: no search engine indexes a dossier.
$CFG->additionalhtmlhead = ($CFG->additionalhtmlhead ?? '') . '<meta name="robots" content="noindex" />';

$dossier = \local_evidencehub\local\dossier::find_by_code($code);
$page = new \local_evidencehub\output\verify_page(
    $dossier,
    $code !== '',
    \local_evidencehub\local\dossier_config::verify_live(),
    \local_evidencehub\local\dossier_config::readiness_basis()
);

// Until header() runs, $OUTPUT is core's bootstrap renderer: the export comes after it.
echo $OUTPUT->header();
// The embedded layout gives the page no gutter: the plugin's container does (styles.css).
echo $OUTPUT->container_start('local-evidencehub-verify-page');
echo $OUTPUT->heading($title);
echo $OUTPUT->render_from_template('local_evidencehub/verify_page', $page->export_for_template($OUTPUT));
echo $OUTPUT->container_end();
echo $OUTPUT->footer();
