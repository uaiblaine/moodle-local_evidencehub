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
 * Tests for the inspection drawer and its fragment.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_evidencehub\output;

use local_evidencehub\local\audit;
use local_evidencehub\local\meta;
use local_evidencehub\local\status;
use local_evidencehub\local\status_repository;
use local_evidencehub\tests\hub_scenario;
use local_evidencehub\tests\scenario;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversFunction;

/**
 * The drawer of one pair: who may open it, what each viewer reads in it, and which actions it offers.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(pair_drawer::class)]
#[CoversFunction('local_evidencehub_output_fragment_pair')]
final class pair_drawer_test extends \advanced_testcase {
    use hub_scenario;
    use scenario;

    /** @var array Capabilities of a hub viewer who may read every worker's evidence. */
    private const array READER = ['local/evidencehub:view', 'moodle/competency:userevidenceview'];

    /** @var \stdClass The worker. */
    private \stdClass $worker;

    /** @var int The competency. */
    private int $competencyid;

    /** @var int The template requiring it, from which the worker has a plan. */
    private int $templateid;

    #[\Override]
    public static function setUpBeforeClass(): void {
        global $CFG;
        parent::setUpBeforeClass();
        require_once($CFG->dirroot . '/local/evidencehub/lib.php');
    }

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('enabled', 1, 'core_competency');
        $this->setTimezone('UTC', 'UTC');
        $this->mock_clock_with_frozen((new \DateTimeImmutable('2026-06-15 12:00', new \DateTimeZone('UTC')))->getTimestamp());
        [$this->competencyid] = $this->competencies(1);
        $this->templateid = $this->template([$this->competencyid]);
        $this->worker = $this->worker($this->templateid, ['firstname' => 'Ana', 'lastname' => 'Souza']);
    }

    /**
     * An evidence of the worker linked to the competency, with details unless told otherwise.
     *
     * @param array|null $details Columns of the meta row; none at all when null.
     * @param string $name The core evidence's name.
     * @param int|null $userid The owner; the worker when null.
     * @param int|null $competencyid The competency; the test's one when null.
     * @return \stdClass userevidenceid and metaid (null without details).
     */
    private function linked(?array $details, string $name, ?int $userid = null, ?int $competencyid = null): \stdClass {
        $userid ??= (int) $this->worker->id;
        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $ue = $generator->create_user_evidence(['userid' => $userid, 'name' => $name]);
        $generator->create_user_evidence_competency([
            'userevidenceid' => $ue->get('id'),
            'competencyid' => $competencyid ?? $this->competencyid,
        ]);
        $metaid = null;
        if ($details !== null) {
            $metaid = (int) $this->getDataGenerator()->get_plugin_generator('local_evidencehub')->create_meta($details + [
                'userid' => $userid,
                'userevidenceid' => $ue->get('id'),
                'issuer' => 'Issuer of ' . $name,
                'hours' => 8,
                'authcode' => 'CODE-' . $name,
            ])->id;
        }
        return (object) ['userevidenceid' => (int) $ue->get('id'), 'metaid' => $metaid];
    }

    /**
     * Work the pair's status out as the plugin does, as an administrator.
     *
     * @return \stdClass The status row.
     */
    private function recompute(): \stdClass {
        global $DB, $USER;
        $current = $USER;
        $this->setAdminUser();
        status_repository::recompute((int) $this->worker->id, $this->competencyid);
        $this->setUser($current);
        return $DB->get_record(status_repository::TABLE, ['userid' => $this->worker->id, 'competencyid' => $this->competencyid]);
    }

    /**
     * The drawer's context as the current user sees it, history keyed by core evidence id.
     *
     * @param int $templateid The hub's template, 0 for none.
     * @return array
     */
    private function export(int $templateid = 0): array {
        global $PAGE;
        $drawer = new pair_drawer(\context_system::instance(), (int) $this->worker->id, $this->competencyid, $templateid);
        $data = $drawer->export_for_template($PAGE->get_renderer('core'));
        $data['byid'] = array_column($data['history'], null, 'userevidenceid');
        return $data;
    }

    /**
     * The drawer's body rendered from an export.
     *
     * @param array $data The export.
     * @return string
     */
    private function render(array $data): string {
        global $PAGE;
        return $PAGE->get_renderer('core')->render_from_template('local_evidencehub/pair_drawer', $data);
    }

    /**
     * The fragment's HTML for the current user.
     *
     * @param \context $context The hub's context.
     * @param array $args More arguments.
     * @return string
     */
    private function fragment(\context $context, array $args = []): string {
        return local_evidencehub_output_fragment_pair($args + [
            'context' => $context,
            'userid' => (string) $this->worker->id,
            'competencyid' => (string) $this->competencyid,
        ]);
    }

    /**
     * The drawer opens on the site or in a category where the viewer holds view; never elsewhere.
     *
     * @return void
     */
    public function test_the_fragment_checks_the_context_and_view(): void {
        $this->linked(['verifystatus' => meta::VERIFY_VERIFIED], 'Recycling 2025');
        $this->recompute();
        $this->setUser($this->viewer(self::READER));
        // Control: allowed on the site.
        $this->assertStringContainsString('data-region="local-evidencehub-pair"', $this->fragment(\context_system::instance()));

        $course = $this->getDataGenerator()->create_course();
        try {
            $this->fragment(\context_course::instance($course->id));
            $this->fail('The drawer opened in a course context.');
        } catch (\moodle_exception $e) {
            $this->assertSame('errorhubcontext', $e->errorcode);
        }

        $this->setUser($this->viewer(['moodle/competency:userevidenceview']));
        $this->expectException(\required_capability_exception::class);
        $this->fragment(\context_system::instance());
    }

    /**
     * A viewer of the hub who may not read the worker's evidence never opens their drawer.
     *
     * @return void
     */
    public function test_the_fragment_needs_read_access_to_the_worker(): void {
        $this->setUser($this->viewer(self::READER));
        $this->assertNotSame('', $this->fragment(\context_system::instance()));

        $this->setUser($this->viewer(['local/evidencehub:view']));
        try {
            $this->fragment(\context_system::instance());
            $this->fail('The drawer opened without the right to read the worker\'s evidence.');
        } catch (\required_capability_exception $e) {
            $this->assertStringContainsString(get_capability_string('moodle/competency:userevidenceview'), $e->getMessage());
        }
    }

    /**
     * A competency that does not exist is refused by the fragment itself.
     *
     * @return void
     */
    public function test_the_fragment_refuses_a_missing_competency(): void {
        $this->setUser($this->viewer(self::READER));
        try {
            $this->fragment(\context_system::instance(), ['competencyid' => (string) ($this->competencyid + 1000)]);
            $this->fail('The drawer opened on a missing competency.');
        } catch (\moodle_exception $e) {
            // The callback's own refusal, not a failing read further down.
            $this->assertNotInstanceOf(\dml_exception::class, $e);
            $this->assertSame('invalidrecord', $e->errorcode);
        }
    }

    /**
     * The fragment prints a competency's name, ID number and policy only to who may read it in its
     * framework's context, as core's read_competency() decides.
     *
     * @return void
     */
    public function test_the_fragment_needs_read_access_to_the_competency(): void {
        $category = \context_coursecat::instance($this->getDataGenerator()->create_category()->id);
        [$hidden] = $this->competencies(1, $category);
        $viewer = $this->viewer(self::READER);
        $this->setUser($viewer);
        // Control: the authenticated user's competencyview reaches the category's framework.
        $this->assertNotSame('', $this->fragment(\context_system::instance(), ['competencyid' => (string) $hidden]));

        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('moodle/competency:competencyview', CAP_PROHIBIT, $roleid, $category->id, true);
        role_assign($roleid, $viewer->id, $category->id);
        accesslib_clear_all_caches_for_unit_testing();
        try {
            $this->fragment(\context_system::instance(), ['competencyid' => (string) $hidden]);
            $this->fail('The drawer showed a competency the viewer may not read.');
        } catch (\required_capability_exception $e) {
            $this->assertStringContainsString(get_capability_string('moodle/competency:competencyview'), $e->getMessage());
        }
    }

    /**
     * Opened from a category, the drawer accepts only a template the hub offers there.
     *
     * @return void
     */
    public function test_the_fragment_refuses_a_template_outside_the_context(): void {
        $generator = $this->getDataGenerator();
        $here = \context_coursecat::instance($generator->create_category()->id);
        $there = \context_coursecat::instance($generator->create_category()->id);
        $inside = $this->template([$this->competencyid], $here);
        $outside = $this->template([$this->competencyid], $there);
        $viewer = $this->viewer(['local/evidencehub:view', 'moodle/competency:templateview'], $here);
        $this->grant($viewer, ['moodle/competency:userevidenceview']);
        $this->setUser($viewer);

        // Control: a template of the category is accepted.
        $this->assertNotSame('', $this->fragment($here, ['templateid' => (string) $inside]));
        try {
            $this->fragment($here, ['templateid' => (string) $outside]);
            $this->fail('The drawer accepted a template of another category.');
        } catch (\moodle_exception $e) {
            $this->assertSame('errorhubtemplate', $e->errorcode);
        }
    }

    /**
     * The history lists every linked evidence, newest issue first, ties by the highest id, those
     * without details last; the evidence in force is the status row's deciding one.
     *
     * @return void
     */
    public function test_the_history_order_and_its_ties(): void {
        $old = $this->linked(['issuedate' => 20240101, 'expirydate' => 20250101], 'Old');
        $tiefirst = $this->linked(['issuedate' => 20250101, 'expirydate' => 20290101], 'Tie created first');
        $tiesecond = $this->linked(['issuedate' => 20250101, 'expirydate' => 20280101], 'Tie created second');
        $bare = $this->linked(null, 'Bare');
        $row = $this->recompute();
        $this->setAdminUser();

        $data = $this->export();
        $this->assertSame(
            [$tiesecond->userevidenceid, $tiefirst->userevidenceid, $old->userevidenceid, $bare->userevidenceid],
            array_column($data['history'], 'userevidenceid')
        );
        $this->assertTrue($data['hasdeciding']);
        $this->assertSame((int) $row->metaid, $data['deciding'][0]['metaid']);
        $this->assertSame($tiefirst->metaid, $data['deciding'][0]['metaid']);
    }

    /**
     * The gap shows on the older of two neighbours that do not touch, in days.
     *
     * @return void
     */
    public function test_the_gap_between_intervals(): void {
        $first = $this->linked(['issuedate' => 20200101, 'expirydate' => 20211231], 'First');
        $second = $this->linked(['issuedate' => 20220101, 'expirydate' => 20231231], 'Second');
        $third = $this->linked(['issuedate' => 20240301, 'expirydate' => 20291231], 'Third');
        $this->setAdminUser();

        $byid = $this->export()['byid'];
        $this->assertSame('', $byid[$third->userevidenceid]['gap']);
        // From 2023-12-31 to 2024-03-01 the days 2024-01-01 to 2024-02-29 are covered by neither.
        $this->assertSame(get_string('drawergap', 'local_evidencehub', 60), $byid[$second->userevidenceid]['gap']);
        $this->assertSame('', $byid[$first->userevidenceid]['gap'], 'Touching intervals showed a gap.');
    }

    /**
     * Restricted items come after every readable one, by id alone, so their place bounds no issue day; the readable
     * ones keep newest issue first. The control is the same viewer with viewsensitive, for whom every item takes its
     * place by issue day.
     *
     * @return void
     */
    public function test_restricted_items_come_last_by_id(): void {
        $newestsensitive = $this->linked(
            ['issuedate' => 20260101, 'expirydate' => 20270101, 'typeid' => $this->type_id('aso')],
            'Newest health certificate'
        );
        $plainnew = $this->linked(['issuedate' => 20250101, 'expirydate' => 20290101], 'Plain new');
        $oldestsensitive = $this->linked(
            ['issuedate' => 20200101, 'expirydate' => 20210101, 'typeid' => $this->type_id('aso')],
            'Oldest health certificate'
        );
        $plainold = $this->linked(['issuedate' => 20230101, 'expirydate' => 20240101], 'Plain old');
        $viewer = $this->viewer(self::READER);
        $this->setUser($viewer);

        $this->assertSame(
            [
                $plainnew->userevidenceid,
                $plainold->userevidenceid,
                $oldestsensitive->userevidenceid,
                $newestsensitive->userevidenceid,
            ],
            array_column($this->export()['history'], 'userevidenceid')
        );

        $this->grant($viewer, ['local/evidencehub:viewsensitive']);
        $this->assertSame(
            [
                $newestsensitive->userevidenceid,
                $plainnew->userevidenceid,
                $plainold->userevidenceid,
                $oldestsensitive->userevidenceid,
            ],
            array_column($this->export()['history'], 'userevidenceid')
        );
    }

    /**
     * No gap is worked out while the timeline holds a restricted item: neither on it nor between readable items,
     * which now neighbour each other across it although it may cover the days between them.
     *
     * @return void
     */
    public function test_no_gap_next_to_a_restricted_item(): void {
        $first = $this->linked(['issuedate' => 20200101, 'expirydate' => 20211130], 'First');
        $sensitive = $this->linked(
            ['issuedate' => 20220101, 'expirydate' => 20231231, 'typeid' => $this->type_id('aso')],
            'Health certificate'
        );
        $this->linked(['issuedate' => 20240301, 'expirydate' => 20291231], 'Third');
        $viewer = $this->viewer(self::READER);
        $this->setUser($viewer);

        $byid = $this->export()['byid'];
        $this->assertTrue($byid[$sensitive->userevidenceid]['restricted']);
        $this->assertSame('', $byid[$sensitive->userevidenceid]['gap']);
        $this->assertSame('', $byid[$first->userevidenceid]['gap']);

        // Control: with viewsensitive both gaps show.
        $this->grant($viewer, ['local/evidencehub:viewsensitive']);
        $byid = $this->export()['byid'];
        $this->assertSame(get_string('drawergap', 'local_evidencehub', 60), $byid[$sensitive->userevidenceid]['gap']);
        $this->assertSame(get_string('drawergap', 'local_evidencehub', 31), $byid[$first->userevidenceid]['gap']);
    }

    /**
     * Each item reads its own state; the one in force is the status row's deciding evidence.
     *
     * @return void
     */
    public function test_each_state(): void {
        // Unverified evidence counts under the default policy: the pending one with the latest expiry decides.
        $inforce = $this->linked(['issuedate' => 20250101, 'expirydate' => 20290101], 'In force');
        $awaiting = $this->linked(['issuedate' => 20250101, 'expirydate' => 20270101], 'Awaiting');
        $rejected = $this->linked(
            ['issuedate' => 20250101, 'expirydate' => 20300101, 'verifystatus' => meta::VERIFY_REJECTED],
            'Rejected'
        );
        $expired = $this->linked(
            ['issuedate' => 20200101, 'expirydate' => 20210101, 'verifystatus' => meta::VERIFY_VERIFIED],
            'Old'
        );
        $future = $this->linked(['issuedate' => 20270101, 'expirydate' => 20300101], 'Future');
        $bare = $this->linked(null, 'Bare');
        $row = $this->recompute();
        $this->assertSame($inforce->metaid, (int) $row->metaid);
        $this->setAdminUser();

        $byid = $this->export()['byid'];
        $expected = [
            $inforce->userevidenceid => pair_drawer::STATE_INFORCE,
            $awaiting->userevidenceid => pair_drawer::STATE_AWAITING,
            $rejected->userevidenceid => pair_drawer::STATE_REJECTED,
            $expired->userevidenceid => pair_drawer::STATE_EXPIRED,
            $future->userevidenceid => pair_drawer::STATE_NOTYETVALID,
            $bare->userevidenceid => pair_drawer::STATE_MISSING,
        ];
        foreach ($expected as $id => $state) {
            $this->assertSame($state, $byid[$id]['state'], 'State of ' . $byid[$id]['name']);
            $this->assertSame(pair_drawer::state_label($state), $byid[$id]['statelabel']);
        }
        $this->assertSame(get_string('state_inforce', 'local_evidencehub'), pair_drawer::state_label(pair_drawer::STATE_INFORCE));
        $this->assertSame(get_string('state_missing', 'local_evidencehub'), pair_drawer::state_label(pair_drawer::STATE_MISSING));
    }

    /**
     * A sensitive deciding evidence is restricted per item for a reader without viewsensitive: the pill,
     * its days left, its details, files and code, its audit rows' actor and note, and the next change.
     * With viewsensitive, and for the worker, everything shows.
     *
     * @return void
     */
    public function test_the_sensitive_rule_per_viewer(): void {
        $verifier = $this->user_with();
        $health = $this->linked(
            ['issuedate' => 20250101, 'expirydate' => 20290101, 'typeid' => $this->type_id('aso'), 'verifystatus' => 1],
            'Health certificate'
        );
        $plain = $this->linked(['issuedate' => 20240101, 'expirydate' => 20250101], 'Training');
        $generator = $this->getDataGenerator()->get_plugin_generator('local_evidencehub');
        $generator->create_audit((object) [
            'id' => $health->metaid,
            'userevidenceid' => $health->userevidenceid,
            'userid' => $this->worker->id,
        ], (int) $verifier->id, audit::ACTION_VERIFIED);
        $this->recompute();
        $reader = $this->viewer(array_merge(self::READER, ['local/evidencehub:verify']));
        $this->setUser($reader);

        $data = $this->export();
        $this->assertSame(status::EFFECTIVE_RESTRICTED, $data['status']['code']);
        $this->assertSame('', $data['status']['daysleft']);
        $deciding = $data['deciding'][0];
        $this->assertTrue($deciding['restricted']);
        $this->assertArrayNotHasKey('issuedate', $deciding);
        $this->assertArrayNotHasKey('issuer', $deciding);
        $this->assertSame([], $deciding['files']);
        $this->assertSame('', $deciding['authcode'] ?? '');
        $item = $data['byid'][$health->userevidenceid];
        $this->assertArrayNotHasKey('interval', $item);
        $this->assertArrayNotHasKey('state', $item);
        $this->assertFalse($item['canverify']);
        $this->assertSame([['', '']], array_map(fn(array $row): array => [$row['actor'], $row['note']], $data['audit']));
        $this->assertStringNotContainsString('2028', $data['footer']);
        // One neutral heading: whether the restricted evidence covers today is not told by it either.
        $this->assertFalse($data['decidinginforce']);
        $html = $this->render($data);
        $this->assertStringContainsString(get_string('drawerdeciding', 'local_evidencehub'), $html);
        $this->assertStringNotContainsString(get_string('drawerinforce', 'local_evidencehub'), $html);
        // Control on the same viewer: the plain item shows its details.
        $this->assertSame('2024-01-01 → 2025-01-01', $data['byid'][$plain->userevidenceid]['interval']);

        $this->grant($reader, ['local/evidencehub:viewsensitive']);
        $data = $this->export();
        $this->assertSame(status::STATUS_VALID, $data['status']['code']);
        $this->assertSame(get_string('drawerdaysleft', 'local_evidencehub', 931), $data['status']['daysleft']);
        $this->assertSame('2025-01-01', $data['deciding'][0]['issuedate']);
        $this->assertSame('CODE-Health certificate', $data['deciding'][0]['authcode']);
        $this->assertSame([[fullname($verifier), 'Note by ' . $verifier->id]], array_map(
            fn(array $row): array => [$row['actor'], $row['note']],
            $data['audit']
        ));
        // The next change: 60 days before the expiry of 2029-01-01.
        $this->assertStringContainsString('2028-11-02', $data['footer']);
        $this->assertTrue($data['decidinginforce']);
        $this->assertStringContainsString(get_string('drawerinforce', 'local_evidencehub'), $this->render($data));

        $this->setUser($this->worker);
        $data = $this->export();
        $this->assertSame(status::STATUS_VALID, $data['status']['code']);
        $this->assertFalse($data['byid'][$health->userevidenceid]['restricted']);
        $this->assertTrue($data['decidinginforce']);
    }

    /**
     * Audit rows come newest first; a note shows to the worker and to verifiers, never to a reader.
     *
     * @return void
     */
    public function test_audit_notes_follow_the_verify_rule(): void {
        $item = $this->linked(['issuedate' => 20250101, 'expirydate' => 20290101], 'Training');
        $meta = (object) ['id' => $item->metaid, 'userevidenceid' => $item->userevidenceid, 'userid' => $this->worker->id];
        $verifier = $this->viewer(array_merge(self::READER, ['local/evidencehub:verify']));
        $generator = $this->getDataGenerator()->get_plugin_generator('local_evidencehub');
        $generator->create_audit($meta, (int) $this->worker->id, audit::ACTION_CREATED);
        $generator->create_audit($meta, (int) $verifier->id, audit::ACTION_REJECTED);

        $reader = $this->viewer(self::READER);
        $this->setUser($reader);
        $audit = $this->export()['audit'];
        $actions = [audit::label(audit::ACTION_REJECTED), audit::label(audit::ACTION_CREATED)];
        $this->assertSame($actions, array_column($audit, 'action'));
        $this->assertSame(['', ''], array_column($audit, 'note'));
        $this->assertSame(fullname($verifier), $audit[0]['actor']);

        $this->setUser($verifier);
        $this->assertSame('Note by ' . $verifier->id, $this->export()['audit'][0]['note']);

        $this->setUser($this->worker);
        $this->assertSame('Note by ' . $verifier->id, $this->export()['audit'][0]['note']);
    }

    /**
     * A restricted item's audit rows keep their date and replace the action with one generic label, since the action
     * would give back the verification state the card hides. The same reader with viewsensitive sees the action, and
     * a plain item's row shows its action all along (the controls).
     *
     * @return void
     */
    public function test_a_restricted_audit_row_names_no_action(): void {
        $health = $this->linked(
            ['issuedate' => 20250101, 'expirydate' => 20290101, 'typeid' => $this->type_id('aso')],
            'Health certificate'
        );
        $plain = $this->linked(['issuedate' => 20240101, 'expirydate' => 20250101], 'Training');
        $generator = $this->getDataGenerator()->get_plugin_generator('local_evidencehub');
        foreach ([$health, $plain] as $item) {
            $generator->create_audit((object) [
                'id' => $item->metaid,
                'userevidenceid' => $item->userevidenceid,
                'userid' => $this->worker->id,
            ], (int) $this->worker->id, audit::ACTION_VERIFIED);
        }
        $reader = $this->viewer(self::READER);
        $this->setUser($reader);
        $verified = audit::label(audit::ACTION_VERIFIED);
        $generic = get_string('audit_restricted', 'local_evidencehub');
        // By evidence name: rows of the same second come by id, which says nothing here.
        $actions = function (array $audit): array {
            $byname = array_combine(array_column($audit, 'evidence'), array_column($audit, 'action'));
            ksort($byname);
            return $byname;
        };

        $audit = $this->export()['audit'];
        $this->assertSame(['Health certificate' => $generic, 'Training' => $verified], $actions($audit));
        $this->assertNotSame('', $audit[0]['date']);
        $this->assertNotSame('', $audit[1]['date']);

        $this->grant($reader, ['local/evidencehub:viewsensitive']);
        $this->assertSame(
            ['Health certificate' => $verified, 'Training' => $verified],
            $actions($this->export()['audit'])
        );
    }

    /**
     * Core's proficiency shows only to who may read the worker's user competencies.
     *
     * @return void
     */
    public function test_proficiency_shows_only_with_read_rights(): void {
        global $DB;
        $this->linked(['issuedate' => 20250101, 'expirydate' => 20290101], 'Training');
        $this->recompute();
        $reader = $this->viewer(self::READER);
        $this->setUser($reader);
        $data = $this->export();
        $this->assertFalse($data['hasproficiency']);
        $this->assertArrayNotHasKey('proficiency', $data);

        $this->grant($reader, ['moodle/competency:usercompetencyview']);
        $this->assertSame(get_string('proficiency_none', 'local_evidencehub'), $this->export()['proficiency']['label']);
        $pair = ['userid' => $this->worker->id, 'competencyid' => $this->competencyid];
        $DB->set_field('competency_usercomp', 'proficiency', 1, $pair);
        $this->assertSame(get_string('proficiency_yes', 'local_evidencehub'), $this->export()['proficiency']['label']);
        $DB->set_field('competency_usercomp', 'proficiency', 0, $pair);
        $this->assertSame(get_string('proficiency_no', 'local_evidencehub'), $this->export()['proficiency']['label']);
    }

    /**
     * Each action shows only when its rule passes: verify to verifiers of others' evidence, edit and
     * recycling to managers, never on a restricted item; the code only to managers and verifiers.
     *
     * @return void
     */
    public function test_each_action_follows_its_rule(): void {
        $plain = $this->linked(['issuedate' => 20250101, 'expirydate' => 20290101], 'Training');
        $health = $this->linked(['issuedate' => 20240101, 'expirydate' => 20250101, 'typeid' => $this->type_id('aso')], 'Health');
        $this->recompute();

        $this->setUser($this->viewer(self::READER));
        $data = $this->export();
        $this->assertFalse($data['canrecycle']);
        $this->assertFalse($data['byid'][$plain->userevidenceid]['canverify']);
        $this->assertFalse($data['byid'][$plain->userevidenceid]['canedit']);
        $this->assertSame('', $data['deciding'][0]['authcode']);

        $this->setUser($this->viewer(array_merge(self::READER, ['local/evidencehub:verify'])));
        $data = $this->export();
        $this->assertTrue($data['byid'][$plain->userevidenceid]['canverify']);
        $this->assertFalse($data['byid'][$health->userevidenceid]['canverify']);
        $this->assertFalse($data['byid'][$plain->userevidenceid]['canedit']);
        $this->assertSame('CODE-Training', $data['deciding'][0]['authcode']);

        $this->setUser($this->viewer(array_merge(self::READER, ['moodle/competency:userevidencemanage'])));
        $data = $this->export();
        $this->assertTrue($data['canrecycle']);
        $this->assertSame($plain->metaid, $data['recyclefrom']);
        $this->assertTrue($data['byid'][$plain->userevidenceid]['canedit']);
        $this->assertFalse($data['byid'][$health->userevidenceid]['canedit']);
        $this->assertFalse($data['byid'][$plain->userevidenceid]['canverify']);

        // The worker never verifies their own evidence, whatever they hold.
        $this->grant($this->worker, ['local/evidencehub:verify']);
        $this->setUser($this->worker);
        $data = $this->export();
        $this->assertFalse($data['byid'][$plain->userevidenceid]['canverify']);
        $this->assertTrue($data['byid'][$plain->userevidenceid]['canedit']);
    }

    /**
     * The learning plan link needs a template requiring the competency, the worker's plan from it, and
     * the viewer's right to read that plan.
     *
     * @return void
     */
    public function test_the_plan_link_needs_a_template_and_a_plan(): void {
        global $DB;
        $viewer = $this->viewer(self::READER);
        $this->setUser($viewer);
        // A viewer who may not read the worker's plans gets no link to one.
        $this->assertFalse($this->export($this->templateid)['hasplan']);

        $this->grant($viewer, ['moodle/competency:planview']);
        $this->assertFalse($this->export()['hasplan']);
        $data = $this->export($this->templateid);
        $this->assertTrue($data['hasplan']);
        $planid = $DB->get_field('competency_plan', 'id', ['userid' => $this->worker->id, 'templateid' => $this->templateid]);
        parse_str(parse_url($data['planurl'], PHP_URL_QUERY), $query);
        $this->assertSame(
            ['userid' => (string) $this->worker->id, 'competencyid' => (string) $this->competencyid, 'planid' => (string) $planid],
            $query
        );

        $other = $this->template([$this->competencyid]);
        $this->assertFalse($this->export($other)['hasplan']);

        // A template that does not require the competency gets no link, though the worker has a plan from it.
        [$unrelated] = $this->competencies(1);
        $without = $this->template([$unrelated]);
        $this->getDataGenerator()->get_plugin_generator('local_evidencehub')->create_template_plan([
            'userid' => $this->worker->id,
            'templateid' => $without,
        ]);
        $this->assertFalse($this->export($without)['hasplan']);
    }

    /**
     * Names leave the export plain and render escaped once.
     *
     * @return void
     */
    public function test_names_are_escaped_once(): void {
        global $PAGE;
        $this->linked(['issuedate' => 20250101, 'expirydate' => 20290101, 'issuer' => 'Smith & Sons'], 'Fish & chips');
        $this->setAdminUser();
        $data = $this->export();
        $this->assertSame('Fish & chips', $data['history'][0]['name']);
        $html = $PAGE->get_renderer('core')->render_from_template('local_evidencehub/pair_drawer', $data);
        $this->assertStringContainsString('Fish &amp; chips', $html);
        $this->assertStringContainsString('Smith &amp; Sons', $html);
        $this->assertStringNotContainsString('&amp;amp;', $html);
    }
}
