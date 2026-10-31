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

use local_evidencehub\local\dossier_pdf;

/**
 * A dossier PDF renderer that records what it is given and the URLs it hands to the QR code.
 *
 * @package    local_evidencehub
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class dossier_pdf_spy extends dossier_pdf {
    /** @var array The URLs handed to write2DBarcode(). */
    public array $urls = [];

    /** @var array The dossiers rendered. */
    public array $rendered = [];

    /**
     * Record the dossier, then render it.
     *
     * @param \stdClass $dossier The dossier.
     * @return string
     */
    #[\Override]
    public function render(\stdClass $dossier): string {
        $this->rendered[] = $dossier;
        return parent::render($dossier);
    }

    /**
     * Record the URL, then draw it.
     *
     * @param \pdf $pdf The document.
     * @param string $url The URL.
     * @param float $x Left edge.
     * @param float $y Top edge.
     * @param float $size Width and height.
     * @return void
     */
    #[\Override]
    protected function barcode(\pdf $pdf, string $url, float $x, float $y, float $size): void {
        $this->urls[] = $url;
        parent::barcode($pdf, $url, $x, $y, $size);
    }
}
