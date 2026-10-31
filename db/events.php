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
 * The plan, cohort and user events retire the hub's cached KPIs. Core fires no event when a plan
 * template gains or loses a competency, so the KPI key reads the template's competencies instead.
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
        'eventname' => '\local_evidencehub\event\evidence_details_updated',
        'callback' => [\local_evidencehub\observer::class, 'details_updated'],
    ],
    [
        'eventname' => '\local_evidencehub\event\validity_status_changed',
        'callback' => [\local_evidencehub\observer::class, 'validity_status_changed'],
        // After every open transaction commits: an alert never announces a change that rolls back.
        'internal' => false,
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
    [
        'eventname' => '\core\event\competency_plan_created',
        'callback' => [\local_evidencehub\observer::class, 'base_set_changed'],
    ],
    [
        'eventname' => '\core\event\competency_plan_updated',
        'callback' => [\local_evidencehub\observer::class, 'base_set_changed'],
    ],
    [
        'eventname' => '\core\event\competency_plan_approved',
        'callback' => [\local_evidencehub\observer::class, 'base_set_changed'],
    ],
    [
        'eventname' => '\core\event\competency_plan_unapproved',
        'callback' => [\local_evidencehub\observer::class, 'base_set_changed'],
    ],
    [
        'eventname' => '\core\event\competency_plan_completed',
        'callback' => [\local_evidencehub\observer::class, 'base_set_changed'],
    ],
    [
        'eventname' => '\core\event\competency_plan_reopened',
        'callback' => [\local_evidencehub\observer::class, 'base_set_changed'],
    ],
    [
        'eventname' => '\core\event\competency_plan_deleted',
        'callback' => [\local_evidencehub\observer::class, 'base_set_changed'],
    ],
    [
        'eventname' => '\core\event\competency_plan_unlinked',
        'callback' => [\local_evidencehub\observer::class, 'base_set_changed'],
    ],
    [
        'eventname' => '\core\event\cohort_member_added',
        'callback' => [\local_evidencehub\observer::class, 'base_set_changed'],
    ],
    [
        'eventname' => '\core\event\cohort_member_removed',
        'callback' => [\local_evidencehub\observer::class, 'base_set_changed'],
    ],
    [
        'eventname' => '\core\event\cohort_deleted',
        'callback' => [\local_evidencehub\observer::class, 'base_set_changed'],
    ],
    [
        'eventname' => '\core\event\user_updated',
        'callback' => [\local_evidencehub\observer::class, 'base_set_changed'],
    ],
];
