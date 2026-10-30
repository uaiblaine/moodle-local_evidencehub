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
 * The "complete the validity details" prompt on tool_lp's evidence pages.
 *
 * @module     local_evidencehub/lp_prompt
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Put the prompt at the top of the main region.
 *
 * @param {Object} config The prompt.
 * @param {String} config.url The worker's evidence page.
 * @param {String} config.message The localised message.
 * @param {String} config.linktext The localised link text.
 */
export const init = ({url, message, linktext}) => {
    const main = document.querySelector('#region-main');
    if (!main || main.querySelector('.local-evidencehub-prompt')) {
        return;
    }
    const alert = document.createElement('div');
    alert.className = 'alert alert-info local-evidencehub-prompt';
    alert.setAttribute('role', 'status');
    alert.append(message + ' ');
    const link = document.createElement('a');
    link.href = url;
    link.textContent = linktext;
    alert.append(link);
    main.prepend(alert);
};
