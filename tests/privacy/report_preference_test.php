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

namespace local_evidencehub\privacy;

use core_privacy\local\request\writer;
use local_evidencehub\local\reportpreference;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The reader's settings of the pairs report: declared and exported as a user preference.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(provider::class)]
final class report_preference_test extends \core_privacy\tests\provider_testcase {
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * The preference is declared, and exported as stored for its owner only; a user who stored none exports none.
     *
     * @return void
     */
    public function test_the_report_preference_is_exported(): void {
        $declared = array_map(
            fn($item): string => $item->get_name(),
            provider::get_metadata(new \core_privacy\local\metadata\collection('local_evidencehub'))->get_collection()
        );
        $this->assertContains(reportpreference::NAME, $declared);

        $owner = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        reportpreference::write_grid(['status', 'changed'], ['status' => 200], true, (int) $owner->id);
        reportpreference::remember_template(5, 7, (int) $owner->id);
        reportpreference::write_grid(['type'], [], false, (int) $other->id);

        provider::export_user_preferences((int) $owner->id);
        $exported = writer::with_context(\context_system::instance())->get_user_preferences('local_evidencehub');
        $stored = get_user_preferences(reportpreference::NAME, null, $owner->id);
        $this->assertSame($stored, $exported->{reportpreference::NAME}->value);
        $this->assertSame(
            ['status', 'changed'],
            json_decode($exported->{reportpreference::NAME}->value, true)['columns']
        );
        $this->assertSame(
            get_string('privacy:metadata:preference:report', 'local_evidencehub'),
            $exported->{reportpreference::NAME}->description
        );

        // The control: a user who stored nothing exports nothing.
        writer::reset();
        provider::export_user_preferences((int) $this->getDataGenerator()->create_user()->id);
        $this->assertFalse(writer::with_context(\context_system::instance())->has_any_data());
    }
}
