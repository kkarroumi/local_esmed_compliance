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
 * Data access layer for {local_esmed_assessment_index}.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_esmed_compliance\assessment;

use dml_exception;
use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Persists categorised assessment attempts.
 *
 * Deduplicates on (source table, source attempt id) so replaying the
 * same logstore window never produces ghost rows.
 */
class assessment_repository {

    /** @var string Table name. */
    public const TABLE = 'local_esmed_assessment_index';

    /**
     * Insert a record if the (source table, source attempt id) pair is new.
     *
     * Returns the id of the existing row when a duplicate is detected.
     *
     * @param stdClass $record
     * @return int
     * @throws dml_exception
     */
    public function insert_unique(stdClass $record): int {
        global $DB;

        if (!empty($record->attempt_id_moodle) && !empty($record->attempt_source_table)) {
            $existing = $DB->get_record(
                self::TABLE,
                [
                    'attempt_source_table' => $record->attempt_source_table,
                    'attempt_id_moodle'    => $record->attempt_id_moodle,
                ]
            );
            if ($existing) {
                return (int) $existing->id;
            }
        }

        return (int) $DB->insert_record(self::TABLE, $record);
    }

    /**
     * Return the list of indexed attempts for a user on a given cmid.
     *
     * @param int $userid
     * @param int $cmid
     * @return stdClass[]
     * @throws dml_exception
     */
    public function find_by_user_cmid(int $userid, int $cmid): array {
        global $DB;
        return $DB->get_records(
            self::TABLE,
            ['userid' => $userid, 'cmid' => $cmid],
            'attempt_date ASC'
        );
    }
}
