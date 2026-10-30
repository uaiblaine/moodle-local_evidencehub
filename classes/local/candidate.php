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
 * One thing that may prove a competency for the validity calculator: an evidence or a rating.
 *
 * A plain value: the status repository builds it from the database, the calculator reads it and
 * nothing else.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class candidate {
    /** @var string A core evidence of prior learning linked to the competency. */
    public const string SOURCE_EVIDENCE = 'evidence';

    /** @var string The user competency's latest rating, while it is proficient. */
    public const string SOURCE_RATING = 'rating';

    /**
     * A candidate.
     *
     * @param string $source SOURCE_EVIDENCE or SOURCE_RATING.
     * @param int|null $metaid The details row; null for a rating or an evidence without details.
     * @param int|null $userevidenceid The core evidence; null for a rating.
     * @param int|null $issue The issue day YYYYMMDD; null for an evidence without details.
     * @param int|null $expiry The last valid day YYYYMMDD; null when it never expires.
     * @param bool $complete Whether details exist.
     * @param bool $verified Whether a verifier confirmed the details; a rating is verified.
     * @param float|null $hours The hours the evidence states; null for none or a rating.
     * @param bool $rejected Whether a verifier refused the details; a refused evidence never counts.
     */
    public function __construct(
        /** @var string SOURCE_EVIDENCE or SOURCE_RATING. */
        public readonly string $source,
        /** @var int|null The details row; null for a rating or an evidence without details. */
        public readonly ?int $metaid,
        /** @var int|null The core evidence; null for a rating. */
        public readonly ?int $userevidenceid,
        /** @var int|null The issue day YYYYMMDD; null for an evidence without details. */
        public readonly ?int $issue,
        /** @var int|null The last valid day YYYYMMDD; null when it never expires. */
        public readonly ?int $expiry,
        /** @var bool Whether details exist. */
        public readonly bool $complete,
        /** @var bool Whether a verifier confirmed the details; a rating is verified. */
        public readonly bool $verified,
        /** @var float|null The hours the evidence states; null for none or a rating. */
        public readonly ?float $hours = null,
        /** @var bool Whether a verifier refused the details. */
        public readonly bool $rejected = false,
    ) {
    }

    /**
     * Whether this is the rating rather than an evidence.
     *
     * @return bool
     */
    public function is_rating(): bool {
        return $this->source === self::SOURCE_RATING;
    }
}
