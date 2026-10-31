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
 * Tests for the dossier settings.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_evidencehub\local;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The two default-off switches read only an explicit '1' as on.
 *
 * The install writes '0' for both, so a missing value (a site upgraded before the setting existed,
 * or one an administrator cleared) is the case that tells the rule apart from "anything but '0'".
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(dossier_config::class)]
final class dossier_config_test extends \advanced_testcase {
    /**
     * Sensitive files stay out of dossiers unless the setting says '1'.
     *
     * @return void
     */
    public function test_sensitive_files_need_an_explicit_on(): void {
        $this->resetAfterTest();

        unset_config('includesensitivefiles', 'local_evidencehub');
        $this->assertFalse(dossier_config::include_sensitive_files());
        set_config('includesensitivefiles', '0', 'local_evidencehub');
        $this->assertFalse(dossier_config::include_sensitive_files());
        set_config('includesensitivefiles', '', 'local_evidencehub');
        $this->assertFalse(dossier_config::include_sensitive_files());

        // Control: the explicit value turns it on.
        set_config('includesensitivefiles', '1', 'local_evidencehub');
        $this->assertTrue(dossier_config::include_sensitive_files());
    }

    /**
     * The live status on the verification page stays off unless the setting says '1'.
     *
     * @return void
     */
    public function test_the_live_status_needs_an_explicit_on(): void {
        $this->resetAfterTest();

        unset_config('verifylive', 'local_evidencehub');
        $this->assertFalse(dossier_config::verify_live());
        set_config('verifylive', '', 'local_evidencehub');
        $this->assertFalse(dossier_config::verify_live());

        // Control: the explicit value turns it on.
        set_config('verifylive', '1', 'local_evidencehub');
        $this->assertTrue(dossier_config::verify_live());
    }

    /**
     * The readiness basis is the real one unless the setting is exactly 'shown'.
     *
     * @return void
     */
    public function test_the_readiness_basis_needs_an_exact_shown(): void {
        $this->resetAfterTest();

        unset_config('readinessbasis', 'local_evidencehub');
        $this->assertSame(dossier_config::READINESS_REAL, dossier_config::readiness_basis());
        foreach (['', 'bogus', 'SHOWN', 'real'] as $value) {
            set_config('readinessbasis', $value, 'local_evidencehub');
            $this->assertSame(dossier_config::READINESS_REAL, dossier_config::readiness_basis(), $value);
        }

        // Control: the exact value switches it.
        set_config('readinessbasis', 'shown', 'local_evidencehub');
        $this->assertSame(dossier_config::READINESS_SHOWN, dossier_config::readiness_basis());
    }
}
