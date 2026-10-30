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
 * Policy kinds and expiry behaviours, as stored in local_evidencehub_policy.
 *
 * The kind is operational (does an expired evidence stop the worker?), not an educational
 * taxonomy.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class policy {
    /** @var int Regulatory (health and safety): verified evidence only, strict expiry, blocking. */
    public const int KIND_REGULATORY = 1;

    /** @var int Technical or role-specific: evidence or a proficient rating, grace days, not blocking. */
    public const int KIND_TECHNICAL = 2;

    /** @var int Behavioural or organisational: a proficient rating valid for cyclemonths, not blocking. */
    public const int KIND_BEHAVIOURAL = 3;

    /** @var int On expiry, log it and alert. */
    public const int ONEXPIRY_LOG = 1;

    /** @var int On expiry, log it, alert and request a review in core; proficiency is never revoked. */
    public const int ONEXPIRY_REVIEW = 2;
}
