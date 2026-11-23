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

namespace local_evidencehub\reportbuilder\local\filters;

use core_reportbuilder\local\filters\select;

/**
 * Core's select filter, with its options read from the filter on every call.
 *
 * Core's select keeps the options of a filter name for the rest of the process. That holds for options
 * that are the same for every report and reader; these differ by reader or by report (the cohorts a
 * context offers, the statuses of the pair set), so a value outside this report's own options must be
 * judged against them, never against another report's.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reader_select extends select {
    /**
     * The filter's options, as this report defines them.
     *
     * @return array Value => label.
     */
    #[\Override]
    protected function get_select_options(): array {
        return (array) $this->filter->get_options();
    }
}
