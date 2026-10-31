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

namespace local_evidencehub;

use local_evidencehub\local\dossier;
use local_evidencehub\local\dossier_builder;
use local_evidencehub\local\dossier_config;
use local_evidencehub\local\status;
use local_evidencehub\output\verify_page;
use local_evidencehub\tests\dossier_pdf_spy;
use local_evidencehub\tests\dossier_scenario;
use local_evidencehub\tests\hub_scenario;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The public verification page: the snapshot only, no identifier or full name, the same answer for an
 * unknown and a malformed code, the revocation, and the live status behind its setting.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(verify_page::class)]
#[CoversClass(dossier::class)]
final class verify_page_test extends \advanced_testcase {
    use hub_scenario;
    use dossier_scenario;

    /** @var \stdClass The worker. */
    private \stdClass $worker;

    /** @var int The competency decided by a plain evidence. */
    private int $plain;

    /** @var int The competency decided by a sensitive evidence. */
    private int $sensitive;

    /** @var \stdClass The dossier row issued about the worker. */
    private \stdClass $dossier;

    /** @var \stdClass What the PDF was given. */
    private \stdClass $printed;

    #[\Override]
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        set_config('enabled', 1, 'core_competency');
        [$c1, $c2] = $this->competencies(2);
        $this->plain = $c1;
        $this->sensitive = $c2;
        $templateid = $this->template([$c1, $c2]);
        $this->worker = $this->worker($templateid, ['firstname' => 'Joana', 'lastname' => 'Pereira', 'idnumber' => 'ID40231']);
        // The only regulatory competency is the one decided by the sensitive evidence.
        $this->regulatory($c2);
        $this->decided($this->worker, $c1);
        $this->decided($this->worker, $c2, 'aso');
        // A requester who sees the sensitive evidence: their PDF states it, the public snapshot does not.
        $this->setUser($this->requester([$this->worker], ['local/evidencehub:viewsensitive']));
        $spy = new dossier_pdf_spy();
        $builder = new dossier_builder(\context_system::instance(), $templateid, $spy);
        $export = $this->getDataGenerator()->get_plugin_generator('local_evidencehub')->create_export([
            'requestedby' => $this->worker->id,
            'templateid' => $templateid,
        ]);
        $zip = \core_files\archive_writer::get_file_writer('verify.zip', \core_files\archive_writer::ZIP_WRITER);
        [$row] = $builder->build($zip, $builder->prepare([$this->worker->id]), $export);
        $this->dossier = $DB->get_record(dossier::TABLE, ['id' => $row->id], '*', MUST_EXIST);
        $this->printed = $spy->rendered[0];
        $this->setUser(null);
    }

    #[\Override]
    protected function tearDown(): void {
        $_GET = [];
        parent::tearDown();
    }

    /**
     * Run the page script, as a visitor without an account, with a code.
     *
     * @param string|null $code The code; none when null.
     * @return string The HTML.
     */
    private function run_page(?string $code): string {
        // The page script reads these from the global scope.
        global $CFG, $DB, $PAGE, $OUTPUT, $USER, $SITE;
        $PAGE = new \moodle_page();
        $OUTPUT = new \bootstrap_renderer();
        $_GET = $code === null ? [] : ['code' => $code];
        $level = ob_get_level();
        ob_start();
        try {
            require($CFG->dirroot . '/local/evidencehub/verify.php');
        } finally {
            $html = '';
            while (ob_get_level() > $level) {
                $html = ob_get_clean() . $html;
            }
        }
        return $html;
    }

    /**
     * A valid code shows the snapshot: the masked name, the template, the status as issued, the hash;
     * never the identifier or the name, which the PDF prints (the control).
     *
     * @return void
     */
    public function test_a_valid_code_shows_the_snapshot_without_identity(): void {
        global $CFG;
        $this->assertSame('ID40231', $this->printed->identifier, 'Control: the PDF prints the identifier.');
        $this->assertStringContainsString('ID40231', (new \local_evidencehub\local\dossier_pdf())->html($this->printed));
        $this->assertStringContainsString('Joana Pereira', $this->printed->fullname, 'Control: the PDF prints the name.');

        $html = $this->run_page($this->dossier->code);

        $this->assertStringContainsString('J*** P***', $html);
        $this->assertStringContainsString($this->dossier->pdfhash, $html);
        $this->assertStringContainsString(get_string('verifyauthentic', 'local_evidencehub'), $html);
        $this->assertStringContainsString(status::label(status::STATUS_VALID), $html);
        $this->assertStringContainsString('2028-01-15', $html);
        $this->assertStringNotContainsString('ID40231', $html);
        $this->assertStringNotContainsString('Joana', $html);
        $this->assertStringNotContainsString('Pereira', $html);
        $this->assertStringNotContainsString(get_string('verifynotfound', 'local_evidencehub'), $html);
        $this->assertStringNotContainsString(get_string('verifystatusnow', 'local_evidencehub'), $html);
        $this->assertStringContainsString('noindex', $CFG->additionalhtmlhead);
    }

    /**
     * An unknown code, a malformed one and an upper-case spelling of a real one: the first two read "not found"
     * alike; the third is the same code.
     *
     * @return void
     */
    public function test_unknown_and_malformed_codes_read_the_same(): void {
        $notfound = get_string('verifynotfound', 'local_evidencehub');
        $dashed = implode('-', str_split($this->dossier->code, 8));
        foreach ([str_repeat('0', 32), 'xyz', str_repeat('g', 32), $this->dossier->code . '0', $dashed] as $code) {
            $html = $this->run_page($code);
            $this->assertStringContainsString($notfound, $html, $code);
            $this->assertStringNotContainsString('J*** P***', $html, $code);
        }
        $html = $this->run_page(strtoupper($this->dossier->code));
        $this->assertStringContainsString('J*** P***', $html);
        $html = $this->run_page(null);
        $this->assertStringNotContainsString($notfound, $html);
        $this->assertStringNotContainsString('J*** P***', $html);
    }

    /**
     * A revoked dossier says so, with the day.
     *
     * @return void
     */
    public function test_a_revoked_dossier_says_so(): void {
        global $DB;
        $DB->update_record(dossier::TABLE, (object) ['id' => $this->dossier->id, 'revoked' => 1, 'timerevoked' => 1798761600]);
        $html = $this->run_page($this->dossier->code);
        $day = userdate(1798761600, get_string('strftimedate', 'core_langconfig'));
        $this->assertStringContainsString(s(get_string('verifyrevoked', 'local_evidencehub', $day)), $html);
        $this->assertStringNotContainsString(get_string('verifyauthentic', 'local_evidencehub'), $html);
    }

    /**
     * With verifylive the page adds today's status, read with no rights: the sensitive pair is restricted,
     * the other one shows its change since issuance.
     *
     * @return void
     */
    public function test_the_live_status_follows_its_setting(): void {
        global $DB;
        $DB->set_field('local_evidencehub_status', 'status', status::STATUS_EXPIRED, [
            'userid' => $this->worker->id,
            'competencyid' => $this->plain,
        ]);
        $export = (new verify_page($this->dossier, true, false))->export_for_template($this->get_renderer());
        $this->assertFalse($export['live']);
        $this->assertArrayNotHasKey('livelabel', $export['competencies'][0]);
        // The requester saw the sensitive pair compliant in the PDF; the public page says only "Restricted".
        $this->assertSame(status::label(status::STATUS_VALID), $this->printed->rows[1]['status']);
        $this->assertSame(get_string('validityrestricted', 'local_evidencehub'), $export['competencies'][1]['statuslabel']);
        $this->assertSame('', $export['competencies'][1]['expiry']);
        $this->assertSame(status::label(status::STATUS_VALID), $export['competencies'][0]['statuslabel']);

        set_config('verifylive', 1, 'local_evidencehub');
        $html = $this->run_page($this->dossier->code);
        $this->assertStringContainsString(get_string('verifystatusnow', 'local_evidencehub'), $html);
        $export = (new verify_page($this->dossier, true, true))->export_for_template($this->get_renderer());
        $this->assertSame(status::label(status::STATUS_VALID), $export['competencies'][0]['statuslabel']);
        $this->assertSame(status::label(status::STATUS_EXPIRED), $export['competencies'][0]['livelabel']);
        $this->assertSame(get_string('validityrestricted', 'local_evidencehub'), $export['competencies'][1]['livelabel']);
    }

    /**
     * The verdict follows the readiness basis read at view time: the snapshot's real readiness by default,
     * the one over the statuses it shows under "shown", where the sensitive row is restricted and unmet.
     *
     * @return void
     */
    public function test_the_verdict_follows_the_readiness_basis(): void {
        $snapshot = json_decode($this->dossier->snapshot, true);
        $this->assertSame(dossier::READINESS_READY, $snapshot['readiness'], 'Control: the real readiness is ready.');
        $this->assertSame(dossier::READINESS_NOTREADY, $snapshot['readinessshown']);
        $ready = dossier::readiness_label(dossier::READINESS_READY);
        $notready = dossier::readiness_label(dossier::READINESS_NOTREADY);

        $real = (new verify_page($this->dossier, true, false, dossier_config::READINESS_REAL))
            ->export_for_template($this->get_renderer());
        $this->assertTrue($real['hasreadiness']);
        $this->assertSame($ready, $real['readiness']);
        $shown = (new verify_page($this->dossier, true, false, dossier_config::READINESS_SHOWN))
            ->export_for_template($this->get_renderer());
        $this->assertTrue($shown['hasreadiness']);
        $this->assertSame($notready, $shown['readiness']);

        // The page script reads the setting itself.
        $html = $this->run_page($this->dossier->code);
        $this->assertStringContainsString('>' . $ready . '</span>', $html);
        $this->assertStringNotContainsString('>' . $notready . '</span>', $html);
        set_config('readinessbasis', dossier_config::READINESS_SHOWN, 'local_evidencehub');
        $html = $this->run_page($this->dossier->code);
        $this->assertStringContainsString('>' . $notready . '</span>', $html);
        $this->assertStringNotContainsString('>' . $ready . '</span>', $html);
    }

    /**
     * A snapshot issued before readinessshown existed states no verdict under "shown", and still its real one
     * under "real".
     *
     * @return void
     */
    public function test_an_old_snapshot_states_no_verdict_under_shown(): void {
        global $DB;
        $snapshot = json_decode($this->dossier->snapshot, true);
        unset($snapshot['readinessshown']);
        $this->dossier->snapshot = json_encode($snapshot);
        $DB->set_field(dossier::TABLE, 'snapshot', $this->dossier->snapshot, ['id' => $this->dossier->id]);
        $heading = get_string('dossierreadiness', 'local_evidencehub');

        $real = (new verify_page($this->dossier, true, false, dossier_config::READINESS_REAL))
            ->export_for_template($this->get_renderer());
        $this->assertTrue($real['hasreadiness']);
        $this->assertSame(dossier::readiness_label(dossier::READINESS_READY), $real['readiness']);
        $this->assertStringContainsString($heading, $this->run_page($this->dossier->code));

        $shown = (new verify_page($this->dossier, true, false, dossier_config::READINESS_SHOWN))
            ->export_for_template($this->get_renderer());
        $this->assertFalse($shown['hasreadiness']);
        set_config('readinessbasis', dossier_config::READINESS_SHOWN, 'local_evidencehub');
        $html = $this->run_page($this->dossier->code);
        $this->assertStringNotContainsString($heading, $html);
        $this->assertStringContainsString('J*** P***', $html, 'Control: the rest of the page is shown.');
    }

    /**
     * The core renderer.
     *
     * @return \renderer_base
     */
    private function get_renderer(): \renderer_base {
        global $PAGE;
        return $PAGE->get_renderer('core');
    }
}
