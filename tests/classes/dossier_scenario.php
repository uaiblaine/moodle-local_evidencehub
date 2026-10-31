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

namespace local_evidencehub\tests;

use core_files\archive_writer;
use local_evidencehub\local\dossier_builder;
use local_evidencehub\local\dossier_plan;
use local_evidencehub\local\export;
use local_evidencehub\local\status;

/**
 * Requesters, decided evidence with files, and a built ZIP read back, for the dossier tests.
 *
 * Use beside {@see hub_scenario}.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait dossier_scenario {
    /**
     * A requester: view, exportdossier and core's templateview on the site, and core's userevidenceview over
     * some workers only.
     *
     * @param array $workers The workers whose evidence the requester reads.
     * @param array $capabilities More capabilities on the site.
     * @return \stdClass The user.
     */
    protected function requester(array $workers, array $capabilities = []): \stdClass {
        $generator = $this->getDataGenerator();
        $user = $generator->create_user();
        $system = \context_system::instance();
        $roleid = $generator->create_role();
        $site = ['local/evidencehub:view', 'local/evidencehub:exportdossier', 'moodle/competency:templateview'];
        foreach (array_merge($site, $capabilities) as $capability) {
            assign_capability($capability, CAP_ALLOW, $roleid, $system->id, true);
        }
        role_assign($roleid, $user->id, $system->id);
        $readerid = $generator->create_role();
        assign_capability('moodle/competency:userevidenceview', CAP_ALLOW, $readerid, $system->id, true);
        foreach ($workers as $worker) {
            role_assign($readerid, $user->id, \context_user::instance($worker->id)->id);
        }
        accesslib_clear_all_caches_for_unit_testing();
        return $user;
    }

    /**
     * A core evidence of a worker with files, its details, and the status row it decides.
     *
     * @param \stdClass $worker The worker.
     * @param int $competencyid The competency.
     * @param string $type The shortname of the evidence's type.
     * @param array $files Path ("/cert.pdf" or "/a/cert.pdf") => content.
     * @param array $meta More columns of local_evidencehub_meta.
     * @param int $status The status the evidence decides.
     * @return \stdClass The meta row.
     */
    protected function decided(
        \stdClass $worker,
        int $competencyid,
        string $type = 'nrcertificate',
        array $files = ['/cert.pdf' => 'certificate'],
        array $meta = [],
        int $status = status::STATUS_VALID
    ): \stdClass {
        global $DB;
        $generator = $this->getDataGenerator()->get_plugin_generator('local_evidencehub');
        $evidence = $this->getDataGenerator()->get_plugin_generator('core_competency')->create_user_evidence([
            'userid' => $worker->id,
        ]);
        $fs = get_file_storage();
        foreach ($files as $path => $content) {
            $fs->create_file_from_string([
                'contextid' => \context_user::instance($worker->id)->id,
                'component' => 'core_competency',
                'filearea' => 'userevidence',
                'itemid' => $evidence->get('id'),
                'filepath' => dirname($path) === '/' ? '/' : dirname($path) . '/',
                'filename' => basename($path),
            ], $content);
        }
        $row = $generator->create_meta($meta + [
            'userid' => $worker->id,
            'userevidenceid' => $evidence->get('id'),
            'typeid' => (int) $DB->get_field('local_evidencehub_type', 'id', ['shortname' => $type], MUST_EXIST),
            'issuedate' => 20260115,
            'expirydate' => 20280115,
            'issuer' => 'Issuer ' . $type,
        ]);
        $generator->create_status([
            'userid' => $worker->id,
            'competencyid' => $competencyid,
            'status' => $status,
            'effectiveexpiry' => $row->expirydate,
            'metaid' => $row->id,
        ]);
        return $row;
    }

    /**
     * Prepare and build the dossiers of some workers as the current user, into a ZIP file, and read it back.
     *
     * @param int $templateid The template.
     * @param array $userids The workers asked for.
     * @param \context|null $context The hub's context; the system when null.
     * @return array plan (dossier_plan), dossiers (rows), files (path => content), manifest (lists of cells).
     */
    protected function build_zip(int $templateid, array $userids, ?\context $context = null): array {
        global $USER;
        $builder = new dossier_builder($context ?? \context_system::instance(), $templateid);
        $plan = $builder->prepare($userids);
        $export = $this->getDataGenerator()->get_plugin_generator('local_evidencehub')->create_export([
            'requestedby' => $USER->id,
            'templateid' => $templateid,
            'status' => export::STATUS_RUNNING,
        ]);
        $zip = archive_writer::get_file_writer('dossiers-test.zip', archive_writer::ZIP_WRITER);
        $dossiers = $builder->build($zip, $plan, $export);
        return ['plan' => $plan, 'dossiers' => $dossiers] + $this->read_zip($zip->get_path_to_zip());
    }

    /**
     * The files of a ZIP and its parsed manifest.
     *
     * @param string $path The ZIP.
     * @return array files (path => content), manifest (lists of cells, without the heading).
     */
    protected function read_zip(string $path): array {
        $archive = new \ZipArchive();
        $this->assertTrue($archive->open($path) === true, 'The ZIP opens.');
        $files = [];
        for ($i = 0; $i < $archive->numFiles; $i++) {
            $name = $archive->getNameIndex($i);
            $files[$name] = $archive->getFromIndex($i);
        }
        $archive->close();
        $this->assertArrayHasKey(dossier_builder::MANIFEST, $files);
        $lines = array_map(
            fn(string $line): array => str_getcsv($line, ',', '"', ''),
            array_filter(explode("\n", $files[dossier_builder::MANIFEST]))
        );
        $this->assertSame(['path', 'bytes', 'sha256', 'note'], array_shift($lines));
        return ['files' => $files, 'manifest' => $lines];
    }

    /**
     * The plan's worker of a user.
     *
     * @param dossier_plan $plan The plan.
     * @param int $userid The worker.
     * @return \stdClass|null
     */
    protected function planned(dossier_plan $plan, int $userid): ?\stdClass {
        foreach ($plan->workers as $worker) {
            if ($worker->userid === $userid) {
                return $worker;
            }
        }
        return null;
    }
}
