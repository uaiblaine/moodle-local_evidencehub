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
 * Observers of core competencies' events and of the plugin's own details events.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\core\event\competency_user_evidence_updated',
        'callback' => [\local_evidencehub\observer::class, 'user_evidence_updated'],
    ],
    [
        'eventname' => '\core\event\competency_user_evidence_deleted',
        'callback' => [\local_evidencehub\observer::class, 'user_evidence_deleted'],
    ],
    [
        'eventname' => '\core\event\competency_evidence_created',
        'callback' => [\local_evidencehub\observer::class, 'evidence_created'],
    ],
    [
        'eventname' => '\core\event\competency_user_competency_rated',
        'callback' => [\local_evidencehub\observer::class, 'user_competency_rated'],
    ],
    [
        'eventname' => '\core\event\competency_user_competency_rated_in_plan',
        'callback' => [\local_evidencehub\observer::class, 'user_competency_rated'],
    ],
    [
        'eventname' => '\core\event\competency_user_competency_rated_in_course',
        'callback' => [\local_evidencehub\observer::class, 'user_competency_rated'],
    ],
    [
        'eventname' => '\local_evidencehub\event\evidence_details_updated',
        'callback' => [\local_evidencehub\observer::class, 'details_changed'],
    ],
    [
        'eventname' => '\local_evidencehub\event\evidence_verified',
        'callback' => [\local_evidencehub\observer::class, 'details_changed'],
    ],
    [
        'eventname' => '\local_evidencehub\event\evidence_rejected',
        'callback' => [\local_evidencehub\observer::class, 'details_changed'],
    ],
    [
        'eventname' => '\core\event\competency_deleted',
        'callback' => [\local_evidencehub\observer::class, 'competency_deleted'],
    ],
    [
        'eventname' => '\core\event\competency_framework_deleted',
        'callback' => [\local_evidencehub\observer::class, 'competency_framework_deleted'],
    ],
    [
        'eventname' => '\core\event\user_deleted',
        'callback' => [\local_evidencehub\observer::class, 'user_deleted'],
    ],
];
