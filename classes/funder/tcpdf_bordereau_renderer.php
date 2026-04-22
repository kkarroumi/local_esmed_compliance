<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * TCPDF-backed bordereau renderer.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_esmed_compliance\funder;

use pdf;

/**
 * Produce a PDF bordereau financeur with Moodle's bundled TCPDF.
 *
 * Layout: organisation block, funder block (type / dossier / period /
 * planned hours / action label / OPCO name when applicable), course
 * block, learners table (rank, last name, first name, email, sessions,
 * duration hh:mm), totals, signatory, and a QR-coded verification URL.
 *
 * Landscape A4 so the learners table fits comfortably without wrapping.
 */
class tcpdf_bordereau_renderer implements bordereau_renderer {
    /**
     * Inherits from parent.
     */
    public function extension(): string {
        return 'pdf';
    }

    /**
     * Inherits from parent.
     */
    public function mime_type(): string {
        return 'application/pdf';
    }

    /**
     * Produce a PDF for the given bordereau payload.
     *
     * @param bordereau_payload $payload
     * @param string|null $verificationtoken
     * @param string|null $verificationurl
     * @return string Raw PDF bytes.
     */
    public function render(
        bordereau_payload $payload,
        ?string $verificationtoken = null,
        ?string $verificationurl = null
    ): string {
        global $CFG;

        require_once($CFG->libdir . '/pdflib.php');

        $doc = new pdf('L', 'mm', 'A4', true, 'UTF-8');
        $doc->setPrintHeader(false);
        $doc->setPrintFooter(false);
        $doc->SetCreator('local_esmed_compliance');
        $doc->SetAuthor($payload->organisation['legal_name'] ?: 'ESMED Compliance');
        $doc->SetTitle('Bordereau financeur');
        $doc->SetMargins(12, 12, 12);
        $doc->SetAutoPageBreak(true, 12);
        $doc->AddPage();

        $doc->SetFont('helvetica', 'B', 16);
        $doc->Cell(0, 9, 'Bordereau financeur', 0, 1, 'C');

        $doc->SetFont('helvetica', '', 9);
        $doc->MultiCell(0, 4, 'Récapitulatif des heures réalisées par apprenant', 0, 'C');
        $doc->Ln(3);

        $doc->SetFont('helvetica', 'B', 11);
        $doc->Cell(0, 6, 'Organisme de formation', 0, 1);
        $doc->SetFont('helvetica', '', 10);
        $doc->MultiCell(0, 5, self::organisation_block($payload->organisation), 0, 'L');
        $doc->Ln(2);

        $doc->SetFont('helvetica', 'B', 11);
        $doc->Cell(0, 6, 'Financeur', 0, 1);
        $doc->SetFont('helvetica', '', 10);
        $doc->MultiCell(0, 5, self::funder_block($payload), 0, 'L');
        $doc->Ln(2);

        $doc->SetFont('helvetica', 'B', 11);
        $doc->Cell(0, 6, 'Action de formation', 0, 1);
        $doc->SetFont('helvetica', '', 10);
        $doc->MultiCell(0, 5, self::course_block($payload), 0, 'L');
        $doc->Ln(2);

        $doc->SetFont('helvetica', 'B', 11);
        $doc->Cell(0, 6, 'Apprenants', 0, 1);
        self::learners_table($doc, $payload->learners);
        $doc->Ln(2);

        self::totals_block($doc, $payload);

        self::signature_and_token($doc, $payload, $verificationtoken, $verificationurl);

        return (string) $doc->Output('bordereau.pdf', 'S');
    }

    /**
     * Render the organisation identification block shown at the top of the bordereau.
     *
     * @param array $org
     */
    private static function organisation_block(array $org): string {
        $lines = [];
        if (!empty($org['legal_name'])) {
            $lines[] = (string) $org['legal_name'];
        }
        if (!empty($org['address'])) {
            $lines[] = (string) $org['address'];
        }
        $trailing = [];
        if (!empty($org['siret'])) {
            $trailing[] = 'SIRET : ' . $org['siret'];
        }
        if (!empty($org['nda'])) {
            $trailing[] = 'NDA : ' . $org['nda'];
        }
        if (!empty($trailing)) {
            $lines[] = implode(' — ', $trailing);
        }
        return implode("\n", $lines) ?: '—';
    }

    /**
     * Render the funder-details block (type, dossier, period) for the bordereau.
     *
     * @param bordereau_payload $payload
     * @return string
     */
    private static function funder_block(bordereau_payload $payload): string {
        $funder = $payload->funder;
        $lines = [];
        $lines[] = 'Type : ' . self::funder_label((string) $funder['type']);
        if (!empty($funder['dossier_number'])) {
            $lines[] = 'Dossier : ' . $funder['dossier_number'];
        }
        if (!empty($funder['opco_name'])) {
            $lines[] = 'OPCO : ' . $funder['opco_name'];
        }
        if (!empty($funder['action_intitule'])) {
            $lines[] = 'Intitulé de l\'action : ' . $funder['action_intitule'];
        }
        if (isset($funder['hours_planned']) && $funder['hours_planned'] !== null) {
            $lines[] = 'Heures prévues : ' . self::format_number((float) $funder['hours_planned']) . ' h';
        }
        if ($payload->periodstart && $payload->periodend) {
            $lines[] = 'Période : du ' . userdate($payload->periodstart, get_string('strftimedate', 'langconfig'))
                     . ' au ' . userdate($payload->periodend, get_string('strftimedate', 'langconfig'));
        }
        return implode("\n", $lines);
    }

    /**
     * Render the course-details block summarising the training action.
     *
     * @param bordereau_payload $payload
     * @return string
     */
    private static function course_block(bordereau_payload $payload): string {
        $course = $payload->course;
        $lines = [(string) ($course['fullname'] ?? '')];
        if (!empty($course['shortname'])) {
            $lines[] = 'Code : ' . $course['shortname'];
        }
        $lines[] = 'Nombre d\'apprenants : ' . $payload->learnercount;
        $lines[] = 'Durée totale réalisée : ' . self::format_duration($payload->totalseconds);
        return implode("\n", $lines);
    }

    /**
     * Render the learners table listing each enrolled apprenant and their totals.
     *
     * @param pdf $doc
     * @param array $learners
     */
    private static function learners_table(pdf $doc, array $learners): void {
        $doc->SetFont('helvetica', 'B', 9);
        $doc->Cell(8, 6, '#', 1, 0, 'C');
        $doc->Cell(55, 6, 'Nom', 1, 0, 'C');
        $doc->Cell(55, 6, 'Prénom', 1, 0, 'C');
        $doc->Cell(75, 6, 'Email', 1, 0, 'C');
        $doc->Cell(30, 6, 'Identifiant', 1, 0, 'C');
        $doc->Cell(20, 6, 'Sessions', 1, 0, 'C');
        $doc->Cell(30, 6, 'Durée', 1, 1, 'C');

        $doc->SetFont('helvetica', '', 9);
        if (empty($learners)) {
            $doc->Cell(273, 6, 'Aucun apprenant inscrit', 1, 1, 'C');
            return;
        }
        $rank = 1;
        foreach ($learners as $learner) {
            $doc->Cell(8, 6, (string) $rank++, 1, 0, 'C');
            $doc->Cell(55, 6, (string) $learner['lastname'], 1, 0, 'L');
            $doc->Cell(55, 6, (string) $learner['firstname'], 1, 0, 'L');
            $doc->Cell(75, 6, (string) $learner['email'], 1, 0, 'L');
            $doc->Cell(30, 6, (string) $learner['idnumber'], 1, 0, 'L');
            $doc->Cell(20, 6, (string) $learner['sessions'], 1, 0, 'C');
            $doc->Cell(30, 6, self::format_duration((int) $learner['duration']), 1, 1, 'R');
        }
    }

    /**
     * Render the grand-total row under the learners table.
     *
     * @param pdf $doc
     * @param bordereau_payload $payload
     */
    private static function totals_block(pdf $doc, bordereau_payload $payload): void {
        $doc->SetFont('helvetica', 'B', 10);
        $doc->Cell(223, 6, 'Total général', 1, 0, 'R');
        $doc->Cell(50, 6, self::format_duration($payload->totalseconds), 1, 1, 'R');
        $doc->Ln(3);
    }

    /**
     * Render the signatory line, verification token and QR code at the foot of the bordereau.
     *
     * @param pdf $doc
     * @param bordereau_payload $payload
     * @param string|null $verificationtoken
     * @param string|null $verificationurl
     */
    private static function signature_and_token(
        pdf $doc,
        bordereau_payload $payload,
        ?string $verificationtoken,
        ?string $verificationurl
    ): void {
        $doc->SetFont('helvetica', '', 10);
        $signatory = trim(($payload->organisation['signatory_name'] ?? '') . ' — '
            . ($payload->organisation['signatory_role'] ?? ''));
        if ($signatory !== ' — ') {
            $doc->MultiCell(0, 5, $signatory, 0, 'L');
        }
        $generated = 'Généré le ' . userdate($payload->generatedat, get_string('strftimedatetime', 'langconfig'));
        $doc->MultiCell(0, 5, $generated, 0, 'L');
        $doc->Ln(3);

        if ($verificationtoken !== null) {
            $doc->SetFont('helvetica', '', 8);
            $doc->MultiCell(0, 4, 'Jeton de vérification : ' . $verificationtoken, 0, 'L');
            if ($verificationurl !== null) {
                $doc->MultiCell(0, 4, 'Vérification en ligne : ' . $verificationurl, 0, 'L');
                $doc->write2DBarcode($verificationurl, 'QRCODE,L', null, null, 28, 28);
            }
        }
    }

    /**
     * Human-readable French label for a funder type code.
     *
     * @param string $code
     * @return string
     */
    private static function funder_label(string $code): string {
        switch ($code) {
            case funder_link_repository::FUNDER_CPF:
                return 'CPF (Compte Personnel de Formation)';
            case funder_link_repository::FUNDER_FT:
                return 'France Travail';
            case funder_link_repository::FUNDER_OPCO:
                return 'OPCO';
            case funder_link_repository::FUNDER_REGION:
                return 'Conseil régional';
            case funder_link_repository::FUNDER_AUTRE:
                return 'Autre';
            default:
                return $code;
        }
    }

    /**
     * Format a duration in hours:minutes — "2 h 30 min".
     *
     * @param int $seconds
     * @return string
     */
    private static function format_duration(int $seconds): string {
        $seconds = max(0, $seconds);
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        if ($h > 0 && $m > 0) {
            return $h . ' h ' . str_pad((string) $m, 2, '0', STR_PAD_LEFT) . ' min';
        }
        if ($h > 0) {
            return $h . ' h';
        }
        return $m . ' min';
    }

    /**
     * Format a numeric value using the French decimal convention.
     *
     * @param float|int|null $value
     * @return string
     */
    private static function format_number($value): string {
        if ($value === null) {
            return '—';
        }
        return number_format((float) $value, 2, ',', ' ');
    }
}
