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
 * The hub's inspection drawer: core's modal as a side sheet, its body a fragment the server checks,
 * its buttons opening the evidence and verification forms.
 *
 * @module     local_evidencehub/drawer
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Modal from 'core/modal';
import Fragment from 'core/fragment';
import ModalForm from 'core_form/modalform';
import {getString} from 'core/str';
import Pending from 'core/pending';

const FORMS = {
    verify: {formClass: 'local_evidencehub\\form\\verify', title: 'actionverify'},
    edit: {formClass: 'local_evidencehub\\form\\evidence', title: 'actioneditdetails'},
    recycle: {formClass: 'local_evidencehub\\form\\evidence', title: 'actionrecycle'},
};

/**
 * The arguments of one action's form.
 *
 * @param {HTMLElement} button The clicked button.
 * @param {HTMLElement} pair The drawer's body.
 * @returns {Object}
 */
const formArgs = (button, pair) => {
    const userid = parseInt(pair.dataset.userid, 10);
    switch (button.dataset.action) {
        case 'verify':
            return {id: parseInt(button.dataset.id, 10)};
        case 'recycle':
            return {
                userid,
                id: 0,
                competencyid: parseInt(pair.dataset.competencyid, 10),
                recyclefrom: parseInt(button.dataset.recyclefrom || '0', 10),
            };
        default:
            return {userid, id: parseInt(button.dataset.id, 10)};
    }
};

/**
 * Open the form of one action; a saved form reloads the hub, whose grid and counts change with it.
 *
 * @param {HTMLElement} button The clicked button.
 * @param {HTMLElement} pair The drawer's body.
 */
const openForm = (button, pair) => {
    const form = FORMS[button.dataset.action];
    const modal = new ModalForm({
        formClass: form.formClass,
        args: formArgs(button, pair),
        modalConfig: {title: getString(form.title, 'local_evidencehub')},
        returnFocus: button,
    });
    modal.addEventListener(modal.events.FORM_SUBMITTED, () => {
        // Left pending on purpose: the reload replaces the page, and until then a test must not
        // read the old rows.
        const pending = new Pending('local_evidencehub/drawer:reload');
        window.location.reload();
        return pending;
    });
    modal.show();
};

/**
 * The drawer. Its template carries the side-sheet class from creation, so the first opening slides in;
 * focus trap, Escape and the return of focus are core's.
 *
 * Not exported: Moodle's AMD build makes a module with a default export resolve to that export alone,
 * so a default class beside the named open() left open() undefined for every importer.
 */
class Drawer extends Modal {
    static TYPE = 'local_evidencehub/drawer';
    static TEMPLATE = 'local_evidencehub/drawer';

    /**
     * Bind the action buttons of the body, which arrives after the drawer opens.
     */
    registerEventListeners() {
        super.registerEventListeners();
        this.getRoot()[0].addEventListener('click', (event) => {
            const button = event.target.closest('[data-action]');
            const pair = event.target.closest('[data-region="local-evidencehub-pair"]');
            if (!button || !pair || !FORMS[button.dataset.action]) {
                return;
            }
            event.preventDefault();
            openForm(button, pair);
        });
    }
}

/**
 * Open the drawer of one pair of the grid.
 *
 * @param {HTMLElement} button The row's button; focus returns to it on close.
 * @param {Number} contextid The hub's context.
 * @param {Number} templateid The hub's template.
 * @returns {Promise<Drawer>}
 */
export const open = async(button, contextid, templateid) => {
    const pending = new Pending('local_evidencehub/drawer:open');
    try {
        return await Drawer.create({
            // Rendered by core's modal template with a double stash: the plain name is escaped once.
            templateContext: {title: button.dataset.title},
            body: Fragment.loadFragment('local_evidencehub', 'pair', contextid, {
                userid: button.dataset.userid,
                competencyid: button.dataset.competencyid,
                templateid: String(templateid),
            }),
            returnElement: button,
            removeOnClose: true,
            show: true,
        });
    } finally {
        pending.resolve();
    }
};
