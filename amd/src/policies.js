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
 * A framework's validity policies: each edit button opens the policy form in a modal.
 *
 * @module     local_evidencehub/policies
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import ModalForm from 'core_form/modalform';
import {getString} from 'core/str';
import Pending from 'core/pending';

const REGION = '[data-region="local-evidencehub-policies"]';

/**
 * Open the policy form of the framework or of one competency.
 *
 * @param {HTMLElement} button The clicked button.
 * @param {Number} frameworkid The framework.
 */
const openForm = (button, frameworkid) => {
    const competencyid = parseInt(button.dataset.competencyid || '0', 10);
    const modal = new ModalForm({
        formClass: 'local_evidencehub\\form\\policy',
        args: {frameworkid, competencyid},
        modalConfig: {title: getString('actioneditpolicy', 'local_evidencehub')},
        returnFocus: button,
    });
    // Every competency without its own row inherits the framework's: the whole table may change.
    modal.addEventListener(modal.events.FORM_SUBMITTED, () => {
        // Left pending on purpose: the reload replaces the page, and until then a test must not
        // read the old rows.
        const pending = new Pending('local_evidencehub/policies:reload');
        window.location.reload();
        return pending;
    });
    modal.show();
};

/**
 * Bind the edit buttons of the page.
 */
export const init = () => {
    const region = document.querySelector(REGION);
    if (!region) {
        return;
    }
    const frameworkid = parseInt(region.dataset.frameworkid, 10);
    region.addEventListener('click', (event) => {
        const button = event.target.closest('[data-action="policy"]');
        if (!button || !region.contains(button)) {
            return;
        }
        event.preventDefault();
        openForm(button, frameworkid);
    });
};
