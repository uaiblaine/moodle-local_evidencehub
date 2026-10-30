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
 * Tests for the plugin's database schema.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_evidencehub;

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * The schema installs on every database family core supports.
 *
 * The validate gate and a PostgreSQL install both accept a column named after a word only MySQL
 * reserves; the install then fails on MariaDB alone, so this reads core's list instead.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class schema_test extends \basic_testcase {
    /**
     * No table or column is named after a word any supported database reserves.
     *
     * @return void
     */
    public function test_no_name_is_a_reserved_word(): void {
        global $CFG;
        require_once($CFG->libdir . '/ddllib.php');

        $file = new \xmldb_file(__DIR__ . '/../db/install.xml');
        $this->assertTrue($file->loadXMLStructure());
        $reserved = \sql_generator::getAllReservedWords();

        $names = [];
        $clashes = [];
        foreach ($file->getStructure()->getTables() as $table) {
            $names[] = $table->getName();
            foreach ($table->getFields() as $field) {
                $names[] = $table->getName() . '.' . $field->getName();
                if (isset($reserved[$field->getName()])) {
                    $clashes[] = $table->getName() . '.' . $field->getName();
                }
            }
            if (isset($reserved[$table->getName()])) {
                $clashes[] = $table->getName();
            }
        }
        // Precondition: the file was read, so an empty list of clashes means something.
        $this->assertContains('local_evidencehub_meta.expirydate', $names);
        $this->assertSame([], $clashes);
    }
}
