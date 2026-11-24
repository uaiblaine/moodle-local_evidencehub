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
 * Tests for the hook callbacks.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_evidencehub;

use core\hook\output\before_footer_html_generation;
use local_evidencehub\local\details;
use local_evidencehub\tests\scenario;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The tool_lp prompt appears on tool_lp's evidence pages only, to who may complete the details.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(hook_callbacks::class)]
final class hook_callbacks_test extends \advanced_testcase {
    use scenario;

    /** @var \stdClass The worker. */
    private \stdClass $worker;

    /** @var \core_competency\user_evidence An evidence with details. */
    private \core_competency\user_evidence $complete;

    /** @var \core_competency\user_evidence An evidence without details. */
    private \core_competency\user_evidence $missing;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('enabled', 1, 'core_competency');
        $this->worker = $this->user_with();
        $this->complete = $this->evidence_of((int) $this->worker->id);
        $this->missing = $this->evidence_of((int) $this->worker->id);
        $this->setUser($this->worker);
        details::save($this->complete, $this->details_data());
    }

    /**
     * Run the callback on a fresh page and return the prompt's arguments.
     *
     * @param string $pagetype The page type.
     * @param \context $context The page context.
     * @param array $params The page URL parameters.
     * @return array|null The arguments of the prompt, or null when no prompt was added.
     */
    private function prompt(string $pagetype, \context $context, array $params = []): ?array {
        global $PAGE;
        $PAGE = new \moodle_page();
        $PAGE->set_pagetype($pagetype);
        $PAGE->set_context($context);
        $PAGE->set_url(new \moodle_url('/admin/tool/lp/user_evidence.php', $params));
        hook_callbacks::before_footer_html_generation(new before_footer_html_generation($PAGE->get_renderer('core')));

        $calls = implode("\n", (new \ReflectionProperty($PAGE->requires, 'amdjscode'))->getValue($PAGE->requires));
        $pattern = '/require\(\[\'local_evidencehub\/lp_prompt\'\], function\(amd\) \{amd\.init\((\{.*?\})\);/';
        if (!preg_match($pattern, $calls, $match)) {
            return null;
        }
        return json_decode($match[1], true);
    }

    /**
     * The worker's own user context.
     *
     * @return \context_user
     */
    private function ctx(): \context_user {
        return \context_user::instance($this->worker->id);
    }

    /**
     * The list page counts the evidence without details and links to the evidence page.
     *
     * @return void
     */
    public function test_the_list_page_counts_the_missing_details(): void {
        $args = $this->prompt(hook_callbacks::PAGETYPE_LIST, $this->ctx(), ['userid' => $this->worker->id]);
        $this->assertNotNull($args);
        // The module reads these three only: the count reaches it inside the message.
        $keys = array_keys($args);
        sort($keys);
        $this->assertSame(['linktext', 'message', 'url'], $keys);
        $this->assertSame(get_string('promptlist', 'local_evidencehub', 1), $args['message']);
        $expected = new \moodle_url('/local/evidencehub/evidence.php', ['userid' => $this->worker->id]);
        $this->assertSame($expected->out(false), $args['url']);
    }

    /**
     * The view page prompts for its own evidence only.
     *
     * @return void
     */
    public function test_the_view_page_prompts_for_its_own_evidence(): void {
        $args = $this->prompt(hook_callbacks::PAGETYPE_VIEW, $this->ctx(), ['id' => $this->missing->get('id')]);
        $this->assertSame(get_string('promptview', 'local_evidencehub'), $args['message']);
        $this->assertNull($this->prompt(hook_callbacks::PAGETYPE_VIEW, $this->ctx(), ['id' => $this->complete->get('id')]));
    }

    /**
     * Another page type never gets the prompt, nor a page outside a user context.
     *
     * @return void
     */
    public function test_only_tool_lp_evidence_pages_in_a_user_context(): void {
        $this->assertNull($this->prompt('admin-tool-lp-plans', $this->ctx(), ['userid' => $this->worker->id]));
        $this->assertNull($this->prompt(hook_callbacks::PAGETYPE_LIST, \context_system::instance()));
        // Control: the list page in the worker's context does.
        $this->assertNotNull($this->prompt(hook_callbacks::PAGETYPE_LIST, $this->ctx()));
    }

    /**
     * A reader who cannot manage the evidence is not prompted; a manager is.
     *
     * @return void
     */
    public function test_only_who_may_manage_is_prompted(): void {
        $this->setUser($this->user_with(['moodle/competency:userevidenceview']));
        $this->assertNull($this->prompt(hook_callbacks::PAGETYPE_LIST, $this->ctx()));
        $this->setUser($this->user_with(['moodle/competency:userevidencemanage']));
        $this->assertNotNull($this->prompt(hook_callbacks::PAGETYPE_LIST, $this->ctx()));
    }

    /**
     * Nothing when every evidence has details.
     *
     * @return void
     */
    public function test_nothing_when_every_evidence_has_details(): void {
        details::save($this->missing, $this->details_data());
        $this->assertNull($this->prompt(hook_callbacks::PAGETYPE_LIST, $this->ctx()));
    }

    /**
     * Dispatching local_dimensions' hook through the manager yields the worker's pills; skipped without it.
     *
     * @return void
     */
    public function test_the_status_badges_hook_is_answered(): void {
        $hookclass = '\\local_dimensions\\hook\\competency_status_badges';
        if (!class_exists($hookclass)) {
            $this->markTestSkipped('local_dimensions with the competency_status_badges hook is not installed.');
        }
        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $competency = $generator->create_competency(['competencyframeworkid' => $generator->create_framework()->get('id')]);
        $this->getDataGenerator()->get_plugin_generator('local_evidencehub')->create_status([
            'userid' => $this->worker->id,
            'competencyid' => $competency->get('id'),
            'status' => local\status::STATUS_DUE,
            'effectiveexpiry' => 20270301,
        ]);
        $this->setUser($this->user_with(['moodle/competency:userevidenceview']));
        $id = (int) $competency->get('id');

        $hook = new $hookclass((int) $this->worker->id, [$id]);
        \core\di::get(\core\hook\manager::class)->dispatch($hook);
        $this->assertSame([['label' => 'Validity: Due soon, 2027-03-01', 'tone' => 'warning']], $hook->get_badges($id));
    }
}
