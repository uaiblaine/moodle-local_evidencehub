<?php
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

namespace local_evidencehub\local;

use core_reportbuilder\exception\report_access_exception;
use core_reportbuilder\local\helpers\database;
use core_reportbuilder\local\report\filter;
use local_evidencehub\reportbuilder\local\filters\validuntil;
use local_evidencehub\reportbuilder\local\systemreports\headless_table;
use local_evidencehub\reportbuilder\local\systemreports\hub_pairs;

/**
 * The hub's pairs report read headlessly: its rows, its facet counts and its summary.
 *
 * The system report ({@see hub_pairs}) stays the one definition of rows, columns, access, masking and
 * filters; this class only reads it. Both answers start from the table core itself builds for the report
 * ({@see headless_table}), so they carry its base conditions (the template, the reader's userevidenceview
 * cut, the restricted rule, the suspended flag), the reader's stored filters and the columns the reader
 * chose. The scope and the search are appended to that table's WHERE by {@see self::conditions()}, the
 * definition the report itself uses for its parameters, and can only narrow it.
 *
 * Every count is SQL, grouped: the report is 300,000 pairs at the seed, so no pair reaches PHP and no statement
 * runs per row; PHP only adds up grouped rows, whose number the options bound, never the pairs. Criteria that share
 * a WHERE share a statement. Nothing is node-local: the figures are the database's, or entries of the MUC
 * application cache reportsummary (the summary, and the rows' total and facet counts), keyed per reader and
 * retired by the shared generation ({@see hub_kpis}).
 *
 * Core keeps one instance of a report per reader for the whole request
 * (\core_reportbuilder\manager::get_report_from_persistent()), so every call is meant to be a request of its
 * own; the preference's writer never builds the report.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class reportengine {
    /** @var array The page sizes a reader may choose; anything else reads as DEFAULT_PERPAGE. */
    public const array PERPAGES = [30, 50, 100];

    /** @var int The page size of a request that names none of PERPAGES. */
    public const int DEFAULT_PERPAGE = 50;

    /** @var string The pairs needing attention: every effective status but compliant. */
    public const string SCOPE_ATTENTION = 'attention';

    /** @var string Every pair. */
    public const string SCOPE_ALL = 'all';

    /** @var array The scopes, the default first. */
    public const array SCOPES = [self::SCOPE_ATTENTION, self::SCOPE_ALL];

    /** @var int A search shorter than this many characters searches nothing. */
    public const int SEARCH_MIN = 2;

    /** @var int The longest search kept, in characters. */
    public const int SEARCH_MAX = 100;

    /** @var int The "expiring" window of the summary, in days from today, both ends included. */
    public const int EXPIRING_DAYS = 30;

    /** @var int The highest page read: a larger request is read as this one, and answers the last page. */
    public const int MAX_PAGE = 1000000;

    /** @var int How many competencies the summary's "most open pairs" list holds. */
    public const int WORST = 5;

    /** @var array The Columns menu's sets (D127): name => the keys stored as the reader's choice. */
    public const array PRESETS = [
        'validity' => ['status', 'expiry', 'count', 'changed'],
        'evidence' => ['status', 'type', 'issuer', 'issuedate', 'verification'],
    ];

    /** @var string The status filter, which the scope and the summary read past. */
    public const string STATUS_FILTER = 'validity:status';

    /** @var string The cohort filter, counted by membership. */
    public const string COHORT_FILTER = 'user:cohort';

    /** @var string The valid-until filter, counted by window. */
    public const string VALIDUNTIL_FILTER = 'validity:validuntil';

    /** @var string The filter whose field the search reads: the worker's full name. */
    public const string SEARCH_FILTER = 'user:fullname';

    /**
     * @var array The facets whose field holds one value per pair, read together in one statement while their own
     *     filters are not in force ({@see self::shared_facet_counts()}). The cohort is not one: a worker is in several.
     */
    public const array SHARED_FACETS = [
        self::STATUS_FILTER,
        'competency:competency',
        'evidence_details:type',
        'evidence_details:verifystatus',
        self::VALIDUNTIL_FILTER,
    ];

    /**
     * The hub context and template a client names, after the report's whole access rule.
     *
     * Both ids come from the client, so both are checked here, with the predicates that do not throw, to give
     * one refusal whatever failed: a context that is not the system or a category, a reader without view there,
     * competencies disabled, a template that does not exist or that the hub does not offer there.
     *
     * @param int $contextid The hub context.
     * @param int $templateid The template.
     * @return array [\context, the template record as {@see hub_access::templates()} lists it].
     * @throws report_access_exception When the reader may not open the pairs of that template there.
     */
    public static function locate(int $contextid, int $templateid): array {
        $context = $contextid > 0 ? \context::instance_by_id($contextid, IGNORE_MISSING) : false;
        if (!$context || !hub_pairs::may_view($context, $templateid)) {
            throw new report_access_exception();
        }
        $template = hub_access::offered_template($templateid, $context);
        if ($template === null) {
            throw new report_access_exception();
        }
        return [$context, $template];
    }

    /**
     * The pairs report of a template in a hub context, refused to a reader it would refuse.
     *
     * Core runs the report's can_view() while it is built, so no instance exists for a reader it refuses.
     *
     * @param \context $context The hub context.
     * @param int $templateid The template.
     * @return hub_pairs
     * @throws report_access_exception When the report's can_view() refuses the current user.
     */
    public static function report(\context $context, int $templateid): hub_pairs {
        return hub_pairs::for_template($context, $templateid);
    }

    /**
     * The scope, from its fixed vocabulary.
     *
     * @param string $scope The requested scope.
     * @return string One of SCOPES; anything else reads as attention, the narrower.
     */
    public static function scope(string $scope): string {
        return in_array($scope, self::SCOPES, true) ? $scope : self::SCOPE_ATTENTION;
    }

    /**
     * The search as it will be applied: cleaned, trimmed, at most SEARCH_MAX characters, and empty when shorter
     * than SEARCH_MIN.
     *
     * @param string $search The requested search.
     * @return string The search, or ''.
     */
    public static function search(string $search): string {
        $search = \core_text::substr(trim(clean_param($search, PARAM_TEXT)), 0, self::SEARCH_MAX);
        return \core_text::strlen($search) >= self::SEARCH_MIN ? $search : '';
    }

    /**
     * The scope, the search and the suspended flag as SQL conditions over one instance of the report.
     *
     * The one definition every reader of these request facts applies: the headless rows and summary append
     * them to the table's WHERE ({@see self::table()}), the report adds them as base conditions from its own
     * parameters ({@see hub_pairs::initialise()}). Each is ANDed, so none can widen the rows.
     *
     * The scope reads the status filter's field, the masked effective status. The search is the worker's name as
     * the report's own full-name filter reads it (never the ID number or the e-mail, which not every reader is
     * shown), case-insensitive; its parameters are renamed to names core generates, so they cannot meet the same
     * filter's own when the reader also applied it.
     *
     * @param hub_pairs $report The instance whose aliases the SQL uses.
     * @param string $scope A cleaned scope ({@see self::scope()}).
     * @param string $search A cleaned search ({@see self::search()}), '' for none.
     * @param bool $suspended Whether suspended accounts are listed; true adds no condition.
     * @return array Each: [the condition, its named parameters].
     */
    public static function conditions(hub_pairs $report, string $scope, string $search, bool $suspended = true): array {
        global $DB;
        $conditions = [];
        if (!$suspended) {
            $conditions[] = ["{$report->user_alias()}.suspended = 0", []];
        }
        if ($scope === self::SCOPE_ATTENTION) {
            $conditions[] = [$report->get_filter(self::STATUS_FILTER)->get_field_sql() . ' <> ' . status::STATUS_VALID, []];
        }
        $filter = $report->get_filter(self::SEARCH_FILTER);
        if ($search !== '' && $filter !== null) {
            // Core calls the callback twice per name (the SQL, then the parameters): one new name for each.
            $names = [];
            [$fieldsql, $fieldparams] = database::sql_replace_parameters(
                $filter->get_field_sql(),
                $filter->get_field_params(),
                static function (string $name) use (&$names): string {
                    return $names[$name] ??= database::generate_param_name('_hs');
                }
            );
            $param = database::generate_param_name();
            $conditions[] = [
                $DB->sql_like($fieldsql, ":{$param}", false, false),
                $fieldparams + [$param => '%' . $DB->sql_like_escape($search) . '%'],
            ];
        }
        return $conditions;
    }

    /**
     * A page of the report's pairs, with what a client needs to draw them.
     *
     * The cells are the report's own formatted values (format_row(), where masking and escaping already ran) for
     * the columns it shows this reader; beside them the pair's key, its effective status and whether it is
     * restricted, read from the report's base fields. The sort names a column key; one the report does not show,
     * or cannot sort by, leaves the report's own sort and comes back empty.
     *
     * The total, the hidden workers and the facet counts do not change with the page, its size or the sort: without
     * a search they are kept in the reportsummary cache under the reader's criteria ({@see hub_kpis::report_rows_key()}),
     * so a page turn or a sort reads the page's keys and nothing else. Counted here, the total is the shared facet
     * read's sum when that read ran over the rows' own WHERE, else a count of its own. An empty page counts again,
     * and a page past the last answers the last one.
     *
     * @param hub_pairs $report The report, as {@see self::report()} returned it.
     * @param array $options 'scope', 'search', 'page' (from 0), 'perpage', 'sort' (a column key), 'direction'
     *     ('asc' or 'desc'), 'facets' (bool, default true).
     * @return array See \local_evidencehub\external\report_rows::execute_returns().
     */
    public static function rows(hub_pairs $report, array $options): array {
        $scope = self::scope((string) ($options['scope'] ?? self::SCOPE_ATTENTION));
        $search = self::search((string) ($options['search'] ?? ''));
        $perpage = (int) ($options['perpage'] ?? 0);
        $perpage = in_array($perpage, self::PERPAGES, true) ? $perpage : self::DEFAULT_PERPAGE;
        $page = min(self::MAX_PAGE, max(0, (int) ($options['page'] ?? 0)));
        $direction = ($options['direction'] ?? '') === 'desc' ? SORT_DESC : SORT_ASC;
        $counted = (bool) ($options['facets'] ?? true);

        $table = self::table($report, $scope, $search);
        /** @var hub_pairs $tablereport */
        $tablereport = $table->get_report();
        $keys = $tablereport->active_keys();
        $columns = $tablereport->get_active_columns();
        $sort = (string) ($options['sort'] ?? '');
        $sorted = isset($keys[$sort]) && $columns[$keys[$sort]]->get_is_sortable() ? $sort : '';
        // An explicit sort, never the one core's table kept in the session from an earlier request.
        $table->set_sortdata($sorted === '' ? [] : [
            ['sortby' => $columns[$keys[$sorted]]->get_column_alias(), 'sortorder' => $direction],
        ]);
        $table->guess_base_url();
        $table->setup();

        // A search is never kept, nor is a name filter with a value: free text would grow the cache without bound.
        $cachekey = null;
        if ($search === '' && !self::name_in_force($tablereport, $table->filter_values())) {
            $cachekey = hub_kpis::report_rows_key(
                $tablereport->get_context(),
                $tablereport->template_id(),
                $tablereport->scope(),
                $table->filter_values(),
                $tablereport->includes_suspended(),
                $scope,
                validuntil::today(),
                self::offered_cohorts($tablereport)
            );
        }
        $kept = $cachekey === null ? [] : (hub_kpis::recall($cachekey) ?? []);
        $known = $kept;
        $counts = $counted ? ($known['facets'] ?? null) : null;
        $fresh = false;
        if ($counted && $counts === null) {
            [$counts, $sum] = self::facet_counts($table);
            $known['facets'] = $counts;
            $known['total'] ??= $sum;
            $fresh = true;
        }
        $known['hidden'] ??= hub_kpis::hidden_workers($tablereport->template_id(), $tablereport->scope());
        $known['total'] ??= $table->count_rows();
        $total = (int) $known['total'];

        $table->know_total($total);
        $records = self::page($table, $page, $perpage);
        if ($records === [] && ($page > 0 || $total > 0)) {
            // Past the last page, or rows gone in a way no generation follows: count again, then read the last page.
            $total = $table->count_rows();
            if ($total !== (int) $known['total'] && !$fresh) {
                // The cached facets counted rows that are gone: this response and the entry take the fresh ones.
                unset($known['facets']);
                if ($counted) {
                    [$counts] = self::facet_counts($table);
                    $known['facets'] = $counts;
                }
            }
            $known['total'] = $total;
            $table->know_total($total);
            $page = $total > 0 ? intdiv($total - 1, $perpage) : 0;
            if ($total > 0) {
                $records = self::page($table, $page, $perpage);
            }
        }
        if ($cachekey !== null && $known !== $kept) {
            hub_kpis::keep($cachekey, $known);
        }

        $aliases = [];
        foreach ($keys as $key => $identifier) {
            $aliases[$key] = $columns[$identifier]->get_column_alias();
        }
        // The selection variant (D129): whether the reader may act on the rows in bulk, the dossier export's own
        // rule. Every listed pair's worker is one the reader may view, so each row is selectable when the page is.
        $candecide = dossier_builder::can_export($tablereport->get_context());
        $rows = [];
        $readable = [];
        foreach ($records as $record) {
            $facts = $tablereport->pair_facts($record);
            $formatted = $table->format_row($record);
            $cells = [];
            foreach ($aliases as $key => $alias) {
                $cells[] = ['key' => $key, 'html' => (string) ($formatted[$alias] ?? '')];
            }
            // The two titles are printed as text, and format_string() still writes a lone "<" or "&" as an entity
            // even unescaped: decode it, as the filter options' labels are below.
            $names = (object) [
                'worker' => $facts['worker'],
                'competency' => html_entity_decode($facts['competency'], ENT_QUOTES | ENT_HTML401, 'UTF-8'),
            ];
            $rows[] = [
                'userid' => $facts['userid'],
                'competencyid' => $facts['competencyid'],
                'status' => $facts['status'],
                'restricted' => $facts['restricted'],
                'evidenceurl' => (new \moodle_url('/local/evidencehub/evidence.php', ['userid' => $facts['userid']]))->out(false),
                'inspect' => $readable[$facts['frameworkcontextid']] ??= self::can_inspect($facts['frameworkcontextid']),
                'selectable' => $candecide,
                'drawertitle' => get_string('drawertitle', 'local_evidencehub', $names),
                'inspectlabel' => get_string('actioninspectlabel', 'local_evidencehub', $names),
                'cells' => $cells,
            ];
        }

        return [
            'scope' => $scope,
            'search' => $search,
            'sort' => $sorted,
            'direction' => $sorted === '' ? '' : ($direction === SORT_ASC ? 'asc' : 'desc'),
            'page' => $page,
            'perpage' => $perpage,
            'total' => $total,
            'hidden' => (int) $known['hidden'],
            'candecide' => $candecide,
            'columns' => self::columns($tablereport),
            'presets' => self::presets($tablereport),
            'rows' => $rows,
            'counted' => $counted,
            'filters' => self::filters($table, $counts),
            'preference' => reportpreference::export(reportpreference::read()),
        ];
    }

    /**
     * Whether the reader may open the inspection drawer of a listed pair.
     *
     * The drawer's fragment checks everything again (lib.php, local_evidencehub_output_fragment_pair()): the
     * hub context and view, competencies enabled, the worker readable and not deleted, the template offered,
     * which the report's own access and base conditions already hold for every listed pair. What is left is
     * core's read check of the competency, competencyview or competencymanage in its framework's context:
     * asked once per framework of the page, from accesslib's cache, never per row. The report's "framework" column
     * asks the same question of every framework of the template ({@see hub_pairs::column_catalogue()}).
     *
     * @param int $frameworkcontextid The context of the pair's competency framework.
     * @return bool
     */
    public static function can_inspect(int $frameworkcontextid): bool {
        // Id 0 is a pair whose competency or framework row is missing: no context, no drawer.
        $context = $frameworkcontextid > 0 ? \context::instance_by_id($frameworkcontextid, IGNORE_MISSING) : false;
        return $context !== false && has_any_capability(
            ['moodle/competency:competencyview', 'moodle/competency:competencymanage'],
            $context
        );
    }

    /**
     * The summary of the reader's pairs: the band's figures.
     *
     * One set of pairs for every figure: the report's rows under every criterion but the scope and the status
     * filter. A worker's readiness is a fact about all their pairs (study, section 2.6), so the workers and
     * ready figures cannot follow a status; the distribution is the status facet's numbers and sums to the
     * pairs; attention, expiring and the competencies with most open pairs are read over the same set, so the
     * band stays still while the reader picks a status or the scope. The search is applied, and a request with
     * one is computed every time; without one the figures come from the reportsummary cache
     * ({@see hub_kpis::report_summary_key()}). The cache holds codes, counts and ids alone: the status labels
     * and the competencies' names are built on every call, in the language and with the names in force.
     *
     * @param hub_pairs $report The report, as {@see self::report()} returned it.
     * @param array $options 'scope' (echoed only), 'search'.
     * @return array See \local_evidencehub\external\report_summary::execute_returns().
     */
    public static function summary(hub_pairs $report, array $options): array {
        $scope = self::scope((string) ($options['scope'] ?? self::SCOPE_ATTENTION));
        $search = self::search((string) ($options['search'] ?? ''));
        $today = validuntil::today();
        $compute = static fn(): array => self::figures($report, $search, $today);
        $values = $report->get_filter_values();
        if ($search === '' && !self::name_in_force($report, $values)) {
            $key = hub_kpis::report_summary_key(
                $report->get_context(),
                $report->template_id(),
                $report->scope(),
                $values,
                $report->includes_suspended(),
                $today,
                self::offered_cohorts($report)
            );
            $figures = hub_kpis::remember($key, $compute);
        } else {
            $figures = $compute();
        }
        return ['scope' => $scope, 'search' => $search] + self::labelled($figures);
    }

    /**
     * The cached figures with their words: each status's label and each listed competency's name, read now.
     *
     * @param array $figures As {@see self::figures()} returns them.
     * @return array The figures with 'distribution' and 'worst' as the service returns them.
     */
    private static function labelled(array $figures): array {
        global $DB;
        $distribution = [];
        foreach ($figures['distribution'] as $code => $count) {
            $distribution[] = ['status' => (int) $code, 'label' => status::effective_label((int) $code), 'count' => (int) $count];
        }
        $figures['distribution'] = $distribution;

        // The cached list holds every competency with an open pair, most open first: one whose row is gone (a damaged
        // database; core refuses to delete a competency a template uses) is skipped before the list is cut to WORST,
        // so the band never lists fewer than the template has. Normally the first read of names is the only one.
        $worst = [];
        foreach (array_chunk($figures['worst'], self::WORST) as $chunk) {
            [$insql, $params] = $DB->get_in_or_equal(array_column($chunk, 'competencyid'), SQL_PARAMS_NAMED);
            $names = $DB->get_records_sql(
                "SELECT c.id, c.shortname, f.contextid
                   FROM {competency} c
                   JOIN {competency_framework} f ON f.id = c.competencyframeworkid
                  WHERE c.id $insql",
                $params
            );
            foreach ($chunk as $entry) {
                $record = $names[$entry['competencyid']] ?? null;
                if ($record === null) {
                    continue;
                }
                $context = \context::instance_by_id((int) $record->contextid, IGNORE_MISSING) ?: \context_system::instance();
                $worst[] = [
                    'competencyid' => $entry['competencyid'],
                    'name' => format_string($record->shortname, true, ['context' => $context, 'escape' => false]),
                    'count' => $entry['count'],
                ];
                if (count($worst) === self::WORST) {
                    break 2;
                }
            }
        }
        $figures['worst'] = $worst;
        return $figures;
    }

    /**
     * The summary's figures, from the database: counts, codes and ids, never a word in a language.
     *
     * @param hub_pairs $report The report.
     * @param string $search A cleaned search.
     * @param int $today Today, YYYYMMDD.
     * @return array workers, ready, rate, pairs, attention, expiring, distribution (status code => pairs), worst (each
     *     competency with an open pair: competencyid and count, the most open first; {@see self::labelled()} cuts it),
     *     hidden.
     */
    private static function figures(hub_pairs $report, string $search, int $today): array {
        global $DB;
        $table = self::table($report, self::SCOPE_ALL, $search);
        /** @var hub_pairs $tablereport */
        $tablereport = $table->get_report();
        [$where, $params] = $table->where_without(self::STATUS_FILTER);
        $params = array_merge($table->sql->params, $params);
        $from = $table->sql->from;
        [$userid, $competencyid] = $tablereport->pair_key_sql();
        $status = $tablereport->get_filter(self::STATUS_FILTER)->get_field_sql();
        $expiry = $tablereport->get_filter(self::VALIDUNTIL_FILTER)->get_field_sql();

        $basesql = "SELECT {$userid} AS userid, {$status} AS estatus FROM {$from} WHERE {$where}";
        [$workers, $ready] = hub_kpis::readiness_of($basesql, $params);

        // The distribution, the expiring pairs and the open pairs per competency share one WHERE: one statement
        // grouped by the three, at most (statuses x template competencies x 2) rows, added up here.
        $window = validuntil::condition('x.expiry', validuntil::within(self::EXPIRING_DAYS), $today);
        $records = $DB->get_records_sql(
            "SELECT ROW_NUMBER() OVER (ORDER BY y.estatus, y.competencyid, y.expiring) AS figurerow,
                    y.estatus, y.competencyid, y.expiring, COUNT(1) AS pairs
               FROM (SELECT x.estatus, x.competencyid, CASE WHEN {$window} THEN 1 ELSE 0 END AS expiring
                       FROM (SELECT {$status} AS estatus, {$competencyid} AS competencyid, {$expiry} AS expiry
                               FROM {$from}
                              WHERE {$where}) x) y
           GROUP BY y.estatus, y.competencyid, y.expiring",
            $params
        );
        $counts = array_fill_keys(status::effective_codes(), 0);
        $expiring = 0;
        $open = [];
        foreach ($records as $record) {
            $code = (int) $record->estatus;
            $pairs = (int) $record->pairs;
            $counts[$code] = ($counts[$code] ?? 0) + $pairs;
            $expiring += (int) $record->expiring === 1 ? $pairs : 0;
            if ($code !== status::STATUS_VALID) {
                $open[(int) $record->competencyid] = ($open[(int) $record->competencyid] ?? 0) + $pairs;
            }
        }
        // The most open pairs first, ties by competency.
        $worst = [];
        foreach ($open as $id => $count) {
            $worst[] = ['competencyid' => $id, 'count' => $count];
        }
        usort(
            $worst,
            static fn(array $a, array $b): int => [$b['count'], $a['competencyid']] <=> [$a['count'], $b['competencyid']]
        );

        $pairs = array_sum($counts);
        return [
            'workers' => $workers,
            'ready' => $ready,
            'rate' => $workers > 0 ? round($ready / $workers, 4) : null,
            'pairs' => $pairs,
            'attention' => $pairs - $counts[status::STATUS_VALID],
            'expiring' => $expiring,
            'distribution' => $counts,
            'worst' => $worst,
            'hidden' => hub_kpis::hidden_workers($tablereport->template_id(), $tablereport->scope()),
        ];
    }

    /**
     * The report's table, with the scope and the search appended to its conditions.
     *
     * Built from the report's persistent and parameters, as core builds it for a page turn; the conditions use
     * that table's report's aliases. The suspended flag is already a base condition of the report.
     *
     * @param hub_pairs $report The report.
     * @param string $scope A cleaned scope.
     * @param string $search A cleaned search, '' for none.
     * @return headless_table The table, not yet set up.
     */
    private static function table(hub_pairs $report, string $scope, string $search): headless_table {
        /** @var headless_table $table */
        $table = headless_table::create((int) $report->get_report_persistent()->get('id'), $report->get_parameters());
        /** @var hub_pairs $tablereport */
        $tablereport = $table->get_report();
        foreach (self::conditions($tablereport, $scope, $search) as [$sql, $params]) {
            $table->narrow($sql, $params);
        }
        return $table;
    }

    /**
     * Read one page of a set-up table whose total it was given ({@see headless_table::know_total()}).
     *
     * @param headless_table $table The table, set up.
     * @param int $page From 0.
     * @param int $perpage Rows per page.
     * @return array The records.
     */
    private static function page(headless_table $table, int $page, int $perpage): array {
        $table->set_page_number($page + 1);
        $table->query_db($perpage, false);
        return $table->rawdata;
    }

    /**
     * The cohort ids the report offers its reader as the cohort filter's options: a stored cohort value applies
     * only while its cohort is among them.
     *
     * @param hub_pairs $report The report.
     * @return array Cohort ids.
     */
    private static function offered_cohorts(hub_pairs $report): array {
        $filter = $report->get_filter(self::COHORT_FILTER);
        return $filter === null ? [] : array_map('intval', array_keys((array) $filter->get_options()));
    }

    /**
     * Whether the reader's stored name filter has a value in force: free text, so its figures are never cached.
     *
     * The value is part of every key, so each one typed would mint an entry, as a search would.
     *
     * @param hub_pairs $report The report.
     * @param array $values The reader's stored filter values.
     * @return bool
     */
    private static function name_in_force(hub_pairs $report, array $values): bool {
        $filter = $report->get_filter(self::SEARCH_FILTER);
        return $filter !== null && self::applies($filter, $values);
    }

    /**
     * The columns the reader sees and those they may choose, in display order: the worker, the identity
     * fields, the competency (all fixed), then the catalogue. Titles in the plain spelling.
     *
     * @param hub_pairs $report The table's report.
     * @return array Each: key, title, sortable, default, active, fixed.
     */
    private static function columns(hub_pairs $report): array {
        $active = $report->get_active_columns();
        $keys = $report->active_keys();
        $catalogue = $report->column_catalogue();
        $columns = [];
        foreach ($keys as $key => $identifier) {
            if (!str_starts_with($key, 'identity:') && !($catalogue[$key]['fixed'] ?? false)) {
                continue;
            }
            $columns[] = [
                'key' => $key,
                'title' => $catalogue[$key]['title'] ?? $active[$identifier]->get_title(),
                'sortable' => $active[$identifier]->get_is_sortable(),
                'default' => true,
                'active' => true,
                'fixed' => true,
            ];
        }
        foreach ($catalogue as $key => $entry) {
            if ($entry['fixed']) {
                continue;
            }
            $columns[] = [
                'key' => $key,
                'title' => $entry['title'],
                'sortable' => isset($keys[$key]) ? $active[$keys[$key]]->get_is_sortable() : $entry['sortable'],
                'default' => $entry['default'],
                'active' => isset($keys[$key]),
                'fixed' => false,
            ];
        }
        return $columns;
    }

    /**
     * The Columns menu's sets, and which one the reader's columns are.
     *
     * @param hub_pairs $report The table's report.
     * @return array Each: name, columns (the keys stored), active.
     */
    private static function presets(hub_pairs $report): array {
        $chosen = array_keys($report->chosen_keys());
        $presets = [];
        foreach (self::PRESETS as $name => $columns) {
            $keys = array_keys($report->keys_for($columns));
            $presets[] = ['name' => $name, 'columns' => $columns, 'active' => $keys !== [] && $keys === $chosen];
        }
        return $presets;
    }

    /**
     * The report's filters as a client draws them: what each is, what it offers, whether it applies, and each
     * option's count.
     *
     * Built from the report's own active filters, so a panel cannot offer a filter the report would not apply.
     * Option labels come back plain: the report's own options are the escaped spelling its form wants. With the
     * counts, an option no row would list is left out unless it is the one chosen.
     *
     * @param headless_table $table The rows' table, its scope and search appended.
     * @param array|null $counts The facets' counts ({@see self::facet_counts()}), null when not counted.
     * @return array Each: name, title, type, facet, options (value, label, count), applied, values (JSON).
     */
    private static function filters(headless_table $table, ?array $counts): array {
        /** @var hub_pairs $report */
        $report = $table->get_report();
        $values = $table->filter_values();
        $filters = [];
        foreach ($report->get_active_filters() as $identifier => $filter) {
            $class = $filter->get_filter_class();
            $type = (new \ReflectionClass($class))->getShortName();
            $offered = self::offered($filter);
            $facet = self::is_facet($filter, $offered) ? 'choice' : '';
            $applied = self::applies($filter, $values);
            $own = array_filter(
                $values,
                static fn(string $name): bool => str_starts_with($name, $identifier . '_'),
                ARRAY_FILTER_USE_KEY
            );
            $chosen = $applied ? (string) ($values[$identifier . '_value'] ?? '') : null;

            $facetcounts = $counts !== null && $facet !== '' ? ($counts[$identifier] ?? []) : null;
            $options = [];
            foreach ($offered as $value => $label) {
                $count = $facetcounts === null ? -1 : (int) ($facetcounts[(string) $value] ?? 0);
                if ($count === 0 && (string) $value !== $chosen) {
                    continue;
                }
                $options[] = ['value' => (string) $value, 'label' => $label, 'count' => $count];
            }
            $filters[] = [
                'name' => $identifier,
                'title' => $filter->get_header(),
                'type' => $type,
                'facet' => $facet,
                'options' => $options,
                'applied' => $applied,
                'values' => json_encode((object) $own),
            ];
        }
        return $filters;
    }

    /**
     * A filter's options in the plain spelling, by value.
     *
     * @param filter $filter The filter.
     * @return array Value (string) => plain label; empty for a filter without options.
     */
    private static function offered(filter $filter): array {
        $offered = [];
        if (is_array($filter->get_options())) {
            foreach ($filter->get_options() as $value => $label) {
                $offered[(string) $value] = html_entity_decode((string) $label, ENT_QUOTES | ENT_HTML401, 'UTF-8');
            }
        }
        return $offered;
    }

    /**
     * Whether a filter is a facet the panel counts: a filter of options that is not free text.
     *
     * @param filter $filter The filter.
     * @param array $offered Its options ({@see self::offered()}).
     * @return bool
     */
    private static function is_facet(filter $filter, array $offered): bool {
        return $offered !== [] && $filter->get_filter_class() !== \core_reportbuilder\local\filters\text::class;
    }

    /**
     * Whether a filter is in force for the reader's stored values: its class builds a condition from them.
     *
     * @param filter $filter The filter.
     * @param array $values The reader's stored filter values.
     * @return bool
     */
    private static function applies(filter $filter, array $values): bool {
        $class = $filter->get_filter_class();
        [$sql] = $class::create($filter)->get_sql_filter($values);
        return $sql !== '';
    }

    /**
     * Every facet's counts, in as few statements as the WHEREs they need.
     *
     * The facets of {@see self::SHARED_FACETS} whose own filter is not in force all count under the rows' own WHERE:
     * one statement reads them together ({@see self::shared_facet_counts()}), and its pairs add up to the rows'
     * total, returned beside the counts. A facet whose own filter is in force counts under the WHERE without it, and
     * the cohort counts memberships: each keeps a statement of its own ({@see self::facet_count()}).
     *
     * @param headless_table $table The rows' table.
     * @return array [facet identifier => (option value => pairs), the rows' total or null when no shared read ran].
     */
    private static function facet_counts(headless_table $table): array {
        /** @var hub_pairs $report */
        $report = $table->get_report();
        $values = $table->filter_values();
        $shared = [];
        $counts = [];
        foreach ($report->get_active_filters() as $identifier => $filter) {
            $offered = self::offered($filter);
            if (!self::is_facet($filter, $offered)) {
                continue;
            }
            if (in_array($identifier, self::SHARED_FACETS, true) && !self::applies($filter, $values)) {
                $shared[$identifier] = $filter;
                continue;
            }
            $counts[$identifier] = self::facet_count($table, $identifier, $filter, array_keys($offered));
        }
        if ($shared === []) {
            return [$counts, null];
        }
        [$sharedcounts, $total] = self::shared_facet_counts($table, $shared);
        return [$sharedcounts + $counts, $total];
    }

    /**
     * The facets whose own filters are not in force, counted in one statement over the rows' own WHERE.
     *
     * The pairs are grouped by every facet's field at once and each facet's counts are the sums of the groups by its
     * own field: the same numbers as one statement per facet, for one read of the pairs. Each field is the filter's
     * own, masked for a restricted pair as the filter compares it. The valid-until facet is grouped by the first of its
     * choices a pair's day matches, in {@see validuntil::choices()} order: "expired" or the smallest window, since the
     * expired days come before today and every window starts today and contains the narrower ones; a window's count
     * then adds up its band and every narrower one, and a pair matching none (no day) adds to none.
     *
     * The groups are at most the product of each facet's distinct values plus one (status codes, the template's
     * competencies, the types, the verification states, the valid-until bands), and never more than the pairs. In
     * practice the facets go together (a competency takes a few types, a status its own band of days), so a template
     * of 30 competencies yields hundreds of groups, not the tens of thousands the product allows.
     *
     * @param headless_table $table The rows' table.
     * @param array $filters Facet identifier => filter, each one of SHARED_FACETS and not in force.
     * @return array [facet identifier => (option value => pairs), the rows' total].
     */
    private static function shared_facet_counts(headless_table $table, array $filters): array {
        global $DB;
        [$where, $params] = $table->where_without('');
        $params = array_merge($table->sql->params, $params);
        $from = $table->from_with(...array_values($filters));
        $today = validuntil::today();
        $choices = array_keys(validuntil::choices());

        $inner = [];
        $middle = [];
        $names = [];
        foreach (array_keys($filters) as $index => $identifier) {
            $name = 'facet' . $index;
            $names[$identifier] = $name;
            $field = $filters[$identifier]->get_field_sql();
            $params += $filters[$identifier]->get_field_params();
            $inner[] = "{$field} AS {$name}";
            if ($identifier !== self::VALIDUNTIL_FILTER) {
                $middle[] = "x.{$name}";
                continue;
            }
            $whens = [];
            foreach ($choices as $position => $choice) {
                $whens[] = 'WHEN ' . validuntil::condition("x.{$name}", $choice, $today) . ' THEN ' . ($position + 1);
            }
            $middle[] = 'CASE ' . implode(' ', $whens) . " ELSE 0 END AS {$name}";
        }
        $grouped = implode(', ', array_map(static fn(string $name): string => "y.{$name}", $names));
        // The row number only gives each group a unique first column, as get_records_sql() keys its rows by it.
        $records = $DB->get_records_sql(
            "SELECT ROW_NUMBER() OVER (ORDER BY {$grouped}) AS facetrow, {$grouped}, COUNT(1) AS pairs
               FROM (SELECT " . implode(', ', $middle) . "
                       FROM (SELECT " . implode(', ', $inner) . " FROM {$from} WHERE {$where}) x) y
           GROUP BY {$grouped}",
            $params
        );

        $counts = array_fill_keys(array_keys($filters), []);
        $bands = [];
        $total = 0;
        foreach ($records as $record) {
            $pairs = (int) $record->pairs;
            $total += $pairs;
            foreach ($names as $identifier => $name) {
                $value = $record->{$name};
                if ($identifier === self::VALIDUNTIL_FILTER) {
                    $bands[(int) $value] = ($bands[(int) $value] ?? 0) + $pairs;
                } else if ($value !== null) {
                    $counts[$identifier][(string) $value] = ($counts[$identifier][(string) $value] ?? 0) + $pairs;
                }
            }
        }
        if (isset($names[self::VALIDUNTIL_FILTER])) {
            $running = 0;
            foreach ($choices as $position => $choice) {
                $band = $bands[$position + 1] ?? 0;
                if ($choice === validuntil::EXPIRED) {
                    $counts[self::VALIDUNTIL_FILTER][$choice] = $band;
                    continue;
                }
                $running += $band;
                $counts[self::VALIDUNTIL_FILTER][$choice] = $running;
            }
        }
        return [$counts, $total];
    }

    /**
     * How many pairs each option of one facet would list, under every other criterion in force: a facet whose own
     * filter is in force, or the cohort.
     *
     * One grouped statement over the table's FROM and its WHERE rebuilt without this facet's own condition
     * ({@see headless_table::where_without()}): the base conditions (the reader's cut, the restricted rule),
     * the scope, the search and every other stored filter hold. Each count is what the rows' total would be
     * with that option chosen in place of the facet's own value. The field is the filter's own, masked for a
     * restricted pair as the filter compares it:
     *
     * - a choice of a select: the pairs by the filter's field, which the select compares with "=";
     * - the cohort: the pairs of each offered cohort's members, joined to the memberships of those cohorts only;
     * - valid until: the four windows of {@see validuntil::condition()} in one statement of conditional sums.
     *
     * @param headless_table $table The rows' table.
     * @param string $identifier The facet's filter.
     * @param filter $filter The filter.
     * @param array $offered The option values offered.
     * @return array Option value => pairs.
     */
    private static function facet_count(headless_table $table, string $identifier, filter $filter, array $offered): array {
        global $DB;
        [$where, $params] = $table->where_without($identifier);
        $params = array_merge($table->sql->params, $params);
        $from = $table->from_with($filter);
        $field = $filter->get_field_sql();
        $params += $filter->get_field_params();

        if ($identifier === self::VALIDUNTIL_FILTER) {
            $today = validuntil::today();
            $sums = [];
            foreach (array_keys(validuntil::choices()) as $index => $choice) {
                $sums[] = "SUM(CASE WHEN " . validuntil::condition($field, $choice, $today) . " THEN 1 ELSE 0 END) AS w{$index}";
            }
            $record = $DB->get_record_sql("SELECT " . implode(', ', $sums) . " FROM {$from} WHERE {$where}", $params);
            $counts = [];
            foreach (array_keys(validuntil::choices()) as $index => $choice) {
                $counts[$choice] = (int) ($record->{"w{$index}"} ?? 0);
            }
            return $counts;
        }

        if ($identifier === self::COHORT_FILTER) {
            $cohortids = array_values(array_filter(array_map('intval', $offered)));
            if ($cohortids === []) {
                return [];
            }
            $members = database::generate_alias();
            [$insql, $inparams] = $DB->get_in_or_equal($cohortids, SQL_PARAMS_NAMED, database::generate_param_name() . '_');
            $sql = "SELECT {$members}.cohortid AS facetvalue, COUNT(1) AS pairs
                      FROM {$from}
                      JOIN {cohort_members} {$members} ON {$members}.userid = {$field} AND {$members}.cohortid {$insql}
                     WHERE {$where}
                  GROUP BY {$members}.cohortid";
            return array_map('intval', $DB->get_records_sql_menu($sql, $params + $inparams));
        }

        $sql = "SELECT x.facetvalue, COUNT(1) AS pairs
                  FROM (SELECT {$field} AS facetvalue FROM {$from} WHERE {$where}) x
                 WHERE x.facetvalue IS NOT NULL
              GROUP BY x.facetvalue";
        return array_map('intval', $DB->get_records_sql_menu($sql, $params));
    }
}
