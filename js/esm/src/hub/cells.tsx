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
 * The hub's cells as the grid and the cards draw them, and the columns' widths.
 *
 * A cell is the report's own formatted HTML (format_row(): the masking and the escaping already ran on the
 * server, as on core's table), so it is inserted as HTML and never built here. Two cells are drawn around
 * it: the status, as a status label whose tone is read from the row's effective status code (study,
 * decision 15), and the worker, with the identity fields the reader's report shows under the name and the
 * Inspect action at the cell's end where the server says the reader may open the pair's drawer.
 *
 * @module     local_evidencehub/hub/cells
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type {ReactElement} from 'react';
import {Lines} from '../datareport/cells';
import {Icon} from '../datareport/icons';
import {makeSizing} from '../datareport/sizing';
import {plain} from '../datareport/str';
import type {Column, PairRow} from './types';

/** Default widths, in pixels, by column key; anything else takes DEFAULT_WIDTH. */
const WIDTHS: Record<string, number> = {
    worker: 260,
    competency: 240,
    status: 180,
    expiry: 132,
    count: 104,
    type: 200,
    issuer: 200,
    competencyidnumber: 150,
    framework: 200,
    issuedate: 132,
    expirydate: 132,
    verification: 160,
    changed: 160,
};

/** The width of a column the table above does not name. */
const DEFAULT_WIDTH = 180;

/**
 * Minimum widths: the worker 200 px (the box, the name and the Inspect action share it; the Design System
 * leaves the first column's minimum with a box open and enrol_apply's queue sets 200), a date 104 px,
 * anything else 80 px.
 */
const MINIMUMS: Record<string, number> = {worker: 200, expiry: 104, issuedate: 104, expirydate: 104, changed: 104};

/** The narrowest any column may be dragged. */
const MINIMUM = 80;

/** The widest a column may be dragged; the server keeps up to 1600. */
const MAXIMUM = 800;

/** The hub's width policy. */
export const SIZING = makeSizing(WIDTHS, DEFAULT_WIDTH, MINIMUMS, MINIMUM, MAXIMUM);

/** Status codes (local\status): the effective ones the hub computes, and the stored ones. */
export const RESTRICTED = -1;
export const MISSING = 0;
const UNVERIFIED = 1;
const VALID = 2;
const DUE = 3;
const CRITICAL = 4;
const EXPIRED = 5;

/**
 * The status label's tone of an effective status (decision 15 of the study): compliant success, due soon
 * info, critical warning, expired danger, pending and restricted neutral.
 *
 * @param {number} status The effective status code.
 * @returns {string} The tone's class suffix.
 */
export const tone = (status: number): string => {
    switch (status) {
        case VALID:
            return 'success';
        case DUE:
            return 'info';
        case CRITICAL:
            return 'warning';
        case EXPIRED:
            return 'danger';
        case UNVERIFIED:
        case MISSING:
        case RESTRICTED:
        default:
            return 'neutral';
    }
};

/**
 * A row's cell for a column, as HTML; '' where the report gave none.
 *
 * @param {object} row The row.
 * @param {string} key The column key.
 * @returns {string} The cell's HTML.
 */
export const cellHtml = (row: PairRow, key: string): string => row.cells.find((cell) => cell.key === key)?.html ?? '';

/**
 * The status as a status label (status-label.md): the cell's words in the status's tone, a lock before a
 * restricted one.
 *
 * @param {object} props The row.
 * @returns {object|null} The label, or nothing for an empty cell.
 */
export const StatusLabel = ({row}: {row: PairRow}): ReactElement | null => {
    const html = cellHtml(row, 'status');
    if (html === '') {
        return null;
    }

    return (
        <span className={`local-evidencehub-rview-status local-evidencehub-rview-status--${tone(row.status)}`} title={plain(html)}>
            {row.status === RESTRICTED ? <Icon name="lock" /> : null}
            <span dangerouslySetInnerHTML={{__html: html}} />
        </span>
    );
};

/**
 * One cell's content: the status label, or the report's HTML as one line.
 *
 * @param {object} props The row and the column key.
 * @returns {object} The content.
 */
export const CellContent = ({row, column}: {row: PairRow, column: string}): ReactElement => (
    column === 'status' ? <StatusLabel row={row} /> : <Lines parts={[cellHtml(row, column)]} />
);

/**
 * The Inspect action (AR-D18), on a row whose drawer the reader may open; the drawer reads the pair and its
 * title from the button's data attributes.
 *
 * @param {object} props The row, the button's word and what a press does.
 * @returns {object|null} The button, or nothing.
 */
export const InspectButton = ({row, label, onInspect}: {row: PairRow, label: string,
        onInspect: (button: HTMLElement) => void}): ReactElement | null => (
    row.inspect ? (
        <button
            type="button"
            className="btn btn-sm btn-outline-secondary local-evidencehub-rview-inspect"
            aria-label={row.inspectlabel}
            data-userid={row.userid}
            data-competencyid={row.competencyid}
            data-title={row.drawertitle}
            onClick={(event) => onInspect(event.currentTarget)}
        >
            {label}
        </button>
    ) : null
);

/**
 * The identity columns' values of a row: the lines under the worker's name.
 *
 * @param {object} props The row and the identity columns.
 * @returns {object} The lines.
 */
export const IdentityLines = ({row, identity}: {row: PairRow, identity: Column[]}): ReactElement => (
    <>
        {identity.map((column) => {
            const html = cellHtml(row, column.key);
            return html === '' ? null : (
                <span
                    key={column.key}
                    className="local-evidencehub-rview-sub"
                    title={plain(html)}
                    dangerouslySetInnerHTML={{__html: html}}
                />
            );
        })}
    </>
);
