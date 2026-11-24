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
 * The shapes the hub's view reads: its props and the three web services' answers.
 *
 * The answers are the allowlists of classes/external/report_rows.php, report_summary.php and
 * preference_structure.php, field for field; keep them in step. The props are what
 * local_evidencehub\output\hub_report_view::props() writes.
 *
 * @module     local_evidencehub/hub/types
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type {Column, FilterOption, ViewMode} from '../datareport/types';

// The shared parts' shapes the hub's answers already have, field for field.
export type {Column, FilterOption, ViewMode};

/** Which pairs: every status but compliant, or every pair. */
export type Scope = 'attention' | 'all';

/** Where the dossier export posts: index.php's own branch, with the page's sesskey (dossier_export::posted_workers()). */
export type ExportForm = {action: string, sesskey: string};

/** The props hub_report_view::props() writes into the page: configuration and strings, never a record. */
export type ViewProps = {
    mount: 'hub',
    contextid: number,
    templateid: number,
    reportid: number,
    perpage: number,
    perpages: number[],
    searchmin: number,
    matrixurl: string,
    tableurl: string,
    /** The dossier export's form, or null for a reader who may not export (the dock is then never drawn). */
    exportform: ExportForm | null,
    labels: Record<string, string>,
};

/** One cell of a row: the report's own formatted HTML. */
export type Cell = {key: string, html: string};

/** One row: a pair of a worker and a competency, its typed facts and its cells. */
export type Row = {
    userid: number,
    competencyid: number,
    status: number,
    restricted: boolean,
    evidenceurl: string,
    inspect: boolean,
    selectable: boolean,
    drawertitle: string,
    inspectlabel: string,
    cells: Cell[],
};

/** A row as the shared parts draw it: the pair with a number unique in this view's life ({@see HubView}). */
export type PairRow = Row & {id: number};

/** The panel's facet that draws a filter: choice, or '' for one set in core's table view. */
export type Facet = '' | 'choice';

/** One of the report's filters, as the rows answer describes it. */
export type Filter = {
    name: string,
    title: string,
    type: string,
    facet: Facet,
    options: FilterOption[],
    applied: boolean,
    values: string,
};

/** One of the Columns menu's sets. */
export type Preset = {name: string, columns: string[], active: boolean};

/** The reader's grid and accounts choice. */
export type Preference = {
    usedefault: boolean,
    columns: string[],
    widths: {key: string, width: number}[],
    suspended: boolean,
};

/** The local_evidencehub_report_rows answer. */
export type RowsAnswer = {
    scope: Scope,
    search: string,
    sort: string,
    direction: '' | 'asc' | 'desc',
    page: number,
    perpage: number,
    total: number,
    hidden: number,
    candecide: boolean,
    columns: Column[],
    presets: Preset[],
    rows: Row[],
    counted: boolean,
    filters: Filter[],
    preference: Preference,
};

/** One line of the status distribution. */
export type StatusCount = {status: number, label: string, count: number};

/** One of the competencies with most open pairs. */
export type Worst = {competencyid: number, name: string, count: number};

/** The local_evidencehub_report_summary answer. */
export type SummaryAnswer = {
    scope: Scope,
    search: string,
    workers: number,
    ready: number,
    rate?: number | null,
    pairs: number,
    attention: number,
    expiring: number,
    distribution: StatusCount[],
    worst: Worst[],
    hidden: number,
};

/** What a rows request asks for. */
export type RowsQuery = {
    scope: Scope,
    search: string,
    page: number,
    perpage: number,
    sort: string,
    direction: 'asc' | 'desc',
};
