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
 * The hub's band: the readiness of the listed workers (study, section 2.6, decision 7).
 *
 * Four tiles - the workers, with the workers hidden by permissions as the sub-line when there are any; the
 * ready workers with the rate; the pairs needing attention of all the pairs; the pairs expiring in 30 days -
 * and two panels: the pairs by status and the competencies with most open pairs. Each line of a panel is a
 * button that sets the report's status or competency filter, its number written as text beside its bar.
 * There is no compact band: every figure is already over the reader's own workers. A figure with no value
 * reads "–". A status counting no pair is left out of the distribution unless it is the status filter in force.
 *
 * Every number is the server's (reportengine::summary()), over the report's own rows under every criterion
 * but the scope and the status filter.
 *
 * @module     local_evidencehub/hub/Band
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type {ReactElement} from 'react';
import {fill, num} from '../datareport/str';
import type {SummaryAnswer} from './types';

/** What a figure without a value reads (data-report.md, "Empty"). */
const NONE = '–';

type BandProps = {
    labels: Record<string, string>,
    summary: SummaryAnswer | null,
    loading: boolean,
    error: boolean,
    /** Whether a line of a panel can set its filter: the rows' answer, which carries the filters, has come. */
    canfilter: boolean,
    /** The status code the stored status filter holds, as a string; '' when none is in force. */
    chosenstatus: string,
    onStatus: (status: number) => void,
    onCompetency: (competencyid: number) => void,
    onRetry: () => void,
};

/**
 * One tile.
 *
 * @param {object} props The label, the value and the line under it.
 * @returns {object} The tile.
 */
const Tile = ({label, value, sub}: {label: string, value: string, sub: string}): ReactElement => (
    <div className="local-evidencehub-rview-tile">
        <div className="local-evidencehub-rview-tile-label">{label}</div>
        <div className="local-evidencehub-rview-tile-value">{value}</div>
        <div className="local-evidencehub-rview-tile-sub">{sub}</div>
    </div>
);

/**
 * One line of a panel: a button with its label, a bar and its figure as text.
 *
 * @param {object} props The label, the bar's share (0 to 1), the figure, the button's title and what a press does.
 * @returns {object} The line.
 */
const BarRow = ({label, share, figure, title, disabled, onPress}: {label: string, share: number, figure: string,
        title: string, disabled: boolean, onPress: () => void}): ReactElement => (
    <li className="local-evidencehub-rview-hitem">
        <button type="button" className="local-evidencehub-rview-hrow" title={title} disabled={disabled} onClick={onPress}>
            <span className="local-evidencehub-rview-hrow-label">{label}</span>
            <span className="local-evidencehub-rview-track" aria-hidden="true">
                <span
                    className="local-evidencehub-rview-fill"
                    style={{width: `${Math.round(Math.min(1, Math.max(0, share)) * 100)}%`}}
                />
            </span>
            <span className="local-evidencehub-rview-hrow-n">{figure}</span>
        </button>
    </li>
);

/**
 * The band's skeleton, in the shape it will settle to.
 *
 * @returns {object} The skeleton.
 */
const BandSkeleton = (): ReactElement => (
    <div aria-hidden="true">
        <div className="local-evidencehub-rview-band">
            {Array.from({length: 4}, (_, i) => (
                <div key={i} className="local-evidencehub-rview-tile local-evidencehub-rview-sk">
                    <span className="local-evidencehub-rview-skb local-evidencehub-rview-skb--w60" />
                    <span className="local-evidencehub-rview-skb local-evidencehub-rview-skb--big" />
                    <span className="local-evidencehub-rview-skb local-evidencehub-rview-skb--w80" />
                </div>
            ))}
        </div>
        <div className="local-evidencehub-rview-dist">
            {[0, 1].map((i) => (
                <div key={i} className="local-evidencehub-rview-panel local-evidencehub-rview-sk">
                    <span className="local-evidencehub-rview-skb local-evidencehub-rview-skb--w40" />
                    <span className="local-evidencehub-rview-skb" />
                    <span className="local-evidencehub-rview-skb" />
                    <span className="local-evidencehub-rview-skb" />
                </div>
            ))}
        </div>
    </div>
);

/**
 * The band.
 *
 * @param {object} props See BandProps.
 * @returns {object} The band's region.
 */
export const Band = (props: BandProps): ReactElement => {
    const {labels, summary, loading, error, canfilter, chosenstatus, onStatus, onCompetency, onRetry} = props;
    let body: ReactElement;
    if (summary === null && error) {
        body = (
            <p className="local-evidencehub-rview-error">
                {labels.loaderror}{' '}
                <button type="button" className="btn btn-sm btn-outline-secondary" onClick={onRetry}>{labels.retry}</button>
            </p>
        );
    } else if (summary === null) {
        body = (
            <>
                <BandSkeleton />
                <span className="visually-hidden" role="status">{labels.loadband}</span>
            </>
        );
    } else {
        const rate = summary.rate ?? null;
        const distribution = summary.distribution.filter((entry) => entry.count > 0 || String(entry.status) === chosenstatus);
        const statusmost = Math.max(1, ...distribution.map((entry) => entry.count));
        const worstmost = Math.max(1, ...summary.worst.map((entry) => entry.count));
        body = (
            <div className="local-evidencehub-rview-dim">
                {error ? (
                    <p className="local-evidencehub-rview-error">
                        {labels.loaderror}{' '}
                        <button type="button" className="btn btn-sm btn-outline-secondary" onClick={onRetry}>{labels.retry}</button>
                    </p>
                ) : null}
                <div className="local-evidencehub-rview-band">
                    <Tile
                        label={labels.workers}
                        value={num(summary.workers)}
                        sub={summary.hidden > 0 ? fill(labels.hiddenworkers, num(summary.hidden)) : labels.workerssub}
                    />
                    <Tile
                        label={labels.ready}
                        value={rate === null ? NONE : num(summary.ready)}
                        sub={rate === null ? labels.readynone : fill(labels.readysub, num(Math.round(rate * 100)))}
                    />
                    <Tile
                        label={labels.attentiontile}
                        value={num(summary.attention)}
                        sub={fill(labels.attentionsub, num(summary.pairs))}
                    />
                    <Tile label={labels.expiring} value={num(summary.expiring)} sub={labels.expiringsub} />
                </div>
                <div className="local-evidencehub-rview-dist">
                    <div className="local-evidencehub-rview-panel">
                        <div className="local-evidencehub-rview-panel-title"><span>{labels.bystatus}</span></div>
                        <ul className="local-evidencehub-rview-hlist">
                            {distribution.map((entry) => (
                                <BarRow
                                    key={entry.status}
                                    label={entry.label}
                                    share={entry.count / statusmost}
                                    figure={num(entry.count)}
                                    title={labels.showpairs}
                                    disabled={!canfilter}
                                    onPress={() => onStatus(entry.status)}
                                />
                            ))}
                        </ul>
                    </div>
                    <div className="local-evidencehub-rview-panel">
                        <div className="local-evidencehub-rview-panel-title"><span>{labels.worst}</span></div>
                        {summary.worst.length > 0 ? (
                            <ul className="local-evidencehub-rview-hlist">
                                {summary.worst.map((entry) => (
                                    <BarRow
                                        key={entry.competencyid}
                                        label={entry.name}
                                        share={entry.count / worstmost}
                                        figure={num(entry.count)}
                                        title={labels.showpairs}
                                        disabled={!canfilter}
                                        onPress={() => onCompetency(entry.competencyid)}
                                    />
                                ))}
                            </ul>
                        ) : <p className="local-evidencehub-rview-note">{labels.noworst}</p>}
                    </div>
                </div>
            </div>
        );
    }

    return (
        <section
            className="local-evidencehub-rview-region local-evidencehub-rview-bandregion"
            aria-label={labels.bandlabel}
            aria-busy={loading}
            data-region="local-evidencehub-band"
        >
            {loading && summary !== null ? <div className="local-evidencehub-rview-progress" /> : null}
            {body}
        </section>
    );
};
