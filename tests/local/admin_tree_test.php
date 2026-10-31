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
 * Tests for the admin tree placement.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_evidencehub\local;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The entries sit in local_dimensions' category when it exists, else in competencies.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(admin_tree::class)]
final class admin_tree_test extends \advanced_testcase {
    /**
     * The real tree, with competencies enabled and local_dimensions installed, lists the three entries inside
     * its category.
     *
     * @return void
     */
    public function test_the_entries_sit_in_the_companions_category(): void {
        global $CFG;
        require_once($CFG->libdir . '/adminlib.php');
        $this->resetAfterTest();
        if (!\core_component::get_component_directory('local_dimensions')) {
            $this->markTestSkipped('local_dimensions is not installed, so it registers no category.');
        }
        set_config('enabled', 1, 'core_competency');
        $this->setAdminUser();
        $root = \admin_get_root(true, true);
        $category = $root->locate('local_dimensions');
        $this->assertInstanceOf(\admin_category::class, $category, 'Control: the category exists.');
        $children = array_map(fn($child): string => $child->name, $category->children);
        foreach (['local_evidencehub_hub', 'local_evidencehub_policies', 'local_evidencehub'] as $name) {
            $this->assertContains($name, $children, $name);
        }
    }

    /**
     * Without the companion's category the choice is competencies; once it is added, the category.
     *
     * @return void
     */
    public function test_the_parent_falls_back_to_competencies(): void {
        $root = new \admin_root(true);
        $root->add('root', new \admin_category('competencies', 'Competencies'));
        $this->assertSame('competencies', admin_tree::parent_category($root));

        $root->add('competencies', new \admin_category('local_dimensions', 'Competency Dimensions'));
        $this->assertSame('local_dimensions', admin_tree::parent_category($root));
    }
}
