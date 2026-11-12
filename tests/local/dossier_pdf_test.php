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

use local_evidencehub\tests\dossier_pdf_spy;
use local_evidencehub\tests\dossier_scenario;
use local_evidencehub\tests\hub_scenario;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The dossier's PDF page: the verification URL in its QR code, the font for its characters, and a width on
 * every table cell.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(dossier_pdf::class)]
final class dossier_pdf_test extends \advanced_testcase {
    use hub_scenario;
    use dossier_scenario;

    /**
     * A dossier as dossier_pdf::render() takes it.
     *
     * @param array $overrides Values to replace.
     * @return \stdClass
     */
    private function dossier(array $overrides = []): \stdClass {
        $code = str_repeat('ab', 16);
        return (object) ($overrides + [
            'sitename' => 'Site',
            'fullname' => 'Ana Souza',
            'identifierlabel' => 'ID number',
            'identifier' => 'W1',
            'template' => 'Electricians',
            'issued' => '2026-10-30',
            'rows' => [
                ['competency' => 'Heights', 'status' => 'Compliant', 'type' => 'Certificate', 'issued' => '2026-01-15',
                    'expiry' => '2028-01-15', 'issuer' => 'Centre', 'verification' => 'Verified'],
                ['competency' => 'Fire', 'status' => 'Pending', 'type' => '', 'issued' => '', 'expiry' => '',
                    'issuer' => '', 'verification' => ''],
            ],
            'readiness' => 'Ready',
            'code' => $code,
            'verifyurl' => 'https://example.com/local/evidencehub/verify.php?code=' . $code,
        ]);
    }

    /**
     * The page is a PDF, and its QR code holds the verification URL of the dossier's code.
     *
     * @return void
     */
    public function test_the_qr_code_holds_the_verification_url(): void {
        $this->resetAfterTest();
        $pdf = new dossier_pdf_spy();
        $dossier = $this->dossier();
        $bytes = $pdf->render($dossier);
        $this->assertStringStartsWith('%PDF', $bytes);
        $this->assertSame([$dossier->verifyurl], $pdf->urls);
    }

    /**
     * The builder hands each page the verify.php URL of the code its row stores.
     *
     * @return void
     */
    public function test_the_builder_hands_the_rows_code_to_the_qr_code(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('enabled', 1, 'core_competency');
        [$c1] = $this->competencies(1);
        $templateid = $this->template([$c1]);
        $worker = $this->worker($templateid, ['idnumber' => 'W1']);
        $this->setUser($this->requester([$worker]));
        $spy = new dossier_pdf_spy();
        $builder = new dossier_builder(\context_system::instance(), $templateid, $spy);
        $plan = $builder->prepare([$worker->id]);
        $export = $this->getDataGenerator()->get_plugin_generator('local_evidencehub')->create_export([
            'requestedby' => $worker->id,
            'templateid' => $templateid,
        ]);
        $zip = \core_files\archive_writer::get_file_writer('qr.zip', \core_files\archive_writer::ZIP_WRITER);
        [$dossier] = $builder->build($zip, $plan, $export);

        $code = $DB->get_field(dossier::TABLE, 'code', ['id' => $dossier->id]);
        $url = (new \moodle_url('/local/evidencehub/verify.php', ['code' => $code]))->out(false);
        $this->assertSame([$url], $spy->urls);
        $this->assertSame($code, $spy->rendered[0]->code);
        $this->assertSame('W1', $spy->rendered[0]->identifier);
    }

    /**
     * Helvetica while every text fits Latin-1, freesans as soon as one does not.
     *
     * @return void
     */
    public function test_the_font_follows_the_characters(): void {
        $this->assertSame('helvetica', dossier_pdf::font(['Ana Souza', 'Conceição', 'São Paulo, 1º']));
        $this->assertSame('freesans', dossier_pdf::font(['Ana Souza', 'Łukasz']));
        $this->assertSame('freesans', dossier_pdf::font(['Work at height — NR35']));
        $this->assertSame('helvetica', dossier_pdf::font([]));
    }

    /**
     * Every cell of every row, the heading's included, carries its width: TCPDF lays out each row alone.
     *
     * @return void
     */
    public function test_every_cell_has_its_width(): void {
        $this->resetAfterTest();
        $html = (new dossier_pdf())->html($this->dossier());
        preg_match_all('/<t[dh](\s[^>]*)?>/', $html, $cells);
        $this->assertCount(3 * count(dossier_pdf::COLUMNS), $cells[0]);
        foreach ($cells[0] as $cell) {
            $this->assertMatchesRegularExpression('/ width="\d+%"/', $cell);
        }
        $this->assertSame(100, array_sum(dossier_pdf::COLUMNS));
    }

    /**
     * The page's texts are escaped once.
     *
     * @return void
     */
    public function test_texts_are_escaped(): void {
        $this->resetAfterTest();
        $html = (new dossier_pdf())->html($this->dossier(['fullname' => 'Ana <b>&</b>', 'template' => 'A & B']));
        $this->assertStringContainsString('Ana &lt;b&gt;&amp;&lt;/b&gt;', $html);
        $this->assertStringContainsString('A &amp; B', $html);
    }
}
