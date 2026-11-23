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

namespace local_evidencehub\local;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The reader's settings of the pairs report: cleaned on write and on read, bounded, and reset to the default.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(reportpreference::class)]
final class reportpreference_test extends \advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());
    }

    /**
     * Keys of the wrong shape, repeats and anything past the bound are dropped on write; widths are clamped to
     * whole pixels within the bounds; the flag is a boolean.
     *
     * @return void
     */
    public function test_a_write_is_cleaned(): void {
        $columns = array_merge(
            ['status', 'status', 'Bad Key', "status\n", '<x>', 'identity:email', 7],
            array_fill(0, 300, 'expiry')
        );
        for ($i = 0; $i < 60; $i++) {
            $columns[] = 'k' . str_repeat('a', $i % 20) . chr(97 + intdiv($i, 20));
        }
        $stored = reportpreference::write_grid(
            $columns,
            ['status' => 5000, 'expiry' => 10, 'count' => 99.6, 'Bad' => 100, 'issuer' => 'wide', 'type' => '250'],
            true
        );
        $this->assertSame(['status', 'identity:email', 'expiry'], array_slice($stored['columns'], 0, 3));
        $this->assertCount(reportpreference::MAX_KEYS, $stored['columns']);
        $this->assertSame(array_values(array_unique($stored['columns'])), $stored['columns']);
        $this->assertSame(
            ['status' => reportpreference::MAX_WIDTH, 'expiry' => reportpreference::MIN_WIDTH, 'count' => 100, 'type' => 250],
            $stored['widths']
        );
        $this->assertTrue($stored['suspended']);
        // What is read back is what the write returned.
        $this->assertSame($stored, reportpreference::read());
    }

    /**
     * A stored value of any other shape is cleaned on read: a forged key is dropped, a width clamped, a flag that
     * is not true reads false, and a value that is not JSON of an object reads as no choice.
     *
     * @return void
     */
    public function test_a_read_is_cleaned(): void {
        set_user_preference(reportpreference::NAME, json_encode([
            'columns' => ['status', 'DROP TABLE', 'changed'],
            'widths' => ['status' => 1, 'changed' => 99999, '<b>' => 100],
            'suspended' => 'yes',
            'templates' => ['5' => 7, 'x' => 3, '9' => -1, '11' => '12'],
            'other' => 'kept nowhere',
        ]));
        $this->assertSame([
            'columns' => ['status', 'changed'],
            'widths' => ['status' => reportpreference::MIN_WIDTH, 'changed' => reportpreference::MAX_WIDTH],
            'suspended' => false,
            'templates' => [5 => 7, 11 => 12],
        ], reportpreference::read());

        foreach (['not json', json_encode('a string'), json_encode(['columns' => 'status']), '[1,2'] as $broken) {
            set_user_preference(reportpreference::NAME, $broken);
            $this->assertSame(
                ['columns' => null, 'widths' => [], 'suspended' => false, 'templates' => []],
                reportpreference::read(),
                $broken
            );
        }
    }

    /**
     * "Use the default" forgets the columns and the widths and keeps the flag and the templates; a preference
     * with nothing but defaults left is removed.
     *
     * @return void
     */
    public function test_use_default_keeps_the_rest(): void {
        global $USER;
        reportpreference::write_grid(['status'], ['status' => 200], true);
        reportpreference::remember_template(5, 7);

        $stored = reportpreference::use_default();
        $this->assertSame(['columns' => null, 'widths' => [], 'suspended' => true, 'templates' => [5 => 7]], $stored);

        // Nothing but defaults: the row goes.
        set_user_preference(reportpreference::NAME, json_encode(['columns' => ['status']]));
        reportpreference::use_default();
        $this->assertNull(get_user_preferences(reportpreference::NAME, null, $USER->id));
    }

    /**
     * A null flag leaves the stored one as it was; the control: a given flag replaces it.
     *
     * @return void
     */
    public function test_a_grid_write_leaves_the_flag_unless_given(): void {
        reportpreference::write_grid(null, [], true);
        $this->assertTrue(reportpreference::write_grid(['status'], [], null)['suspended']);
        $this->assertFalse(reportpreference::write_grid(['status'], [], false)['suspended']);
    }

    /**
     * The last template per hub context: written only when it changes, the most recent first, at most
     * MAX_TEMPLATES contexts.
     *
     * @return void
     */
    public function test_the_template_memory_is_bounded(): void {
        $this->assertNull(reportpreference::last_template(3));
        $this->assertTrue(reportpreference::remember_template(3, 30));
        $this->assertFalse(reportpreference::remember_template(3, 30), 'The same template is not written again.');
        $this->assertTrue(reportpreference::remember_template(3, 31));
        $this->assertSame(31, reportpreference::last_template(3));
        $this->assertFalse(reportpreference::remember_template(0, 31));
        $this->assertFalse(reportpreference::remember_template(4, 0));

        for ($contextid = 100; $contextid < 100 + reportpreference::MAX_TEMPLATES + 5; $contextid++) {
            reportpreference::remember_template($contextid, $contextid + 1);
        }
        $templates = reportpreference::read()['templates'];
        $this->assertCount(reportpreference::MAX_TEMPLATES, $templates);
        // The most recent first; the oldest, context 3 among them, are forgotten.
        $this->assertSame(100 + reportpreference::MAX_TEMPLATES + 4, array_key_first($templates));
        $this->assertNull(reportpreference::last_template(3));
        $this->assertSame(125, reportpreference::last_template(124));
    }

    /**
     * Another user's preference is theirs: a write for one user leaves the other's untouched.
     *
     * @return void
     */
    public function test_the_preference_is_per_user(): void {
        $other = $this->getDataGenerator()->create_user();
        reportpreference::write_grid(['status'], [], true, (int) $other->id);
        $this->assertSame(['columns' => null, 'widths' => [], 'suspended' => false, 'templates' => []], reportpreference::read());
        $this->assertSame(['status'], reportpreference::read((int) $other->id)['columns']);
    }
}
