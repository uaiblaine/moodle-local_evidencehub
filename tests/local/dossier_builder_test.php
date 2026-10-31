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

use local_evidencehub\tests\dossier_scenario;
use local_evidencehub\tests\hub_scenario;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The dossier builder: the ZIP's folders, names, PDFs and manifest, the sensitive rule (D7), who is
 * left out, the dossier rows and their snapshot, and the one statement over {files}.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(dossier_builder::class)]
#[CoversClass(dossier_plan::class)]
#[CoversClass(dossier::class)]
final class dossier_builder_test extends \advanced_testcase {
    use hub_scenario;
    use dossier_scenario;
    use \local_evidencehub\tests\scenario;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('enabled', 1, 'core_competency');
    }

    /**
     * One folder per worker named by identifier and name, the PDF, each deciding evidence's files named by
     * identifier, competency ID number and expiry (a counter on a clash), and a manifest with every file's
     * SHA-256 and the worker left out.
     *
     * @return void
     */
    public function test_the_zip_holds_a_folder_per_worker_and_a_manifest(): void {
        global $DB;
        [$c1, $c2] = $this->competencies(2);
        $templateid = $this->template([$c1, $c2]);
        $ana = $this->worker($templateid, ['firstname' => 'Ana', 'lastname' => 'Souza', 'idnumber' => 'W1']);
        $bia = $this->worker($templateid, ['firstname' => 'Bia', 'lastname' => 'Lima', 'idnumber' => '']);
        $hidden = $this->worker($templateid);
        $this->decided($ana, $c1, 'nrcertificate', ['/a/cert.pdf' => 'first', '/b/cert.pdf' => 'second']);
        $this->decided($ana, $c2, 'diploma', ['/diploma.pdf' => 'diploma'], ['expirydate' => null, 'noexpiry' => 1]);
        $this->setUser($this->requester([$ana, $bia]));

        $built = $this->build_zip($templateid, [$ana->id, $bia->id, $hidden->id]);

        $c1number = $DB->get_field('competency', 'idnumber', ['id' => $c1]);
        $c2number = $DB->get_field('competency', 'idnumber', ['id' => $c2]);
        $anafolder = 'W1_Souza-Ana/';
        $biafolder = 'u' . $bia->id . '_Lima-Bia/';
        $expected = [
            $anafolder . 'W1_dossier.pdf',
            $anafolder . 'W1_' . $c1number . '_2028-01-15_cert.pdf',
            $anafolder . 'W1_' . $c1number . '_2028-01-15_cert_2.pdf',
            $anafolder . 'W1_' . $c2number . '_never_diploma.pdf',
            $biafolder . 'u' . $bia->id . '_dossier.pdf',
            dossier_builder::MANIFEST,
        ];
        $this->assertEqualsCanonicalizing($expected, array_keys($built['files']));
        $this->assertSame('first', $built['files'][$anafolder . 'W1_' . $c1number . '_2028-01-15_cert.pdf']);
        $this->assertSame('second', $built['files'][$anafolder . 'W1_' . $c1number . '_2028-01-15_cert_2.pdf']);
        $this->assertStringStartsWith('%PDF', $built['files'][$anafolder . 'W1_dossier.pdf']);

        // Every file but the manifest is listed with its size and SHA-256; then the worker left out.
        $listed = [];
        foreach ($built['manifest'] as [$path, $bytes, $sha256, $note]) {
            if ($note !== '') {
                $this->assertSame(['u' . $hidden->id . '/', '', ''], [$path, $bytes, $sha256]);
                $this->assertSame(get_string('dossiernotavailable', 'local_evidencehub'), $note);
                continue;
            }
            $listed[] = $path;
            $this->assertSame(strlen($built['files'][$path]), (int) $bytes);
            $this->assertSame(hash('sha256', $built['files'][$path]), $sha256);
        }
        $this->assertEqualsCanonicalizing(array_diff($expected, [dossier_builder::MANIFEST]), $listed);
        $this->assertCount(1, array_filter($built['manifest'], fn(array $line): bool => $line[3] !== ''));
        $this->assertSame([(int) $hidden->id], $built['plan']->omitted);
        $this->assertCount(2, $built['dossiers']);
        $this->assertSame(strlen('first') + strlen('second') + strlen('diploma'), $built['plan']->bytes);
    }

    /**
     * The identifier setting reads a text profile field; a worker without a value is "u" and the user id.
     *
     * @return void
     */
    public function test_the_identifier_can_be_a_profile_field(): void {
        [$c1] = $this->competencies(1);
        $templateid = $this->template([$c1]);
        $this->getDataGenerator()->create_custom_profile_field(['datatype' => 'text', 'shortname' => 'cpf', 'name' => 'CPF']);
        $ana = $this->worker($templateid, ['firstname' => 'Ana', 'lastname' => 'Souza', 'idnumber' => 'W1',
            'profile_field_cpf' => '123.456.789-00']);
        $bia = $this->worker($templateid, ['firstname' => 'Bia', 'lastname' => 'Lima', 'idnumber' => 'W2']);
        $this->setUser($this->requester([$ana, $bia]));

        set_config('identifierfield', 'profile_field_cpf', 'local_evidencehub');
        $plan = (new dossier_builder(\context_system::instance(), $templateid))->prepare([$ana->id, $bia->id]);
        $this->assertSame('123.456.789-00', $this->planned($plan, (int) $ana->id)->identifier);
        $this->assertSame('u' . $bia->id, $this->planned($plan, (int) $bia->id)->identifier);

        // A field that is gone falls back to the ID number rather than dropping every worker.
        set_config('identifierfield', 'profile_field_gone', 'local_evidencehub');
        $plan = (new dossier_builder(\context_system::instance(), $templateid))->prepare([$ana->id, $bia->id]);
        $this->assertSame('W1', $this->planned($plan, (int) $ana->id)->identifier);
        $this->assertSame('W2', $this->planned($plan, (int) $bia->id)->identifier);
    }

    /**
     * A sensitive evidence's files stay out by default, stay out without viewsensitive even with the
     * setting on, and go in with both; its row is restricted to a requester without viewsensitive.
     *
     * @return void
     */
    public function test_a_sensitive_file_is_excluded_unless_both_allow_it(): void {
        [$c1, $c2] = $this->competencies(2);
        $templateid = $this->template([$c1, $c2]);
        $ana = $this->worker($templateid, ['idnumber' => 'W1']);
        $this->decided($ana, $c1, 'aso', ['/aso.pdf' => 'health']);
        $this->decided($ana, $c2, 'nrcertificate', ['/nr.pdf' => 'training']);
        $plain = $this->requester([$ana]);
        $sensitive = $this->requester([$ana], ['local/evidencehub:viewsensitive']);
        $named = fn(array $built, string $suffix): array => array_values(preg_grep(
            '/_' . preg_quote($suffix, '/') . '$/',
            array_keys($built['files'])
        ));

        // Default: the setting is off, for a requester holding viewsensitive too.
        $this->setUser($sensitive);
        $built = $this->build_zip($templateid, [$ana->id]);
        $this->assertSame([], $named($built, 'aso.pdf'));
        $this->assertCount(1, $named($built, 'nr.pdf'), 'Control: the file of a plain type goes in.');
        $this->assertSame(status::STATUS_VALID, $built['plan']->workers[0]->rows[0]->code);
        // That requester's PDF states the sensitive competency; the public snapshot never does.
        $cells = dossier_builder::pdf_row($built['plan']->workers[0]->rows[0]);
        $this->assertSame(status::label(status::STATUS_VALID), $cells['status']);
        $snapshot = json_decode($built['dossiers'][0]->snapshot, true);
        $this->assertSame([status::EFFECTIVE_RESTRICTED, status::STATUS_VALID], array_column($snapshot['competencies'], 'status'));
        $this->assertSame([null, 20280115], array_column($snapshot['competencies'], 'expiry'));
        $this->assertSame(dossier::READINESS_READY, $snapshot['readiness']);

        // The setting on, without viewsensitive: still out, and the row is restricted.
        set_config('includesensitivefiles', 1, 'local_evidencehub');
        $this->setUser($plain);
        $built = $this->build_zip($templateid, [$ana->id]);
        $this->assertSame([], $named($built, 'aso.pdf'));
        $row = $built['plan']->workers[0]->rows[0];
        $this->assertSame(status::EFFECTIVE_RESTRICTED, $row->code);
        // The readiness is the worker's real one, the same for both requesters.
        $this->assertSame(dossier::READINESS_READY, $built['plan']->workers[0]->readiness);
        $snapshot = json_decode($built['dossiers'][0]->snapshot, true);
        $this->assertSame([status::EFFECTIVE_RESTRICTED, status::STATUS_VALID], array_column($snapshot['competencies'], 'status'));
        $cells = dossier_builder::pdf_row($row);
        $this->assertSame(get_string('validityrestricted', 'local_evidencehub'), $cells['status']);
        $this->assertSame(['', '', '', ''], [$cells['type'], $cells['expiry'], $cells['issuer'], $cells['verification']]);

        // The worker exporting their own dossier sees the evidence but does not hold viewsensitive: still out.
        $this->grant($ana, [
            'local/evidencehub:view',
            'local/evidencehub:exportdossier',
            'moodle/competency:userevidenceview',
            'moodle/competency:templateview',
        ]);
        $this->setUser($ana);
        $built = $this->build_zip($templateid, [$ana->id]);
        $this->assertSame(status::STATUS_VALID, $built['plan']->workers[0]->rows[0]->code);
        $this->assertSame([], $named($built, 'aso.pdf'));

        // Both: the file goes in.
        $this->setUser($sensitive);
        $built = $this->build_zip($templateid, [$ana->id]);
        $found = $named($built, 'aso.pdf');
        $this->assertCount(1, $found);
        $this->assertSame('health', $built['files'][$found[0]]);
    }

    /**
     * The PDF's readiness follows the basis: the worker's real readiness, or that of the statuses the
     * requester is shown. The snapshot keeps both, and the worker's real readiness stays the real one.
     *
     * @return void
     */
    public function test_the_pdf_readiness_follows_the_basis(): void {
        [$c1] = $this->competencies(1);
        $templateid = $this->template([$c1]);
        $this->regulatory($c1);
        $ana = $this->worker($templateid);
        // The only regulatory competency is decided by a sensitive evidence and is compliant.
        $this->decided($ana, $c1, 'aso');
        $ready = dossier::readiness_label(dossier::READINESS_READY);
        $notready = dossier::readiness_label(dossier::READINESS_NOTREADY);
        $printed = function (\stdClass $requester) use ($templateid, $ana): array {
            $this->setUser($requester);
            $spy = new \local_evidencehub\tests\dossier_pdf_spy();
            $builder = new dossier_builder(\context_system::instance(), $templateid, $spy);
            $plan = $builder->prepare([$ana->id]);
            $export = $this->getDataGenerator()->get_plugin_generator('local_evidencehub')->create_export([
                'requestedby' => $requester->id,
                'templateid' => $templateid,
            ]);
            $zip = \core_files\archive_writer::get_file_writer('basis.zip', \core_files\archive_writer::ZIP_WRITER);
            $dossiers = $builder->build($zip, $plan, $export);
            return [$spy->rendered[0]->readiness, $plan->workers[0], json_decode($dossiers[0]->snapshot, true)];
        };
        $plain = $this->requester([$ana]);
        $sensitive = $this->requester([$ana], ['local/evidencehub:viewsensitive']);

        // Real, the default: every requester's PDF says ready.
        [$pdf, $worker, $snapshot] = $printed($plain);
        $this->assertSame(status::EFFECTIVE_RESTRICTED, $worker->rows[0]->code, 'Control: the row is restricted.');
        $this->assertSame($ready, $pdf);
        $this->assertSame(dossier::READINESS_READY, $snapshot['readiness']);
        $this->assertSame(dossier::READINESS_NOTREADY, $snapshot['readinessshown'], 'The snapshot shows the row restricted.');

        // Shown: the restricted requester reads not ready; the real readiness is untouched.
        set_config('readinessbasis', dossier_config::READINESS_SHOWN, 'local_evidencehub');
        [$pdf, $worker, $snapshot] = $printed($plain);
        $this->assertSame($notready, $pdf);
        $this->assertSame(dossier::READINESS_READY, $worker->readiness);
        $this->assertSame(dossier::READINESS_READY, $snapshot['readiness']);
        $this->assertSame(dossier::READINESS_NOTREADY, $snapshot['readinessshown']);

        // Control: the requester who is shown the competency reads ready under the same basis, and the snapshot,
        // issued for everyone, is the same.
        [$pdf, $worker, $snapshot] = $printed($sensitive);
        $this->assertSame(status::STATUS_VALID, $worker->rows[0]->code);
        $this->assertSame($ready, $pdf);
        $this->assertSame(dossier::READINESS_NOTREADY, $snapshot['readinessshown']);
    }

    /**
     * A worker the requester may not read, without an active plan from the template, or deleted, is left out.
     *
     * @return void
     */
    public function test_workers_outside_the_requesters_reach_are_omitted(): void {
        global $DB;
        [$c1] = $this->competencies(1);
        $templateid = $this->template([$c1]);
        $read = $this->worker($templateid);
        $unread = $this->worker($templateid);
        $noplan = $this->getDataGenerator()->create_user();
        $gone = $this->worker($templateid);
        $this->setUser($this->requester([$read, $noplan, $gone]));
        // Deleted, with the plan core would leave behind a half-finished deletion.
        $DB->set_field('user', 'deleted', 1, ['id' => $gone->id]);

        $plan = (new dossier_builder(\context_system::instance(), $templateid))->prepare(
            [$read->id, $unread->id, $noplan->id, $gone->id]
        );
        $this->assertSame([(int) $read->id], array_column($plan->workers, 'userid'));
        $this->assertEqualsCanonicalizing([(int) $unread->id, (int) $noplan->id, (int) $gone->id], $plan->omitted);
    }

    /**
     * Without exportdossier, view, or the template on offer in the context, nothing is prepared.
     *
     * @return void
     */
    public function test_the_requester_needs_both_capabilities_and_the_template(): void {
        [$c1] = $this->competencies(1);
        $templateid = $this->template([$c1]);
        $worker = $this->worker($templateid);
        $category = $this->getDataGenerator()->create_category();
        $categorycontext = \context_coursecat::instance($category->id);

        $all = [
            'local/evidencehub:view',
            'local/evidencehub:exportdossier',
            'moodle/competency:userevidenceview',
            'moodle/competency:templateview',
        ];
        foreach (['local/evidencehub:exportdossier', 'local/evidencehub:view'] as $missing) {
            $this->setUser($this->viewer(array_diff($all, [$missing])));
            try {
                (new dossier_builder(\context_system::instance(), $templateid))->prepare([$worker->id]);
                $this->fail('Prepared without ' . $missing);
            } catch (\required_capability_exception $e) {
                $this->assertStringContainsString(get_capability_string($missing), $e->getMessage());
            }
        }

        // A site template is not offered in a category.
        $this->setUser($this->requester([$worker]));
        $plan = (new dossier_builder(\context_system::instance(), $templateid))->prepare([$worker->id]);
        $this->assertCount(1, $plan->workers, 'Control: the requester prepares on the site.');
        $this->expectException(\moodle_exception::class);
        (new dossier_builder($categorycontext, $templateid))->prepare([$worker->id]);
    }

    /**
     * Each dossier row stores its code, the PDF's hash and a snapshot with the masked name only; readiness
     * follows the policy kind.
     *
     * @return void
     */
    public function test_the_rows_hold_the_hash_and_a_snapshot_without_identity(): void {
        global $DB, $USER;
        [$c1, $c2] = $this->competencies(2);
        $templateid = $this->template([$c1, $c2]);
        $ana = $this->worker($templateid, ['firstname' => 'Ana Clara', 'lastname' => 'Souza', 'idnumber' => 'ID7788']);
        $this->decided($ana, $c1);
        $this->setUser($this->requester([$ana]));

        $built = $this->build_zip($templateid, [$ana->id]);
        $row = $DB->get_record(dossier::TABLE, ['id' => $built['dossiers'][0]->id], '*', MUST_EXIST);
        $pdf = $built['files']['ID7788_Souza-Ana Clara/ID7788_dossier.pdf'];
        $this->assertSame(hash('sha256', $pdf), $row->pdfhash);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $row->code);
        $this->assertSame((int) $USER->id, (int) $row->createdby);
        $this->assertSame((int) $ana->id, (int) $row->userid);
        $this->assertSame(0, (int) $row->revoked);

        $this->assertStringNotContainsString('ID7788', $row->snapshot);
        $this->assertStringNotContainsString('Souza', $row->snapshot);
        $this->assertStringNotContainsString('Clara', $row->snapshot);
        $snapshot = json_decode($row->snapshot, true);
        $this->assertSame('A*** C*** S***', $snapshot['name']);
        $this->assertSame([$c1, $c2], array_column($snapshot['competencies'], 'id'));
        $this->assertSame([status::STATUS_VALID, status::EFFECTIVE_MISSING], array_column($snapshot['competencies'], 'status'));
        $this->assertSame([20280115, null], array_column($snapshot['competencies'], 'expiry'));
        // C2 is missing under the default technical policy: at risk, not stopped.
        $this->assertSame(dossier::READINESS_ATRISK, $snapshot['readiness']);
        $this->assertSame(dossier::READINESS_ATRISK, $built['plan']->workers[0]->readiness);
    }

    /**
     * Readiness: ready when every competency is met, not ready on an unmet regulatory one, at risk otherwise;
     * a restricted competency is unmet.
     *
     * @return void
     */
    public function test_readiness_by_policy_kind(): void {
        $this->assertSame(dossier::READINESS_READY, dossier::readiness([
            [status::STATUS_VALID, policy::KIND_REGULATORY],
            [status::STATUS_CRITICAL, policy::KIND_TECHNICAL],
        ]));
        $this->assertSame(dossier::READINESS_ATRISK, dossier::readiness([
            [status::STATUS_VALID, policy::KIND_REGULATORY],
            [status::STATUS_EXPIRED, policy::KIND_BEHAVIOURAL],
        ]));
        $this->assertSame(dossier::READINESS_NOTREADY, dossier::readiness([
            [status::STATUS_EXPIRED, policy::KIND_TECHNICAL],
            [status::EFFECTIVE_RESTRICTED, policy::KIND_REGULATORY],
        ]));
    }

    /**
     * The evidence files of every worker are read in one statement, whatever their number.
     *
     * @return void
     */
    public function test_the_files_are_read_in_one_statement(): void {
        global $DB;
        [$c1, $c2] = $this->competencies(2);
        $templateid = $this->template([$c1, $c2]);
        $ids = [];
        $contexts = [];
        for ($i = 0; $i < 3; $i++) {
            $worker = $this->worker($templateid);
            $ids[] = (int) $this->decided($worker, $c1, 'nrcertificate', ['/a.pdf' => 'a', '/b.pdf' => 'b'])->userevidenceid;
            $ids[] = (int) $this->decided($worker, $c2, 'nrcertificate', ['/c.pdf' => 'c'])->userevidenceid;
            $contexts[] = \context_user::instance($worker->id)->id;
        }

        $before = $DB->perf_get_reads();
        $files = dossier_builder::files($ids, $contexts);
        $this->assertSame(1, $DB->perf_get_reads() - $before);
        $this->assertCount(6, $files);
        $this->assertSame(9, array_sum(array_map('count', $files)));
        $this->assertSame(['a.pdf', 'b.pdf'], array_map(fn(\stored_file $f): string => $f->get_filename(), $files[$ids[0]]));
    }

    /**
     * A manifest cell a spreadsheet would read as a formula starts with a single quote.
     *
     * @return void
     */
    public function test_manifest_cells_never_start_a_formula(): void {
        [$c1] = $this->competencies(1);
        $templateid = $this->template([$c1]);
        $worker = $this->worker($templateid, ['firstname' => 'Ana', 'lastname' => 'Souza', 'idnumber' => '=1+1']);
        $this->setUser($this->requester([$worker]));

        $built = $this->build_zip($templateid, [$worker->id]);

        $this->assertArrayHasKey('=1+1_Souza-Ana/=1+1_dossier.pdf', $built['files']);
        $paths = array_column($built['manifest'], 0);
        $this->assertSame(["'=1+1_Souza-Ana/=1+1_dossier.pdf"], $paths);
        foreach ($built['manifest'] as $line) {
            foreach ($line as $cell) {
                $this->assertDoesNotMatchRegularExpression('/^[=+\-@\t\r]/', $cell);
            }
        }
    }

    /**
     * Each dossier row is stored before its PDF enters the archive, so a streamed PDF's code resolves.
     *
     * @return void
     */
    public function test_each_row_is_stored_before_its_pdf(): void {
        global $USER;
        [$c1] = $this->competencies(1);
        $templateid = $this->template([$c1]);
        $first = $this->worker($templateid, ['idnumber' => 'W1', 'lastname' => 'A']);
        $second = $this->worker($templateid, ['idnumber' => 'W2', 'lastname' => 'B']);
        $this->setUser($this->requester([$first, $second]));
        $builder = new dossier_builder(\context_system::instance(), $templateid);
        $plan = $builder->prepare([$first->id, $second->id]);
        $export = $this->getDataGenerator()->get_plugin_generator('local_evidencehub')->create_export([
            'requestedby' => $USER->id,
            'templateid' => $templateid,
        ]);
        $zip = \local_evidencehub\tests\recording_zip_writer::file_instance('order.zip');

        $builder->build($zip, $plan, $export);

        $pdfs = array_filter($zip->rowsat, fn(string $name): bool => str_ends_with($name, '_dossier.pdf'), ARRAY_FILTER_USE_KEY);
        $this->assertSame([1, 2], array_values($pdfs));
    }

    /**
     * A name taken gets a counter before its extension.
     *
     * @return void
     */
    public function test_names_are_unique(): void {
        $taken = [];
        $this->assertSame('a.pdf', dossier_builder::unique('a.pdf', $taken));
        $this->assertSame('a_2.pdf', dossier_builder::unique('a.pdf', $taken));
        $this->assertSame('a_3.pdf', dossier_builder::unique('a.pdf', $taken));
        $this->assertSame('folder', dossier_builder::unique('folder', $taken));
        $this->assertSame('folder_2', dossier_builder::unique('folder', $taken));
    }

    /**
     * The masked name keeps the first letter of each part.
     *
     * @return void
     */
    public function test_the_masked_name(): void {
        $this->assertSame('J*** d*** S***', dossier::mask_name('João', 'da  Silva'));
        $this->assertSame('Á***', dossier::mask_name('Álvaro', ''));
    }
}
