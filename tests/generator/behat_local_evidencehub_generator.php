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

/**
 * Behat data generator.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * The plugin's entities for "the following ... exist" steps: plan templates, plans from them, status rows,
 * and evidence linked to a competency with its details.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_local_evidencehub_generator extends behat_generator_base {
    /**
     * The entities this generator creates.
     *
     * @return array
     */
    protected function get_creatable_entities(): array {
        return [
            'plan templates' => [
                'singular' => 'plan template',
                'datagenerator' => 'plan_template',
                'required' => ['shortname'],
                'switchids' => ['category' => 'categoryid'],
            ],
            'template plans' => [
                'singular' => 'template plan',
                'datagenerator' => 'template_plan',
                'required' => ['user', 'template'],
                'switchids' => ['user' => 'userid', 'template' => 'templateid'],
            ],
            'statuses' => [
                'singular' => 'status',
                'datagenerator' => 'status',
                'required' => ['user', 'competency', 'status'],
                'switchids' => ['user' => 'userid', 'competency' => 'competencyid'],
            ],
            'evidence records' => [
                'singular' => 'evidence record',
                'datagenerator' => 'evidence',
                'required' => ['user', 'competency', 'name', 'issuedate'],
                'switchids' => ['user' => 'userid', 'competency' => 'competencyid'],
            ],
        ];
    }

    /**
     * Read the competencies column of a plan template: ID numbers, comma separated.
     *
     * @param array $data The row.
     * @return array The row with competencyids.
     */
    protected function preprocess_plan_template(array $data): array {
        if (isset($data['competencies'])) {
            $idnumbers = array_filter(array_map('trim', explode(',', $data['competencies'])));
            $data['competencyids'] = array_map([$this, 'get_competency_id'], $idnumbers);
            unset($data['competencies']);
        }
        return $data;
    }

    /**
     * Read the type column of an evidence record: a type's shortname.
     *
     * @param array $data The row.
     * @return array The row with typeid.
     * @throws Exception When no type has that shortname.
     */
    protected function preprocess_evidence(array $data): array {
        global $DB;
        if (isset($data['type'])) {
            $typeid = $DB->get_field('local_evidencehub_type', 'id', ['shortname' => $data['type']]);
            if (!$typeid) {
                throw new Exception('The specified evidence type "' . $data['type'] . '" could not be found.');
            }
            $data['typeid'] = (int) $typeid;
            unset($data['type']);
        }
        return $data;
    }

    /**
     * A plan template's id from its short name.
     *
     * @param string $shortname The template's short name.
     * @return int
     * @throws Exception When no template has it.
     */
    protected function get_template_id(string $shortname): int {
        global $DB;
        $id = $DB->get_field('competency_template', 'id', ['shortname' => $shortname]);
        if (!$id) {
            throw new Exception('The specified template with name "' . $shortname . '" could not be found.');
        }
        return (int) $id;
    }

    /**
     * A competency's id from its ID number.
     *
     * @param string $idnumber The competency's ID number.
     * @return int
     * @throws Exception When no competency has it.
     */
    protected function get_competency_id(string $idnumber): int {
        global $DB;
        $id = $DB->get_field('competency', 'id', ['idnumber' => $idnumber]);
        if (!$id) {
            throw new Exception('The specified competency with idnumber "' . $idnumber . '" could not be found.');
        }
        return (int) $id;
    }
}
