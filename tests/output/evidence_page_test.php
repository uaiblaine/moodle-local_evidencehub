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
 * Tests for the worker's evidence page.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_evidencehub\output;

use local_evidencehub\local\details;
use local_evidencehub\tests\scenario;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * What each viewer sees of a worker's evidence, and which actions are offered.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(evidence_page::class)]
final class evidence_page_test extends \advanced_testcase {
    use scenario;

    /** @var \stdClass The worker. */
    private \stdClass $worker;

    /** @var int The core evidence of a sensitive type. */
    private int $healthid;

    /** @var int The core evidence without details. */
    private int $bareid;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('enabled', 1, 'core_competency');
        $this->worker = $this->user_with();
        $health = $this->evidence_of((int) $this->worker->id);
        $this->healthid = (int) $health->get('id');
        $this->bareid = (int) $this->evidence_of((int) $this->worker->id)->get('id');
        $this->setUser($this->worker);
        $meta = details::save($health, $this->details_data([
            'typeid' => $this->type_id('aso'),
            'authcode' => 'CRM-123',
            'expirydate' => 20270115,
        ]));
        $this->setUser($this->user_with([
            'local/evidencehub:verify',
            'local/evidencehub:viewsensitive',
            'moodle/competency:userevidenceview',
        ]));
        details::reject((int) $meta->id, 'Unreadable.');
    }

    /**
     * The page's context as the current user sees it, items keyed by core evidence id.
     *
     * @return array
     */
    private function export(): array {
        global $PAGE;
        $context = (new evidence_page((int) $this->worker->id))->export_for_template($PAGE->get_renderer('core'));
        $context['items'] = array_column($context['items'], null, 'id');
        return $context;
    }

    /**
     * The worker sees everything of their own evidence and may edit and complete, never verify.
     *
     * @return void
     */
    public function test_the_owner_sees_their_details_and_actions(): void {
        $this->grant($this->worker, ['local/evidencehub:verify']);
        $this->setUser($this->worker);
        $export = $this->export();
        $health = $export['items'][$this->healthid];
        $this->assertTrue($export['canadd']);
        $this->assertFalse($health['restricted']);
        $this->assertSame('2026-01-15', $health['issuedate']);
        $this->assertSame('2027-01-15', $health['expirydate']);
        $this->assertSame('CRM-123', $health['authcode']);
        $this->assertSame('Unreadable.', $health['note']);
        $this->assertStringContainsString('text-white', $health['badgeclass']);
        $this->assertTrue($health['canedit']);
        $this->assertFalse($health['canverify'], 'The owner was offered to verify their own evidence.');
        $this->assertFalse($export['items'][$this->bareid]['hasdetails']);
        $this->assertTrue($export['items'][$this->bareid]['cancomplete']);
    }

    /**
     * A reader without viewsensitive sees the type and "restricted"; with it, the details.
     *
     * @return void
     */
    public function test_sensitive_details_need_viewsensitive(): void {
        global $OUTPUT;
        $reader = $this->user_with(['moodle/competency:userevidenceview']);
        $this->setUser($reader);
        $health = $this->export()['items'][$this->healthid];
        $this->assertTrue($health['restricted']);
        $this->assertNotEmpty($health['typename']);
        foreach (['issuedate', 'expirydate', 'issuer', 'hours', 'authcode', 'note'] as $hidden) {
            $this->assertArrayNotHasKey($hidden, $health);
        }
        $page = new evidence_page((int) $this->worker->id);
        $html = $OUTPUT->render_from_template('local_evidencehub/evidence_page', $page->export_for_template($OUTPUT));
        $this->assertStringContainsString(get_string('detailsrestricted', 'local_evidencehub'), $html);
        $this->assertStringNotContainsString('2027-01-15', $html);

        $this->grant($reader, ['local/evidencehub:viewsensitive']);
        $health = $this->export()['items'][$this->healthid];
        $this->assertFalse($health['restricted']);
        $this->assertSame('2026-01-15', $health['issuedate']);
        // A reader who neither manages nor verifies never sees the code nor the verifier's note.
        $this->assertArrayNotHasKey('authcode', $health);
        $this->assertArrayNotHasKey('note', $health);
    }

    /**
     * A reader is offered no action; a verifier is offered to verify, with the code and the note.
     *
     * @return void
     */
    public function test_the_verify_action_follows_the_verify_rule(): void {
        $reader = $this->user_with(['moodle/competency:userevidenceview', 'local/evidencehub:viewsensitive']);
        $this->setUser($reader);
        $export = $this->export();
        $health = $export['items'][$this->healthid];
        $this->assertFalse($export['canadd']);
        $this->assertFalse($health['canverify']);
        $this->assertFalse($health['canedit']);
        $this->assertFalse($export['items'][$this->bareid]['cancomplete']);

        $this->grant($reader, ['local/evidencehub:verify']);
        $health = $this->export()['items'][$this->healthid];
        $this->assertTrue($health['canverify']);
        $this->assertSame('CRM-123', $health['authcode']);
        $this->assertSame('Unreadable.', $health['note']);
    }

    /**
     * A restricted row offers neither edit nor verification; the same viewer with viewsensitive gets
     * both, and a row of another type offers them all along.
     *
     * @return void
     */
    public function test_a_restricted_row_offers_no_action(): void {
        $manager = $this->user_with([
            'moodle/competency:userevidencemanage',
            'moodle/competency:userevidenceview',
            'local/evidencehub:verify',
        ]);
        $plainue = $this->evidence_of((int) $this->worker->id);
        $this->setUser($manager);
        $plain = details::save($plainue, $this->details_data());

        $items = $this->export()['items'];
        $health = $items[$this->healthid];
        $this->assertTrue($health['restricted']);
        $this->assertFalse($health['canedit']);
        $this->assertFalse($health['cancomplete']);
        $this->assertFalse($health['canverify']);
        $this->assertTrue($items[(int) $plain->userevidenceid]['canedit']);
        $this->assertTrue($items[(int) $plain->userevidenceid]['canverify']);

        $this->grant($manager, ['local/evidencehub:viewsensitive']);
        $health = $this->export()['items'][$this->healthid];
        $this->assertFalse($health['restricted']);
        $this->assertTrue($health['canedit']);
        $this->assertTrue($health['canverify']);
    }
}
