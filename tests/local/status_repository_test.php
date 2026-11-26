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
 * Tests for the status repository.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_evidencehub\local;

use core_competency\evidence;
use core_competency\user_competency;
use local_evidencehub\event\validity_status_changed;
use local_evidencehub\task\recompute_scope;
use local_evidencehub\tests\held_lock_factory;
use local_evidencehub\tests\scenario;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Rows written, replaced and removed; today in the site's timezone; the event and core's log entry
 * only on a change.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(status_repository::class)]
#[CoversClass(validity_status_changed::class)]
final class status_repository_test extends \advanced_testcase {
    use scenario;

    /** @var \stdClass The worker. */
    private \stdClass $worker;

    /** @var \core_competency\competency_framework The framework. */
    private $framework;

    /** @var \core_competency\competency The competency. */
    private $competency;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('enabled', 1, 'core_competency');
        $this->setTimezone('UTC', 'UTC');
        $this->worker = $this->user_with();
        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $this->framework = $generator->create_framework();
        $this->competency = $generator->create_competency(['competencyframeworkid' => $this->framework->get('id')]);
        $this->freeze('2026-06-15 12:00');
        $this->setAdminUser();
    }

    /**
     * Freeze the clock at a UTC time.
     *
     * @param string $utc The time, read in UTC.
     * @return int The timestamp.
     */
    private function freeze(string $utc): int {
        $time = (new \DateTimeImmutable($utc, new \DateTimeZone('UTC')))->getTimestamp();
        $this->mock_clock_with_frozen($time);
        return $time;
    }

    /**
     * An evidence of the worker linked to the competency, with details, written without events.
     *
     * @param array|null $meta Columns of the meta row; none at all when null.
     * @param int|null $competencyid The competency; the test's one when null.
     * @return \stdClass The meta row, or a row with only userevidenceid when there are no details.
     */
    private function linked(?array $meta = [], ?int $competencyid = null): \stdClass {
        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $ue = $generator->create_user_evidence(['userid' => $this->worker->id]);
        $generator->create_user_evidence_competency([
            'userevidenceid' => $ue->get('id'),
            'competencyid' => $competencyid ?? $this->competency->get('id'),
        ]);
        if ($meta === null) {
            return (object) ['userevidenceid' => (int) $ue->get('id')];
        }
        return $this->getDataGenerator()->get_plugin_generator('local_evidencehub')->create_meta($meta + [
            'userid' => $this->worker->id,
            'userevidenceid' => $ue->get('id'),
            'issuedate' => 20260101,
            'expirydate' => 20280101,
        ]);
    }

    /**
     * Recompute the test's pair.
     *
     * @return validity|null
     */
    private function recompute(): ?validity {
        return status_repository::recompute((int) $this->worker->id, (int) $this->competency->get('id'));
    }

    /**
     * The stored row of the test's pair.
     *
     * @return \stdClass|false
     */
    private function row() {
        global $DB;
        return $DB->get_record(status_repository::TABLE, [
            'userid' => $this->worker->id,
            'competencyid' => $this->competency->get('id'),
        ]);
    }

    /**
     * The plugin's log entries in the history of the test's user competency.
     *
     * @return array Evidence rows, oldest first.
     */
    private function log_rows(): array {
        global $DB;
        $sql = "SELECT e.*
                  FROM {" . evidence::TABLE . "} e
                  JOIN {competency_usercomp} uc ON uc.id = e.usercompetencyid
                 WHERE uc.userid = :userid AND uc.competencyid = :competencyid AND e.desccomponent = :component
              ORDER BY e.id";
        return array_values($DB->get_records_sql($sql, [
            'userid' => $this->worker->id,
            'competencyid' => $this->competency->get('id'),
            'component' => 'local_evidencehub',
        ]));
    }

    /**
     * Save the framework's policy as the admin.
     *
     * @param array $overrides Policy values.
     * @return void
     */
    private function framework_policy(array $overrides): void {
        policy::save((object) ($overrides + [
            'frameworkid' => (int) $this->framework->get('id'),
            'competencyid' => 0,
            'kind' => policy::KIND_TECHNICAL,
            'requireverified' => 0,
            'requireproficiency' => 0,
            'minhours' => null,
            'alert1' => 60,
            'alert2' => 30,
            'gracedays' => 0,
            'cyclemonths' => 0,
            'onexpiry' => policy::ONEXPIRY_LOG,
        ]));
    }

    /**
     * The first recomputation inserts the row; the next replaces it in place.
     *
     * @return void
     */
    public function test_insert_then_update(): void {
        global $DB;
        $meta = $this->linked();
        $first = $this->freeze('2026-06-15 12:00');
        $result = $this->recompute();
        $this->assertSame(status::STATUS_VALID, $result->status);
        $row = $this->row();
        $this->assertSame([status::STATUS_VALID, (int) $meta->id, 20280101, 1], [
            (int) $row->status, (int) $row->metaid, (int) $row->effectiveexpiry, (int) $row->evidencecount,
        ]);
        // Sixty days before 2028-01-01, at midnight of the site's timezone (UTC here).
        $this->assertSame(status_repository::site_midnight(20271102), (int) $row->nexttransition);
        $this->assertSame($first, (int) $row->timechanged);

        $later = $this->freeze('2026-06-16 12:00');
        $this->recompute();
        $again = $this->row();
        $this->assertSame((int) $row->id, (int) $again->id);
        $this->assertSame(1, $DB->count_records(status_repository::TABLE));
        $this->assertSame($later, (int) $again->timemodified);
        $this->assertSame($first, (int) $again->timechanged, 'timechanged moved without a change of status.');

        $expired = $this->freeze('2028-01-02 12:00');
        $this->recompute();
        $this->assertSame(status::STATUS_EXPIRED, (int) $this->row()->status);
        $this->assertSame($expired, (int) $this->row()->timechanged);
    }

    /**
     * Nothing to prove the competency: no row is written, and a stored one is removed.
     *
     * @return void
     */
    public function test_no_status_removes_the_row(): void {
        global $DB;
        $this->assertNull($this->recompute());
        $this->assertFalse($this->row(), 'A row was stored for a pair with nothing to prove it.');

        $meta = $this->linked();
        $this->recompute();
        $this->assertNotFalse($this->row());
        $DB->delete_records('competency_userevidencecomp', ['userevidenceid' => $meta->userevidenceid]);
        $this->assertNull($this->recompute());
        $this->assertFalse($this->row());
    }

    /**
     * An evidence without details makes the pair unverified, never missing.
     *
     * @return void
     */
    public function test_an_evidence_without_details_is_unverified(): void {
        $this->linked(null);
        $this->assertSame(status::STATUS_UNVERIFIED, $this->recompute()->status);
        $this->assertSame(1, (int) $this->row()->evidencecount);
        $this->assertNull($this->row()->metaid);
    }

    /**
     * Where it is still yesterday in the site's timezone, an evidence expiring yesterday in UTC
     * still covers, whatever the user's timezone.
     *
     * @return void
     */
    public function test_today_is_the_sites_day_behind_utc(): void {
        $this->setTimezone('America/Sao_Paulo', 'America/Sao_Paulo');
        $this->setUser($this->getDataGenerator()->create_user(['timezone' => 'Asia/Tokyo']));
        $this->linked(['expirydate' => 20260614]);
        // 2026-06-15 01:00 UTC is 2026-06-14 22:00 in São Paulo and 10:00 on the 15th in Tokyo.
        $this->freeze('2026-06-15 01:00');
        $this->assertSame(status::STATUS_CRITICAL, $this->recompute()->status);
        $midnight = (new \DateTimeImmutable('2026-06-15 00:00', new \DateTimeZone('America/Sao_Paulo')))->getTimestamp();
        $this->assertSame($midnight, (int) $this->row()->nexttransition);
    }

    /**
     * Where it is already tomorrow in the site's timezone, the same evidence has expired, whatever
     * the user's timezone.
     *
     * @return void
     */
    public function test_today_is_the_sites_day_ahead_of_utc(): void {
        $this->setTimezone('Asia/Tokyo', 'Asia/Tokyo');
        $this->setUser($this->getDataGenerator()->create_user(['timezone' => 'America/Sao_Paulo']));
        $this->linked(['expirydate' => 20260614]);
        // 2026-06-14 20:00 UTC is 05:00 on the 15th in Tokyo and 17:00 on the 14th in São Paulo.
        $this->freeze('2026-06-14 20:00');
        $this->assertSame(status::STATUS_EXPIRED, $this->recompute()->status);
    }

    /**
     * A change fires the event and writes core's log entry, after the row; a recomputation that
     * changes nothing writes neither.
     *
     * @return void
     */
    public function test_event_and_log_only_on_a_change(): void {
        $this->linked();
        $sink = $this->redirectEvents();
        $this->recompute();
        $events = $sink->get_events();
        $sink->clear();
        $this->assertCount(2, $events);
        // Core's log entry is written inside the row's transaction; the plugin's event after the commit.
        [$created, $changed] = $events;
        $this->assertInstanceOf(validity_status_changed::class, $changed);
        $this->assertInstanceOf(\core\event\competency_evidence_created::class, $created);
        // The event names the stored row: it was written first.
        $this->assertSame((int) $this->row()->id, (int) $changed->objectid);
        $expected = ['from' => null, 'to' => status::STATUS_VALID, 'competencyid' => (int) $this->competency->get('id')];
        $this->assertSame($expected, $changed->other);
        $this->assertSame((int) $this->worker->id, (int) $changed->relateduserid);
        $logs = $this->log_rows();
        $this->assertCount(1, $logs);
        $this->assertSame(['evidence_status_valid', evidence::ACTION_LOG, null], [
            $logs[0]->descidentifier, (int) $logs[0]->action, $logs[0]->grade,
        ]);

        // Control: the same recomputation again changes nothing and writes nothing.
        $this->recompute();
        $this->assertCount(0, $sink->get_events());
        $this->assertCount(1, $this->log_rows());

        $this->freeze('2027-12-05 12:00');
        $this->recompute();
        $events = array_values(array_filter(
            $sink->get_events(),
            fn($event): bool => $event instanceof validity_status_changed
        ));
        $sink->close();
        $this->assertCount(1, $events);
        $this->assertSame(['from' => status::STATUS_VALID, 'to' => status::STATUS_CRITICAL], [
            'from' => $events[0]->other['from'],
            'to' => $events[0]->other['to'],
        ]);
        $logs = $this->log_rows();
        $this->assertSame('evidence_status_critical', end($logs)->descidentifier);
    }

    /**
     * A log entry core refuses rolls the status change back with it; the next recomputation that
     * can log writes both.
     *
     * core_competency's add_evidence() refuses while competencies are disabled: a real core state,
     * so no seam is needed.
     *
     * @return void
     */
    public function test_a_failing_log_rolls_the_status_back(): void {
        // The test's own transaction would hold the inner rollback until the end of the test.
        $this->preventResetByRollback();
        $this->linked(['expirydate' => 20270101]);
        $this->recompute();
        $before = $this->row();
        $this->assertCount(1, $this->log_rows());

        $this->freeze('2027-01-02 12:00');
        set_config('enabled', 0, 'core_competency');
        $sink = $this->redirectEvents();
        try {
            $this->recompute();
            $this->fail('A status change was stored without its log entry.');
        } catch (\moodle_exception $e) {
            $this->assertSame('competenciesarenotenabled', $e->errorcode);
        }
        $this->assertEquals($before, $this->row(), 'The status row changed although its log entry failed.');
        $this->assertCount(1, $this->log_rows());
        $this->assertCount(0, $sink->get_events(), 'The change was announced although it rolled back.');

        set_config('enabled', 1, 'core_competency');
        $this->recompute();
        $this->assertSame(status::STATUS_EXPIRED, (int) $this->row()->status);
        $logs = $this->log_rows();
        $this->assertCount(2, $logs);
        $this->assertSame('evidence_status_expired', end($logs)->descidentifier);
        $changed = array_filter($sink->get_events(), fn($event): bool => $event instanceof validity_status_changed);
        $sink->close();
        $this->assertCount(1, $changed);
    }

    /**
     * Only a lock timeout is queued for later; any other failure reaches the caller.
     *
     * @return void
     */
    public function test_recompute_or_queue_queues_only_a_lock_timeout(): void {
        $this->preventResetByRollback();
        $this->linked();
        $userid = (int) $this->worker->id;
        $competencyid = (int) $this->competency->get('id');
        held_lock_factory::while_held([$userid], function () use ($userid, $competencyid): void {
            status_repository::recompute_or_queue($userid, $competencyid);
        });
        $this->assertFalse($this->row());
        $this->assertCount(1, \core\task\manager::get_adhoc_tasks(recompute_scope::class));

        // Control: a log entry core refuses is no lock timeout, and is not swallowed.
        set_config('enabled', 0, 'core_competency');
        try {
            status_repository::recompute_or_queue($userid, $competencyid);
            $this->fail('A failure other than a lock timeout was swallowed.');
        } catch (\moodle_exception $e) {
            $this->assertSame('competenciesarenotenabled', $e->errorcode);
        }
        $this->assertCount(1, \core\task\manager::get_adhoc_tasks(recompute_scope::class));
    }

    /**
     * A recomputation that changes nothing writes no log entry and works with competencies disabled.
     *
     * @return void
     */
    public function test_no_change_needs_no_log(): void {
        $this->linked();
        $this->recompute();
        set_config('enabled', 0, 'core_competency');
        $this->assertSame(status::STATUS_VALID, $this->recompute()->status);
        $this->assertCount(1, $this->log_rows());
    }

    /**
     * Expiry under a review policy asks core for a review; under a log policy it does not; neither
     * changes the rating.
     *
     * @return void
     */
    public function test_expiry_requests_a_review_only_under_a_review_policy(): void {
        $this->linked(['expirydate' => 20260601]);
        $this->framework_policy(['onexpiry' => policy::ONEXPIRY_LOG]);
        $this->recompute();
        $uc = user_competency::get_record(['userid' => $this->worker->id, 'competencyid' => $this->competency->get('id')]);
        $logs = $this->log_rows();
        $this->assertSame('evidence_status_expired', end($logs)->descidentifier);
        $this->assertEquals(user_competency::STATUS_IDLE, $uc->get('status'));

        // A second worker under the review policy.
        $this->framework_policy(['onexpiry' => policy::ONEXPIRY_REVIEW]);
        $this->worker = $this->user_with();
        $this->linked(['expirydate' => 20260601]);
        $this->recompute();
        $uc = user_competency::get_record(['userid' => $this->worker->id, 'competencyid' => $this->competency->get('id')]);
        $this->assertEquals(user_competency::STATUS_WAITING_FOR_REVIEW, $uc->get('status'));
        $this->assertNull($uc->get('grade'));
        $this->assertNull($uc->get('proficiency'));
    }

    /**
     * A recommendation is never asked for a status other than expired, even under a review policy.
     *
     * @return void
     */
    public function test_no_review_while_valid(): void {
        $this->framework_policy(['onexpiry' => policy::ONEXPIRY_REVIEW]);
        $this->linked();
        $this->recompute();
        $uc = user_competency::get_record(['userid' => $this->worker->id, 'competencyid' => $this->competency->get('id')]);
        $this->assertEquals(user_competency::STATUS_IDLE, $uc->get('status'));
    }

    /**
     * The log entry carries the plugin's component and the effective expiry, and reads back as text.
     *
     * @return void
     */
    public function test_the_log_entry_is_the_plugins(): void {
        $this->linked(['expirydate' => 20260701]);
        $this->recompute();
        $logs = $this->log_rows();
        $log = end($logs);
        $this->assertSame('local_evidencehub', $log->desccomponent);
        $description = (string) (new evidence((int) $log->id))->get_description();
        $this->assertStringContainsString('2026-07-01', $description);
        $this->assertSame('evidence_status_critical', $log->descidentifier);
    }

    /**
     * A pair decided by a sensitive evidence logs a neutral entry, with neither its status nor its date: core's
     * competency pages show the history to readers without viewsensitive. The expiry still asks for a review under a
     * review policy. The control is the same expiry decided by a plain type, whose entry names its status and date.
     *
     * @return void
     */
    public function test_a_sensitive_pair_logs_a_neutral_entry(): void {
        $this->framework_policy(['onexpiry' => policy::ONEXPIRY_REVIEW]);
        $this->linked(['typeid' => $this->type_id('aso'), 'expirydate' => 20260601]);
        $this->recompute();
        $this->assertSame(
            [status::STATUS_EXPIRED, 1],
            [(int) $this->row()->status, (int) $this->row()->issensitive],
            'Precondition: an expired sensitive evidence decides.'
        );
        $logs = $this->log_rows();
        $log = end($logs);
        $this->assertSame('evidence_status_restricted', $log->descidentifier);
        $this->assertNull($log->desca);
        $description = (string) (new evidence((int) $log->id))->get_description();
        $this->assertSame(get_string('evidence_status_restricted', 'local_evidencehub'), $description);
        $this->assertStringNotContainsString('2026-06-01', $description);
        $uc = user_competency::get_record(['userid' => $this->worker->id, 'competencyid' => $this->competency->get('id')]);
        $this->assertEquals(user_competency::STATUS_WAITING_FOR_REVIEW, $uc->get('status'));

        // The control: a second worker, the same expiry on a plain type.
        $this->worker = $this->user_with();
        $this->linked(['typeid' => $this->type_id('nrcertificate'), 'expirydate' => 20260601]);
        $this->recompute();
        $this->assertSame(0, (int) $this->row()->issensitive);
        $logs = $this->log_rows();
        $log = end($logs);
        $this->assertSame('evidence_status_expired', $log->descidentifier);
        $this->assertStringContainsString('2026-06-01', (string) (new evidence((int) $log->id))->get_description());
    }

    /**
     * A pair that turns sensitive while its status stays the same has its earlier dated entries rewritten into the
     * neutral one: no new entry is logged, since the status did not change. Controls: the entries were dated before the
     * flip, another worker's plain pair of the same competency keeps its dated entry, and core's own entry on the
     * pair keeps its text.
     *
     * @return void
     */
    public function test_a_pair_turning_sensitive_neutralises_its_history(): void {
        global $DB;
        $meta = $this->linked(['typeid' => $this->type_id('nrcertificate'), 'expirydate' => 20260701]);
        $this->recompute();
        $worker = $this->worker;
        $this->worker = $this->user_with();
        $this->linked(['typeid' => $this->type_id('nrcertificate'), 'expirydate' => 20260701]);
        $this->recompute();
        $otherlogs = $this->log_rows();
        $this->worker = $worker;
        $logs = $this->log_rows();
        $this->assertCount(1, $logs);
        $this->assertSame('evidence_status_critical', $logs[0]->descidentifier, 'Precondition: a dated entry.');
        $ucid = (int) $logs[0]->usercompetencyid;
        $coreid = (int) $DB->insert_record(evidence::TABLE, (object) [
            'usercompetencyid' => $ucid,
            'contextid' => \context_user::instance((int) $this->worker->id)->id,
            'action' => evidence::ACTION_LOG,
            'descidentifier' => 'evidence_status_valid',
            'desccomponent' => 'core_competency',
            'desca' => json_encode('2026-07-01'),
            'timecreated' => time(),
            'timemodified' => time(),
            'usermodified' => 2,
        ]);

        $DB->set_field(details::TABLE, 'typeid', $this->type_id('aso'), ['id' => $meta->id]);
        $this->recompute();

        $this->assertSame(
            [status::STATUS_CRITICAL, 1],
            [(int) $this->row()->status, (int) $this->row()->issensitive],
            'Precondition: the status stayed, the flag turned.'
        );
        $logs = $this->log_rows();
        $this->assertCount(1, $logs, 'An unchanged status logged a new entry.');
        $this->assertSame('evidence_status_restricted', $logs[0]->descidentifier);
        $this->assertNull($logs[0]->desca);
        $core = $DB->get_record(evidence::TABLE, ['id' => $coreid], '*', MUST_EXIST);
        $this->assertSame(['evidence_status_valid', json_encode('2026-07-01')], [$core->descidentifier, $core->desca]);
        $this->assertEquals($otherlogs, $this->log_rows_of((int) $otherlogs[0]->usercompetencyid));
    }

    /**
     * The plugin's log entries of one user competency, oldest first.
     *
     * @param int $usercompetencyid The user competency.
     * @return array Evidence rows.
     */
    private function log_rows_of(int $usercompetencyid): array {
        global $DB;
        return array_values($DB->get_records(
            evidence::TABLE,
            ['usercompetencyid' => $usercompetencyid, 'desccomponent' => 'local_evidencehub'],
            'id'
        ));
    }

    /**
     * A proficient rating proves a technical competency on its own; a behavioural one for its cycle
     * from the day of the rating in the site's timezone.
     *
     * @return void
     */
    public function test_a_rating_is_a_candidate(): void {
        global $DB;
        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $uc = $generator->create_user_competency([
            'userid' => $this->worker->id,
            'competencyid' => $this->competency->get('id'),
            'grade' => 2,
            'proficiency' => 0,
        ]);
        $rated = (new \DateTimeImmutable('2026-03-10 12:00', new \DateTimeZone('UTC')))->getTimestamp();
        $rating = ['usercompetencyid' => $uc->get('id'), 'action' => evidence::ACTION_OVERRIDE, 'grade' => 2];
        $record = $generator->create_evidence($rating);
        $DB->set_field(evidence::TABLE, 'timecreated', $rated, ['id' => $record->get('id')]);
        // A rating that is not proficient proves nothing.
        $this->assertNull($this->recompute());

        $DB->set_field('competency_usercomp', 'proficiency', 1, ['id' => $uc->get('id')]);
        $result = $this->recompute();
        $this->assertSame([status::STATUS_VALID, null, null], [$result->status, $result->metaid, $result->effectiveexpiry]);
        $this->assertSame(0, $result->evidencecount);

        $this->framework_policy(['kind' => policy::KIND_BEHAVIOURAL, 'cyclemonths' => 5]);
        $result = $this->recompute();
        $this->assertSame([status::STATUS_DUE, 20260810], [$result->status, $result->effectiveexpiry]);

        // Control: a regulatory policy never counts the rating.
        $this->framework_policy(['kind' => policy::KIND_REGULATORY, 'requireverified' => 1]);
        $this->assertNull($this->recompute());
    }

    /**
     * A verifier's refusal keeps an evidence from counting, even where unverified evidence counts.
     *
     * @return void
     */
    public function test_a_rejected_evidence_does_not_count(): void {
        global $DB;
        $meta = $this->linked(['verifystatus' => meta::VERIFY_REJECTED]);
        $this->assertSame(status::STATUS_UNVERIFIED, $this->recompute()->status);
        // Control: the same evidence pending counts under the default policy.
        $DB->set_field(details::TABLE, 'verifystatus', meta::VERIFY_PENDING, ['id' => $meta->id]);
        $this->assertSame(status::STATUS_VALID, $this->recompute()->status);
    }

    /**
     * Every competency the evidence is linked to is recomputed, and no other.
     *
     * @return void
     */
    public function test_recompute_for_userevidence(): void {
        global $DB;
        $second = $this->getDataGenerator()->get_plugin_generator('core_competency')
            ->create_competency(['competencyframeworkid' => $this->framework->get('id')]);
        $unrelated = $this->getDataGenerator()->get_plugin_generator('core_competency')
            ->create_competency(['competencyframeworkid' => $this->framework->get('id')]);
        $meta = $this->linked();
        $this->getDataGenerator()->get_plugin_generator('core_competency')->create_user_evidence_competency([
            'userevidenceid' => $meta->userevidenceid,
            'competencyid' => $second->get('id'),
        ]);
        $this->linked([], (int) $unrelated->get('id'));
        status_repository::recompute_for_userevidence((int) $meta->userevidenceid);
        $stored = $DB->get_fieldset_select(status_repository::TABLE, 'competencyid', 'userid = :userid', [
            'userid' => $this->worker->id,
        ]);
        sort($stored);
        $expected = [(int) $this->competency->get('id'), (int) $second->get('id')];
        sort($expected);
        $this->assertSame($expected, array_map('intval', $stored));
    }

    /**
     * The row stores whether its deciding evidence's type is sensitive, on insert and on update: a type
     * changed through the details API, which recomputes, rewrites it both ways.
     *
     * @return void
     */
    public function test_the_row_stores_the_deciding_types_sensitive_flag(): void {
        $meta = $this->linked(['typeid' => $this->type_id('nrcertificate')]);
        $this->recompute();
        $this->assertSame([(int) $meta->id, 0], [(int) $this->row()->metaid, (int) $this->row()->issensitive]);

        $ue = new \core_competency\user_evidence((int) $meta->userevidenceid);
        details::save($ue, $this->details_data(['typeid' => $this->type_id('aso'), 'issuedate' => 20260101]));
        $this->assertSame([(int) $meta->id, 1], [(int) $this->row()->metaid, (int) $this->row()->issensitive]);

        // The control: back to a plain type, back to 0.
        details::save($ue, $this->details_data(['typeid' => $this->type_id('nrcertificate'), 'issuedate' => 20260101]));
        $this->assertSame(0, (int) $this->row()->issensitive);
    }

    /**
     * A first row decided by a sensitive evidence is inserted with the flag set.
     *
     * @return void
     */
    public function test_a_first_row_decided_by_a_sensitive_type_stores_it(): void {
        $this->linked(['typeid' => $this->type_id('aso')]);
        $this->assertFalse($this->row());
        $this->recompute();
        $this->assertSame(1, (int) $this->row()->issensitive);
    }

    /**
     * A rewrite of the row that changes nothing the KPIs count keeps their generation: the weekly
     * reconcile rewrites every row and must not empty every cached count.
     *
     * @return void
     */
    public function test_a_rewrite_with_the_same_status_keeps_the_kpis(): void {
        $this->linked();
        $before = hub_kpis::generation();
        $beforereport = hub_kpis::report_generation();
        $this->recompute();
        $inserted = hub_kpis::generation();
        $insertedreport = hub_kpis::report_generation();
        $this->assertNotSame($before, $inserted, 'A new row left the KPIs standing.');
        $this->assertNotSame($beforereport, $insertedreport, 'A new row left the report summary standing.');

        $this->freeze('2026-06-16 12:00');
        $this->recompute();
        $this->assertSame(status::STATUS_VALID, (int) $this->row()->status);
        $this->assertSame($inserted, hub_kpis::generation(), 'A rewrite with the same status retired the KPIs.');
        $this->assertSame(
            $insertedreport,
            hub_kpis::report_generation(),
            'A rewrite with the same values retired the report summary.'
        );

        // The control: a change of status retires them.
        $this->freeze('2028-01-02 12:00');
        $this->recompute();
        $this->assertSame(status::STATUS_EXPIRED, (int) $this->row()->status);
        $this->assertNotSame($inserted, hub_kpis::generation());
        $this->assertNotSame($insertedreport, hub_kpis::report_generation());
    }

    /**
     * Both generations, after a pair has a stored row.
     *
     * @return array [the KPIs' generation, the report summary's].
     */
    private function generations(): array {
        return [hub_kpis::generation(), hub_kpis::report_generation()];
    }

    /**
     * A new effective expiry with the same status, deciding evidence and count retires the report summary and
     * leaves the KPIs: the summary's "expiring" figure and "valid until" filter read the day.
     *
     * @return void
     */
    public function test_a_new_expiry_retires_the_report_generation_alone(): void {
        global $DB;
        $meta = $this->linked(['expirydate' => 20280101]);
        $this->recompute();
        [$kpis, $report] = $this->generations();

        $DB->set_field(details::TABLE, 'expirydate', 20290101, ['id' => $meta->id]);
        $this->recompute();
        $this->assertSame([status::STATUS_VALID, 20290101], [(int) $this->row()->status, (int) $this->row()->effectiveexpiry]);
        $this->assertSame($kpis, hub_kpis::generation(), 'A new expiry retired the KPIs.');
        $this->assertNotSame($report, hub_kpis::report_generation(), 'A new expiry left the report summary standing.');
    }

    /**
     * A different deciding evidence with the same status, expiry and count retires the report summary alone: its
     * type and verification, read through the stored filters, are the evidence's.
     *
     * @return void
     */
    public function test_a_new_deciding_evidence_retires_the_report_generation_alone(): void {
        global $DB;
        $first = $this->linked(['issuedate' => 20260201]);
        $second = $this->linked(['issuedate' => 20260201]);
        $this->recompute();
        // The tie on expiry and issue goes to the highest details id.
        $this->assertSame((int) $second->id, (int) $this->row()->metaid);
        [$kpis, $report] = $this->generations();

        $DB->set_field(details::TABLE, 'issuedate', 20260101, ['id' => $second->id]);
        $this->recompute();
        $this->assertSame((int) $first->id, (int) $this->row()->metaid);
        $this->assertSame(2, (int) $this->row()->evidencecount);
        $this->assertSame($kpis, hub_kpis::generation(), 'A new deciding evidence retired the KPIs.');
        $this->assertNotSame($report, hub_kpis::report_generation(), 'A new deciding evidence left the summary standing.');
    }

    /**
     * A new evidence count with the same status, expiry and deciding evidence retires the report summary alone.
     *
     * @return void
     */
    public function test_a_new_evidence_count_retires_the_report_generation_alone(): void {
        $first = $this->linked(['expirydate' => 20280101]);
        $this->recompute();
        $this->assertSame(1, (int) $this->row()->evidencecount);
        [$kpis, $report] = $this->generations();

        // An earlier expiry: the first evidence still decides.
        $this->linked(['expirydate' => 20270101]);
        $this->recompute();
        $this->assertSame([(int) $first->id, 2], [(int) $this->row()->metaid, (int) $this->row()->evidencecount]);
        $this->assertSame($kpis, hub_kpis::generation(), 'A new count retired the KPIs.');
        $this->assertNotSame($report, hub_kpis::report_generation(), 'A new count left the report summary standing.');
    }

    /**
     * Details that change without the status row changing (the deciding evidence's type) retire the report
     * summary through the details' event, and the KPIs stay.
     *
     * @return void
     */
    public function test_a_details_change_retires_the_report_summary(): void {
        $meta = $this->linked(['typeid' => $this->type_id('nrcertificate')]);
        $this->recompute();
        $row = $this->row();
        [$kpis, $report] = $this->generations();

        $ue = new \core_competency\user_evidence((int) $meta->userevidenceid);
        details::save($ue, $this->details_data([
            'typeid' => $this->type_id('diploma'),
            'issuedate' => 20260101,
            'expirydate' => 20280101,
        ]));
        $after = $this->row();
        $this->assertSame(
            [(int) $row->status, (int) $row->metaid, (int) $row->effectiveexpiry, (int) $row->evidencecount,
                (int) $row->issensitive],
            [(int) $after->status, (int) $after->metaid, (int) $after->effectiveexpiry, (int) $after->evidencecount,
                (int) $after->issensitive],
            'The precondition: the row did not change.'
        );
        $this->assertSame($kpis, hub_kpis::generation());
        $this->assertNotSame($report, hub_kpis::report_generation(), 'A change of type left the report summary standing.');
    }

    /**
     * A row whose deciding type turns sensitive keeps its status and still retires the KPIs: they count
     * a pair the viewer may not see as restricted.
     *
     * @return void
     */
    public function test_a_change_of_sensitivity_alone_retires_the_kpis(): void {
        $meta = $this->linked(['typeid' => $this->type_id('nrcertificate')]);
        $this->recompute();
        $generation = hub_kpis::generation();

        $ue = new \core_competency\user_evidence((int) $meta->userevidenceid);
        details::save($ue, $this->details_data(['typeid' => $this->type_id('aso'), 'issuedate' => 20260101]));
        $this->assertSame([status::STATUS_VALID, 1], [(int) $this->row()->status, (int) $this->row()->issensitive]);
        $this->assertNotSame($generation, hub_kpis::generation(), 'A change of sensitivity left the KPIs standing.');
    }

    /**
     * A removed row retires the KPIs; a pair that had no row and still has none keeps them.
     *
     * @return void
     */
    public function test_only_a_removed_row_retires_the_kpis(): void {
        global $DB;
        $meta = $this->linked();
        $this->recompute();
        $DB->delete_records('competency_userevidencecomp', ['userevidenceid' => $meta->userevidenceid]);
        $generation = hub_kpis::generation();
        $this->assertNull($this->recompute());
        $removed = hub_kpis::generation();
        $this->assertNotSame($generation, $removed, 'A removed row left the KPIs standing.');

        $this->assertNull($this->recompute());
        $this->assertSame($removed, hub_kpis::generation(), 'A pair with no row before or after retired the KPIs.');
    }
}
