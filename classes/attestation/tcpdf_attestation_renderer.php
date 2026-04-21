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
 * TCPDF-backed attestation renderer.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_esmed_compliance\attestation;

use pdf;

/**
 * Produce a PDF attestation d'assiduité with Moodle's bundled TCPDF.
 *
 * The layout is deliberately minimal and self-contained: a title,
 * the organisation block, the learner block, the totals, the session
 * table and the footer carrying the verification token. A QR-coded
 * verification URL is embedded as a 2D barcode if a URL is provided.
 *
 * French labels are used throughout because the attestation is a
 * French regulatory document (article D.6353-4 of the Code du travail).
 */
class tcpdf_attestation_renderer implements attestation_renderer {
    /**
     * Inherits from parent.
     */
    public function render(
        attestation_payload $payload,
        ?string $verificationtoken = null,
        ?string $verificationurl = null
    ): string {
        global $CFG;

        require_once($CFG->libdir . '/pdflib.php');

        $doc = new pdf('P', 'mm', 'A4', true, 'UTF-8');
        $doc->setPrintHeader(false);
        $doc->setPrintFooter(false);
        $doc->SetCreator('local_esmed_compliance');
        $doc->SetAuthor($payload->organisation['legal_name'] ?: 'ESMED Compliance');
        $doc->SetTitle('Attestation d\'assiduité');
        $doc->SetMargins(15, 15, 15);
        $doc->SetAutoPageBreak(true, 15);
        $doc->AddPage();

        $doc->SetFont('helvetica', 'B', 16);
        $doc->Cell(0, 10, 'Attestation d\'assiduité', 0, 1, 'C');

        $doc->SetFont('helvetica', '', 9);
        $doc->MultiCell(0, 5, 'Article D.6353-4 du Code du travail', 0, 'C');
        $doc->Ln(4);

        $doc->SetFont('helvetica', 'B', 11);
        $doc->Cell(0, 6, 'Organisme de formation', 0, 1);
        $doc->SetFont('helvetica', '', 10);
        $doc->MultiCell(0, 5, self::organisation_block($payload->organisation), 0, 'L');
        $doc->Ln(2);

        $doc->SetFont('helvetica', 'B', 11);
        $doc->Cell(0, 6, 'Apprenant(e)', 0, 1);
        $doc->SetFont('helvetica', '', 10);
        $doc->MultiCell(0, 5, self::learner_block($payload->learner), 0, 'L');
        $doc->Ln(2);

        $doc->SetFont('helvetica', 'B', 11);
        $doc->Cell(0, 6, 'Action de formation', 0, 1);
        $doc->SetFont('helvetica', '', 10);
        $doc->MultiCell(0, 5, self::course_block($payload), 0, 'L');
        $doc->Ln(2);

        $doc->SetFont('helvetica', 'B', 11);
        $doc->Cell(0, 6, 'Sessions certifiées', 0, 1);
        self::sessions_table($doc, $payload->sessions);
        $doc->Ln(2);

        if (!empty($payload->assessments)) {
            $doc->SetFont('helvetica', 'B', 11);
            $doc->Cell(0, 6, 'Évaluations', 0, 1);
            self::assessments_table($doc, $payload->assessments);
            $doc->Ln(2);
        }

        self::signature_and_token($doc, $payload, $verificationtoken, $verificationurl);

        return (string) $doc->Output('attestation.pdf', 'S');
    }

    /**
     * Render the organisation block shown at the top of the attestation.
     *
     * @param array<string, mixed> $org
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
     * Render the learner identity block shown on the attestation.
     *
     * @param array<string, mixed> $learner
     */
    private static function learner_block(array $learner): string {
        $fullname = trim(($learner['firstname'] ?? '') . ' ' . ($learner['lastname'] ?? ''));
        $lines = [];
        if ($fullname !== '') {
            $lines[] = $fullname;
        }
        if (!empty($learner['email'])) {
            $lines[] = (string) $learner['email'];
        }
        if (!empty($learner['idnumber'])) {
            $lines[] = 'Identifiant : ' . $learner['idnumber'];
        }
        return implode("\n", $lines) ?: '—';
    }

    /**
     * Render the course / period / total duration block shown on the attestation.
     *
     * @param attestation_payload $payload
     * @return string
     */
    private static function course_block(attestation_payload $payload): string {
        $course = $payload->course;
        $lines = [$course['fullname'] ?? ''];
        if ($payload->periodstart && $payload->periodend) {
            $lines[] = 'Période : du ' . userdate($payload->periodstart, get_string('strftimedate', 'langconfig'))
                     . ' au ' . userdate($payload->periodend, get_string('strftimedate', 'langconfig'));
        }
        $lines[] = 'Durée totale certifiée : ' . self::format_duration($payload->totalseconds);
        return implode("\n", $lines);
    }

    /**
     * Render the certified-sessions table inside the attestation.
     *
     * @param pdf $doc
     * @param array<int, array<string, mixed>> $sessions
     */
    private static function sessions_table(pdf $doc, array $sessions): void {
        $doc->SetFont('helvetica', 'B', 9);
        $doc->Cell(35, 6, 'Début', 1, 0, 'C');
        $doc->Cell(35, 6, 'Fin', 1, 0, 'C');
        $doc->Cell(25, 6, 'Durée', 1, 0, 'C');
        $doc->Cell(35, 6, 'Clôture', 1, 1, 'C');

        $doc->SetFont('helvetica', '', 9);
        if (empty($sessions)) {
            $doc->Cell(130, 6, 'Aucune session certifiée', 1, 1, 'C');
            return;
        }
        foreach ($sessions as $session) {
            $doc->Cell(35, 6, userdate((int) $session['start'], get_string('strftimedatetimeshort', 'langconfig')), 1, 0, 'C');
            $doc->Cell(35, 6, userdate((int) $session['end'], get_string('strftimedatetimeshort', 'langconfig')), 1, 0, 'C');
            $doc->Cell(25, 6, self::format_duration((int) $session['duration']), 1, 0, 'C');
            $doc->Cell(35, 6, (string) ($session['closure_type'] ?? '—'), 1, 1, 'C');
        }
    }

    /**
     * Render the assessments table inside the attestation.
     *
     * @param pdf $doc
     * @param array<int, array<string, mixed>> $assessments
     */
    private static function assessments_table(pdf $doc, array $assessments): void {
        $doc->SetFont('helvetica', 'B', 9);
        $doc->Cell(55, 6, 'Type', 1, 0, 'C');
        $doc->Cell(35, 6, 'Date', 1, 0, 'C');
        $doc->Cell(25, 6, 'Score', 1, 0, 'C');
        $doc->Cell(25, 6, '%', 1, 1, 'C');

        $doc->SetFont('helvetica', '', 9);
        foreach ($assessments as $assessment) {
            $score = $assessment['score'] !== null && $assessment['max_score']
                ? sprintf('%s / %s', self::format_number($assessment['score']), self::format_number($assessment['max_score']))
                : '—';
            $percent = $assessment['grade_percent'] !== null
                ? sprintf('%s %%', self::format_number($assessment['grade_percent']))
                : '—';
            $doc->Cell(55, 6, (string) $assessment['assessment_type'], 1, 0, 'L');
            $doc->Cell(35, 6, userdate((int) $assessment['attempt_date'], get_string('strftimedate', 'langconfig')), 1, 0, 'C');
            $doc->Cell(25, 6, $score, 1, 0, 'C');
            $doc->Cell(25, 6, $percent, 1, 1, 'C');
        }
    }

    /**
     * Render the signatory, verification token and QR code footer.
     *
     * @param pdf $doc
     * @param attestation_payload $payload
     * @param string|null $verificationtoken
     * @param string|null $verificationurl
     */
    private static function signature_and_token(
        pdf $doc,
        attestation_payload $payload,
        ?string $verificationtoken,
        ?string $verificationurl
    ): void {
        $doc->Ln(6);
        $doc->SetFont('helvetica', '', 10);
        $signatory = trim(($payload->organisation['signatory_name'] ?? '') . ' — '
            . ($payload->organisation['signatory_role'] ?? ''));
        if ($signatory !== ' — ') {
            $doc->MultiCell(0, 5, $signatory, 0, 'L');
        }
        $doc->Ln(4);

        if ($verificationtoken !== null) {
            $doc->SetFont('helvetica', '', 8);
            $doc->MultiCell(0, 4, 'Jeton de vérification : ' . $verificationtoken, 0, 'L');
            if ($verificationurl !== null) {
                $doc->MultiCell(0, 4, 'Vérification en ligne : ' . $verificationurl, 0, 'L');
                $doc->write2DBarcode($verificationurl, 'QRCODE,L', null, null, 30, 30);
            }
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
     * Format a numeric value for display in the attestation.
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
