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
 * States of a dossier export request, local_evidencehub_export.status.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class export {
    /** @var int Waiting for the ad hoc task. */
    public const int STATUS_QUEUED = 0;

    /** @var int The task is building the ZIP. */
    public const int STATUS_RUNNING = 1;

    /** @var int The ZIP is ready until timeexpires. */
    public const int STATUS_DONE = 2;

    /** @var int The task gave up; the requester was told. */
    public const int STATUS_FAILED = 3;
}
