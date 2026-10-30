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

namespace local_evidencehub;

use PHPUnit\Framework\Attributes\CoversFunction;

/**
 * The callbacks of lib.php.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversFunction('local_evidencehub_extend_navigation_category_settings')]
final class lib_test extends \advanced_testcase {
    #[\Override]
    public static function setUpBeforeClass(): void {
        global $CFG;
        parent::setUpBeforeClass();
        require_once($CFG->dirroot . '/local/evidencehub/lib.php');
    }

    /**
     * The hub node a category's settings get for the current user, or null.
     *
     * @param \context_coursecat $context The category's context.
     * @return \navigation_node|null
     */
    private function node(\context_coursecat $context): ?\navigation_node {
        $root = \navigation_node::create('root');
        local_evidencehub_extend_navigation_category_settings($root, $context);
        return $root->find('local_evidencehub_hub', \navigation_node::TYPE_SETTING) ?: null;
    }

    /**
     * The More menu offers the hub to holders of view in the category, locked to it; not to others,
     * and not while competencies are disabled.
     *
     * @return void
     */
    public function test_the_category_menu_offers_the_hub(): void {
        $this->resetAfterTest();
        set_config('enabled', 1, 'core_competency');
        $generator = $this->getDataGenerator();
        $category = $generator->create_category();
        $context = \context_coursecat::instance($category->id);
        $manager = $generator->create_user();
        $roleid = $generator->create_role();
        assign_capability('local/evidencehub:view', CAP_ALLOW, $roleid, $context->id, true);
        role_assign($roleid, $manager->id, $context->id);
        accesslib_clear_all_caches_for_unit_testing();

        $this->setUser($manager);
        $node = $this->node($context);
        $this->assertNotNull($node);
        $this->assertSame((string) $context->id, $node->action->get_param('contextid'));
        $this->assertTrue($node->forceintomoremenu);

        $this->setUser($generator->create_user());
        $this->assertNull($this->node($context));

        $this->setUser($manager);
        set_config('enabled', 0, 'core_competency');
        $this->assertNull($this->node($context));
    }
}
