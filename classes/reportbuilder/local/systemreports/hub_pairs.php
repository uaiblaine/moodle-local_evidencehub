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

namespace local_evidencehub\reportbuilder\local\systemreports;

use core\lang_string;
use core_competency\api;
use core_competency\plan;
use core_competency\reportbuilder\local\entities\competency;
use core_reportbuilder\local\entities\user;
use core_reportbuilder\local\helpers\database;
use core_reportbuilder\local\report\column;
use core_reportbuilder\local\report\filter;
use core_reportbuilder\system_report;
use core_reportbuilder\system_report_factory;
use core_user\fields;
use local_evidencehub\local\details;
use local_evidencehub\local\hub_access;
use local_evidencehub\local\hub_query;
use local_evidencehub\local\hub_scope;
use local_evidencehub\local\pairset;
use local_evidencehub\local\reportengine;
use local_evidencehub\local\reportpreference;
use local_evidencehub\local\status_repository;
use local_evidencehub\reportbuilder\local\entities\evidence_details;
use local_evidencehub\reportbuilder\local\entities\validity;
use local_evidencehub\reportbuilder\local\filters\cohort;
use local_evidencehub\reportbuilder\local\filters\reader_select;

/**
 * The hub's pairs of one plan template in one context, as core's system report.
 *
 * The rows are the hub's pair set ({@see pairset}) for the reader: one row per (worker, competency) of an
 * active plan from the template, the workers the reader may view, suspended accounts left out, a missing
 * pair listed as "missing" and a pair decided by a sensitive evidence the reader may not see as
 * "restricted". Every value a restricted pair hides is masked in SQL, so no column, filter or sort
 * reveals it ({@see validity}, {@see evidence_details}).
 *
 * The report belongs to the hub's context (the system or a course category) and is identified by the
 * template, its item id: core keeps one set of stored filters per reader, context and template. The rows
 * are cut by the context's own rule and the template, both checked by {@see self::may_view()} on every
 * request, never by a parameter a client sends.
 *
 * Three request facts can only narrow the rows or pick from a fixed vocabulary, through the one definition
 * the headless rows use ({@see reportengine::conditions()}): the scope, the search by the worker's name and
 * whether suspended accounts are listed. The columns are the reader's choice from {@see self::column_catalogue()},
 * read from their preference ({@see reportpreference}) on every build, so the page, the web services and a
 * download agree on them.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hub_pairs extends system_report {
    /** @var string The value of index.php's view parameter that renders this report. */
    public const string VIEW = 'table';

    /** @var string The report area, beside the component, in core's report persistent. */
    public const string AREA = 'hub';

    /** @var array|null The column catalogue, built once per instance. */
    private ?array $catalogue = null;

    /** @var array Catalogue or fixed key => unique identifier of the columns shown, in display order. */
    private array $activekeys = [];

    /** @var array The aliases of the pair set's tables in this instance's SQL, by table role. */
    private array $aliases = [];

    /** @var hub_scope|null The reader's capability cuts, worked out once per build. */
    private ?hub_scope $scope = null;

    /** @var bool Whether suspended accounts are listed. */
    private bool $suspended = false;

    /** @var array|null The reader's settings, read once per build ({@see reportpreference::read()}). */
    private ?array $preference = null;

    /** @var bool Whether the reader may see full names in the report's context. */
    private bool $viewfullnames = false;

    /**
     * The report of a template's pairs in a hub context, for the current user.
     *
     * @param \context $context The hub's context: the system or a course category.
     * @param int $templateid The plan template, the report's item.
     * @param array $parameters Report parameters; a non-empty 'download' makes the report refuse.
     * @return self
     * @throws \core_reportbuilder\exception\report_access_exception When {@see self::may_view()} refuses.
     */
    public static function for_template(\context $context, int $templateid, array $parameters = []): self {
        return system_report_factory::create(self::class, $context, 'local_evidencehub', self::AREA, $templateid, $parameters);
    }

    /**
     * The report's name.
     *
     * @return string
     */
    #[\Override]
    public static function get_name(): string {
        return get_string('report:hub_pairs', 'local_evidencehub');
    }

    /**
     * Whether the current user may view this report: {@see self::may_view()} on its context and template.
     *
     * Every sort, filter and page turn rebuilds the report from its persistent, so this runs on each of them.
     *
     * @return bool
     */
    #[\Override]
    protected function can_view(): bool {
        // Core's dynamic table builds the report with the caller's parameters and downloads when one of them
        // is 'download', a path that never asks can_be_downloaded(); the hub's own download fires an audit event.
        if ($this->get_parameter('download', '', PARAM_RAW) !== '') {
            return false;
        }
        return self::may_view($this->get_context(), $this->templateid());
    }

    /**
     * Whether the current user may view the pairs of a template in a context, without building the report.
     *
     * The hub's own rules, as its page applies them: a signed-in user who is not a guest (core reaches the
     * report without the page), a hub context with local/evidencehub:view there, competencies enabled, and
     * the template offered in that context ({@see hub_access}).
     *
     * @param \context $context The hub's context.
     * @param int $templateid The plan template.
     * @return bool
     */
    public static function may_view(\context $context, int $templateid): bool {
        return isloggedin()
            && !isguestuser()
            && hub_access::can_view($context)
            && api::is_enabled()
            && hub_access::offered_template($templateid, $context) !== null;
    }

    /**
     * The template the report lists, its item id.
     *
     * @return int
     */
    private function templateid(): int {
        return (int) $this->get_report_persistent()->get('itemid');
    }

    /**
     * The template the report lists, for a reader of the report beside its rows.
     *
     * @return int
     */
    public function template_id(): int {
        return $this->templateid();
    }

    /**
     * The reader's capability cuts this build used, so a reader of the report needs not work them out again.
     *
     * @return hub_scope
     */
    public function scope(): hub_scope {
        return $this->scope;
    }

    /**
     * Whether this build lists suspended accounts: the 'suspended' parameter when given, else the reader's
     * preference (decision 5), so a download follows what the reader chose.
     *
     * @return bool
     */
    public function includes_suspended(): bool {
        return $this->suspended;
    }

    /**
     * The alias of the worker's user table in this instance's SQL; each instance generates its own.
     *
     * @return string
     */
    public function user_alias(): string {
        return $this->aliases['user'];
    }

    /**
     * The SQL of the pair's two-part key in this instance: the plan's worker and the template's competency.
     *
     * @return array [userid SQL, competencyid SQL].
     */
    public function pair_key_sql(): array {
        return ["{$this->aliases['plan']}.userid", "{$this->aliases['templatecomp']}.competencyid"];
    }

    /**
     * Build the report: the pair set, its entities, columns, filters and sort.
     *
     * Runs before {@see self::can_view()}, so nothing here may assume the context or the template is valid:
     * the rows are cut by them, and the report is refused before any row is read.
     *
     * @return void
     */
    #[\Override]
    protected function initialise(): void {
        $scope = hub_scope::for_user();
        $this->scope = $scope;
        $this->viewfullnames = has_capability('moodle/site:viewfullnames', $this->get_context());
        $names = pairset::report_names();
        $p = database::generate_alias();
        $tc = database::generate_alias();
        $s = database::generate_alias();
        $m = database::generate_alias();
        $t = database::generate_alias();
        $r = database::generate_alias();
        $f = database::generate_alias();
        $this->set_main_table(plan::TABLE, $p);

        $userentity = new user();
        $u = $userentity->get_table_alias('user');
        foreach (pairset::joins($p, $u, $tc, $s) as $join) {
            $this->add_join($join);
        }
        [$where, $params] = pairset::where($p, $this->templateid(), $scope, $names);
        $this->add_base_condition_sql($where, $params);

        // The deciding evidence and its type, for the details entity.
        $this->add_join("LEFT JOIN {" . details::TABLE . "} {$m} ON {$m}.id = {$s}.metaid");
        $this->add_join("LEFT JOIN {local_evidencehub_type} {$t} ON {$t}.id = {$m}.typeid");
        if ($scope->sees_every_sensitive()) {
            // Nothing is restricted to this reader: the rule is false for every row, and the status table is read once.
            $restricted = '1 = 0';
        } else {
            // The status row again, joined only when it is restricted to the reader.
            [$restrictedjoin, $restrictedparams] = pairset::restricted_join($r, $s, "{$p}.userid", $scope, $names);
            $this->add_join($restrictedjoin, $restrictedparams);
            $restricted = "{$r}.id IS NOT NULL";
        }

        // LEFT joins on primary keys, so a statement that reads neither table (the page's keys, the total, the facets)
        // lets the planner drop them on every database. They list the same pairs as inner joins: core refuses to
        // delete a competency or a framework that a template uses (competency::can_all_be_deleted()), so a template's
        // competency always has its row and its framework.
        $competencyentity = new competency();
        $c = $competencyentity->get_table_alias('competency');
        $this->add_join("LEFT JOIN {competency} {$c} ON {$c}.id = {$tc}.competencyid");
        $this->add_join("LEFT JOIN {competency_framework} {$f} ON {$f}.id = {$c}.competencyframeworkid");
        $this->aliases = ['plan' => $p, 'user' => $u, 'templatecomp' => $tc, 'competency' => $c, 'framework' => $f];

        $this->add_entity($userentity);
        $this->add_entity($competencyentity);
        $validity = (new validity($restricted, true))->set_table_alias(status_repository::TABLE, $s);
        $this->add_entity($validity);
        $this->add_entity((new evidence_details($restricted, true))
            ->set_table_alias(details::TABLE, $m)
            ->set_table_alias('local_evidencehub_type', $t));

        // The pair's key, for a reader of the rows that needs it.
        $this->add_base_fields("{$p}.userid, {$tc}.competencyid");
        // What a reader of the rows needs whatever columns are chosen ({@see self::pair_facts()}).
        $namefields = implode(', ', array_map(
            static fn(string $field): string => "{$u}.{$field} AS pairname_{$field}",
            fields::get_name_fields()
        ));
        $this->add_base_fields($validity->effective_status_sql() . " AS pairstatus, "
            . "CASE WHEN {$restricted} THEN 1 ELSE 0 END AS pairrestricted, "
            . "{$c}.shortname AS paircompetencyname, {$f}.contextid AS pairframeworkcontextid, {$namefields}");

        $this->add_report_columns($userentity, $p, $tc, $c);
        $this->add_report_filters($p);

        // The request facts, where the report's parameters carry them, through the one definition the headless
        // rows use: each is ANDed with the base conditions above, so a parameter can only narrow what this reader
        // may read. No scope parameter lists every pair, as core's own table does; the headless rows add theirs
        // to the table instead ({@see reportengine::rows()}).
        $this->suspended = $this->read_suspended();
        $requested = (string) $this->get_parameter('scope', '', PARAM_ALPHA);
        $search = reportengine::search((string) $this->get_parameter('search', '', PARAM_TEXT));
        $conditions = reportengine::conditions(
            $this,
            $requested === '' ? reportengine::SCOPE_ALL : reportengine::scope($requested),
            $search,
            $this->suspended
        );
        foreach ($conditions as [$sql, $conditionparams]) {
            $this->add_base_condition_sql("({$sql})", $conditionparams);
        }

        // Core cannot sort by a column the report does not carry: a reader who hid "Valid until" reads by name.
        if (isset($this->activekeys['expiry'])) {
            $this->set_initial_sort_column('validity:effectiveexpiry', SORT_ASC);
        } else {
            $this->set_initial_sort_column('user:worker', SORT_ASC);
        }
        $this->set_default_per_page(reportengine::DEFAULT_PERPAGE);
        // Core's download would lose the hub's download event: the hub's own download stays.
        $this->set_downloadable(false);
    }

    /**
     * Whether suspended accounts are listed: the 'suspended' parameter when it is given (0 or 1), else the
     * reader's preference.
     *
     * @return bool
     */
    private function read_suspended(): bool {
        $parameter = $this->get_parameter('suspended', null, PARAM_INT);
        return $parameter === null ? $this->preference()['suspended'] : (int) $parameter === 1;
    }

    /**
     * The reader's settings of this report, read once per build: a preference written later in the same request
     * reaches the next build, as core builds a report once per request.
     *
     * @return array As {@see reportpreference::read()} returns them.
     */
    private function preference(): array {
        $this->preference ??= reportpreference::read();
        return $this->preference;
    }

    /**
     * The columns a reader may choose, and the two that are always shown, in display order.
     *
     * The identity fields are not in it: they follow the worker whenever the reader may see them
     * ({@see hub_query::identity_fields_in()}). Every column here is one this reader may see, its values
     * masked in SQL for a restricted pair, so a key the preference names that is not here, forged or stale,
     * adds nothing. The "framework" column is here only for a reader who may read every framework of the template.
     *
     * @return array Key => identifier (the column's unique identifier), title (plain), sortable, default, fixed.
     */
    public function column_catalogue(): array {
        if ($this->catalogue !== null) {
            return $this->catalogue;
        }
        $entries = [
            'worker' => ['user:worker', 'worker', true, true],
            'competency' => ['competency:name', 'competency', true, true],
            'status' => ['validity:status', 'status', true, false],
            'expiry' => ['validity:effectiveexpiry', 'effectiveexpiry', true, false],
            'count' => ['validity:evidencecount', 'evidencecount', true, false],
            'type' => ['evidence_details:type', 'type', true, false],
            'issuer' => ['evidence_details:issuer', 'issuer', true, false],
            'competencyidnumber' => ['competency:idnumber', 'competencyidnumber', false, false],
            'framework' => ['competency:framework', 'framework', false, false],
            'issuedate' => ['evidence_details:issuedate', 'issuedate', false, false],
            'expirydate' => ['evidence_details:expirydate', 'expirydate', false, false],
            'verification' => ['evidence_details:verifystatus', 'verification', false, false],
            'changed' => ['validity:timechanged', 'timechanged', false, false],
        ];
        $catalogue = [];
        $frameworks = $this->may_read_every_framework();
        foreach ($entries as $key => [$identifier, $string, $default, $fixed]) {
            if ($key === 'framework' && !$frameworks) {
                continue;
            }
            $catalogue[$key] = [
                'identifier' => $identifier,
                'title' => get_string($string, 'local_evidencehub'),
                'sortable' => true,
                'default' => $default,
                'fixed' => $fixed,
            ];
        }
        $this->catalogue = $catalogue;
        return $this->catalogue;
    }

    /**
     * Whether the reader may read every framework the template's competencies belong to: the rule that offers the
     * drawer for a row ({@see reportengine::can_inspect()}), asked of each distinct framework context, so the
     * "framework" column never shows a framework's name to a reader who could not open its competencies.
     *
     * @return bool
     */
    private function may_read_every_framework(): bool {
        global $DB;
        $contextids = $DB->get_fieldset_sql(
            "SELECT DISTINCT f.contextid
               FROM {competency_templatecomp} tc
               JOIN {competency} c ON c.id = tc.competencyid
               JOIN {competency_framework} f ON f.id = c.competencyframeworkid
              WHERE tc.templateid = :templateid",
            ['templateid' => $this->templateid()]
        );
        foreach ($contextids as $contextid) {
            if (!reportengine::can_inspect((int) $contextid)) {
                return false;
            }
        }
        return true;
    }

    /**
     * The catalogue keys this reader's report shows besides the fixed ones: their preference intersected with
     * the catalogue, or the default set when they have chosen nothing.
     *
     * @return array Key => true, in catalogue order.
     */
    public function chosen_keys(): array {
        $preference = $this->preference()['columns'];
        if ($preference === null) {
            $chosen = [];
            foreach ($this->column_catalogue() as $key => $entry) {
                if ($entry['default'] && !$entry['fixed']) {
                    $chosen[$key] = true;
                }
            }
            return $chosen;
        }
        return $this->keys_for($preference);
    }

    /**
     * The catalogue keys a stored choice of columns shows: the choice intersected with the catalogue's
     * columns that are not fixed. What a reader's preference and a Columns menu set mean here
     * ({@see reportengine::PRESETS}).
     *
     * @param array $columns The keys chosen, as the preference stores them.
     * @return array Key => true, in catalogue order.
     */
    public function keys_for(array $columns): array {
        $named = array_flip(array_filter($columns, 'is_string'));
        $chosen = [];
        foreach ($this->column_catalogue() as $key => $entry) {
            if (!$entry['fixed'] && isset($named[$key])) {
                $chosen[$key] = true;
            }
        }
        return $chosen;
    }

    /**
     * The columns this report shows, by key: "worker", "identity:<field>", "competency", then the chosen
     * catalogue keys. The unique identifiers are the same on every instance, so a caller holding another
     * instance finds the columns by them.
     *
     * @return array Key => column unique identifier, in display order.
     */
    public function active_keys(): array {
        return $this->activekeys;
    }

    /**
     * The facts of a row a reader needs beside its cells, whatever columns are chosen.
     *
     * Read from the base fields, so they hold the masked status and the restricted flag of the pair as the
     * columns do; the names are plain, for text the client escapes itself.
     *
     * @param \stdClass $record A row of the report's table.
     * @return array userid, competencyid, status (effective code), restricted, frameworkcontextid (0 when the pair's
     *     competency or framework row is missing), worker and competency (plain names; '' when a row is missing).
     */
    public function pair_facts(\stdClass $record): array {
        $names = new \stdClass();
        foreach (fields::get_name_fields() as $field) {
            $names->{$field} = (string) ($record->{'pairname_' . $field} ?? '');
        }
        // A pair whose competency or framework row is gone (a damaged database: the joins are LEFT) keeps its row, with
        // no name and no drawer; a name is formatted in the system context when its framework's context is gone too.
        $named = $record->paircompetencyname !== null && $record->pairframeworkcontextid !== null;
        $frameworkcontextid = $named ? (int) $record->pairframeworkcontextid : 0;
        $context = $named ? \context::instance_by_id($frameworkcontextid, IGNORE_MISSING) : false;
        $competency = '';
        if ($named) {
            $competency = format_string(
                (string) $record->paircompetencyname,
                true,
                ['context' => $context ?: \context_system::instance(), 'escape' => false]
            );
        }
        return [
            'userid' => (int) $record->userid,
            'competencyid' => (int) $record->competencyid,
            'status' => (int) $record->pairstatus,
            'restricted' => (int) $record->pairrestricted === 1,
            'frameworkcontextid' => $frameworkcontextid,
            'worker' => fullname($names, $this->viewfullnames),
            'competency' => $competency,
        ];
    }

    /**
     * Add the columns: the worker, the identity fields the reader may see, the competency, then the catalogue's
     * columns the reader chose ({@see self::chosen_keys()}), recording each one's key.
     *
     * @param user $userentity The worker's entity.
     * @param string $p The plan's alias.
     * @param string $tc The template competency's alias.
     * @param string $c The competency's alias.
     * @return void
     */
    private function add_report_columns(user $userentity, string $p, string $tc, string $c): void {
        $this->activekeys = ['worker' => $this->add_column($this->worker_column($userentity))->get_unique_identifier()];
        // The list is empty outside moodle/site:viewuseridentity: absence, not a blanked cell.
        // Only the user table's fields, as the hub's list shows them: no custom profile field column.
        foreach (hub_query::identity_fields_in($this->get_context()) as $field) {
            $this->add_column($userentity->get_identity_column($field));
        }
        foreach (hub_query::identity_fields_in($this->get_context()) as $field) {
            $this->activekeys['identity:' . $field] = $userentity->get_entity_name() . ':' . $field;
        }
        $this->activekeys['competency'] = $this->add_column($this->competency_column($c))->get_unique_identifier();

        // The reader's choice, in catalogue order: a key the catalogue lacks never reaches this loop.
        $catalogue = $this->column_catalogue();
        foreach (array_keys($this->chosen_keys()) as $key) {
            $column = match ($key) {
                // The hub's default order: restricted and missing first (their key is 0), then the earliest day,
                // then the pair's key, so tied rows keep one order across pages.
                'expiry' => $this->add_column_from_entity('validity:effectiveexpiry')
                    ->add_field("{$p}.userid", 'pairuserid')
                    ->add_field("{$tc}.competencyid", 'paircompetencyid')
                    ->set_is_sortable(true, ['effectiveexpiry', 'pairuserid', 'paircompetencyid']),
                'competencyidnumber' => $this->add_column($this->competency_idnumber_column($c)),
                'framework' => $this->add_column($this->framework_column()),
                default => $this->add_column_from_entity($catalogue[$key]['identifier']),
            };
            $this->activekeys[$key] = $column->get_unique_identifier();
        }

        // Each cell carries its column's heading, for a layout that shows the rows as cards.
        foreach ($this->get_active_columns() as $column) {
            $column->add_attributes(['data-cardtitle' => $column->get_title()]);
        }
    }

    /**
     * The worker's full name, linked to their evidence page.
     *
     * Full names follow moodle/site:viewfullnames in the report's context, as the hub's download does.
     *
     * @param user $userentity The worker's entity.
     * @return column
     */
    private function worker_column(user $userentity): column {
        $u = $userentity->get_table_alias('user');
        $namefields = user::get_name_fields_select($u);
        $viewfullnames = $this->viewfullnames;
        return (new column('worker', new lang_string('worker', 'local_evidencehub'), $userentity->get_entity_name()))
            ->add_fields("{$u}.id, {$namefields}")
            ->set_is_sortable(true, explode(', ', $namefields))
            ->add_callback(static function ($value, \stdClass $row) use ($viewfullnames): string {
                global $OUTPUT;
                if ($value === null) {
                    return '';
                }
                foreach (fields::get_name_fields() as $field) {
                    $row->{$field} ??= '';
                }
                $url = new \moodle_url('/local/evidencehub/evidence.php', ['userid' => (int) $value]);
                return $OUTPUT->action_link($url, s(fullname($row, $viewfullnames)));
            });
    }

    /**
     * The competency's short name, formatted in its framework's context as the hub formats it.
     *
     * @param string $c The competency's alias.
     * @return column
     */
    private function competency_column(string $c): column {
        $f = $this->aliases['framework'];
        return (new column('name', new lang_string('competency', 'local_evidencehub'), 'competency'))
            ->add_fields("{$c}.shortname, {$f}.contextid")
            ->set_is_sortable(true, ["{$c}.shortname"])
            ->add_callback(static function ($value, \stdClass $row): string {
                if ($value === null || $row->contextid === null) {
                    return '';
                }
                return format_string($value, true, ['context' => \context::instance_by_id((int) $row->contextid)]);
            });
    }

    /**
     * The competency's ID number, as plain text.
     *
     * @param string $c The competency's alias.
     * @return column
     */
    private function competency_idnumber_column(string $c): column {
        return (new column('idnumber', new lang_string('competencyidnumber', 'local_evidencehub'), 'competency'))
            ->add_field("COALESCE({$c}.idnumber, '')", 'idnumber')
            ->set_is_sortable(true)
            ->add_callback(static fn($value): string => s((string) $value));
    }

    /**
     * The competency's framework by short name, formatted in its own context.
     *
     * @return column
     */
    private function framework_column(): column {
        $f = $this->aliases['framework'];
        return (new column('framework', new lang_string('framework', 'local_evidencehub'), 'competency'))
            ->add_fields("{$f}.shortname, {$f}.contextid")
            ->set_is_sortable(true, ["{$f}.shortname"])
            ->add_callback(static function ($value, \stdClass $row): string {
                if ($value === null || $row->contextid === null) {
                    return '';
                }
                return format_string($value, true, ['context' => \context::instance_by_id((int) $row->contextid)]);
            });
    }

    /**
     * Add the filters: the status, the cohort, the competency, the type of evidence, its verification, the
     * valid-until window and the worker's name.
     *
     * @param string $p The plan's alias.
     * @return void
     */
    private function add_report_filters(string $p): void {
        $this->add_filter_from_entity('validity:status');
        $this->add_filter((new filter(
            cohort::class,
            'cohort',
            new lang_string('cohort', 'core_cohort'),
            'user',
            "{$p}.userid"
        ))->set_options($this->cohort_options()));
        $templateid = $this->templateid();
        $this->add_filter((new filter(
            reader_select::class,
            'competency',
            new lang_string('competency', 'local_evidencehub'),
            'competency',
            "{$this->aliases['templatecomp']}.competencyid"
        ))->set_options_callback(static fn(): array => self::competency_options($templateid)));
        $this->add_filter_from_entity('evidence_details:type');
        $this->add_filter_from_entity('evidence_details:verifystatus');
        $this->add_filter_from_entity('validity:validuntil');
        $this->add_filter_from_entity('user:fullname');
    }

    /**
     * The template's competencies by short name, each formatted in its framework's context, as the filter's options.
     *
     * Read when the options are first asked for, so a build that never reads them sends no statement.
     *
     * @param int $templateid The template.
     * @return array Competency id => name, escaped for the filter form.
     */
    private static function competency_options(int $templateid): array {
        global $DB;
        $records = $DB->get_records_sql(
            "SELECT c.id, c.shortname, f.contextid
               FROM {competency_templatecomp} tc
               JOIN {competency} c ON c.id = tc.competencyid
               JOIN {competency_framework} f ON f.id = c.competencyframeworkid
              WHERE tc.templateid = :templateid
           ORDER BY c.shortname, c.id",
            ['templateid' => $templateid]
        );
        $options = [];
        foreach ($records as $record) {
            $context = \context::instance_by_id((int) $record->contextid);
            $options[(int) $record->id] = format_string($record->shortname, true, ['context' => $context]);
        }
        return $options;
    }

    /**
     * The cohorts the hub offers this reader in the report's context, as the filter's options.
     *
     * Read for the context in hand even before {@see self::can_view()} refuses it: the list is the
     * hub's own rule ({@see hub_access::cohorts()}), and a refused report reads no row.
     *
     * @return array Cohort id => name, escaped for the filter form.
     */
    private function cohort_options(): array {
        $options = [];
        foreach (hub_access::cohorts($this->get_context()) as $cohort) {
            $options[$cohort->id] = format_string($cohort->name, true, ['context' => $this->get_context()]);
        }
        return $options;
    }
}
