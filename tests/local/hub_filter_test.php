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
 * The hub's filter: every field validated, the request and the stored preference read alike.
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

    #[\Override]
    protected function tearDown(): void {
        $_GET = [];
        parent::tearDown();
    }

    /**
     * Values outside an allowlist fall back to the default; the search is cleaned, trimmed and capped.
     *
     * @return void
     */
    public function test_every_field_is_validated(): void {
        $filter = hub_filter::from_values([
            'templateid' => (string) $this->templateid,
            'statuses' => [5, 99, '2', -1, 5, 0],
            'search' => '  <b>Ana</b> ' . str_repeat('x', 200),
            'sort' => 'password',
            'dir' => 'sideways',
            'page' => -3,
            'perpage' => 7,
            'view' => 'password',
        ], \context_system::instance());
        $this->assertSame($this->templateid, $filter->templateid);
        // Known codes only, in display order, once each.
        $this->assertSame(
            [status::EFFECTIVE_MISSING, status::STATUS_EXPIRED, status::STATUS_VALID, status::EFFECTIVE_RESTRICTED],
            $filter->statuses
        );
        $this->assertStringStartsWith('Ana x', $filter->search);
        $this->assertSame(hub_filter::SEARCH_MAXLENGTH, \core_text::strlen($filter->search));
        $this->assertSame([hub_filter::SORT_EXPIRY, 'asc', 0, hub_filter::DEFAULT_PERPAGE, hub_filter::VIEW_LIST], [
            $filter->sort,
            $filter->dir,
            $filter->page,
            $filter->perpage,
            $filter->view,
        ]);

        $filter = hub_filter::from_values([
            'templateid' => $this->templateid,
            'sort' => 'worker',
            'dir' => 'desc',
            'page' => 2,
            'perpage' => 100,
            'view' => 'matrix',
        ], \context_system::instance());
        $this->assertSame(
            ['worker', 'desc', 2, 100, hub_filter::VIEW_MATRIX],
            [$filter->sort, $filter->dir, $filter->page, $filter->perpage, $filter->view]
        );
        $this->assertSame(hub_filter::needs_attention(), $filter->statuses);
        $this->assertNotContains(status::STATUS_VALID, $filter->statuses);
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

    /**
     * The request names a filter by any of its parameters; statusfilter without statuses means all.
     *
     * @return void
     */
    public function test_the_request(): void {
        $system = \context_system::instance();
        $this->assertFalse(hub_filter::has_request_params());
        $_GET = ['contextid' => $system->id, 'templateid' => $this->templateid];
        $this->assertTrue(hub_filter::has_request_params());
        $this->assertSame(hub_filter::needs_attention(), hub_filter::from_request($system)->statuses);

        $_GET['statusfilter'] = 1;
        $this->assertSame([], hub_filter::from_request($system)->statuses);
        $_GET['statuses'] = [status::STATUS_DUE];
        $this->assertSame([status::STATUS_DUE], hub_filter::from_request($system)->statuses);

        $_GET = ['contextid' => $system->id, 'search' => 'x'];
        $this->assertTrue(hub_filter::has_request_params());
        $this->assertNull(hub_filter::from_request($system));
    }

    /**
     * The stored filter comes back as it was saved, without its page; one whose template is no
     * longer offered comes back as nothing.
     *
     * @return void
     */
    public function test_the_preference_round_trip(): void {
        $system = \context_system::instance();
        $cohort = $this->getDataGenerator()->create_cohort();
        $filter = hub_filter::from_values([
            'templateid' => $this->templateid,
            'cohortid' => $cohort->id,
            'statuses' => [status::STATUS_EXPIRED],
            'search' => 'Ana',
            'suspended' => 1,
            'sort' => 'competency',
            'dir' => 'desc',
            'page' => 3,
            'perpage' => 25,
            'view' => 'matrix',
        ], $system);
        $this->assertNull(hub_filter::from_preference($system));
        $filter->save_preference();

        $stored = json_decode(get_user_preferences(hub_filter::PREFERENCE), true);
        $this->assertSame($filter->to_values(), $stored);
        $this->assertArrayNotHasKey('page', $stored);
        $this->assertSame(hub_filter::VIEW_MATRIX, $stored['view']);
        $this->assertEquals($filter->with(['page' => 0]), hub_filter::from_preference($system));

        $category = $this->getDataGenerator()->create_category();
        $this->assertNull(hub_filter::from_preference(\context_coursecat::instance($category->id)));
        set_user_preference(hub_filter::PREFERENCE, 'not json');
        $this->assertNull(hub_filter::from_preference($system));
    }

    /**
     * The request parameters name the filter back.
     *
     * @return void
     */
    public function test_the_params_name_the_filter_back(): void {
        $system = \context_system::instance();
        $filter = hub_filter::from_values(
            ['templateid' => $this->templateid, 'statuses' => [], 'page' => 2, 'view' => 'matrix'],
            $system
        );
        $_GET = array_map(fn($value) => is_array($value) ? $value : (string) $value, $filter->to_params());
        $this->assertEquals($filter, hub_filter::from_request($system));
    }

    /**
     * Only from_values() builds a filter, and a copy changes the view only, validated again.
     *
     * The private constructor keeps a caller from setting a template or cohort that was never
     * validated; with() refuses those keys at run time.
     *
     * @return void
     */
    public function test_with_changes_only_the_view(): void {
        $this->assertTrue((new \ReflectionMethod(hub_filter::class, '__construct'))->isPrivate());
        $system = \context_system::instance();
        $filter = hub_filter::from_values(['templateid' => $this->templateid], $system);

        $copy = $filter->with([
            'statuses' => [99, status::STATUS_DUE],
            'sort' => 'password',
            'dir' => 'up',
            'page' => -1,
            'perpage' => 7,
            'view' => 'grid',
        ]);
        $defaults = [[status::STATUS_DUE], hub_filter::SORT_EXPIRY, 'asc', 0, hub_filter::DEFAULT_PERPAGE, hub_filter::VIEW_LIST];
        $this->assertSame($defaults, [
            $copy->statuses,
            $copy->sort,
            $copy->dir,
            $copy->page,
            $copy->perpage,
            $copy->view,
        ]);
        // The control: allowed values pass, and what was not named stays.
        $copy = $filter->with(['sort' => 'worker', 'page' => 2, 'view' => hub_filter::VIEW_MATRIX]);
        $this->assertSame(['worker', 2, hub_filter::VIEW_MATRIX, $filter->statuses, $filter->templateid], [
            $copy->sort,
            $copy->page,
            $copy->view,
            $copy->statuses,
            $copy->templateid,
        ]);

        foreach (['templateid', 'cohortid', 'search', 'suspended'] as $key) {
            try {
                $filter->with([$key => 1]);
                $this->fail('A copy changed ' . $key . '.');
            } catch (\coding_exception $e) {
                $this->assertStringContainsString($key, $e->getMessage());
            }
        }
    }
}
