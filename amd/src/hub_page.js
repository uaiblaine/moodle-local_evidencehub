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
 * The evidence hub's grid: the checkbox in the heading selects or clears every row of the page, and
 * each row's button opens the pair's inspection drawer.
 *
 * @module     local_evidencehub/hub_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {open as openDrawer} from 'local_evidencehub/drawer';
import Notification from 'core/notification';

const REGION = '[data-region="local-evidencehub-hub"]';
const SELECTALL = '[data-action="selectall"]';
const PAIR = '[data-region="pair"]';
const INSPECT = '[data-action="inspect"]';

/**
 * Make the heading checkbox reflect the rows: ticked when all are, mixed when some are.
 *
 * @param {HTMLElement} region The hub.
 */
const reflect = (region) => {
    const all = region.querySelector(SELECTALL);
    if (!all) {
        return;
    }
    const pairs = [...region.querySelectorAll(PAIR)];
    const ticked = pairs.filter((pair) => pair.checked).length;
    all.checked = pairs.length > 0 && ticked === pairs.length;
    all.indeterminate = ticked > 0 && ticked < pairs.length;
};

/**
 * Bind the selection checkboxes and the drawer buttons of the page.
 */
export const init = () => {
    const region = document.querySelector(REGION);
    if (!region) {
        return;
    }
    region.addEventListener('change', (event) => {
        if (event.target.matches(SELECTALL)) {
            region.querySelectorAll(PAIR).forEach((pair) => {
                pair.checked = event.target.checked;
            });
        }
        if (event.target.matches(SELECTALL) || event.target.matches(PAIR)) {
            reflect(region);
        }
    });
    region.addEventListener('click', (event) => {
        const button = event.target.closest(INSPECT);
        if (!button || !region.contains(button)) {
            return;
        }
        event.preventDefault();
        openDrawer(button, parseInt(region.dataset.contextid, 10), parseInt(region.dataset.templateid, 10))
            .catch(Notification.exception);
    });
};
