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
 * The dossier export is a real form posted to index.php's own branch, never a web service.
 *
 * The form carries what that branch reads, under its own names: `contextid`, `action=exportdossiers`, `sesskey`,
 * the `templateid`, and one `pairs[]` per selected row, `<userid>:<competencyid>` (dossier_export::posted_workers()
 * reads the workers out of them). The server then requires the POST and the sesskey, `exportdossier` in the
 * context, the template offered there, at most the configured number of workers, and keeps only the posted workers
 * of the template's pair set this reader may view, suspended accounts as the reader's preference lists them
 * (dossier_export::selection()): what the client selected decides nothing on the server. A small export answers
 * with the ZIP, which the browser saves while the page stays; a larger one redirects back to the list with a
 * notification.
 *
 * @module     local_evidencehub/hub/export
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** What one export posts. */
export type Posted = {
    /** Index.php, the form's action. */
    action: string,
    sesskey: string,
    contextid: number,
    templateid: number,
    /** The selected rows' pairs. */
    pairs: {userid: number, competencyid: number}[],
};

/**
 * One hidden field of the form.
 *
 * @param {HTMLFormElement} form The form.
 * @param {string} name The field's name, as index.php reads it.
 * @param {string} value Its value.
 * @returns {void}
 */
const field = (form: HTMLFormElement, name: string, value: string): void => {
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = name;
    input.value = value;
    form.appendChild(input);
};

/**
 * Build the form and submit it; it is removed again, since a streamed ZIP leaves the page where it is.
 *
 * @param {object} posted What to post; see Posted.
 * @returns {void}
 */
export const exportDossiers = (posted: Posted): void => {
    const form = document.createElement('form');
    form.method = 'post';
    form.action = posted.action;
    form.hidden = true;

    field(form, 'contextid', String(posted.contextid));
    field(form, 'action', 'exportdossiers');
    field(form, 'sesskey', posted.sesskey);
    field(form, 'templateid', String(posted.templateid));
    posted.pairs.forEach((pair) => field(form, 'pairs[]', `${pair.userid}:${pair.competencyid}`));

    document.body.appendChild(form);
    form.submit();
    form.remove();
};
