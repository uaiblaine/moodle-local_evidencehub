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
 * The cuts of the hub's pair set a reader of {@see hub_query::base_sql()} asks for: a validated plan template,
 * cohort, search and whether suspended workers are included.
 *
 * Only {@see self::from_values()} builds one, and it validates every field: the template and the cohort against
 * what the context offers ({@see hub_access}), the search against its length. The list's own criteria (scope,
 * stored filters, sort, page) are the pairs report's ({@see reportengine}); this object serves the readers of the
 * pair set outside it: the matrix's cells, the dossier export's selection and its builder, the weekly digest and
 * the readiness web service.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class hub_filter {
    /** @var int The longest search kept. */
    public const int SEARCH_MAXLENGTH = 100;

    /**
     * A filter; only {@see self::from_values()} builds one, after validating the template and the cohort.
     *
     * @param int $templateid The plan template.
     * @param int $cohortid The cohort the workers belong to, 0 for none.
     * @param string $search Part of a worker's first name, last name or shown ID number; '' for none.
     * @param bool $suspended Whether suspended workers are included.
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
    ) {
    }

    /**
     * A validated filter from raw values.
     *
     * @param array $values templateid (required), and optionally cohortid, search, suspended; any other key is ignored.
     * @param \context $context The context, which decides the templates and cohorts on offer.
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
        $search = trim(clean_param((string) ($values['search'] ?? ''), PARAM_TEXT));
        return new self(
            $templateid,
            $cohortid,
            \core_text::substr($search, 0, self::SEARCH_MAXLENGTH),
            !empty($values['suspended']),
        );
    }
}
