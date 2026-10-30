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
 * The worker's evidence page: each action button opens its dynamic form in a modal.
 *
 * @module     local_evidencehub/evidence_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import ModalForm from 'core_form/modalform';
import {getString} from 'core/str';
import Pending from 'core/pending';

const REGION = '[data-region="local-evidencehub-evidence"]';

const FORMS = {
    add: {formClass: 'local_evidencehub\\form\\evidence', title: 'actionadd'},
    edit: {formClass: 'local_evidencehub\\form\\evidence', title: 'actionedit'},
    complete: {formClass: 'local_evidencehub\\form\\evidence', title: 'actioncomplete'},
    verify: {formClass: 'local_evidencehub\\form\\verify', title: 'actionverify'},
};

/**
 * Open the form of one action.
 *
 * @param {HTMLElement} button The clicked button.
 * @param {Number} userid The worker.
 */
const openForm = (button, userid) => {
    const action = button.dataset.action;
    const id = parseInt(button.dataset.id || '0', 10);
    const args = action === 'verify' ? {id} : {userid, id};
    const modal = new ModalForm({
        formClass: FORMS[action].formClass,
        args,
        modalConfig: {title: getString(FORMS[action].title, 'local_evidencehub')},
        returnFocus: button,
    });
    // The list is rebuilt by the server: a decision or a reset changes badges and buttons alike.
    modal.addEventListener(modal.events.FORM_SUBMITTED, () => {
        // Left pending on purpose: the reload replaces the page, and until then a test must not
        // read the old rows.
        const pending = new Pending('local_evidencehub/evidence_page:reload');
        window.location.reload();
        return pending;
    });
    modal.show();
};

/**
 * Bind the action buttons of the page.
 */
export const init = () => {
    const region = document.querySelector(REGION);
    if (!region) {
        return;
    }
    const userid = parseInt(region.dataset.userid, 10);
    region.addEventListener('click', (event) => {
        const button = event.target.closest('[data-action]');
        if (!button || !region.contains(button) || !FORMS[button.dataset.action]) {
            return;
        }
        event.preventDefault();
        openForm(button, userid);
    });
};
