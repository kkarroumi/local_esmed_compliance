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
 * External function: verify a sealed document by its public token.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_esmed_compliance\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_esmed_compliance\archive\verifier;

/**
 * Look up a sealed archive (attestation, bordereau, ...) by its verification
 * token and return the current integrity status.
 *
 * Unlike most webservice endpoints this one is callable by any authenticated
 * user: the token is the authority (knowledge equals access) and carries no
 * personal information. The returned payload is intentionally minimal so it
 * can be consumed safely by third-party funder systems or QR-code scanners.
 */
class verify_token extends external_api {
    /**
     * Declare the input parameters accepted by the webservice.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'token' => new external_value(
                PARAM_ALPHANUMEXT,
                'Public verification token embedded in the QR code or URL.',
                VALUE_REQUIRED
            ),
        ]);
    }

    /**
     * Execute the webservice call and return the current integrity status for the given token.
     *
     * @param string $token
     * @return array{status:string, archive_type:?string, timestamp_sealed:?int, sealed_hash:?string, computed_hash:?string}
     */
    public static function execute(string $token): array {
        $params = self::validate_parameters(self::execute_parameters(), ['token' => $token]);

        $result = (new verifier())->verify((string) $params['token']);
        $record = $result['record'];

        return [
            'status'           => (string) $result['status'],
            'archive_type'     => $record ? (string) $record->archive_type : null,
            'timestamp_sealed' => $record ? (int) $record->timestamp_sealed : null,
            'sealed_hash'      => $result['sealed_hash'],
            'computed_hash'    => $result['computed_hash'],
        ];
    }

    /**
     * Declare the shape of the value returned by the webservice.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(
                PARAM_ALPHA,
                'One of: valid, tampered, missing, unknown.'
            ),
            'archive_type' => new external_value(
                PARAM_ALPHANUMEXT,
                'Archive type, e.g. attestation_assiduite.',
                VALUE_OPTIONAL,
                null,
                NULL_ALLOWED
            ),
            'timestamp_sealed' => new external_value(
                PARAM_INT,
                'Unix timestamp when the document was sealed.',
                VALUE_OPTIONAL,
                null,
                NULL_ALLOWED
            ),
            'sealed_hash' => new external_value(
                PARAM_ALPHANUM,
                'SHA-256 of the document bytes at sealing time.',
                VALUE_OPTIONAL,
                null,
                NULL_ALLOWED
            ),
            'computed_hash' => new external_value(
                PARAM_ALPHANUM,
                'SHA-256 of the document bytes as currently stored.',
                VALUE_OPTIONAL,
                null,
                NULL_ALLOWED
            ),
        ]);
    }
}
