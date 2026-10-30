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
 * Tests for the validity rule.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_evidencehub\local;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The rule on its own: boundaries of the alert windows and of expiry, ties, and what counts under
 * each kind of policy.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(validity_calculator::class)]
#[CoversClass(candidate::class)]
#[CoversClass(validity::class)]
final class validity_calculator_test extends \basic_testcase {
    /** @var int The day most cases are computed on. */
    private const int TODAY = 20260615;

    /**
     * A complete, verified evidence.
     *
     * @param int $metaid The details id.
     * @param int $issue The issue day.
     * @param int|null $expiry The last valid day, null for never.
     * @param array $flags Optional verified, hours, complete, rejected.
     * @return candidate
     */
    private static function evidence(int $metaid, int $issue, ?int $expiry, array $flags = []): candidate {
        $complete = $flags['complete'] ?? true;
        return new candidate(
            source: candidate::SOURCE_EVIDENCE,
            metaid: $complete ? $metaid : null,
            userevidenceid: $metaid + 100,
            issue: $complete ? $issue : null,
            expiry: $complete ? $expiry : null,
            complete: $complete,
            verified: $flags['verified'] ?? true,
            hours: $flags['hours'] ?? null,
            rejected: $flags['rejected'] ?? false,
        );
    }

    /**
     * A proficient rating.
     *
     * @param int $issue The day of the rating.
     * @param int|null $expiry The last valid day, null for never.
     * @return candidate
     */
    private static function rating(int $issue, ?int $expiry): candidate {
        return new candidate(candidate::SOURCE_RATING, null, null, $issue, $expiry, true, true);
    }

    /**
     * The default policy with some values replaced.
     *
     * @param array $overrides Column => value.
     * @return \stdClass
     */
    private static function policy(array $overrides = []): \stdClass {
        return (object) ($overrides + (array) policy::defaults());
    }

    /**
     * The shapes of the rule.
     *
     * @return array Case name => [candidates, policy overrides, today, proficient, expected status,
     *     expected metaid, expected effective expiry, expected next transition].
     */
    public static function shapes(): array {
        $regulatory = ['kind' => policy::KIND_REGULATORY, 'requireverified' => 1];
        $behavioural = ['kind' => policy::KIND_BEHAVIOURAL, 'cyclemonths' => 12];
        // Sixty days after 2026-06-15: the first alert falls exactly on TODAY.
        $alerted = 20260814;
        return [
            'expiry today still covers' => [[self::evidence(1, 20250615, 20260615)], [], self::TODAY, false,
                status::STATUS_CRITICAL, 1, 20260615, 20260616],
            'the day after expiry it has expired' => [[self::evidence(1, 20250615, 20260615)], [], 20260616, false,
                status::STATUS_EXPIRED, 1, 20260615, null],
            'one day before the first alert' => [[self::evidence(1, 20250101, $alerted)], [], 20260614, false,
                status::STATUS_VALID, 1, $alerted, 20260615],
            'on the first alert day' => [[self::evidence(1, 20250101, $alerted)], [], self::TODAY, false,
                status::STATUS_DUE, 1, $alerted, 20260715],
            'one day after the first alert' => [[self::evidence(1, 20250101, $alerted)], [], 20260616, false,
                status::STATUS_DUE, 1, $alerted, 20260715],
            'one day before the second alert' => [[self::evidence(1, 20250101, $alerted)], [], 20260714, false,
                status::STATUS_DUE, 1, $alerted, 20260715],
            'on the second alert day' => [[self::evidence(1, 20250101, $alerted)], [], 20260715, false,
                status::STATUS_CRITICAL, 1, $alerted, 20260815],
            'one day after the second alert' => [[self::evidence(1, 20250101, $alerted)], [], 20260716, false,
                status::STATUS_CRITICAL, 1, $alerted, 20260815],
            'leap day plus a year, last day' => [[self::evidence(1, 20280229, meta::add_months(20280229, 12))], [], 20290228, false,
                status::STATUS_CRITICAL, 1, 20290228, 20290301],
            'leap day plus a year, the day after' => [[self::evidence(1, 20280229, meta::add_months(20280229, 12))], [], 20290301,
                false, status::STATUS_EXPIRED, 1, 20290228, null],
            'never expires: no alert, no transition' => [[self::evidence(1, 20200101, null)], [], self::TODAY, false,
                status::STATUS_VALID, 1, null, null],
            'a future issue alone waits for its day' => [[self::evidence(1, 20260701, 20280701)], [], self::TODAY, false,
                status::STATUS_UNVERIFIED, null, null, 20260701],
            'a future issue beside a valid one' => [
                [self::evidence(1, 20250101, 20270101), self::evidence(2, 20260701, 20280701)], [], self::TODAY, false,
                status::STATUS_VALID, 1, 20270101, 20260701],
            'an expired one and its renewal not yet issued' => [
                [self::evidence(1, 20240101, 20260101), self::evidence(2, 20260701, 20280701)], [], self::TODAY, false,
                status::STATUS_EXPIRED, 1, 20260101, 20260701],
            'expired: the latest expiry decides' => [
                [self::evidence(1, 20230101, 20250101), self::evidence(2, 20240101, 20260101)], [], self::TODAY, false,
                status::STATUS_EXPIRED, 2, 20260101, null],
            'tie on expiry: the latest issue' => [
                [self::evidence(1, 20250601, 20270101), self::evidence(2, 20250101, 20270101)], [], self::TODAY, false,
                status::STATUS_VALID, 1, 20270101, 20261102],
            'tie on expiry and issue: the highest metaid' => [
                [self::evidence(3, 20250101, 20270101), self::evidence(5, 20250101, 20270101)], [], self::TODAY, false,
                status::STATUS_VALID, 5, 20270101, 20261102],
            'never beats any day' => [
                [self::evidence(2, 20250101, 20990101), self::evidence(1, 20250101, null)], [], self::TODAY, false,
                status::STATUS_VALID, 1, null, null],
            'a rating loses a tie to an evidence' => [
                [self::rating(20250101, 20270101), self::evidence(4, 20250101, 20270101)], [], self::TODAY, true,
                status::STATUS_VALID, 4, 20270101, 20261102],
            'a later rating decides' => [
                [self::rating(20250101, 20280101), self::evidence(4, 20250101, 20270101)], [], self::TODAY, true,
                status::STATUS_VALID, null, 20280101, 20271102],
            'unverified under requireverified' => [
                [self::evidence(1, 20250101, 20270101, ['verified' => false])], ['requireverified' => 1], self::TODAY, false,
                status::STATUS_UNVERIFIED, null, null, null],
            'control: unverified counts without requireverified' => [
                [self::evidence(1, 20250101, 20270101, ['verified' => false])], [], self::TODAY, false,
                status::STATUS_VALID, 1, 20270101, 20261102],
            'a rejected evidence never counts' => [
                [self::evidence(1, 20250101, 20270101, ['verified' => false, 'rejected' => true])], [], self::TODAY, false,
                status::STATUS_UNVERIFIED, null, null, null],
            'hours below the minimum' => [
                [self::evidence(1, 20250101, 20270101, ['hours' => 7.5])], ['minhours' => 8.0], self::TODAY, false,
                status::STATUS_UNVERIFIED, null, null, null],
            'hours equal to the minimum' => [
                [self::evidence(1, 20250101, 20270101, ['hours' => 8.0])], ['minhours' => 8.0], self::TODAY, false,
                status::STATUS_VALID, 1, 20270101, 20261102],
            'no hours under a minimum' => [
                [self::evidence(1, 20250101, 20270101)], ['minhours' => 8.0], self::TODAY, false,
                status::STATUS_UNVERIFIED, null, null, null],
            'no hours under a minimum of zero' => [
                [self::evidence(1, 20250101, 20270101)], ['minhours' => 0.0], self::TODAY, false,
                status::STATUS_UNVERIFIED, null, null, null],
            'a rating under a minimum of hours' => [
                [self::rating(20250101, 20270101)], ['minhours' => 8.0], self::TODAY, true,
                status::STATUS_VALID, null, 20270101, 20261102],
            'incomplete evidence' => [[self::evidence(1, 20250101, 20270101, ['complete' => false])], [], self::TODAY, false,
                status::STATUS_UNVERIFIED, null, null, null],
            'technical grace days extend the expiry' => [[self::evidence(1, 20250101, 20260610)], ['gracedays' => 10],
                self::TODAY, false, status::STATUS_CRITICAL, 1, 20260620, 20260621],
            'regulatory has no grace days' => [[self::evidence(1, 20250101, 20260610)], $regulatory + ['gracedays' => 10],
                self::TODAY, false, status::STATUS_EXPIRED, 1, 20260610, null],
            'behavioural has no grace days' => [[self::rating(20250101, 20260610)], $behavioural + ['gracedays' => 10],
                self::TODAY, true, status::STATUS_EXPIRED, null, 20260610, null],
            'regulatory ignores a rating' => [[self::rating(20250101, null)], $regulatory, self::TODAY, true,
                null, null, null, null],
            'regulatory ignores a rating beside unverified evidence' => [
                [self::rating(20250101, null), self::evidence(1, 20250101, 20270101, ['verified' => false])], $regulatory,
                self::TODAY, true, status::STATUS_UNVERIFIED, null, null, null],
            'behavioural ignores evidence' => [[self::evidence(1, 20250101, 20270101)], $behavioural, self::TODAY, false,
                status::STATUS_UNVERIFIED, null, null, null],
            'behavioural counts the rating for its cycle' => [[self::rating(20260101, meta::add_months(20260101, 12))],
                $behavioural, self::TODAY, true, status::STATUS_VALID, null, 20270101, 20261102],
            'behavioural rating past its cycle' => [[self::rating(20250601, meta::add_months(20250601, 12))], $behavioural,
                self::TODAY, true, status::STATUS_EXPIRED, null, 20260601, null],
            'requireproficiency without proficiency' => [[self::evidence(1, 20250101, 20270101)], ['requireproficiency' => 1],
                self::TODAY, false, status::STATUS_UNVERIFIED, null, null, null],
            'control: requireproficiency with proficiency' => [[self::evidence(1, 20250101, 20270101)],
                ['requireproficiency' => 1], self::TODAY, true, status::STATUS_VALID, 1, 20270101, 20261102],
            'requireproficiency is technical only' => [[self::evidence(1, 20250101, 20270101)],
                $regulatory + ['requireproficiency' => 1], self::TODAY, false, status::STATUS_VALID, 1, 20270101, 20261102],
            'nothing at all' => [[], [], self::TODAY, false, null, null, null, null],
        ];
    }

    /**
     * Each shape gives its status, deciding evidence, effective expiry and next transition.
     *
     * @param array $candidates The candidates.
     * @param array $overrides Policy values replacing the default's.
     * @param int $today Today.
     * @param bool $proficient Whether the user competency is proficient.
     * @param int|null $status The expected status.
     * @param int|null $metaid The expected deciding details id.
     * @param int|null $expiry The expected effective expiry.
     * @param int|null $next The expected next transition.
     * @return void
     */
    #[DataProvider('shapes')]
    public function test_the_rule(
        array $candidates,
        array $overrides,
        int $today,
        bool $proficient,
        ?int $status,
        ?int $metaid,
        ?int $expiry,
        ?int $next
    ): void {
        $result = validity_calculator::calculate($candidates, self::policy($overrides), $today, $proficient);
        $this->assertSame(
            ['status' => $status, 'metaid' => $metaid, 'effectiveexpiry' => $expiry, 'nexttransition' => $next],
            [
                'status' => $result->status,
                'metaid' => $result->metaid,
                'effectiveexpiry' => $result->effectiveexpiry,
                'nexttransition' => $result->nexttransition,
            ]
        );
    }

    /**
     * Readiness and blocking per kind: only a regulatory policy that is not ready blocks.
     *
     * @return array Case name => [kind, today, ready, blocking].
     */
    public static function readiness(): array {
        return [
            'regulatory, expired' => [policy::KIND_REGULATORY, 20270102, false, true],
            'regulatory, valid' => [policy::KIND_REGULATORY, self::TODAY, true, false],
            'regulatory, due' => [policy::KIND_REGULATORY, 20261201, true, false],
            'technical, expired' => [policy::KIND_TECHNICAL, 20270102, false, false],
            'technical, valid' => [policy::KIND_TECHNICAL, self::TODAY, true, false],
        ];
    }

    /**
     * An expired technical competency is at risk, not blocking; a regulatory one blocks.
     *
     * @param int $kind The policy kind.
     * @param int $today Today.
     * @param bool $ready Whether it is expected ready.
     * @param bool $blocking Whether it is expected blocking.
     * @return void
     */
    #[DataProvider('readiness')]
    public function test_ready_and_blocking_per_kind(int $kind, int $today, bool $ready, bool $blocking): void {
        $candidates = [self::evidence(1, 20250101, 20270101)];
        $result = validity_calculator::calculate($candidates, self::policy(['kind' => $kind]), $today, false);
        $this->assertSame([$ready, $blocking], [$result->ready, $result->blocking]);
    }

    /**
     * A regulatory pair with nothing that counts blocks; with nothing at all it is not ready either.
     *
     * @return void
     */
    public function test_an_unverified_regulatory_pair_blocks(): void {
        $policy = self::policy(['kind' => policy::KIND_REGULATORY, 'requireverified' => 1]);
        $candidates = [self::evidence(1, 20250101, 20270101, ['verified' => false])];
        $result = validity_calculator::calculate($candidates, $policy, self::TODAY, false);
        $this->assertSame(status::STATUS_UNVERIFIED, $result->status);
        $this->assertTrue($result->blocking);
        $this->assertFalse($result->ready);
    }

    /**
     * The evidence count holds every evidence, counted or not, and never the rating.
     *
     * @return void
     */
    public function test_the_evidence_count(): void {
        $candidates = [
            self::evidence(1, 20250101, 20270101),
            self::evidence(2, 20250101, 20270101, ['complete' => false]),
            self::evidence(3, 20250101, 20250201),
            self::rating(20250101, null),
        ];
        $result = validity_calculator::calculate($candidates, self::policy(), self::TODAY, true);
        $this->assertSame(3, $result->evidencecount);
    }
}
