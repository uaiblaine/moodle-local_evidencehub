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

namespace local_evidencehub\output;

use core\output\renderable;
use core\output\renderer_base;
use core\output\templatable;
use local_evidencehub\local\policy;

/**
 * The competency frameworks whose validity policies the user may manage.
 *
 * A framework's context is its own (system or a course category), so a category manager sees the
 * frameworks of their categories only.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class policy_frameworks implements renderable, templatable {
    /**
     * The template context.
     *
     * @param renderer_base $output The renderer.
     * @return array The context of templates/policy_frameworks.mustache.
     */
    #[\Override]
    public function export_for_template(renderer_base $output): array {
        global $DB;
        $frameworks = [];
        $records = $DB->get_records('competency_framework', null, 'shortname, id', 'id, shortname, idnumber, contextid');
        foreach ($records as $record) {
            $context = \context::instance_by_id((int) $record->contextid, IGNORE_MISSING);
            if (!$context || !has_capability('local/evidencehub:managepolicies', $context)) {
                continue;
            }
            $frameworks[] = [
                'id' => (int) $record->id,
                // Plain spellings: the template's double stashes escape them once.
                'name' => format_string($record->shortname, true, ['context' => $context, 'escape' => false]),
                'idnumber' => $record->idnumber,
                'contextname' => $context->get_context_name(false, false, false),
                'url' => (new \moodle_url('/local/evidencehub/policies.php', ['frameworkid' => $record->id]))->out(false),
            ];
        }
        return [
            'hasframeworks' => !empty($frameworks),
            'frameworks' => $frameworks,
        ];
    }

    /**
     * The policy of one row for a template, with its labels.
     *
     * Shared with {@see framework_policies}.
     *
     * @param \stdClass $policy A resolved policy.
     * @return array
     */
    public static function summary(\stdClass $policy): array {
        $yes = get_string('yes');
        $no = get_string('no');
        $kind = (int) $policy->kind;
        return [
            'kind' => policy::kind_label($kind),
            'requireverified' => $policy->requireverified ? $yes : $no,
            'minhours' => $policy->minhours === null ? '' : format_float((float) $policy->minhours, 2, true, true),
            'alerts' => get_string('policyalerts', 'local_evidencehub', (object) [
                'alert1' => (int) $policy->alert1,
                'alert2' => (int) $policy->alert2,
            ]),
            'gracedays' => $kind === policy::KIND_TECHNICAL ? (string) (int) $policy->gracedays : '',
            'cyclemonths' => $kind === policy::KIND_REGULATORY ? '' : (string) (int) $policy->cyclemonths,
            'onexpiry' => policy::onexpiry_label((int) $policy->onexpiry),
            'source' => policy::source_label($policy->source),
        ];
    }
}
