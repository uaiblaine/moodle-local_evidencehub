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
 * The bulk dock under the hub's rows (the Design System's data-report.md, "Bulk dock", D133 to D135).
 *
 * The count of the rows selected on this page, then how many workers they name: a dossier is per worker,
 * so two pairs of one worker are one dossier (the plugin's addition to the mould's count). One action,
 * "Export dossiers", the page's only one, so a solid fill. It needs no more input, so it opens no dialog
 * (D135): it posts the form at once (export.ts). Drawn only while something is selected; the view keeps it
 * out otherwise.
 *
 * @module     local_evidencehub/hub/Dock
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type {ReactElement} from 'react';
import {fill, fillCount, num} from '../datareport/str';

type DockProps = {
    labels: Record<string, string>,
    /** The rows selected on this page. */
    count: number,
    /** The distinct workers of those rows. */
    workers: number,
    /** Whether the export has been posted: the button acts once. */
    posting: boolean,
    onExport: () => void,
};

/**
 * The dock.
 *
 * @param {object} props See DockProps.
 * @returns {object} The dock.
 */
export const Dock = ({labels, count, workers, posting, onExport}: DockProps): ReactElement => (
    <div className="local-evidencehub-rview-dock" role="region" aria-label={labels.dock} data-region="local-evidencehub-dock">
        <span className="local-evidencehub-rview-dockcount" aria-live="polite">
            {fill(labels.selected, num(count))}
            <span className="local-evidencehub-rview-dockworkers">
                {' · '}{fillCount(labels, 'selectedworkers', workers, num(workers))}
            </span>
        </span>
        <div className="local-evidencehub-rview-dockactions">
            <button type="button" className="btn btn-primary" disabled={posting} onClick={onExport}>
                {labels.exportdossiers}
            </button>
        </div>
    </div>
);
