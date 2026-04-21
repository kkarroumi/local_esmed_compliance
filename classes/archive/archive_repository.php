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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Data access layer for {local_esmed_archive_index}.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_esmed_compliance\archive;

use dml_exception;
use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Persist and look up sealed document index rows.
 *
 * The verification token is globally unique and the only safe key to
 * expose to third parties — it carries no information about the user
 * and can be revoked by deleting the row.
 */
class archive_repository {

    /** @var string Table name. */
    public const TABLE = 'local_esmed_archive_index';

    /** @var string Archive type for attestations d'assiduité (D.6353-4). */
    public const TYPE_ATTESTATION_ASSIDUITE = 'attestation_assiduite';
    /** @var string Archive type for funder statements (bordereaux). */
    public const TYPE_BORDEREAU_FINANCEUR = 'bordereau_financeur';
    /** @var string Archive type for serialised session evidence. */
    public const TYPE_SESSION_LOG_JSON = 'session_log_json';
    /** @var string Archive type for evaluation reports. */
    public const TYPE_EVALUATION_REPORT = 'evaluation_report';

    /**
     * Insert a sealed document record.
     *
     * @param stdClass $record
     * @return int Row id.
     * @throws dml_exception
     */
    public function insert(stdClass $record): int {
        global $DB;
        return (int) $DB->insert_record(self::TABLE, $record);
    }

    /**
     * Find an archived document by its public verification token.
     *
     * @param string $token
     * @return stdClass|null
     * @throws dml_exception
     */
    public function find_by_token(string $token): ?stdClass {
        global $DB;
        $record = $DB->get_record(self::TABLE, ['verification_token' => $token]);
        return $record ?: null;
    }

    /**
     * Find every archived document of a given type for a user / course pair.
     *
     * Useful for "I already generated the attestation, don't re-seal" checks.
     *
     * @param int    $userid
     * @param int    $courseid
     * @param string $type
     * @return stdClass[]
     * @throws dml_exception
     */
    public function find_for_user_course(int $userid, int $courseid, string $type): array {
        global $DB;
        return $DB->get_records(
            self::TABLE,
            ['userid' => $userid, 'courseid' => $courseid, 'archive_type' => $type],
            'timestamp_sealed DESC'
        );
    }

    /**
     * Generate a cryptographically random verification token that is unique in the table.
     *
     * The `verification_token` column is UNIQUE so a collision is already
     * a DML-level failure, but this helper keeps insert attempts from ever
     * hitting a retry loop in practice.
     *
     * @return string A 64-character hex token.
     * @throws dml_exception
     */
    public function generate_unique_token(): string {
        global $DB;
        for ($i = 0; $i < 5; $i++) {
            $candidate = bin2hex(random_bytes(32));
            if (!$DB->record_exists(self::TABLE, ['verification_token' => $candidate])) {
                return $candidate;
            }
        }
        // Outrageously improbable; bubble up so the caller decides.
        throw new \moodle_exception('tokengenfailed', 'local_esmed_compliance');
    }
}
