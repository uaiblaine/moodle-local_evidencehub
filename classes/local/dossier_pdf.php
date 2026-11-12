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
 * One worker's readiness dossier as a PDF page, built in memory with core's TCPDF.
 *
 * Two TCPDF traps: writeHTML() lays out each table row on its own, so every cell of every row carries its
 * width ({@see self::html()}); and helvetica, a PDF core font, holds Latin-1 only, so a page with any other
 * character is set in freesans, which embeds a subset of a TrueType font and makes the file larger
 * ({@see self::font()}).
 *
 * Not final: the tests subclass it to read the URL handed to the QR code ({@see self::barcode()}).
 *
 * @package    local_evidencehub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dossier_pdf {
    /** @var array The table's columns and their widths, in percent of the page's text width. */
    public const array COLUMNS = [
        'competency' => 20,
        'status' => 14,
        'type' => 16,
        'issued' => 11,
        'expiry' => 11,
        'issuer' => 16,
        'verification' => 12,
    ];

    /**
     * The PDF of one dossier.
     *
     * @param \stdClass $dossier sitename, fullname, identifierlabel, identifier, template, issued (YYYY-MM-DD),
     *     rows (lists of plain cells keyed as COLUMNS), readiness, code, verifyurl; every text plain.
     * @return string The PDF's bytes.
     */
    public function render(\stdClass $dossier): string {
        global $CFG;
        require_once($CFG->libdir . '/pdflib.php');
        $html = $this->html($dossier);
        $text = get_string('dossierverifyhelp', 'local_evidencehub') . "\n" . $dossier->verifyurl . "\n"
            . get_string('dossiercode', 'local_evidencehub', $dossier->code);

        $pdf = new \pdf('P', 'mm', 'A4', true, 'UTF-8');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->SetTitle(get_string('dossiertitle', 'local_evidencehub'));
        $pdf->SetFont(self::font([$html, $text]), '', 9);
        $pdf->AddPage();
        $logo = $this->logo();
        if ($logo !== null) {
            $pdf->Image('@' . $logo, 15, 15, 0, 14);
            $pdf->SetY(32);
        }
        $pdf->writeHTML($html, true, false, true, false, '');

        $y = $pdf->GetY() + 6;
        if ($y > 240) {
            $pdf->AddPage();
            $y = 15;
        }
        $this->barcode($pdf, $dossier->verifyurl, 15, $y, 32);
        $pdf->SetXY(52, $y + 2);
        $pdf->MultiCell(140, 0, $text, 0, 'L');
        return $pdf->Output('', 'S');
    }

    /**
     * Draw the QR code of the verification URL.
     *
     * @param \pdf $pdf The document.
     * @param string $url The verification page of this dossier's code.
     * @param float $x Left edge, in millimetres.
     * @param float $y Top edge, in millimetres.
     * @param float $size Width and height, in millimetres.
     * @return void
     */
    protected function barcode(\pdf $pdf, string $url, float $x, float $y, float $size): void {
        $style = ['border' => false, 'padding' => 0, 'fgcolor' => [0, 0, 0], 'bgcolor' => false];
        $pdf->write2DBarcode($url, 'QRCODE,M', $x, $y, $size, $size, $style, 'N');
    }

    /**
     * The font of a page: helvetica when every text fits Latin-1, freesans otherwise.
     *
     * @param array $texts Every text the page prints.
     * @return string
     */
    public static function font(array $texts): string {
        foreach ($texts as $text) {
            if (preg_match('/[^\x{00}-\x{FF}]/u', (string) $text)) {
                return 'freesans';
            }
        }
        return 'helvetica';
    }

    /**
     * The page's body as the HTML TCPDF lays out: headings, the worker, the table and the readiness.
     *
     * @param \stdClass $dossier As {@see self::render()} takes it.
     * @return string
     */
    public function html(\stdClass $dossier): string {
        $str = fn(string $id, $a = null): string => s(get_string($id, 'local_evidencehub', $a));
        $headings = [
            'competency' => $str('competency'),
            'status' => $str('status'),
            'type' => $str('type'),
            'issued' => $str('issuedate'),
            'expiry' => $str('effectiveexpiry'),
            'issuer' => $str('issuer'),
            'verification' => $str('verification'),
        ];
        $head = '';
        foreach (self::COLUMNS as $key => $width) {
            $head .= '<th width="' . $width . '%">' . $headings[$key] . '</th>';
        }
        $body = '';
        foreach ($dossier->rows as $row) {
            $body .= '<tr>';
            foreach (self::COLUMNS as $key => $width) {
                $body .= '<td width="' . $width . '%">' . s($row[$key] ?? '') . '</td>';
            }
            $body .= '</tr>';
        }
        return '<p>' . s($dossier->sitename) . '</p>'
            . '<h2>' . $str('dossiertitle') . '</h2>'
            . '<p><b>' . $str('worker') . ':</b> ' . s($dossier->fullname)
            . ' &nbsp; <b>' . s($dossier->identifierlabel) . ':</b> ' . s($dossier->identifier) . '<br>'
            . '<b>' . $str('template') . ':</b> ' . s($dossier->template)
            . ' &nbsp; <b>' . $str('dossierissued') . ':</b> ' . s($dossier->issued) . '<br>'
            . '<b>' . $str('dossierreadiness') . ':</b> ' . s($dossier->readiness) . '</p>'
            . '<table border="1" cellpadding="3"><thead><tr style="background-color:#f1f3f5;font-weight:bold">'
            . $head . '</tr></thead><tbody>' . $body . '</tbody></table>';
    }

    /**
     * The site's logo (core's logo setting), when it is a PNG or JPEG image TCPDF can place.
     *
     * @return string|null The image's bytes.
     */
    protected function logo(): ?string {
        if (empty(get_config('core_admin', 'logo'))) {
            return null;
        }
        $files = get_file_storage()->get_area_files(\context_system::instance()->id, 'core_admin', 'logo', 0, 'id', false);
        foreach ($files as $file) {
            if (in_array($file->get_mimetype(), ['image/png', 'image/jpeg'], true)) {
                return $file->get_content();
            }
        }
        return null;
    }
}
