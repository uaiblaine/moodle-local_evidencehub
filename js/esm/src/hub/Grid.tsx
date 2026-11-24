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
 * The hub's grid: the shared bounded grid (datareport/Grid) with the hub's cells.
 *
 * The lead column is the worker: their name linked to their evidence page as the report formats it, the
 * identity fields the reader's report shows under it, and the Inspect action at the cell's end. The status
 * is a status label; every other cell is the report's formatted HTML. A reader who may export dossiers gets
 * the selection (the Design System's selection variant), which the view builds and passes.
 *
 * @module     local_evidencehub/hub/Grid
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type {ReactElement} from 'react';
import {Grid as DataGrid} from '../datareport/Grid';
import type {Selection} from '../datareport/types';
import {CellContent, IdentityLines, InspectButton, SIZING, cellHtml} from './cells';
import type {Column, PairRow} from './types';

type GridProps = {
    labels: Record<string, string>,
    columns: Column[],
    identity: Column[],
    rows: PairRow[],
    widths: Record<string, number>,
    sort: string,
    direction: string,
    selection?: Selection<PairRow>,
    onInspect: (button: HTMLElement) => void,
    onSort: (key: string) => void,
    onWidth: (key: string, width: number | null, defer?: boolean) => void,
    onWidthCommit: () => void,
};

/**
 * The grid.
 *
 * @param {object} props See GridProps.
 * @returns {object} The frame and its table.
 */
export const Grid = ({labels, columns, identity, rows, widths, sort, direction, selection, onInspect, onSort, onWidth,
        onWidthCommit}: GridProps): ReactElement => (
    <DataGrid<PairRow>
        labels={labels}
        columns={columns}
        lead="worker"
        rows={rows}
        widths={widths}
        sizing={SIZING}
        sort={sort}
        direction={direction}
        selection={selection}
        renderLead={(row) => (
            <div className="local-evidencehub-rview-who">
                <div className="local-evidencehub-rview-who-text">
                    <span
                        className="local-evidencehub-rview-c"
                        dangerouslySetInnerHTML={{__html: cellHtml(row, 'worker')}}
                    />
                    <IdentityLines row={row} identity={identity} />
                </div>
                <InspectButton row={row} label={labels.inspect} onInspect={onInspect} />
            </div>
        )}
        renderCell={(row, column) => <CellContent row={row} column={column.key} />}
        onSort={onSort}
        onWidth={onWidth}
        onWidthCommit={onWidthCommit}
    />
);
