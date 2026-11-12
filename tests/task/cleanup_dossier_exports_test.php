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

use local_evidencehub\local\dossier;
use local_evidencehub\local\dossier_export;
use local_evidencehub\local\export;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The cleanup of prepared dossier ZIPs: expired files go, their exports are marked expired, the dossier rows
 * and the unexpired exports stay.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(cleanup_dossier_exports::class)]
#[CoversClass(dossier_export::class)]
final class cleanup_dossier_exports_test extends \advanced_testcase {
    /**
     * A done export of a requester with its stored ZIP and one dossier row.
     *
     * @param \stdClass $requester The requester.
     * @param int $timeexpires When it expires.
     * @return \stdClass The export row.
     */
    private function export_with_zip(\stdClass $requester, int $timeexpires): \stdClass {
        $generator = $this->getDataGenerator()->get_plugin_generator('local_evidencehub');
        $export = $generator->create_export([
            'requestedby' => $requester->id,
            'templateid' => 1,
            'status' => export::STATUS_DONE,
            'timeexpires' => $timeexpires,
        ]);
        get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance($requester->id)->id,
            'component' => 'local_evidencehub',
            'filearea' => dossier_export::FILEAREA,
            'itemid' => $export->id,
            'filepath' => '/',
            'filename' => dossier_export::filename($export),
        ], 'zip');
        $generator->create_dossier([
            'userid' => $this->getDataGenerator()->create_user()->id,
            'templateid' => 1,
            'exportid' => $export->id,
            'createdby' => $requester->id,
        ]);
        return $export;
    }

    /**
     * How many files an export's area holds.
     *
     * @param \stdClass $export The export row.
     * @return int
     */
    private function files_of(\stdClass $export): int {
        return count(get_file_storage()->get_area_files(
            \context_user::instance((int) $export->requestedby)->id,
            'local_evidencehub',
            dossier_export::FILEAREA,
            (int) $export->id,
            'id',
            false
        ));
    }

    /**
     * Expired ZIPs are deleted and their exports marked expired; unexpired ones and every dossier row stay.
     *
     * @return void
     */
    public function test_expired_zips_are_deleted_and_rows_kept(): void {
        global $DB;
        $this->resetAfterTest();
        $now = $this->mock_clock_with_frozen()->time();
        $requester = $this->getDataGenerator()->create_user();
        $expired = $this->export_with_zip($requester, $now - 1);
        $due = $this->export_with_zip($requester, $now);
        $kept = $this->export_with_zip($requester, $now + 1);
        $failed = $this->export_with_zip($requester, $now - 1);
        $DB->set_field(dossier_export::TABLE, 'status', export::STATUS_FAILED, ['id' => $failed->id]);

        ob_start();
        (new cleanup_dossier_exports())->execute();
        $output = ob_get_clean();

        $this->assertStringContainsString('2 expired', $output);
        foreach ([$expired, $due] as $export) {
            $this->assertSame(0, $this->files_of($export));
            $this->assertSame(export::STATUS_EXPIRED, (int) $DB->get_field(dossier_export::TABLE, 'status', ['id' => $export->id]));
        }
        $this->assertSame(1, $this->files_of($kept));
        $this->assertSame(export::STATUS_DONE, (int) $DB->get_field(dossier_export::TABLE, 'status', ['id' => $kept->id]));
        // A failed export expires by its age, not by timeexpires: a recent one stays.
        $this->assertSame(1, $this->files_of($failed));
        $this->assertSame(4, $DB->count_records(dossier::TABLE));
    }

    /**
     * An export left queued, running or failed past the retention period expires, with any file it owns;
     * a recent one stays.
     *
     * @return void
     */
    public function test_exports_left_behind_expire_after_the_retention(): void {
        global $DB;
        $this->resetAfterTest();
        $now = $this->mock_clock_with_frozen()->time();
        set_config('exportretentiondays', 7, 'local_evidencehub');
        $requester = $this->getDataGenerator()->create_user();
        $old = $now - 7 * DAYSECS;
        $exports = [];
        foreach ([export::STATUS_QUEUED, export::STATUS_RUNNING, export::STATUS_FAILED] as $status) {
            $exports[$status] = $this->export_with_zip($requester, $now + DAYSECS);
            $DB->update_record(dossier_export::TABLE, (object) [
                'id' => $exports[$status]->id,
                'status' => $status,
                'timecreated' => $old,
            ]);
        }
        $recent = $this->export_with_zip($requester, $now + DAYSECS);
        $DB->update_record(dossier_export::TABLE, (object) [
            'id' => $recent->id,
            'status' => export::STATUS_QUEUED,
            'timecreated' => $old + 1,
        ]);

        $this->assertSame(3, dossier_export::cleanup($now));

        foreach ($exports as $export) {
            $this->assertSame(0, $this->files_of($export));
            $this->assertSame(export::STATUS_EXPIRED, (int) $DB->get_field(dossier_export::TABLE, 'status', ['id' => $export->id]));
        }
        $this->assertSame(1, $this->files_of($recent));
        $this->assertSame(export::STATUS_QUEUED, (int) $DB->get_field(dossier_export::TABLE, 'status', ['id' => $recent->id]));
    }
}
