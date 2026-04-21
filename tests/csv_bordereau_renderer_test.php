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
 * CSV bordereau renderer tests.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_esmed_compliance;

use local_esmed_compliance\funder\bordereau_payload;
use local_esmed_compliance\funder\csv_bordereau_renderer;

/**
 * Tests for the  component.
 *
 * @covers \local_esmed_compliance\funder\csv_bordereau_renderer
 */
final class csv_bordereau_renderer_test extends \advanced_testcase {
    /**
     * Header row starts with the BOM, uses comma separators and CRLF line endings.
     */
    public function test_header_row_is_rfc4180_with_bom(): void {
        $output = (new csv_bordereau_renderer())->render(self::payload([]));

        $this->assertStringStartsWith("\xEF\xBB\xBF", $output);
        $lines = explode("\r\n", trim($output, "\r\n"));
        $this->assertNotEmpty($lines);
        // Strip the BOM for header comparison.
        $header = substr($lines[0], 3);
        $this->assertSame(
            'user_id,lastname,firstname,email,idnumber,sessions_count,duration_seconds,duration_hours,'
                . 'first_session,last_session,funder_type,dossier_number,course_shortname',
            $header
        );
    }

    /**
     * Fields containing comma, quote or newline are wrapped in double quotes
     * with embedded quotes doubled.
     */
    public function test_fields_with_special_characters_are_quoted(): void {
        $output = (new csv_bordereau_renderer())->render(self::payload([
            [
                'userid'        => 1,
                'firstname'     => 'Alice "A"',
                'lastname'      => 'Dupont, Jr',
                'email'         => "a\nb@example.com",
                'idnumber'      => 'ID-1',
                'sessions'      => 1,
                'duration'      => 3600,
                'first_session' => 1700000000,
                'last_session'  => 1700003600,
            ],
        ]));

        $this->assertStringContainsString('"Dupont, Jr"', $output);
        $this->assertStringContainsString('"Alice ""A"""', $output);
        $this->assertStringContainsString("\"a\nb@example.com\"", $output);
    }

    /**
     * Duration is rendered as seconds and as hours with two decimals.
     */
    public function test_duration_hours_conversion(): void {
        $output = (new csv_bordereau_renderer())->render(self::payload([
            [
                'userid'        => 2,
                'firstname'     => 'B',
                'lastname'      => 'B',
                'email'         => 'b@example.com',
                'idnumber'      => '',
                'sessions'      => 2,
                'duration'      => 5400,
                'first_session' => 1700000000,
                'last_session'  => 1700005400,
            ],
        ]));
        $this->assertStringContainsString(',5400,1.50,', $output);
    }

    /**
     * Verification token is appended as a comment row when provided.
     */
    public function test_verification_token_footer(): void {
        $token = str_repeat('f', 64);
        $url = 'https://example.com/verify.php?t=' . $token;
        $output = (new csv_bordereau_renderer())->render(self::payload([]), $token, $url);

        $this->assertStringContainsString('# verification_token,' . $token, $output);
        $this->assertStringContainsString('# verification_url,' . $url, $output);
    }

    /**
     * Rendering is a pure function of the payload: identical inputs -> identical bytes.
     */
    public function test_render_is_deterministic(): void {
        $payload = self::payload([
            [
                'userid'        => 3,
                'firstname'     => 'Chloé',
                'lastname'      => 'Zamora',
                'email'         => 'chloe@example.com',
                'idnumber'      => 'E-3',
                'sessions'      => 1,
                'duration'      => 1200,
                'first_session' => 1700000000,
                'last_session'  => 1700001200,
            ],
        ]);
        $renderer = new csv_bordereau_renderer();

        $first = $renderer->render($payload);
        $second = $renderer->render($payload);
        $this->assertSame($first, $second);
    }

    /**
     * Build a lightweight payload so the renderer can be exercised without DB access.
     *
     * @param array<int, array<string, mixed>> $learners
     * @return bordereau_payload
     */
    private static function payload(array $learners): bordereau_payload {
        return new bordereau_payload(
            [
                'legal_name' => 'ESMED',
                'siret'      => '12345678901234',
                'nda'        => '99 99 99999 99',
                'address'    => '1 rue de la Formation',
            ],
            [
                'type'            => 'CPF',
                'dossier_number'  => 'CPF-TEST',
                'action_intitule' => 'Initiation',
                'opco_name'       => '',
                'hours_planned'   => 21.0,
            ],
            [
                'id'        => 100,
                'fullname'  => 'Cours de test',
                'shortname' => 'TEST101',
            ],
            $learners,
            array_sum(array_column($learners, 'duration')),
            count($learners),
            null,
            null,
            1700000000
        );
    }
}
