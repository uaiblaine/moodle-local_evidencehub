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
 * The evidence hub's list of pairs, drawn in React (stage E3 of docs/design/data-report-study.md).
 *
 * index.php renders the shell (local_evidencehub\output\hub_report_view) with configuration and strings;
 * the rows and the figures come from the plugin's web services, which read the system report hub_pairs
 * headlessly, so access, the reader's cut, the restriction, the stored filters and the chosen columns are
 * the report's, as on core's table. Three regions load on their own:
 *
 * - the rows (local_evidencehub_report_rows): a sort or a page reloads them alone;
 * - the band (local_evidencehub_report_summary): a filter, the scope or the search reload it with the rows;
 * - the grid (local_evidencehub_set_report_preference): a column change is written, and the rows are read
 *   again only once the write has landed, because the report reads the columns in initialise(). The
 *   "Accounts" choice is written there too, and then both regions are read again.
 *
 * One request is in flight per region in effect: core's ajax cannot abort, so each region numbers its
 * requests and drops any answer but the newest. The search is sent 300 ms after the last key, from two
 * characters. The first load draws skeletons with aria-busy; later ones keep the old rows, dimmed, under a
 * 2 px line. The view (Automatic, Table, Cards) is remembered in this browser only, and Automatic is the
 * cards under a 576 px container.
 *
 * A reader who may export dossiers (the rows answer's candecide) gets the selection variant: a box per
 * selectable row and one for the page, and the dock under the rows while anything is selected. The
 * selection belongs to the page on screen (D131): any other page, page size, scope, filter, search, sort or
 * accounts choice empties it, and a re-read of the same rows keeps only the ids still selectable.
 *
 * A pair has no id of its own: its key is the worker and the competency. The shared parts key rows by a
 * number, so each pair gets one the first time this view meets it, kept for the view's life, so a re-read
 * of the same pair finds its selection again.
 *
 * @module     local_evidencehub/hub/HubView
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useCallback, useEffect, useMemo, useRef, useState} from 'react';
import type {ReactElement} from 'react';
import {rememberView, storedView, useNarrow} from '../datareport/layout';
import {Pager} from '../datareport/Pager';
import {hold} from '../datareport/pending';
import {RowsRegion} from '../datareport/Rows';
import {fill} from '../datareport/str';
import type {Selection} from '../datareport/types';
import {inspect} from './amd';
import {Band} from './Band';
import {Bar} from './Bar';
import {Cards} from './Cards';
import {SIZING} from './cells';
import {Dock} from './Dock';
import {exportDossiers} from './export';
import {COMPETENCY_FILTER, STATUS_FILTER, withChoice, without} from './filters';
import {Grid} from './Grid';
import {getRows, getSummary, resetFilters, setFilters, setPreference} from './repository';
import type {Target} from './repository';
import type {Column, Filter, PairRow, Preference, Preset, RowsAnswer, RowsQuery, Scope, SummaryAnswer, ViewMode,
    ViewProps} from './types';

/** The wait before the search is sent, after the last key (data-report.md). */
const SEARCH_DELAY = 300;

/** The wait before a width set from the keyboard is saved, after the last key. */
const WIDTH_DELAY = 500;

/** How long the dock's button stays pressed after an export is posted: a streamed ZIP leaves the page as it is. */
const POST_GUARD = 3000;

/** The browser key the reader's view choice is remembered under: a convenience of this browser alone. */
const VIEW_KEY = 'local_evidencehub_hub_view';

/** The columns the first skeleton draws, before the report has said which it shows. */
const SKELETON_COLUMNS = ['worker', 'competency', 'status', 'expiry', 'count', 'type', 'issuer'];

/** The grid as the preference writer takes it. */
type GridPreference = Omit<Preference, 'suspended'>;

/**
 * The widths as the preference stores them.
 *
 * @param {object} map The widths by key.
 * @returns {Array} Each: key, width.
 */
const widthList = (map: Record<string, number>): {key: string, width: number}[] =>
    Object.entries(map).map(([key, width]) => ({key, width}));

/**
 * The grid to write for the columns in force and some widths.
 *
 * The server's "use the default" forgets the widths with the column choice
 * (local_evidencehub_set_report_preference), so a reader on the default columns who sets a width has the
 * default set written as their choice, which keeps the same columns and the width.
 *
 * @param {boolean} usedefault Whether the default columns show.
 * @param {Array} chosen The chosen columns' keys, as the report shows them.
 * @param {object} widths The widths by key.
 * @returns {object} The grid.
 */
const gridOf = (usedefault: boolean, chosen: string[], widths: Record<string, number>): GridPreference => {
    const list = widthList(widths);
    if (usedefault && list.length === 0) {
        return {usedefault: true, columns: [], widths: []};
    }

    return {usedefault: false, columns: chosen, widths: list};
};

/**
 * What the view reads off the last rows answer, with the defaults before the first.
 *
 * @param {object|null} rows The answer, or null before the first.
 * @returns {object} The column catalogue, whether the default columns show, whether suspended accounts are
 *     listed, and whether the reader may select rows.
 */
const settingsOf = (rows: RowsAnswer | null): {allcolumns: Column[], usedefault: boolean, suspended: boolean,
        candecide: boolean} => (rows === null
    ? {allcolumns: [], usedefault: true, suspended: false, candecide: false}
    : {
        allcolumns: rows.columns,
        usedefault: rows.preference.usedefault,
        suspended: rows.preference.suspended,
        candecide: rows.candecide,
    });

/**
 * The hub's view.
 *
 * @param {object} props The props hub_report_view::props() wrote; see ViewProps.
 * @returns {object} The view.
 */
const HubView = (props: ViewProps): ReactElement => {
    const {labels, reportid} = props;
    const target = useMemo<Target>(() => ({contextid: props.contextid, templateid: props.templateid}),
        [props.contextid, props.templateid]);
    const root = useRef<HTMLDivElement | null>(null);
    // Automatic is cards under a 576 px container: the report's own width, not the window's.
    const narrow = useNarrow(root);
    const [view, setViewState] = useState<ViewMode>(() => storedView(VIEW_KEY));

    const initial: RowsQuery = {
        scope: 'attention',
        search: '',
        page: 0,
        perpage: props.perpage,
        sort: '',
        direction: 'asc',
    };
    const query = useRef<RowsQuery>(initial);
    const [current, setCurrent] = useState<RowsQuery>(initial);
    const [searchtext, setSearchtext] = useState('');

    const [rows, setRows] = useState<RowsAnswer | null>(null);
    const [rowsloading, setRowsloading] = useState(true);
    const [rowserror, setRowserror] = useState(false);
    const [summary, setSummary] = useState<SummaryAnswer | null>(null);
    const [bandloading, setBandloading] = useState(true);
    const [banderror, setBanderror] = useState(false);
    const [widths, setWidths] = useState<Record<string, number>>({});
    const [counted, setCounted] = useState<Record<string, Filter>>({});
    const [selected, setSelected] = useState<ReadonlySet<number>>(() => new Set<number>());
    const [posting, setPosting] = useState(false);

    const ids = useRef(new Map<string, number>());
    const rowsseq = useRef(0);
    const bandseq = useRef(0);
    const rowshold = useRef<(() => void) | null>(null);
    const bandhold = useRef<(() => void) | null>(null);
    const searchtimer = useRef<number | null>(null);
    const searchhold = useRef<(() => void) | null>(null);
    const widthsread = useRef(false);
    const widthtimer = useRef<number | null>(null);
    const widthhold = useRef<(() => void) | null>(null);
    const pendingwidths = useRef<Record<string, number> | null>(null);
    const flushref = useRef<() => void>(() => undefined);
    const posttimer = useRef<number | null>(null);

    /**
     * The number the shared parts key a pair by: given the first time the view meets the pair.
     *
     * @param {number} userid The worker.
     * @param {number} competencyid The competency.
     * @returns {number} The pair's number.
     */
    const idOf = useCallback((userid: number, competencyid: number): number => {
        const key = `${userid}:${competencyid}`;
        let id = ids.current.get(key);
        if (id === undefined) {
            id = ids.current.size + 1;
            ids.current.set(key, id);
        }
        return id;
    }, []);

    /**
     * Read a page of rows; an answer to an older request than the newest is dropped.
     *
     * @param {object} asked What to read.
     * @param {boolean} facets Whether the panel's counts can have moved, and so are asked for.
     * @returns {void}
     */
    const loadRows = useCallback((asked: RowsQuery, facets: boolean): void => {
        const seq = ++rowsseq.current;
        if (!rowshold.current) {
            rowshold.current = hold('local_evidencehub/hub:rows');
        }
        setRowsloading(true);
        getRows(target, asked, facets).then((answer) => {
            if (seq !== rowsseq.current) {
                return;
            }
            if (answer.counted) {
                setCounted(Object.fromEntries(answer.filters.map((filter) => [filter.name, filter])));
            }
            if (!widthsread.current) {
                widthsread.current = true;
                setWidths(Object.fromEntries(answer.preference.widths.map((entry) => [entry.key, entry.width])));
            }
            if (answer.page !== query.current.page) {
                // Past the last page (a filter shrank the rows): the server answered the last one.
                query.current = {...query.current, page: answer.page};
                setCurrent(query.current);
            }
            setRows(answer);
            // Only rows of this answer that may be selected stay selected: none out of sight.
            setSelected((before) => {
                const open = new Set(answer.rows.filter((row) => row.selectable)
                    .map((row) => idOf(row.userid, row.competencyid)));
                const kept = Array.from(before).filter((id) => answer.candecide && open.has(id));
                return kept.length === before.size ? before : new Set(kept);
            });
            setRowserror(false);
            setRowsloading(false);
            return;
        }).catch(() => {
            if (seq === rowsseq.current) {
                setRowserror(true);
                setRowsloading(false);
            }
        });
    }, [target, idOf]);

    /**
     * Read the band's figures; an answer to an older request than the newest is dropped.
     *
     * @param {object} asked The scope and the search to summarise.
     * @returns {void}
     */
    const loadBand = useCallback((asked: RowsQuery): void => {
        const seq = ++bandseq.current;
        if (!bandhold.current) {
            bandhold.current = hold('local_evidencehub/hub:band');
        }
        setBandloading(true);
        getSummary(target, asked.scope, asked.search).then((answer) => {
            if (seq === bandseq.current) {
                setSummary(answer);
                setBanderror(false);
                setBandloading(false);
            }
            return;
        }).catch(() => {
            if (seq === bandseq.current) {
                setBanderror(true);
                setBandloading(false);
            }
        });
    }, [target]);

    /**
     * Change what is asked for and read the regions it moves. Another set of rows: the selection goes.
     *
     * @param {object} change The parts of the query that change.
     * @param {boolean} readband Whether the band moves (and so the panel's counts).
     * @returns {void}
     */
    const run = useCallback((change: Partial<RowsQuery>, readband: boolean): void => {
        query.current = {...query.current, ...change};
        setCurrent(query.current);
        setSelected(new Set<number>());
        loadRows(query.current, readband);
        if (readband) {
            loadBand(query.current);
        }
    }, [loadRows, loadBand]);

    // Behat waits on these holds: each is released once the answer it waited for has been drawn.
    useEffect(() => {
        if (!rowsloading && rowshold.current) {
            rowshold.current();
            rowshold.current = null;
        }
    }, [rowsloading, rows, rowserror]);
    useEffect(() => {
        if (!bandloading && bandhold.current) {
            bandhold.current();
            bandhold.current = null;
        }
    }, [bandloading, summary, banderror]);

    // The first load: both regions, in parallel, each in a request of its own.
    useEffect(() => {
        loadRows(query.current, true);
        loadBand(query.current);
    }, [loadRows, loadBand]);

    // A pending search is dropped with the component, and its hold released; a pending width is saved.
    useEffect(() => () => {
        if (searchtimer.current !== null) {
            window.clearTimeout(searchtimer.current);
        }
        if (posttimer.current !== null) {
            window.clearTimeout(posttimer.current);
        }
        searchhold.current?.();
        flushref.current();
    }, []);

    /**
     * Remember the view and draw it.
     *
     * @param {string} chosen auto, table or cards.
     * @returns {void}
     */
    const setView = (chosen: ViewMode): void => {
        setViewState(chosen);
        rememberView(VIEW_KEY, chosen);
    };

    /**
     * The search as typed: sent 300 ms after the last key, from the minimum length; cleared at once.
     *
     * @param {string} text What the field holds.
     * @returns {void}
     */
    const onSearch = (text: string): void => {
        setSearchtext(text);
        if (searchtimer.current !== null) {
            window.clearTimeout(searchtimer.current);
            searchtimer.current = null;
        }
        const trimmed = text.trim();
        const applied = trimmed.length >= props.searchmin ? trimmed : '';
        if (applied === query.current.search) {
            searchhold.current?.();
            searchhold.current = null;
            return;
        }
        if (text === '') {
            searchhold.current?.();
            searchhold.current = null;
            run({search: '', page: 0}, true);
            return;
        }
        if (!searchhold.current) {
            searchhold.current = hold('local_evidencehub/hub:search');
        }
        searchtimer.current = window.setTimeout(() => {
            searchtimer.current = null;
            run({search: applied, page: 0}, true);
            searchhold.current?.();
            searchhold.current = null;
        }, SEARCH_DELAY);
    };

    /**
     * Store a new set of filter values in core's store, then read both regions again.
     *
     * @param {Function} write The write: core's set or reset.
     * @returns {void}
     */
    const writeFilters = (write: () => Promise<boolean>): void => {
        // The write starts another set of rows: the selection of the set on screen goes with it (D131).
        setSelected(new Set<number>());
        const release = hold('local_evidencehub/hub:filters');
        write().then(() => {
            run({page: 0}, true);
            return;
        }).catch(() => {
            setRowserror(true);
        }).finally(release);
    };

    // The panel draws each filter as the rows last counted it, its applied values as they are now.
    const filters: Filter[] = (rows?.filters ?? []).map((filter) => (counted[filter.name]
        ? {...filter, options: counted[filter.name].options}
        : filter));

    /**
     * Set one filter to one option: the band's lines write the status and the competency filters this way.
     *
     * @param {string} name The filter's name.
     * @param {string} value The option.
     * @returns {void}
     */
    const setChoice = (name: string, value: string): void => {
        const filter = filters.find((entry) => entry.name === name);
        if (filter) {
            writeFilters(() => setFilters(reportid, withChoice(filters, filter, value)));
        }
    };

    const {allcolumns, usedefault, suspended, candecide} = settingsOf(rows);
    const chosenkeys = allcolumns.filter((column) => !column.fixed && column.active).map((column) => column.key);

    /**
     * Store the reader's grid (and the accounts choice); read the rows again afterwards when they depend on it.
     *
     * @param {object} grid The whole grid.
     * @param {boolean} reread Whether the rows depend on it (a column change).
     * @returns {void}
     */
    const writePreference = (grid: GridPreference, reread: boolean): void => {
        const release = hold('local_evidencehub/hub:preference');
        setPreference(target, grid, null).then(() => {
            if (reread) {
                loadRows(query.current, false);
            }
            return;
        }).catch(() => {
            if (reread) {
                setRowserror(true);
            }
        }).finally(release);
    };

    /**
     * "Active only" or "Include suspended": the reader's preference, then both regions read again, as for a filter.
     *
     * @param {boolean} on Whether suspended accounts are listed.
     * @returns {void}
     */
    const onAccounts = (on: boolean): void => {
        if (rows) {
            // The panel answers at once; the rows and the band follow.
            setRows({...rows, preference: {...rows.preference, suspended: on}});
        }
        setSelected(new Set<number>());
        const release = hold('local_evidencehub/hub:accounts');
        setPreference(target, gridOf(usedefault, chosenkeys, widths), on).then(() => {
            run({page: 0}, true);
            return;
        }).catch(() => {
            setRowserror(true);
        }).finally(release);
    };

    /**
     * Show or hide one column: the choice is written, then the rows are read with it.
     *
     * @param {string} key The column key.
     * @param {boolean} on Whether to show it.
     * @returns {void}
     */
    const onColumn = (key: string, on: boolean): void => {
        const next = on ? [...chosenkeys, key] : chosenkeys.filter((chosen) => chosen !== key);
        if (rows) {
            // The menu answers at once; the grid follows when the rows come back with the column.
            setRows({
                ...rows,
                columns: rows.columns.map((column) => (column.key === key ? {...column, active: on} : column)),
                preference: {...rows.preference, usedefault: false, columns: next},
            });
        }
        writePreference({usedefault: false, columns: next, widths: widthList(widths)}, true);
    };

    /**
     * Save the widths waiting to be saved, if any: once, whatever number of steps led to them.
     *
     * @returns {void}
     */
    const flushWidths = (): void => {
        if (widthtimer.current !== null) {
            window.clearTimeout(widthtimer.current);
            widthtimer.current = null;
        }
        const next = pendingwidths.current;
        pendingwidths.current = null;
        if (next !== null) {
            writePreference(gridOf(usedefault, chosenkeys, next), false);
        }
        widthhold.current?.();
        widthhold.current = null;
    };
    // The timer and the unmount call the newest flush, which writes the columns as they are then.
    flushref.current = flushWidths;

    /**
     * One column's width, drawn at once; null restores its default. A drag's release and a double click
     * write it now; a key step (defer) waits for the last key, or for the handle to lose the focus.
     *
     * @param {string} key The column key.
     * @param {number|null} width Pixels, or null.
     * @param {boolean} defer Whether to wait for more steps before writing.
     * @returns {void}
     */
    const onWidth = (key: string, width: number | null, defer = false): void => {
        const next = {...widths};
        if (width === null) {
            delete next[key];
        } else {
            next[key] = width;
        }
        setWidths(next);
        pendingwidths.current = next;
        if (!defer) {
            flushWidths();
            return;
        }
        if (widthtimer.current !== null) {
            window.clearTimeout(widthtimer.current);
        }
        if (!widthhold.current) {
            widthhold.current = hold('local_evidencehub/hub:width');
        }
        widthtimer.current = window.setTimeout(() => flushref.current(), WIDTH_DELAY);
    };

    /**
     * Apply one of the Columns menu's sets: written as the reader's choice, then the rows are read with it.
     *
     * @param {object} preset The set.
     * @returns {void}
     */
    const onPreset = (preset: Preset): void => {
        const named = new Set(preset.columns);
        if (rows) {
            setRows({
                ...rows,
                columns: rows.columns.map((column) => (column.fixed ? column : {...column, active: named.has(column.key)})),
                presets: rows.presets.map((entry) => ({...entry, active: entry.name === preset.name})),
                preference: {...rows.preference, usedefault: false, columns: preset.columns},
            });
        }
        writePreference({usedefault: false, columns: preset.columns, widths: widthList(widths)}, true);
    };

    /**
     * Sort by a column: a second press on the sorted one turns the order round.
     *
     * @param {string} key The column key.
     * @returns {void}
     */
    const onSort = (key: string): void => {
        const sorted = rows?.sort ?? '';
        const direction = sorted === key && rows?.direction === 'asc' ? 'desc' : 'asc';
        run({sort: key, direction, page: 0}, false);
    };

    /**
     * Open a pair's drawer from its Inspect button, holding Behat's counter until the drawer has opened.
     *
     * @param {HTMLElement} button The button pressed.
     * @returns {void}
     */
    const onInspect = (button: HTMLElement): void => {
        const release = hold('local_evidencehub/hub:inspect');
        inspect(button, props.contextid, props.templateid).finally(release);
    };

    // The rows with their numbers, and the selection over them.
    const list: PairRow[] = useMemo(
        () => (rows?.rows ?? []).map((row) => ({...row, id: idOf(row.userid, row.competencyid)})),
        [rows, idOf],
    );
    const decides = candecide && props.exportform !== null;
    const locked = new Set(list.filter((row) => !row.selectable).map((row) => row.id));
    const chosen = list.filter((row) => selected.has(row.id));
    /**
     * Select or release one row; a row that may not be selected is never added.
     *
     * @param {number} id The row's number.
     * @param {boolean} on Whether to select it.
     * @returns {void}
     */
    const onSelect = (id: number, on: boolean): void => setSelected((before) => {
        const next = new Set(before);
        if (on && !locked.has(id)) {
            next.add(id);
        } else {
            next.delete(id);
        }
        return next;
    });

    /**
     * Select every selectable row of this page, and no other, or release them all.
     *
     * @param {boolean} on Whether to select them.
     * @returns {void}
     */
    const onSelectPage = (on: boolean): void => setSelected(
        () => (on ? new Set(list.filter((row) => !locked.has(row.id)).map((row) => row.id)) : new Set<number>())
    );

    /**
     * A row box's name: "Select" and the pair.
     *
     * @param {object} row The row.
     * @returns {string} The name.
     */
    const boxLabel = (row: PairRow): string => fill(labels.selectrow, row.drawertitle);

    const selection: Selection<PairRow> | undefined = decides ? {
        selected,
        locked,
        label: boxLabel,
        pagelabel: labels.selectpage,
        onSelect,
        onSelectPage,
    } : undefined;

    /**
     * Post the export of the selected rows' workers' dossiers; the button acts once for a while.
     *
     * @returns {void}
     */
    const onExport = (): void => {
        if (props.exportform === null || chosen.length === 0 || posting) {
            return;
        }
        setPosting(true);
        posttimer.current = window.setTimeout(() => {
            posttimer.current = null;
            setPosting(false);
        }, POST_GUARD);
        exportDossiers({
            ...props.exportform,
            contextid: props.contextid,
            templateid: props.templateid,
            suspended,
            pairs: chosen.map((row) => ({userid: row.userid, competencyid: row.competencyid})),
        });
    };

    const filtered = filters.some((filter) => filter.applied) || current.search !== '' || current.scope !== 'all';
    const identity = allcolumns.filter((column) => column.key.startsWith('identity:'));
    const shown = allcolumns.filter((column) => column.key === 'worker' || column.key === 'competency'
        || (!column.fixed && column.active));
    const cards = view === 'cards' || (view === 'auto' && narrow);
    // The rows as the reader views them; the region draws them only once an answer has some.
    let body: ReactElement | null = null;
    if (rows !== null) {
        body = cards ? (
            <Cards
                labels={labels}
                columns={shown}
                identity={identity}
                rows={list}
                single={narrow}
                selection={selection}
                onInspect={onInspect}
            />
        ) : (
            <Grid
                labels={labels}
                columns={shown}
                identity={identity}
                rows={list}
                widths={widths}
                sort={rows.sort}
                direction={rows.direction}
                selection={selection}
                onInspect={onInspect}
                onSort={onSort}
                onWidth={onWidth}
                onWidthCommit={() => flushref.current()}
            />
        );
    }
    const skeleton: Column[] = SKELETON_COLUMNS.map((key) => ({
        key,
        title: key === 'worker' ? labels.worker : '',
        sortable: false,
        "default": true,
        active: true,
        fixed: key === 'worker',
    }));
    const workers = new Set(chosen.map((row) => row.userid)).size;

    return (
        <div className="local-evidencehub-rview-root" ref={root} data-region="local-evidencehub-hubview">
            <Band
                labels={labels}
                summary={summary}
                loading={bandloading}
                error={banderror}
                canfilter={rows !== null}
                onStatus={(status) => setChoice(STATUS_FILTER, String(status))}
                onCompetency={(competencyid) => setChoice(COMPETENCY_FILTER, String(competencyid))}
                onRetry={() => loadBand(query.current)}
            />
            <Bar
                labels={labels}
                narrow={narrow}
                scope={current.scope}
                summary={summary}
                total={rows === null ? null : rows.total}
                filters={filters}
                columns={allcolumns.filter((column) => !column.key.startsWith('identity:'))}
                presets={rows?.presets ?? []}
                usedefault={usedefault}
                haswidths={Object.keys(widths).length > 0}
                suspended={suspended}
                view={view}
                search={searchtext}
                searchmin={props.searchmin}
                searching={rowsloading}
                matrixurl={props.matrixurl}
                tableurl={props.tableurl}
                onScope={(scope: Scope) => run({scope, page: 0}, true)}
                onSearch={onSearch}
                onFilter={(filter, value) => writeFilters(() => setFilters(reportid, withChoice(filters, filter, value)))}
                onRemoveFilter={(filter) => writeFilters(() => setFilters(reportid, without(filters, filter)))}
                onClearFilters={() => writeFilters(() => resetFilters(reportid))}
                onClearAll={() => {
                    setSearchtext('');
                    query.current = {...query.current, search: ''};
                    if (filters.some((filter) => filter.applied)) {
                        writeFilters(() => resetFilters(reportid));
                    } else {
                        run({page: 0}, true);
                    }
                }}
                onAccounts={onAccounts}
                onColumn={onColumn}
                onPreset={onPreset}
                onDefaultColumns={() => {
                    // The server forgets the widths with the column choice.
                    setWidths({});
                    if (rows) {
                        setRows({...rows, preference: {...rows.preference, usedefault: true, columns: [], widths: []}});
                    }
                    writePreference({usedefault: true, columns: [], widths: []}, true);
                }}
                onResetWidths={() => {
                    setWidths({});
                    writePreference(gridOf(usedefault, chosenkeys, {}), false);
                }}
                onView={setView}
            />
            <RowsRegion
                labels={labels}
                loading={rowsloading}
                loaded={rows !== null}
                error={rowserror}
                total={rows?.total ?? 0}
                filtered={filtered}
                cards={cards}
                narrow={narrow}
                skeleton={{columns: skeleton, lead: 'worker', widths, sizing: SIZING}}
                dock={decides && chosen.length > 0 ? (
                    <Dock labels={labels} count={chosen.length} workers={workers} posting={posting} onExport={onExport} />
                ) : null}
                onRetry={() => loadRows(query.current, true)}
            >
                {body}
            </RowsRegion>
            {rows !== null && rows.total > 0 ? (
                <Pager
                    labels={labels}
                    page={current.page}
                    perpage={current.perpage}
                    perpages={props.perpages}
                    total={rows.total}
                    shown={rows.rows.length}
                    narrow={narrow}
                    onPage={(page) => run({page}, false)}
                    onPerpage={(perpage) => run({perpage, page: 0}, false)}
                />
            ) : null}
        </div>
    );
};

export default HubView;
