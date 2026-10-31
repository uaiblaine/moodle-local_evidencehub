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
 * Tests for the weekly digest to mentors.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_evidencehub\task;

use local_evidencehub\local\alerts;
use local_evidencehub\local\digest;
use local_evidencehub\local\status;
use local_evidencehub\tests\hub_scenario;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Mentors are the holders of receivealerts and userevidenceview through roles in a worker's user context;
 * each gets one digest a round, of what is not compliant as they may see it.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(send_weekly_digest::class)]
#[CoversClass(digest::class)]
final class send_weekly_digest_test extends \advanced_testcase {
    use hub_scenario;

    /** @var array The competencies C1, C2, C3. */
    private array $competencyids;

    /** @var int The template requiring them. */
    private int $templateid;

    /** @var int The role of the last mentor made. */
    private int $mentorroleid = 0;

    /** @var \phpunit_message_sink The message sink. */
    private \phpunit_message_sink $sink;

    #[\Override]
    protected function setUp(): void {
        global $CFG;
        require_once($CFG->dirroot . '/cohort/lib.php');
        parent::setUp();
        $this->resetAfterTest();
        set_config('enabled', 1, 'core_competency');
        $this->setTimezone('UTC', 'UTC');
        // A Monday, after the round opened at 07:00.
        $this->freeze('2026-06-15 08:00');
        $this->setAdminUser();
        $this->competencyids = $this->competencies(3);
        $this->templateid = $this->template($this->competencyids);
        $this->sink = $this->redirectMessages();
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
     * A mentor of some workers through tool_cohortroles: a role holding capabilities, given over a cohort.
     *
     * The mentor also reads the plan templates (moodle/competency:templateview in the system context) unless
     * told otherwise.
     *
     * @param array $workers The workers, made the cohort's members.
     * @param array $capabilities The role's capabilities.
     * @param bool $templateview Whether the mentor reads the templates.
     * @return \stdClass The mentor.
     */
    private function mentor(array $workers, array $capabilities, bool $templateview = true): \stdClass {
        $generator = $this->getDataGenerator();
        $mentor = $generator->create_user();
        $cohort = $generator->create_cohort();
        foreach ($workers as $worker) {
            cohort_add_member($cohort->id, $worker->id);
        }
        $roleid = $generator->create_role();
        set_role_contextlevels($roleid, [CONTEXT_USER]);
        foreach ($capabilities as $capability) {
            assign_capability($capability, CAP_ALLOW, $roleid, \context_system::instance()->id, true);
        }
        \tool_cohortroles\api::create_cohort_role_assignment((object) [
            'userid' => $mentor->id,
            'roleid' => $roleid,
            'cohortid' => $cohort->id,
        ]);
        \tool_cohortroles\api::sync_all_cohort_roles();
        if ($templateview) {
            $readerid = $generator->create_role();
            assign_capability('moodle/competency:templateview', CAP_ALLOW, $readerid, \context_system::instance()->id, true);
            role_assign($readerid, $mentor->id, \context_system::instance()->id);
        }
        accesslib_clear_all_caches_for_unit_testing();
        $this->mentorroleid = $roleid;
        return $mentor;
    }

    /**
     * Run a task and return the digests it sent, by recipient.
     *
     * @param send_weekly_digest|null $task The task; the plain one when null.
     * @return array Recipient id => message.
     */
    private function run_digest(?send_weekly_digest $task = null): array {
        ob_start();
        ($task ?? new send_weekly_digest())->execute();
        ob_end_clean();
        $digests = [];
        foreach ($this->sink->get_messages() as $message) {
            if ($message->eventtype === alerts::WEEKLY_DIGEST) {
                $digests[(int) $message->useridto] = $message;
            }
        }
        $this->sink->clear();
        return $digests;
    }

    /**
     * A mentor through tool_cohortroles gets one digest listing the pairs not compliant, with the compliant
     * count; a system holder of the same capabilities and a mentor without userevidenceview get none.
     *
     * @return void
     */
    public function test_a_mentor_gets_what_is_not_compliant(): void {
        [$c1, $c2] = $this->competencyids;
        $worker = $this->worker($this->templateid);
        $this->status_row((int) $worker->id, $c1, status::STATUS_VALID, 20280101);
        $this->status_row((int) $worker->id, $c2, status::STATUS_DUE, 20260801);
        $mentor = $this->mentor([$worker], ['local/evidencehub:receivealerts', 'moodle/competency:userevidenceview']);
        $this->mentor([$worker], ['local/evidencehub:receivealerts']);
        $this->viewer(['local/evidencehub:receivealerts', 'moodle/competency:userevidenceview']);

        $this->assertSame([(int) $mentor->id], digest::mentors(0, 10));
        $digests = $this->run_digest();
        $this->assertSame([(int) $mentor->id], array_keys($digests));
        $body = $digests[(int) $mentor->id]->fullmessage;
        $this->assertStringContainsString(fullname($worker) . ' (1 compliant)', $body);
        $this->assertStringContainsString('C2: Due soon, expiry 2026-08-01', $body);
        $this->assertStringContainsString('C3: Pending, no evidence', $body);
        $this->assertStringNotContainsString('C1:', $body);
        $this->assertSame('Weekly summary of evidence validity', $digests[(int) $mentor->id]->subject);
    }

    /**
     * A pair decided by a sensitive evidence reads "Restricted", without its expiry, to a mentor without
     * viewsensitive over the worker, and its status to a mentor with it.
     *
     * @return void
     */
    public function test_a_sensitive_pair_reads_restricted_without_viewsensitive(): void {
        [$c1] = $this->competencyids;
        $worker = $this->worker($this->templateid);
        $this->status_row((int) $worker->id, $c1, status::STATUS_DUE, 20260801, 'aso');
        $plain = $this->mentor([$worker], ['local/evidencehub:receivealerts', 'moodle/competency:userevidenceview']);
        $sensitive = $this->mentor(
            [$worker],
            ['local/evidencehub:receivealerts', 'moodle/competency:userevidenceview', 'local/evidencehub:viewsensitive']
        );

        $digests = $this->run_digest();
        $this->assertStringContainsString('C1: Restricted', $digests[(int) $plain->id]->fullmessage);
        $this->assertStringNotContainsString('2026-08-01', $digests[(int) $plain->id]->fullmessage);
        $this->assertStringContainsString('C1: Due soon, expiry 2026-08-01', $digests[(int) $sensitive->id]->fullmessage);
    }

    /**
     * A mentor whose workers are all compliant gets nothing; one with something to report does.
     *
     * @return void
     */
    public function test_a_mentor_with_nothing_to_report_gets_none(): void {
        $compliant = $this->worker($this->templateid);
        $behind = $this->worker($this->templateid);
        foreach ($this->competencyids as $index => $competencyid) {
            $this->status_row((int) $compliant->id, $competencyid, status::STATUS_VALID, 20280101);
            $status = $index === 0 ? status::STATUS_EXPIRED : status::STATUS_VALID;
            $this->status_row((int) $behind->id, $competencyid, $status, $index === 0 ? 20260601 : 20280101);
        }
        $capabilities = ['local/evidencehub:receivealerts', 'moodle/competency:userevidenceview'];
        $quiet = $this->mentor([$compliant], $capabilities);
        $told = $this->mentor([$behind], $capabilities);

        $digests = $this->run_digest();
        $this->assertArrayNotHasKey((int) $quiet->id, $digests);
        $this->assertSame([(int) $told->id], array_keys($digests));
        $this->assertStringContainsString(fullname($behind) . ' (2 compliant)', $digests[(int) $told->id]->fullmessage);
    }

    /**
     * The round opens on Monday at 07:00: a second run in the same round sends nothing, the next round sends again.
     *
     * @return void
     */
    public function test_one_digest_per_mentor_per_round(): void {
        $this->assertSame(20260608, digest::round($this->freeze('2026-06-15 06:59')));
        $this->assertSame(20260615, digest::round($this->freeze('2026-06-15 07:00')));
        $this->assertSame(20260615, digest::round($this->freeze('2026-06-21 23:59')));

        $worker = $this->worker($this->templateid);
        $mentor = $this->mentor([$worker], ['local/evidencehub:receivealerts', 'moodle/competency:userevidenceview']);
        $this->freeze('2026-06-15 08:00');
        $this->assertSame([(int) $mentor->id], array_keys($this->run_digest()));
        $this->freeze('2026-06-15 09:00');
        $this->assertSame([], $this->run_digest(), 'A second run of the round sent the digest again.');
        $this->freeze('2026-06-22 06:00');
        $this->assertSame([], $this->run_digest(), 'The round opened before Monday 07:00.');
        $this->freeze('2026-06-22 08:00');
        $this->assertSame([(int) $mentor->id], array_keys($this->run_digest()));
    }

    /**
     * The cap spreads the mentors of a round over runs, in id order.
     *
     * @return void
     */
    public function test_the_cap_spreads_mentors_over_runs(): void {
        $worker = $this->worker($this->templateid);
        $capabilities = ['local/evidencehub:receivealerts', 'moodle/competency:userevidenceview'];
        $mentors = [];
        for ($i = 0; $i < 3; $i++) {
            $mentors[] = (int) $this->mentor([$worker], $capabilities)->id;
        }
        $task = new class extends send_weekly_digest {
            /**
             * Two mentors a run.
             *
             * @return int
             */
            #[\Override]
            protected function cap(): int {
                return 2;
            }
        };
        $this->assertSame(array_slice($mentors, 0, 2), array_keys($this->run_digest($task)));
        $this->assertSame([$mentors[2]], array_keys($this->run_digest($task)));
        $this->assertSame([], $this->run_digest($task));
    }

    /**
     * A prevention of userevidenceview in one worker's user context drops that worker from the mentor's
     * workers and digest, though the role allows it; the other worker stays.
     *
     * @return void
     */
    public function test_a_prevented_view_drops_that_worker(): void {
        $hidden = $this->worker($this->templateid);
        $shown = $this->worker($this->templateid);
        $mentor = $this->mentor([$hidden, $shown], ['local/evidencehub:receivealerts', 'moodle/competency:userevidenceview']);
        $this->assertSame([(int) $hidden->id, (int) $shown->id], digest::workers((int) $mentor->id));

        $context = \context_user::instance((int) $hidden->id);
        assign_capability('moodle/competency:userevidenceview', CAP_PREVENT, $this->mentorroleid, $context->id, true);
        accesslib_clear_all_caches_for_unit_testing();
        $this->assertSame([(int) $shown->id], digest::workers((int) $mentor->id));
        $body = $this->run_digest()[(int) $mentor->id]->fullmessage;
        $this->assertStringContainsString(fullname($shown), $body);
        $this->assertStringNotContainsString(fullname($hidden), $body);
    }

    /**
     * A mentor who may not read the plan template gets nothing about its pairs; one who may, does.
     *
     * @return void
     */
    public function test_a_mentor_who_cannot_read_the_template_gets_none(): void {
        $worker = $this->worker($this->templateid);
        $capabilities = ['local/evidencehub:receivealerts', 'moodle/competency:userevidenceview'];
        $blind = $this->mentor([$worker], $capabilities, false);
        $reader = $this->mentor([$worker], $capabilities);
        $this->assertSame([(int) $reader->id], array_keys($this->run_digest()));
        $this->assertSame([], (new digest())->report((int) $blind->id, [(int) $worker->id]));
    }

    /**
     * Opening a round writes the resume point before the round: a failure between the two leaves the round
     * to be opened again, never a new round resuming after the last week's mentor.
     *
     * @return void
     */
    public function test_the_resume_point_is_reset_before_the_round(): void {
        $worker = $this->worker($this->templateid);
        $mentor = $this->mentor([$worker], ['local/evidencehub:receivealerts', 'moodle/competency:userevidenceview']);
        set_config('digestround', 20260608, 'local_evidencehub');
        set_config('digestafter', $mentor->id, 'local_evidencehub');
        $failing = new class extends send_weekly_digest {
            /**
             * Fail when the round is written.
             *
             * @param string $name The value's name.
             * @param int $value The value.
             * @return void
             * @throws \moodle_exception When the round is written.
             */
            #[\Override]
            protected function remember(string $name, int $value): void {
                if ($name === 'digestround') {
                    throw new \moodle_exception('error');
                }
                parent::remember($name, $value);
            }
        };
        try {
            $failing->execute();
            $this->fail('The round was written.');
        } catch (\moodle_exception $e) {
            $this->assertSame('error', $e->errorcode);
        }
        $this->assertSame('0', get_config('local_evidencehub', 'digestafter'));
        $this->assertSame('20260608', get_config('local_evidencehub', 'digestround'));
        $this->assertSame([(int) $mentor->id], array_keys($this->run_digest()));
    }
}
