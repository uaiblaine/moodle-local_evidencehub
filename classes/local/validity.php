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
 * What the validity calculator worked out for one (user, competency) pair.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class validity {
    /**
     * A result.
     *
     * @param int|null $status A status::STATUS_* constant; null when nothing could prove the
     *     competency, so no row is stored.
     * @param int|null $metaid The details row of the deciding evidence; null for a rating or none.
     * @param int|null $effectiveexpiry The deciding candidate's last valid day YYYYMMDD, grace
     *     included; null when it never expires or nothing decides.
     * @param int $evidencecount The evidence candidates, counted or not.
     * @param int|null $nexttransition The first day YYYYMMDD after today the status changes by
     *     itself; null when it never does.
     * @param bool $ready Whether the status is valid, due or critical.
     * @param bool $blocking Whether the pair stops the worker: a regulatory policy that is not ready.
     */
    public function __construct(
        /** @var int|null A status::STATUS_* constant, or null for no status. */
        public readonly ?int $status,
        /** @var int|null The details row of the deciding evidence. */
        public readonly ?int $metaid,
        /** @var int|null The deciding candidate's last valid day YYYYMMDD, grace included. */
        public readonly ?int $effectiveexpiry,
        /** @var int The evidence candidates, counted or not. */
        public readonly int $evidencecount,
        /** @var int|null The first day YYYYMMDD after today the status changes by itself. */
        public readonly ?int $nexttransition,
        /** @var bool Whether the status is valid, due or critical. */
        public readonly bool $ready,
        /** @var bool Whether the pair stops the worker. */
        public readonly bool $blocking,
    ) {
    }
}
