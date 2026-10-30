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
 * Capabilities definition.
 *
 * Reading a worker's evidence also needs core's moodle/competency:userevidenceview in that
 * worker's user context, so the user-context capabilities here follow core's model: a mentor
 * receives them through tool_cohortroles, a manager through the system role.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [

    /*
     * The hub lists workers with the validity of their evidence, hence RISK_PERSONAL. Granted in a
     * course category, it opens the hub locked to that category's plan templates.
     */
    'local/evidencehub:view' => [
        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSECAT,
        'archetypes' => [
            'manager' => CAP_ALLOW,
        ],
    ],

    // Verifying or rejecting an evidence decides whether it counts for a regulatory competency.
    'local/evidencehub:verify' => [
        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'write',
        'contextlevel' => CONTEXT_USER,
        'archetypes' => [
            'manager' => CAP_ALLOW,
        ],
    ],

    /*
     * Evidence of a sensitive type (an occupational health certificate is health data) shows its
     * details and files only to holders of this capability. No archetype: it is granted on purpose.
     */
    'local/evidencehub:viewsensitive' => [
        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'read',
        'contextlevel' => CONTEXT_USER,
        'archetypes' => [],
    ],

    'local/evidencehub:managepolicies' => [
        'riskbitmask' => RISK_CONFIG,
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
        'archetypes' => [
            'manager' => CAP_ALLOW,
        ],
    ],

    'local/evidencehub:managetypes' => [
        'riskbitmask' => RISK_CONFIG,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW,
        ],
    ],

    // A dossier carries a worker's evidence files out of the site, hence RISK_PERSONAL.
    'local/evidencehub:exportdossier' => [
        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSECAT,
        'archetypes' => [
            'manager' => CAP_ALLOW,
        ],
    ],

    /*
     * Who is told when a worker's evidence is about to expire, has expired or awaits verification.
     * Held in the worker's user context, so a mentor role given through tool_cohortroles reaches
     * exactly the workers of the mentor's cohorts. No archetype: the site decides who is alerted.
     */
    'local/evidencehub:receivealerts' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_USER,
        'archetypes' => [],
    ],
];
