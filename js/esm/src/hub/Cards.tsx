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
 * The hub's cards: the shared card view (datareport/Cards) with the hub's fixed lines (study, section 2.7).
 *
 * The worker with their identity lines and the Inspect action; the competency with the status label; "Valid
 * until"; the type of evidence. Every other chosen column follows in the card's list, behind "More" on a
 * narrow container, a field with nothing in it left out. A fixed line whose column the reader hid is left
 * out too: a card shows what the grid would.
 *
 * @module     local_evidencehub/hub/Cards
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type {ReactElement} from 'react';
import {Cards as DataCards} from '../datareport/Cards';
import type {CardContent} from '../datareport/Cards';
import {Lines} from '../datareport/cells';
import type {Selection} from '../datareport/types';
import {IdentityLines, InspectButton, StatusLabel, cellHtml} from './cells';
import type {Column, PairRow} from './types';

/** The columns the fixed lines draw; every other active column goes in the list. */
const FIXED = ['worker', 'competency', 'status', 'expiry', 'type'];

type CardsProps = {
    labels: Record<string, string>,
    columns: Column[],
    identity: Column[],
    rows: PairRow[],
    single: boolean,
    selection?: Selection<PairRow>,
    onInspect: (button: HTMLElement) => void,
};

/**
 * One keyed line of a card: the column's title, then its cell.
 *
 * @param {object} props The row and the column.
 * @returns {object} The line.
 */
const KeyedLine = ({row, column}: {row: PairRow, column: Column}): ReactElement => (
    <div className="local-evidencehub-rview-card-line">
        <span className="local-evidencehub-rview-card-key">{column.title}</span>
        <span className="local-evidencehub-rview-inline" dangerouslySetInnerHTML={{__html: cellHtml(row, column.key)}} />
    </div>
);

/**
 * What one card holds.
 *
 * @param {object} labels The strings.
 * @param {Array} columns The columns shown.
 * @param {Array} identity The identity columns.
 * @param {object} row The row.
 * @param {Function} onInspect What the Inspect action does.
 * @returns {object} The card's first line, its fixed lines and its list.
 */
const content = (labels: Record<string, string>, columns: Column[], identity: Column[], row: PairRow,
        onInspect: (button: HTMLElement) => void): CardContent => {
    /**
     * A column the reader's report shows, by key.
     *
     * @param {string} key The column key.
     * @returns {object|undefined} The column.
     */
    const shown = (key: string): Column | undefined => columns.find((column) => column.key === key);
    const expiry = shown('expiry');
    const type = shown('type');
    const extra = columns.filter((column) => !FIXED.includes(column.key) && cellHtml(row, column.key).trim() !== '');

    return {
        head: (
            <>
                <div className="local-evidencehub-rview-card-who">
                    <span
                        className="local-evidencehub-rview-card-name"
                        dangerouslySetInnerHTML={{__html: cellHtml(row, 'worker')}}
                    />
                    <IdentityLines row={row} identity={identity} />
                </div>
                <InspectButton row={row} label={labels.inspect} onInspect={onInspect} />
            </>
        ),
        lines: (
            <>
                <div className="local-evidencehub-rview-card-line">
                    <span
                        className="local-evidencehub-rview-inline"
                        dangerouslySetInnerHTML={{__html: cellHtml(row, 'competency')}}
                    />
                    {shown('status') ? <StatusLabel row={row} /> : null}
                </div>
                {expiry ? <KeyedLine row={row} column={expiry} /> : null}
                {type ? <KeyedLine row={row} column={type} /> : null}
            </>
        ),
        fields: extra.map((column) => ({
            key: column.key,
            title: column.title,
            content: <Lines parts={[cellHtml(row, column.key)]} />,
        })),
    };
};

/**
 * The cards.
 *
 * @param {object} props See CardsProps.
 * @returns {object} The list of cards.
 */
export const Cards = ({labels, columns, identity, rows, single, selection, onInspect}: CardsProps): ReactElement => (
    <DataCards<PairRow>
        labels={labels}
        rows={rows}
        single={single}
        selection={selection}
        renderCard={(row) => content(labels, columns, identity, row, onInspect)}
    />
);
