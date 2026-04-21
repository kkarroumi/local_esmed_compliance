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
 * CSV bordereau renderer.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_esmed_compliance\funder;

/**
 * Produce a deterministic UTF-8 CSV bordereau.
 *
 * RFC 4180 compliant: comma separator, CRLF line endings, fields
 * containing separator / quote / newline are wrapped in double quotes
 * with embedded quotes doubled. A BOM is prepended so Excel on Windows
 * auto-detects the encoding.
 */
class csv_bordereau_renderer implements bordereau_renderer {
    /** @var string Byte order mark for UTF-8, so Excel opens accented text correctly. */
    private const BOM = "\xEF\xBB\xBF";

    /** @var string CRLF line terminator, per RFC 4180. */
    private const EOL = "\r\n";

    /**
     * Inherits from parent.
     */
    public function extension(): string {
        return 'csv';
    }

    /**
     * Inherits from parent.
     */
    public function mime_type(): string {
        return 'text/csv; charset=utf-8';
    }

    /**
     * Inherits from parent.
     */
    public function render(
        bordereau_payload $payload,
        ?string $verificationtoken = null,
        ?string $verificationurl = null
    ): string {
        $headers = [
            'user_id',
            'lastname',
            'firstname',
            'email',
            'idnumber',
            'sessions_count',
            'duration_seconds',
            'duration_hours',
            'first_session',
            'last_session',
            'funder_type',
            'dossier_number',
            'course_shortname',
        ];

        $lines = [self::BOM . self::join_row($headers)];
        foreach ($payload->learners as $learner) {
            $lines[] = self::join_row([
                (string) $learner['userid'],
                (string) $learner['lastname'],
                (string) $learner['firstname'],
                (string) $learner['email'],
                (string) $learner['idnumber'],
                (string) $learner['sessions'],
                (string) $learner['duration'],
                self::hours((int) $learner['duration']),
                $learner['first_session'] !== null ? self::date_iso((int) $learner['first_session']) : '',
                $learner['last_session'] !== null ? self::date_iso((int) $learner['last_session']) : '',
                (string) $payload->funder['type'],
                (string) $payload->funder['dossier_number'],
                (string) $payload->course['shortname'],
            ]);
        }

        if ($verificationtoken !== null) {
            $lines[] = '';
            $lines[] = self::join_row(['# verification_token', $verificationtoken]);
            if ($verificationurl !== null) {
                $lines[] = self::join_row(['# verification_url', $verificationurl]);
            }
        }

        return implode(self::EOL, $lines) . self::EOL;
    }

    /**
     * Join and quote one CSV row.
     *
     * @param array<int, string> $fields
     * @return string
     */
    private static function join_row(array $fields): string {
        $escaped = [];
        foreach ($fields as $field) {
            $escaped[] = self::escape_field($field);
        }
        return implode(',', $escaped);
    }

    /**
     * Escape a single field per RFC 4180.
     *
     * @param string $value
     * @return string
     */
    private static function escape_field(string $value): string {
        if ($value === '') {
            return '';
        }
        $needsquote = preg_match('/[",\r\n]/', $value) === 1;
        if (!$needsquote) {
            return $value;
        }
        return '"' . str_replace('"', '""', $value) . '"';
    }

    /**
     * Format seconds as an hour string with two decimals.
     *
     * @param int $seconds
     * @return string
     */
    private static function hours(int $seconds): string {
        if ($seconds <= 0) {
            return '0.00';
        }
        return number_format($seconds / 3600, 2, '.', '');
    }

    /**
     * Format a unix timestamp as ISO-8601 "Y-m-d\TH:i:sP" in UTC.
     *
     * @param int $timestamp
     * @return string
     */
    private static function date_iso(int $timestamp): string {
        return gmdate('Y-m-d\TH:i:s\Z', $timestamp);
    }
}
