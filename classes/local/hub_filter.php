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

/**
 * The hub's filter: a validated plan template, cohort, effective statuses, search, sort and page.
 *
 * Built from request parameters or from the stored preference, both through {@see self::from_values()},
 * which validates every field: the template and the cohort against what the page context offers
 * ({@see hub_access}), everything else against an allowlist. The preference holds the validated
 * filter, never raw input.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class hub_filter {
    /** @var string Most urgent first: restricted and missing, then by effective expiry. */
    public const string SORT_EXPIRY = 'expiry';

    /** @var string By the worker's last name, then first name. */
    public const string SORT_WORKER = 'worker';

    /** @var string By the competency's short name. */
    public const string SORT_COMPETENCY = 'competency';

    /** @var string By effective status code. */
    public const string SORT_STATUS = 'status';

    /** @var array The sorts the hub accepts. */
    public const array SORTS = [self::SORT_EXPIRY, self::SORT_WORKER, self::SORT_COMPETENCY, self::SORT_STATUS];

    /** @var array The page sizes the hub accepts. */
    public const array PERPAGES = [25, 50, 100];

    /** @var int The page size of a filter that names none. */
    public const int DEFAULT_PERPAGE = 50;

    /** @var int The longest search the hub keeps. */
    public const int SEARCH_MAXLENGTH = 100;

    /** @var string The user preference holding the last filter used. */
    public const string PREFERENCE = 'local_evidencehub_hubfilter';

    /** @var array The view values a copy may change: {@see self::with()}. */
    private const array VIEW = ['statuses', 'sort', 'dir', 'page', 'perpage'];

    /** @var array The request parameters of a filter; any of them present means the request names one. */
    private const array PARAMS = [
        'templateid', 'cohortid', 'statusfilter', 'search', 'suspended', 'sort', 'dir', 'page', 'perpage',
    ];

    /**
     * A filter; only {@see self::from_values()} builds one, after validating the template and the cohort.
     *
     * @param int $templateid The plan template.
     * @param int $cohortid The cohort the workers belong to, 0 for none.
     * @param string $search Part of a worker's first name, last name or shown ID number; '' for none.
     * @param bool $suspended Whether suspended workers are included.
     * @param array $statuses Effective status codes; empty for every status.
     * @param string $sort A SORT_* constant.
     * @param string $dir 'asc' or 'desc'.
     * @param int $page The page, from 0.
     * @param int $perpage Rows per page, one of PERPAGES.
     */
    private function __construct(
        /** @var int The plan template. */
        public readonly int $templateid,
        /** @var int The cohort, 0 for none. */
        public readonly int $cohortid,
        /** @var string The search. */
        public readonly string $search,
        /** @var bool Whether suspended workers are included. */
        public readonly bool $suspended,
        /** @var array Effective status codes, empty for all. */
        public readonly array $statuses,
        /** @var string The sort. */
        public readonly string $sort,
        /** @var string The direction. */
        public readonly string $dir,
        /** @var int The page. */
        public readonly int $page,
        /** @var int Rows per page. */
        public readonly int $perpage,
    ) {
    }

    /**
     * The default view, "needs attention": every effective status except compliant.
     *
     * @return array Effective status codes.
     */
    public static function needs_attention(): array {
        return array_values(array_diff(status::effective_codes(), [status::STATUS_VALID]));
    }

    /**
     * A validated filter from raw values.
     *
     * @param array $values templateid (required), and optionally cohortid, statuses (absent: needs
     *     attention; empty: every status), search, suspended, sort, dir, page, perpage.
     * @param \context $context The page context, which decides the templates and cohorts on offer.
     * @return self
     * @throws \moodle_exception When the template or the cohort is not offered in the context.
     */
    public static function from_values(array $values, \context $context): self {
        $templateid = (int) clean_param($values['templateid'] ?? 0, PARAM_INT);
        hub_access::require_template($templateid, $context);
        $cohortid = max(0, (int) clean_param($values['cohortid'] ?? 0, PARAM_INT));
        if ($cohortid) {
            hub_access::require_cohort($cohortid, $context);
        }
        if (!array_key_exists('statuses', $values)) {
            $values['statuses'] = self::needs_attention();
        }
        $search = trim(clean_param((string) ($values['search'] ?? ''), PARAM_TEXT));
        return new self(
            $templateid,
            $cohortid,
            \core_text::substr($search, 0, self::SEARCH_MAXLENGTH),
            !empty($values['suspended']),
            ...self::clean_view($values),
        );
    }

    /**
     * The view values of raw input, each against its allowlist: statuses, sort, dir, page, perpage.
     *
     * @param array $values Raw values; a missing one takes its default (statuses: every status).
     * @return array Property name => clean value.
     */
    private static function clean_view(array $values): array {
        $raw = array_map(fn($code): int => (int) clean_param($code, PARAM_INT), (array) ($values['statuses'] ?? []));
        $sort = (string) ($values['sort'] ?? '');
        $perpage = (int) clean_param($values['perpage'] ?? 0, PARAM_INT);
        return [
            // The allowlist, in display order and without repeats.
            'statuses' => array_values(array_intersect(status::effective_codes(), $raw)),
            'sort' => in_array($sort, self::SORTS, true) ? $sort : self::SORT_EXPIRY,
            'dir' => ($values['dir'] ?? '') === 'desc' ? 'desc' : 'asc',
            'page' => max(0, (int) clean_param($values['page'] ?? 0, PARAM_INT)),
            'perpage' => in_array($perpage, self::PERPAGES, true) ? $perpage : self::DEFAULT_PERPAGE,
        ];
    }

    /**
     * Whether the request names a filter at all; without one the page restores the stored filter.
     *
     * @return bool
     */
    public static function has_request_params(): bool {
        foreach (self::PARAMS as $name) {
            if (optional_param($name, null, PARAM_RAW) !== null) {
                return true;
            }
        }
        return optional_param_array('statuses', null, PARAM_INT) !== null;
    }

    /**
     * The filter the request names.
     *
     * The status chips post statusfilter with them, so a request that ticks none means every status
     * and one without statusfilter means the default view.
     *
     * @param \context $context The page context.
     * @return self|null The filter, or null when no template is chosen.
     * @throws \moodle_exception When the template or the cohort is not offered in the context.
     */
    public static function from_request(\context $context): ?self {
        $templateid = optional_param('templateid', 0, PARAM_INT);
        if ($templateid <= 0) {
            return null;
        }
        $values = [
            'templateid' => $templateid,
            'cohortid' => optional_param('cohortid', 0, PARAM_INT),
            'search' => optional_param('search', '', PARAM_TEXT),
            'suspended' => optional_param('suspended', 0, PARAM_BOOL),
            'sort' => optional_param('sort', '', PARAM_ALPHA),
            'dir' => optional_param('dir', '', PARAM_ALPHA),
            'page' => optional_param('page', 0, PARAM_INT),
            'perpage' => optional_param('perpage', 0, PARAM_INT),
        ];
        $statuses = optional_param_array('statuses', [], PARAM_INT);
        if ($statuses || optional_param('statusfilter', 0, PARAM_BOOL)) {
            $values['statuses'] = $statuses;
        }
        return self::from_values($values, $context);
    }

    /**
     * The stored filter of a user, when it is still valid in the context.
     *
     * A template no longer offered (another context, a lost right, a deleted template) drops the
     * stored filter silently: the page asks for a template instead.
     *
     * @param \context $context The page context.
     * @param int|null $userid The user; the current one when null.
     * @return self|null
     */
    public static function from_preference(\context $context, ?int $userid = null): ?self {
        $stored = get_user_preferences(self::PREFERENCE, null, $userid);
        $values = $stored === null ? null : json_decode($stored, true);
        if (!is_array($values) || empty($values['templateid'])) {
            return null;
        }
        try {
            return self::from_values($values, $context);
        } catch (\moodle_exception $e) {
            return null;
        }
    }

    /**
     * Store this filter as the user's last one, without its page.
     *
     * @param int|null $userid The user; the current one when null.
     * @return void
     */
    public function save_preference(?int $userid = null): void {
        set_user_preference(self::PREFERENCE, json_encode($this->to_values()), $userid);
    }

    /**
     * The filter's values, as from_values() reads them back; the page is left out.
     *
     * @return array
     */
    public function to_values(): array {
        return [
            'templateid' => $this->templateid,
            'cohortid' => $this->cohortid,
            'statuses' => $this->statuses,
            'search' => $this->search,
            'suspended' => (int) $this->suspended,
            'sort' => $this->sort,
            'dir' => $this->dir,
            'perpage' => $this->perpage,
        ];
    }

    /**
     * The request parameters naming this filter, for links and forms.
     *
     * @return array Name => value; statuses is a list.
     */
    public function to_params(): array {
        $params = $this->to_values();
        $params['statusfilter'] = 1;
        if ($this->page > 0) {
            $params['page'] = $this->page;
        }
        return $params;
    }

    /**
     * A copy showing another view of the same set: other statuses, sort, direction, page or page size.
     *
     * The template, cohort, search and suspended flag stay this filter's, which were validated; the
     * view values are validated again against their allowlists.
     *
     * @param array $changes Some of statuses, sort, dir, page, perpage => raw value.
     * @return self
     * @throws \coding_exception On any other key.
     */
    public function with(array $changes): self {
        $unknown = array_diff(array_keys($changes), self::VIEW);
        if ($unknown) {
            throw new \coding_exception('A hub filter copy cannot change ' . implode(', ', $unknown));
        }
        $view = array_intersect_key(get_object_vars($this), array_flip(self::VIEW));
        return new self($this->templateid, $this->cohortid, $this->search, $this->suspended, ...self::clean_view($changes + $view));
    }

    /**
     * The effective statuses this filter shows.
     *
     * @return array Effective status codes; every code when the filter names none.
     */
    public function shown_statuses(): array {
        return $this->statuses ?: status::effective_codes();
    }
}
