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
 * One framework's own validity policy and the policy in force for each of its competencies.
 *
 * The caller checked local/evidencehub:managepolicies in the framework's context.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class framework_policies implements renderable, templatable {
    /**
     * The page of one framework.
     *
     * @param int $frameworkid The framework.
     */
    public function __construct(
        /** @var int The framework. */
        private readonly int $frameworkid,
    ) {
    }

    /**
     * The template context.
     *
     * @param renderer_base $output The renderer.
     * @return array The context of templates/framework_policies.mustache.
     */
    #[\Override]
    public function export_for_template(renderer_base $output): array {
        global $DB;
        $framework = $DB->get_record('competency_framework', ['id' => $this->frameworkid], 'id, shortname, contextid', MUST_EXIST);
        $context = \context::instance_by_id((int) $framework->contextid);
        // One query for the framework's rows; each competency's policy is resolved among them.
        $rows = $DB->get_records(policy::TABLE, ['frameworkid' => $this->frameworkid]);
        $records = $DB->get_records(
            'competency',
            ['competencyframeworkid' => $this->frameworkid],
            'shortname, id',
            'id, shortname, idnumber'
        );
        $competencies = [];
        foreach ($records as $record) {
            $resolved = policy::resolve_from($rows, (int) $record->id);
            $competencies[] = [
                'id' => (int) $record->id,
                // Plain spellings: the template's double stashes escape them once.
                'name' => format_string($record->shortname, true, ['context' => $context, 'escape' => false]),
                'idnumber' => $record->idnumber,
                'isoverride' => $resolved->source === policy::SOURCE_COMPETENCY,
                'policy' => policy_frameworks::summary($resolved),
            ];
        }
        return [
            'frameworkid' => (int) $framework->id,
            'name' => format_string($framework->shortname, true, ['context' => $context, 'escape' => false]),
            'policy' => policy_frameworks::summary(policy::resolve_from($rows, 0)),
            'actionurl' => (new \moodle_url('/local/evidencehub/policies.php'))->out(false),
            'sesskey' => sesskey(),
            'hascompetencies' => !empty($competencies),
            'competencies' => $competencies,
        ];
    }
}
