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
 * What an export's dossiers will hold, read and checked as the requester by {@see dossier_builder::prepare()}.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class dossier_plan {
    /**
     * A plan; only dossier_builder::prepare() builds one.
     *
     * @param \stdClass $template The plan template: id, name (plain), contextid.
     * @param array $workers List of workers kept, by name: userid, firstname, lastname, fullname, identifier
     *     (as printed), fileid (as in file names), rows (one per competency of the template, in its order),
     *     files (list of [name in the worker's folder, stored_file, whether its evidence is sensitive]),
     *     readiness (the real one) and readinessshown (over what the requester is shown), each a dossier READINESS_*.
     * @param array $omitted The user ids asked for and left out: no longer available to the requester.
     * @param int $bytes The size of every evidence file the dossiers hold.
     */
    public function __construct(
        /** @var \stdClass The plan template. */
        public readonly \stdClass $template,
        /** @var array The workers kept. */
        public readonly array $workers,
        /** @var array The user ids left out. */
        public readonly array $omitted,
        /** @var int The evidence files' size. */
        public readonly int $bytes,
    ) {
    }
}
