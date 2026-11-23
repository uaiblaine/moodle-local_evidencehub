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

use core_competency\template;

/**
 * Where the hub opens and what it offers there: the context, its plan templates and its cohorts.
 *
 * Entered from a course category, the hub is locked to the templates of that category and its
 * subcategories; from the site, it offers every template. Either way a template is offered, and
 * accepted, only when the viewer can read it, so a template id outside the offered set is refused.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class hub_access {
    /**
     * Whether the hub opens in a context: the system or a course category.
     *
     * @param \context $context The page context.
     * @return bool
     */
    public static function opens_in(\context $context): bool {
        return in_array((int) $context->contextlevel, [CONTEXT_SYSTEM, CONTEXT_COURSECAT], true);
    }

    /**
     * Whether the current user may open the hub in a context: {@see self::require_view()}'s rule, without throwing.
     *
     * @param \context $context The page context.
     * @return bool
     */
    public static function can_view(\context $context): bool {
        return self::opens_in($context) && has_capability('local/evidencehub:view', $context);
    }

    /**
     * Refuse a context the hub does not open in, and a viewer without local/evidencehub:view there.
     *
     * @param \context $context The page context.
     * @return void
     * @throws \moodle_exception When the context is neither the system nor a course category.
     * @throws \required_capability_exception Without local/evidencehub:view in the context.
     */
    public static function require_view(\context $context): void {
        if (!self::opens_in($context)) {
            throw new \moodle_exception('errorhubcontext', 'local_evidencehub');
        }
        require_capability('local/evidencehub:view', $context);
    }

    /**
     * The plan templates the hub offers in a context, by name.
     *
     * @param \context $context The page context: the system or a course category.
     * @return array Template id => record with id, shortname, contextid; only the readable ones.
     */
    public static function templates(\context $context): array {
        global $DB;
        $params = [];
        $where = '';
        if ((int) $context->contextlevel !== CONTEXT_SYSTEM) {
            // The category itself and every context below it.
            $where = 'WHERE ctx.id = :contextid OR ' . $DB->sql_like('ctx.path', ':path');
            $params = ['contextid' => $context->id, 'path' => $DB->sql_like_escape($context->path) . '/%'];
        }
        $sql = "SELECT t.id, t.shortname, t.contextid
                  FROM {" . template::TABLE . "} t
                  JOIN {context} ctx ON ctx.id = t.contextid
                $where
              ORDER BY t.shortname, t.id";
        $templates = [];
        foreach ($DB->get_records_sql($sql, $params) as $record) {
            $templatecontext = \context::instance_by_id((int) $record->contextid, IGNORE_MISSING);
            if ($templatecontext && template::can_read_context($templatecontext)) {
                $record->id = (int) $record->id;
                $record->contextid = (int) $record->contextid;
                $templates[$record->id] = $record;
            }
        }
        return $templates;
    }

    /**
     * Accept a template only when the hub offers it in the context.
     *
     * @param int $templateid The template.
     * @param \context $context The page context.
     * @return \stdClass The template record, as {@see self::templates()} lists it.
     * @throws \moodle_exception When the template is not offered there.
     */
    public static function require_template(int $templateid, \context $context): \stdClass {
        $template = self::offered_template($templateid, $context);
        if ($template === null) {
            throw new \moodle_exception('errorhubtemplate', 'local_evidencehub');
        }
        return $template;
    }

    /**
     * A template the hub offers in the context, or null: {@see self::require_template()}'s rule, without throwing.
     *
     * @param int $templateid The template.
     * @param \context $context The page context.
     * @return \stdClass|null The template record, as {@see self::templates()} lists it.
     */
    public static function offered_template(int $templateid, \context $context): ?\stdClass {
        return self::templates($context)[$templateid] ?? null;
    }

    /**
     * The cohorts the hub offers as a filter in a context, by name.
     *
     * Mirrors cohort_get_cohort() and cohort_get_available_cohorts(): a cohort of the context or of
     * one above it, hidden ones only to holders of moodle/cohort:view in the cohort's context. Unlike
     * them, the context's own cohorts count: core's helpers serve a course, whose category cohorts are
     * its parents', while the hub's page context is the category or the system itself.
     *
     * @param \context $context The page context.
     * @return array Cohort id => record with id, name, contextid, visible.
     */
    public static function cohorts(\context $context): array {
        global $DB;
        [$insql, $params] = $DB->get_in_or_equal($context->get_parent_context_ids(true), SQL_PARAMS_NAMED, 'hubctx');
        $cohorts = [];
        $records = $DB->get_records_select('cohort', "contextid $insql", $params, 'name, id', 'id, name, contextid, visible');
        foreach ($records as $cohort) {
            if (self::can_see_cohort($cohort)) {
                $cohort->id = (int) $cohort->id;
                $cohorts[$cohort->id] = $cohort;
            }
        }
        return $cohorts;
    }

    /**
     * Accept a cohort only when the hub offers it in the context.
     *
     * @param int $cohortid The cohort.
     * @param \context $context The page context.
     * @return \stdClass The cohort record.
     * @throws \moodle_exception When the cohort is not offered there.
     */
    public static function require_cohort(int $cohortid, \context $context): \stdClass {
        global $DB;
        $cohort = $DB->get_record('cohort', ['id' => $cohortid], 'id, name, contextid, visible');
        $offered = $cohort && in_array((int) $cohort->contextid, $context->get_parent_context_ids(true));
        if (!$offered || !self::can_see_cohort($cohort)) {
            throw new \moodle_exception('errorhubcohort', 'local_evidencehub');
        }
        $cohort->id = (int) $cohort->id;
        return $cohort;
    }

    /**
     * Whether the viewer may see a cohort: a visible one, or a hidden one with moodle/cohort:view in its context.
     *
     * @param \stdClass $cohort The cohort record with contextid and visible.
     * @return bool
     */
    private static function can_see_cohort(\stdClass $cohort): bool {
        if ($cohort->visible) {
            return true;
        }
        $cohortcontext = \context::instance_by_id((int) $cohort->contextid, IGNORE_MISSING);
        return $cohortcontext && has_capability('moodle/cohort:view', $cohortcontext);
    }
}
