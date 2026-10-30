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

use local_evidencehub\event\hub_downloaded;
use local_evidencehub\tests\hub_scenario;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The rows the download hands to the dataformat writer: the grid's columns and restriction, every
 * pair of the filter or only the posted ones of its set.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(hub_download::class)]
#[CoversClass(hub_downloaded::class)]
final class hub_download_test extends \advanced_testcase {
    use hub_scenario;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('enabled', 1, 'core_competency');
        $this->setAdminUser();
    }

    /**
     * The download of a filter, for the current user.
     *
     * @return hub_download
     */
    private function download(): hub_download {
        $system = \context_system::instance();
        return new hub_download($system, new hub_query($system, hub_scope::for_user()));
    }

    /**
     * Every pair, in the grid's order, with the columns' keys; a restricted pair has its status only.
     *
     * @return void
     */
    public function test_the_rows_follow_the_grid_and_its_restriction(): void {
        [$c1, $c2] = $this->competencies(2);
        $templateid = $this->template([$c1, $c2]);
        $worker = $this->worker($templateid, ['firstname' => 'Ana', 'lastname' => 'Souza', 'email' => 'ana@example.com']);
        $this->status_row((int) $worker->id, $c1, status::STATUS_EXPIRED, 20250101, 'aso');
        $this->status_row((int) $worker->id, $c2, status::STATUS_DUE, 20261201, 'nrcertificate');
        $filter = $this->filter($templateid);

        $this->setUser($this->viewer(['local/evidencehub:view', 'moodle/competency:userevidenceview']));
        $download = $this->download();
        $rows = iterator_to_array($this->download()->rows($filter), false);
        $this->assertSame(array_keys($download->columns()), array_keys($rows[0]));
        // Restricted sorts first and keeps its status only.
        $this->assertSame(['Ana Souza', 'C1', get_string('validityrestricted', 'local_evidencehub'), '', '', '', ''], [
            $rows[0]['worker'],
            $rows[0]['competency'],
            $rows[0]['status'],
            $rows[0]['expiry'],
            $rows[0]['evidencecount'],
            $rows[0]['type'],
            $rows[0]['issuer'],
        ]);
        $this->assertSame(status::label(status::STATUS_DUE), $rows[1]['status']);
        $this->assertSame(1, $rows[1]['evidencecount']);
        $this->assertSame('2026-12-01', $rows[1]['expiry']);
        $this->assertSame('Issuer nrcertificate', $rows[1]['issuer']);
        $this->assertSame(get_string('type_nrcertificate', 'local_evidencehub'), $rows[1]['type']);

        // The control: the admin reads the sensitive pair in full, with the identity fields.
        $this->setAdminUser();
        $rows = iterator_to_array($this->download()->rows($filter), false);
        $this->assertSame(status::label(status::STATUS_EXPIRED), $rows[0]['status']);
        $this->assertSame('2025-01-01', $rows[0]['expiry']);
        $this->assertSame('Issuer aso', $rows[0]['issuer']);
        $this->assertSame(1, $rows[0]['evidencecount']);
        $this->assertSame('ana@example.com', $rows[0]['email']);
    }

    /**
     * Posted pairs narrow the rows to those of the filter's set; a posted pair outside it is dropped.
     *
     * @return void
     */
    public function test_posted_pairs_narrow_the_rows(): void {
        [$c1, $c2] = $this->competencies(2);
        $templateid = $this->template([$c1]);
        $first = $this->worker($templateid);
        $second = $this->worker($templateid);
        $outsider = $this->getDataGenerator()->create_user();
        $filter = $this->filter($templateid);
        $download = $this->download();
        $kpis = hub_kpis::get($filter, hub_scope::for_user(), \context_system::instance());

        $this->assertSame(2, iterator_count($download->rows($filter)));
        $this->assertSame(2, $download->count($filter, null, $kpis));
        $pairs = hub_download::parse_pairs([$this->pair($second, $c1), $this->pair($first, $c2), $this->pair($outsider, $c1)]);
        $rows = iterator_to_array($download->rows($filter, $pairs), false);
        $this->assertCount(1, $rows);
        $this->assertSame(fullname($second), $rows[0]['worker']);
        $this->assertSame(1, $download->count($filter, $pairs, $kpis));
    }

    /**
     * A posted value of another shape, or too many, is refused.
     *
     * @return void
     */
    public function test_malformed_pairs_are_refused(): void {
        $this->assertSame(['3:4' => true], hub_download::parse_pairs(['3:4', '3:4']));
        foreach (['3', '3:4:5', 'a:b', '0:4', '-1:4', '3:4 OR 1=1'] as $value) {
            try {
                hub_download::parse_pairs([$value]);
                $this->fail($value . ' was accepted.');
            } catch (\moodle_exception $e) {
                $this->assertSame('errorhubpairs', $e->errorcode);
            }
        }
        $this->expectException(\moodle_exception::class);
        hub_download::parse_pairs(array_fill(0, hub_download::MAX_PAIRS + 1, '1:1'));
    }

    /**
     * The event names the template and the row count, in the page's context.
     *
     * @return void
     */
    public function test_the_event(): void {
        $category = $this->getDataGenerator()->create_category();
        $context = \context_coursecat::instance($category->id);
        $sink = $this->redirectEvents();
        hub_downloaded::create_from_download($context, 12, 34)->trigger();
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(hub_downloaded::class, $events[0]);
        $this->assertSame($context->id, $events[0]->contextid);
        $this->assertSame(['templateid' => 12, 'rows' => 34], $events[0]->other);
        $this->assertStringContainsString('contextid=' . $context->id, $events[0]->get_url()->out(false));
    }
}
