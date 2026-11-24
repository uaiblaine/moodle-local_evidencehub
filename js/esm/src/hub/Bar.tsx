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
 * The hub's filter bar: the shared bar (datareport/Bar) with the hub's vocabulary.
 *
 * The scope is "Needs attention" (every status but compliant, the default) or "All", each with its count
 * from the summary (decision 3). Filters draws the report's facets, which live in core's store
 * (core_reportbuilder_set_filters, filters.ts): status, cohort, competency, type of evidence, verification
 * and "Valid until", each option with the pairs it would list under every other criterion in force, a facet
 * above eight options as a searched list. Under them, "Accounts" ("Active only" or "Include suspended"),
 * which is not a stored filter but the reader's preference (decision 5), and a note leading to core's table
 * view for the filter the panel does not draw (the worker's name). Columns starts with the two sets
 * (Validity, Deciding evidence), then the catalogue, the worker and the competency always shown. View is
 * Automatic, Table or Cards, then the matrix (in the same scope) and core's table view. Download is the capsule's last
 * button (D114): a menu of the site's enabled formats, each a link to index.php's download branch with the scope, the
 * search and the sort in force, so the file holds what the list shows; the report applies the stored filters and the
 * columns itself. It is offered only once rows have arrived and while there are any. No More menu: the dossiers are
 * the dock's (the selection variant).
 *
 * @module     local_evidencehub/hub/Bar
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type {ReactElement} from 'react';
import {Bar as DataBar} from '../datareport/Bar';
import {Segmented} from '../datareport/Segmented';
import {fillObject, num} from '../datareport/str';
import type {Chip, ColumnSet, DownloadLink, FacetDefinition} from '../datareport/types';
import {COHORT_FILTER, COMPETENCY_FILTER, choosable, chosen, chosenLabel} from './filters';
import type {Column, Filter, Format, Preset, Scope, SummaryAnswer, ViewMode} from './types';

type BarProps = {
    labels: Record<string, string>,
    narrow: boolean,
    scope: Scope,
    summary: SummaryAnswer | null,
    total: number | null,
    filters: Filter[],
    columns: Column[],
    presets: Preset[],
    usedefault: boolean,
    haswidths: boolean,
    suspended: boolean,
    view: ViewMode,
    search: string,
    searchmin: number,
    searching: boolean,
    matrixurl: string,
    tableurl: string,
    /** Index.php's download branch with the context, the template and the sesskey; '' offers no download. */
    downloadurl: string,
    formats: Format[],
    /** The search in force (as the rows were read with it), the sort and its direction. */
    appliedsearch: string,
    sort: string,
    direction: '' | 'asc' | 'desc',
    /** Whether the rows can be downloaded now: they have arrived and there are some. */
    downloadable: boolean,
    onScope: (scope: Scope) => void,
    onSearch: (text: string) => void,
    onFilter: (filter: Filter, value: string) => void,
    onRemoveFilter: (filter: Filter) => void,
    onClearFilters: () => void,
    onClearAll: () => void,
    onAccounts: (suspended: boolean) => void,
    onColumn: (key: string, on: boolean) => void,
    onPreset: (preset: Preset) => void,
    onDefaultColumns: () => void,
    onResetWidths: () => void,
    onView: (view: ViewMode) => void,
};

/** Every view the hub offers. */
const VIEWS: ViewMode[] = ['auto', 'table', 'cards'];

/**
 * A Columns set's name, by the literal key of each (a label is never looked up by a computed name).
 *
 * @param {object} labels The strings.
 * @param {string} name The set's name (reportengine::PRESETS).
 * @returns {string} Its label.
 */
const presetLabel = (labels: Record<string, string>, name: string): string => {
    switch (name) {
        case 'validity':
            return labels.presetvalidity;
        case 'evidence':
            return labels.presetevidence;
        default:
            return name;
    }
};

/**
 * A report filter as a facet of the panel: a searched list's words name what it lists.
 *
 * @param {object} labels The strings.
 * @param {object} filter The filter, one the panel draws.
 * @returns {object} The facet.
 */
const facet = (labels: Record<string, string>, filter: Filter): FacetDefinition => {
    let find = {label: labels.searchoptions, nomatch: labels.nooptionmatch};
    if (filter.name === COHORT_FILTER) {
        find = {label: labels.searchcohorts, nomatch: labels.nocohortmatch};
    } else if (filter.name === COMPETENCY_FILTER) {
        find = {label: labels.searchcompetencies, nomatch: labels.nocompetencymatch};
    }

    return {name: filter.name, title: filter.title, options: filter.options, groupings: [], find};
};

/**
 * What an applied filter reads as on its chip: its heading and its option, or its heading alone.
 *
 * @param {object} labels The strings.
 * @param {object} filter The filter, applied.
 * @returns {object} The chip.
 */
const chip = (labels: Record<string, string>, filter: Filter): Chip => {
    const value = chosenLabel(filter);

    return {key: filter.name, text: value === '' ? filter.title : fillObject(labels.filterchip, {title: filter.title, value})};
};

/**
 * The matrix's address for the scope in force: its own default is the pairs needing attention, as here.
 *
 * @param {string} url The matrix's address, with the context and the template.
 * @param {string} scope The scope in force.
 * @returns {string} The address.
 */
const matrixHref = (url: string, scope: Scope): string => (scope === 'all' ? `${url}&scope=all` : url);

/**
 * The criteria of the download link: the scope, the search in force and the sort, as index.php's download branch reads
 * them; the stored filters and the columns are the report's own.
 *
 * @param {string} scope The scope in force.
 * @param {string} search The search in force, or ''.
 * @param {string} sort The column key sorted by, or '' for the report's own order.
 * @param {string} direction asc or desc.
 * @returns {string} The query string's added part, encoded.
 */
const downloadQuery = (scope: Scope, search: string, sort: string, direction: string): string => {
    const query = new URLSearchParams({scope});
    if (search !== '') {
        query.set('search', search);
    }
    if (sort !== '') {
        query.set('sort', sort);
        query.set('dir', direction === 'desc' ? 'desc' : 'asc');
    }

    return query.toString();
};

/**
 * The panel's "Accounts" group: a preference, not a stored filter, drawn under the facets.
 *
 * @param {object} props The strings, whether suspended accounts are listed and what a change does.
 * @returns {object} The group.
 */
const Accounts = ({labels, suspended, onAccounts}: {labels: Record<string, string>, suspended: boolean,
        onAccounts: (suspended: boolean) => void}): ReactElement => (
    <div className="local-evidencehub-rview-fgroup" data-region="local-evidencehub-accounts">
        <span className="local-evidencehub-rview-fpanel-head">{labels.accounts}</span>
        <Segmented
            label={labels.accounts}
            small
            value={suspended ? 'all' : 'active'}
            options={[{value: 'active', label: labels.activeonly}, {value: 'all', label: labels.includesuspended}]}
            onChange={(value) => {
                if ((value === 'all') !== suspended) {
                    onAccounts(value === 'all');
                }
            }}
        />
    </div>
);

/**
 * The bar.
 *
 * @param {object} props See BarProps.
 * @returns {object} The bar.
 */
export const Bar = (props: BarProps): ReactElement => {
    const {labels, filters, summary} = props;
    const panelfilters = filters.filter(choosable);
    const others = filters.filter((filter) => !choosable(filter));
    /**
     * The filter a facet or a chip names.
     *
     * @param {string} name The filter's name.
     * @returns {object|undefined} The filter.
     */
    const named = (name: string): Filter | undefined => filters.find((filter) => filter.name === name);
    const query = downloadQuery(props.scope, props.appliedsearch, props.sort, props.direction);
    const downloads: DownloadLink[] = props.downloadable && props.downloadurl !== '' ? props.formats.map((format) => ({
        key: format.value,
        label: format.label,
        href: `${props.downloadurl}&${query}&download=${encodeURIComponent(format.value)}`,
    })) : [];
    const sets: ColumnSet[] = props.presets.map((preset) => ({
        name: preset.name,
        label: presetLabel(labels, preset.name),
        active: preset.active,
    }));

    return (
        <DataBar
            labels={labels}
            narrow={props.narrow}
            scopes={[
                {value: 'attention', label: labels.attention, count: summary === null ? undefined : num(summary.attention)},
                {value: 'all', label: labels.all, count: summary === null ? undefined : num(summary.pairs)},
            ]}
            scope={props.scope}
            total={props.total}
            facets={panelfilters.map((filter) => facet(labels, filter))}
            values={Object.fromEntries(panelfilters.map((filter) => [filter.name, chosen(filter)]))}
            panelnote={(
                <>
                    <Accounts labels={labels} suspended={props.suspended} onAccounts={props.onAccounts} />
                    {others.length > 0 ? (
                        <p className="local-evidencehub-rview-note">
                            {labels.morefiltershelp}{' '}
                            <a href={props.tableurl}>{labels.morefilters}</a>
                        </p>
                    ) : null}
                </>
            )}
            chips={filters.filter((filter) => filter.applied).map((filter) => chip(labels, filter))}
            columns={props.columns}
            sets={sets}
            usedefault={props.usedefault}
            haswidths={props.haswidths}
            view={props.view}
            views={VIEWS}
            tableurl={props.tableurl}
            viewlinks={props.matrixurl === '' ? [] : [
                {key: 'matrix', label: labels.matrix, href: matrixHref(props.matrixurl, props.scope), icon: 'matrix'},
            ]}
            downloads={downloads}
            search={props.search}
            searchmin={props.searchmin}
            searching={props.searching}
            onScope={(scope) => props.onScope(scope === 'all' ? 'all' : 'attention')}
            onSearch={props.onSearch}
            onFacet={(name, value) => {
                const filter = named(name);
                if (filter) {
                    props.onFilter(filter, value);
                }
            }}
            onRemoveChip={(name) => {
                const filter = named(name);
                if (filter) {
                    props.onRemoveFilter(filter);
                }
            }}
            onClearFilters={props.onClearFilters}
            onClearAll={props.onClearAll}
            onColumn={props.onColumn}
            onSet={(name) => {
                const preset = props.presets.find((entry) => entry.name === name);
                if (preset) {
                    props.onPreset(preset);
                }
            }}
            onDefaultColumns={props.onDefaultColumns}
            onResetWidths={props.onResetWidths}
            onView={props.onView}
        />
    );
};
