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
     * Evidence of a sensitive type (an occupational health certificate is health data): the hub, its pages,
     * services and dossiers hide its details, and leave its files out of dossiers, from a viewer other than the
     * worker who lacks this capability. It does not hide the files in core: tool_lp serves an evidence's files to
     * any holder of moodle/competency:userevidenceview over the worker (see README). No archetype: it is
     * granted on purpose.
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
     * Who may open a dossier's public verification page, as tool/certificate:verify does for certificates. A
     * visitor without an account is checked as the guest role, so the default keeps the page open to anyone
     * holding the code; a site that wants it closed prevents it for guest (and for user).
     */
    'local/evidencehub:verifydossier' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'guest' => CAP_ALLOW,
            'user' => CAP_ALLOW,
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
