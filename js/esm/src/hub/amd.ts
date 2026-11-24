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
 * The one door from the hub's ES modules to the plugin's AMD drawer.
 *
 * A component cannot import an AMD module: the import map Moodle serves resolves `@moodle/lms/`, react and
 * react-dom, and nothing of RequireJS. The inspection drawer is an AMD core/modal subclass
 * (amd/src/drawer.js), so it is reached through core's own loader (`@moodle/lms/core/amd`), here only, and
 * never copied: the list and the matrix open the same drawer. Every module the view may load is a key of
 * `Modules`, so any other name is a type error under the tsc gate.
 *
 * @module     local_evidencehub/hub/amd
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {requireAsync} from '@moodle/lms/core/amd';

/** Local_evidencehub/drawer: opens one pair's drawer; the button takes the focus back when it closes. */
export type DrawerModule = {
    open: (button: HTMLElement, contextid: number, templateid: number) => Promise<unknown>,
};

/** The part of core/notification the view uses. */
export type NotificationModule = {exception: (error: unknown) => unknown};

/** Every AMD module the view may load, by its RequireJS name. */
export interface Modules {
    'local_evidencehub/drawer': DrawerModule,
    'core/notification': NotificationModule,
}

/**
 * Load one AMD module, once core/first has loaded.
 *
 * With the JavaScript cache on, every AMD module the page asked for arrives in one bundle, reached as
 * core/first; asked for by name before RequireJS knows the module is inside it, a module would be fetched a
 * second time on its own. The footer has already asked for core/first, so waiting for it costs no request.
 *
 * @param {string} name The module's RequireJS name, one of `Modules`.
 * @returns {Promise} Resolves with the module; rejects with RequireJS's error when it cannot load.
 */
export const load = async<K extends keyof Modules>(name: K): Promise<Modules[K]> => {
    await requireAsync('core/first');
    return requireAsync<Modules[K]>(name);
};

/**
 * Open one pair's inspection drawer from its Inspect button.
 *
 * The drawer reads the pair and its title from the button's data attributes (data-userid,
 * data-competencyid, data-title), as the matrix's cells carry them, and gives the focus back to it on close.
 * A failure is core's notification, as the matrix reports it.
 *
 * @param {HTMLElement} button The row's Inspect button.
 * @param {number} contextid The hub context.
 * @param {number} templateid The template.
 * @returns {Promise} Resolves once the drawer opened, or the failure was reported.
 */
export const inspect = async(button: HTMLElement, contextid: number, templateid: number): Promise<void> => {
    try {
        const drawer = await load('local_evidencehub/drawer');
        await drawer.open(button, contextid, templateid);
    } catch (error) {
        try {
            const notification = await load('core/notification');
            notification.exception(error);
        } catch {
            window.console.error(error);
        }
    }
};
