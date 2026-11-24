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
 * The only module of the hub's view that talks to the server's web services.
 *
 * Every call goes through core's ES module @moodle/lms/core/ajax (a component cannot import an AMD
 * module), and every call is ONE request of its own (fetchOne), never a batch: core keeps one instance
 * of a report per user for the whole request, so a rows call batched after a filter or preference write
 * would read the report built before it (the preference writer never builds the report for that reason,
 * classes/external/set_report_preference.php). Core's ajax holds Behat's pending counter while each
 * request is in flight; the view holds it across the render that follows (datareport/pending.ts).
 *
 * The report is identified by its context and template (hub_pairs, the template being its item): core's
 * filter services rebuild it from its persistent, whose context and item it already holds, and take no
 * parameter of the hub's.
 *
 * @module     local_evidencehub/hub/repository
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {fetchOne} from '@moodle/lms/core/ajax';
import type {Preference, RowsAnswer, RowsQuery, Scope, SummaryAnswer} from './types';

/** The report's object: the hub context and the template. */
export type Target = {contextid: number, templateid: number};

/**
 * A page of the pairs.
 *
 * @param {object} target The context and the template.
 * @param {object} query What to read; see RowsQuery.
 * @param {boolean} facets Whether to count the filter panel's options: only when a count can have moved.
 * @returns {Promise} The answer.
 */
export const getRows = (target: Target, query: RowsQuery, facets: boolean): Promise<RowsAnswer> =>
    fetchOne<RowsAnswer>({methodname: 'local_evidencehub_report_rows', args: {...target, ...query, facets}});

/**
 * The band's figures over the same pairs.
 *
 * @param {object} target The context and the template.
 * @param {string} scope Which pairs; the figures do not depend on it, the server echoes it.
 * @param {string} search Part of the worker's name, or ''.
 * @returns {Promise} The answer.
 */
export const getSummary = (target: Target, scope: Scope, search: string): Promise<SummaryAnswer> =>
    fetchOne<SummaryAnswer>({methodname: 'local_evidencehub_report_summary', args: {...target, scope, search}});

/**
 * Store the reader's grid, and whether suspended accounts are listed.
 *
 * @param {object} target The context and the template the reader is on.
 * @param {object} preference The grid: whether the default columns show, the chosen keys, the widths.
 * @param {boolean|null} suspended Whether suspended accounts are listed; null leaves it as stored.
 * @returns {Promise} The grid as stored.
 */
export const setPreference = (target: Target, preference: Omit<Preference, 'suspended'>,
        suspended: boolean | null): Promise<Preference> =>
    fetchOne<Preference>({
        methodname: 'local_evidencehub_set_report_preference',
        args: {
            ...target,
            usedefault: preference.usedefault,
            columns: preference.columns,
            widths: preference.widths,
            suspended: suspended === null ? -1 : Number(suspended),
        },
    });

/**
 * Store the reader's filter values in core's own store for the report.
 *
 * @param {number} reportid The report persistent.
 * @param {object} values Every filter value of the report, as core's filter form posts them.
 * @returns {Promise} Whether core stored them.
 */
export const setFilters = (reportid: number, values: Record<string, unknown>): Promise<boolean> =>
    fetchOne<boolean>({
        methodname: 'core_reportbuilder_set_filters',
        args: {reportid, parameters: '{}', values: JSON.stringify(values)},
    });

/**
 * Forget every filter value the reader stored for the report.
 *
 * @param {number} reportid The report persistent.
 * @returns {Promise} Whether core reset them.
 */
export const resetFilters = (reportid: number): Promise<boolean> =>
    fetchOne<boolean>({
        methodname: 'core_reportbuilder_filters_reset',
        args: {reportid, parameters: '{}'},
    });
