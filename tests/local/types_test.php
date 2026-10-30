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
 * Tests for the default evidence types.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_evidencehub\local;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The install seeds the five default types; seeding again respects what the administrator did.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(types::class)]
final class types_test extends \advanced_testcase {
    /**
     * The installed site already holds the defaults, in order, with the occupational health
     * certificate as the one sensitive type.
     *
     * @return void
     */
    public function test_the_install_seeds_the_defaults(): void {
        global $DB;
        $this->resetAfterTest();
        $records = $DB->get_records('local_evidencehub_type', null, 'sortorder');
        $this->assertSame(
            ['nrcertificate', 'aso', 'artrrt', 'diploma', 'practical'],
            array_values(array_map(fn($r) => $r->shortname, $records))
        );
        $this->assertSame([1, 2, 3, 4, 5], array_values(array_map(fn($r) => (int) $r->sortorder, $records)));
        $sensitive = array_filter($records, fn($r) => (int) $r->issensitive === 1);
        $this->assertSame(['aso'], array_values(array_map(fn($r) => $r->shortname, $sensitive)));
        $byname = array_column($records, null, 'shortname');
        $this->assertSame(get_string('type_aso', 'local_evidencehub'), $byname['aso']->name);
        $this->assertSame(0, (int) $byname['diploma']->requireexpiry);
        $this->assertSame(1, (int) $byname['artrrt']->requireauthcode);
    }

    /**
     * Seeding again inserts nothing over existing types, leaves a renamed one renamed and puts
     * back only a deleted one.
     *
     * @return void
     */
    public function test_seeding_again_keeps_the_administrators_changes(): void {
        global $DB;
        $this->resetAfterTest();
        $this->assertSame(0, types::seed_defaults());

        $DB->set_field('local_evidencehub_type', 'name', 'Renamed', ['shortname' => 'diploma']);
        $DB->delete_records('local_evidencehub_type', ['shortname' => 'practical']);

        $this->assertSame(1, types::seed_defaults());
        $this->assertSame('Renamed', $DB->get_field('local_evidencehub_type', 'name', ['shortname' => 'diploma']));
        $this->assertTrue($DB->record_exists('local_evidencehub_type', ['shortname' => 'practical']));
        $this->assertSame(5, $DB->count_records('local_evidencehub_type'));
    }
}
