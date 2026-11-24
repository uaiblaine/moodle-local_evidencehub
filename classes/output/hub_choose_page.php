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
use local_evidencehub\local\hub_access;

/**
 * The hub of a context before a template is chosen: the heading, the template picker the list and the matrix draw, and
 * a sentence asking for a template, or saying that the context offers none.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hub_choose_page implements renderable, templatable {
    /**
     * The page of a hub context.
     *
     * @param \context $context The hub context: the system or a course category.
     */
    public function __construct(
        /** @var \context The hub context. */
        private readonly \context $context,
    ) {
    }

    /**
     * The template context.
     *
     * @param renderer_base $output The renderer.
     * @return array The context of templates/hub_choose.mustache.
     */
    #[\Override]
    public function export_for_template(renderer_base $output): array {
        return [
            'title' => get_string('hubpage', 'local_evidencehub'),
            'picker' => hub_report_view::picker($output, $this->context, 0),
            'locked' => (int) $this->context->contextlevel === CONTEXT_COURSECAT,
            'contextname' => $this->context->get_context_name(false, false, false),
            'hastemplates' => hub_access::templates($this->context) !== [],
        ];
    }
}
