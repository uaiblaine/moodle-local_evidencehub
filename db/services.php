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
 * Web service functions and the pre-built service.
 *
 * The readiness function serves gate systems that call it with a token; the hub's pages do not use
 * it, hence no AJAX. The pre-built service is disabled until an administrator enables it.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_evidencehub_get_readiness' => [
        'classname' => \local_evidencehub\external\get_readiness::class,
        'description' => 'The readiness of a worker for a learning plan template, with the validity status of each '
            . 'of its competencies as the caller may see it.',
        'type' => 'read',
        'ajax' => false,
        'capabilities' => 'local/evidencehub:view, moodle/competency:userevidenceview',
    ],
];

$services = [
    'Evidence hub readiness' => [
        'functions' => ['local_evidencehub_get_readiness'],
        'shortname' => 'local_evidencehub_readiness',
        'restrictedusers' => 0,
        'enabled' => 0,
        'downloadfiles' => 0,
        'uploadfiles' => 0,
    ],
];
