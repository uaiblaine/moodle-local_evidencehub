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

/**
 * The validity rule: from the candidates of one (user, competency) pair, its policy and today,
 * the status and when it next changes.
 *
 * Pure: no database, no globals, no clock. Every input is a parameter, so the rule is tested on
 * its own and {@see status_repository} only gathers the inputs and stores the result.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class validity_calculator {
    /**
     * Work out the validity of one pair.
     *
     * @param array $candidates The candidate objects of the pair: every linked evidence and, when
     *     the user competency is proficient, its rating.
     * @param \stdClass $policy The effective policy, as policy::resolve() returns it.
     * @param int $today Today YYYYMMDD in the site's timezone.
     * @param bool $proficient Whether the user competency is proficient.
     * @return validity
     */
    public static function calculate(array $candidates, \stdClass $policy, int $today, bool $proficient): validity {
        $kind = (int) $policy->kind;
        // A regulatory competency is proven by evidence only: its rating is no candidate at all.
        $usable = array_values(array_filter(
            $candidates,
            fn(candidate $candidate): bool => !($candidate->is_rating() && $kind === policy::KIND_REGULATORY)
        ));
        $evidencecount = count(array_filter($usable, fn(candidate $candidate): bool => !$candidate->is_rating()));

        $covering = [];
        $past = [];
        $upcoming = [];
        foreach ($usable as $candidate) {
            if (!self::counts($candidate, $policy, $proficient)) {
                continue;
            }
            $expiry = self::effective_expiry($candidate, $policy);
            if ($candidate->issue > $today) {
                $upcoming[] = [$candidate, $expiry];
            } else if ($expiry === null || $today <= $expiry) {
                $covering[] = [$candidate, $expiry];
            } else {
                $past[] = [$candidate, $expiry];
            }
        }

        $status = null;
        $deciding = null;
        $transitions = array_map(fn(array $pair): int => $pair[0]->issue, $upcoming);
        if ($covering) {
            $deciding = self::latest($covering);
            $expiry = $deciding[1];
            $status = status::STATUS_VALID;
            if ($expiry !== null) {
                $daysleft = meta::days_between($today, $expiry);
                if ($daysleft <= (int) $policy->alert2) {
                    $status = status::STATUS_CRITICAL;
                } else if ($daysleft <= (int) $policy->alert1) {
                    $status = status::STATUS_DUE;
                }
                $transitions[] = meta::add_days($expiry, -(int) $policy->alert1);
                $transitions[] = meta::add_days($expiry, -(int) $policy->alert2);
                $transitions[] = meta::add_days($expiry, 1);
            }
        } else if ($past) {
            $deciding = self::latest($past);
            $status = status::STATUS_EXPIRED;
        } else if ($usable) {
            $status = status::STATUS_UNVERIFIED;
        }

        $ahead = array_filter($transitions, fn(int $day): bool => $day > $today);
        $ready = in_array($status, [status::STATUS_VALID, status::STATUS_DUE, status::STATUS_CRITICAL], true);
        return new validity(
            status: $status,
            metaid: $deciding ? $deciding[0]->metaid : null,
            effectiveexpiry: $deciding ? $deciding[1] : null,
            evidencecount: $evidencecount,
            nexttransition: $ahead ? min($ahead) : null,
            ready: $ready,
            blocking: !$ready && $kind === policy::KIND_REGULATORY,
        );
    }

    /**
     * Whether a candidate counts under a policy, whatever its dates.
     *
     * @param candidate $candidate The candidate.
     * @param \stdClass $policy The effective policy.
     * @param bool $proficient Whether the user competency is proficient.
     * @return bool
     */
    private static function counts(candidate $candidate, \stdClass $policy, bool $proficient): bool {
        $kind = (int) $policy->kind;
        // A behavioural competency is proven by its rating alone.
        if ($kind === policy::KIND_BEHAVIOURAL && !$candidate->is_rating()) {
            return false;
        }
        if (!$candidate->complete || $candidate->issue === null || $candidate->rejected) {
            return false;
        }
        if (!empty($policy->requireverified) && !$candidate->verified) {
            return false;
        }
        // The hours are a property of a training certificate; a rating states none to compare.
        $minhours = $policy->minhours ?? null;
        if ($minhours !== null && $minhours !== '' && !$candidate->is_rating()) {
            if ($candidate->hours === null || $candidate->hours < (float) $minhours) {
                return false;
            }
        }
        if ($kind === policy::KIND_TECHNICAL && !empty($policy->requireproficiency) && !$proficient) {
            return false;
        }
        return true;
    }

    /**
     * A counted candidate's last valid day, with the grace days of a technical policy.
     *
     * @param candidate $candidate The candidate.
     * @param \stdClass $policy The effective policy.
     * @return int|null The YYYYMMDD day, or null when it never expires.
     */
    private static function effective_expiry(candidate $candidate, \stdClass $policy): ?int {
        if ($candidate->expiry === null) {
            return null;
        }
        if ((int) $policy->kind === policy::KIND_TECHNICAL) {
            return meta::add_days($candidate->expiry, (int) $policy->gracedays);
        }
        return $candidate->expiry;
    }

    /**
     * The candidate that decides among several: the latest effective expiry (one that never
     * expires beats any day), then the latest issue, then the highest details id, so a rating
     * loses a tie.
     *
     * @param array $pairs Non-empty list of [candidate, effective expiry or null].
     * @return array The deciding [candidate, effective expiry or null].
     */
    private static function latest(array $pairs): array {
        usort($pairs, fn(array $a, array $b): int => [
            $b[1] ?? PHP_INT_MAX,
            $b[0]->issue,
            $b[0]->metaid ?? 0,
        ] <=> [
            $a[1] ?? PHP_INT_MAX,
            $a[0]->issue,
            $a[0]->metaid ?? 0,
        ]);
        return $pairs[0];
    }
}
