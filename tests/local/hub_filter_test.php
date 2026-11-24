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

use local_evidencehub\tests\hub_scenario;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The pair set's cuts: every field validated, a template or a cohort only from the context's offer.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(hub_filter::class)]
final class hub_filter_test extends \advanced_testcase {
    use hub_scenario;

    /** @var int The template. */
    private int $templateid;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('enabled', 1, 'core_competency');
        $this->setAdminUser();
        $this->templateid = $this->template($this->competencies(1));
    }

    /**
     * The search is cleaned, trimmed and capped; any key the filter does not hold is ignored, so a caller's old
     * key (a status, a sort, a page) cannot reach the pair set.
     *
     * @return void
     */
    public function test_every_field_is_validated(): void {
        $filter = hub_filter::from_values([
            'templateid' => (string) $this->templateid,
            'statuses' => [5, 99],
            'search' => '  <b>Ana</b> ' . str_repeat('x', 200),
            'suspended' => '1',
            'sort' => 'password',
        ], \context_system::instance());
        $this->assertSame($this->templateid, $filter->templateid);
        $this->assertSame(0, $filter->cohortid);
        $this->assertTrue($filter->suspended);
        $this->assertStringStartsWith('Ana x', $filter->search);
        $this->assertSame(hub_filter::SEARCH_MAXLENGTH, \core_text::strlen($filter->search));
        $this->assertSame(['templateid', 'cohortid', 'search', 'suspended'], array_keys(get_object_vars($filter)));

        // The control: no suspended value reads as active accounts only.
        $this->assertFalse(hub_filter::from_values(['templateid' => $this->templateid], \context_system::instance())->suspended);
    }

    /**
     * A template or a cohort the context does not offer is refused, not dropped.
     *
     * @return void
     */
    public function test_a_template_or_cohort_outside_the_offer_is_refused(): void {
        $category = $this->getDataGenerator()->create_category();
        $catcontext = \context_coursecat::instance($category->id);
        try {
            hub_filter::from_values(['templateid' => $this->templateid], $catcontext);
            $this->fail('A template of the system was accepted in a category.');
        } catch (\moodle_exception $e) {
            $this->assertSame('errorhubtemplate', $e->errorcode);
        }
        $other = $this->getDataGenerator()->create_category();
        $cohort = $this->getDataGenerator()->create_cohort(['contextid' => \context_coursecat::instance($other->id)->id]);
        $this->expectExceptionMessage(get_string('errorhubcohort', 'local_evidencehub'));
        hub_filter::from_values(['templateid' => $this->templateid, 'cohortid' => $cohort->id], \context_system::instance());
    }
}
