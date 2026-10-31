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

namespace local_evidencehub\task;

use local_evidencehub\local\digest;

/**
 * Send the weekly digest to mentors, a capped chunk of mentors per run.
 *
 * A round opens each Monday at 07:00 in the site's timezone ({@see digest::round()}). The task runs
 * hourly, so a round larger than one run's cap spreads over the following runs: the plugin config
 * keeps the round (digestround) and the last mentor handled in it (digestafter), written after every
 * mentor, so a mentor whose digest failed with an exception is not retried in that round. A fatal error
 * or a crash between a mentor's send and that write leaves the resume point before the mentor, who is
 * then sent the digest again by the next run.
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class send_weekly_digest extends \core\task\scheduled_task {
    /** @var int The most mentors one run handles. */
    public const int CAP = 200;

    /**
     * The task's name.
     *
     * @return string
     */
    #[\Override]
    public function get_name(): string {
        return get_string('tasksendweeklydigest', 'local_evidencehub');
    }

    /**
     * The most mentors one run handles; a seam for the tests.
     *
     * @return int
     */
    protected function cap(): int {
        return self::CAP;
    }

    /**
     * Write one value of the round's state into the plugin config; a seam for the tests.
     *
     * @param string $name digestround or digestafter.
     * @param int $value The value.
     * @return void
     */
    protected function remember(string $name, int $value): void {
        set_config($name, $value, 'local_evidencehub');
    }

    /**
     * Handle the next chunk of mentors of the current round.
     *
     * @return void
     */
    #[\Override]
    public function execute(): void {
        if (!\core_competency\api::is_enabled()) {
            mtrace('Competencies are not enabled.');
            return;
        }
        $round = digest::round(\core\di::get(\core\clock::class)->time());
        if ((int) get_config('local_evidencehub', 'digestround') !== $round) {
            // The resume point first: a crash before the round is written opens the round again next run.
            $this->remember('digestafter', 0);
            $this->remember('digestround', $round);
        }
        $mentors = digest::mentors((int) get_config('local_evidencehub', 'digestafter'), $this->cap());
        $digest = new digest();
        $sent = 0;
        foreach ($mentors as $mentorid) {
            try {
                $sent += (int) $digest->send($mentorid);
            } catch (\Throwable $e) {
                // One mentor that fails never stops the others, and is not retried this round.
                mtrace("The weekly digest of user $mentorid failed: " . $e->getMessage());
            }
            $this->remember('digestafter', $mentorid);
        }
        mtrace("Sent $sent weekly digests to " . count($mentors) . " mentors of the round of $round.");
    }
}
