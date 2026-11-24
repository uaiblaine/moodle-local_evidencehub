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
 * Cache definitions.
 *
 * The hub's cached figures are retired by a generation, never by a time to live: see local\hub_kpis.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$definitions = [
    // One value: the generation of the pair set and its statuses; each replacement also replaces the report's
    // generation ({@see \local_evidencehub\local\hub_kpis::bump_generation()}).
    'hubgeneration' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => true,
    ],
    // The pairs report's summary, and its rows' total, hidden workers and facet counts, of one reader's criteria, keyed
    // by a hash that holds today and the report generation. The ttl only bounds how long a dead key lives: correctness
    // never depends on it.
    'reportsummary' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => true,
        'ttl' => 86400,
    ],
    // One value: the current generation of the report summary. It moves with the KPIs' generation and also when
    // a status row's expiry, deciding evidence or evidence count changes, or an evidence's details do.
    'reportgeneration' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => true,
    ],
];
