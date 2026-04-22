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
 * Data access layer for {local_esmed_compliance_sessions}.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_esmed_compliance\session;

use dml_exception;
use stdClass;

/**
 * Data access layer for certifiable sessions.
 *
 * Keeps all SQL in one place so the tracker remains focused on business
 * rules and is easy to unit-test with stub repositories when needed.
 */
class session_repository {
    /** @var string Table name. */
    public const TABLE = 'local_esmed_compliance_sessions';

    /**
     * Return the currently open session for a user, if any.
     *
     * The "open" definition is strictly `session_end IS NULL`. If multiple
     * records match (should not happen but can under failure modes) the
     * most recent one is returned and the caller must decide what to do
     * with the leftovers.
     *
     * @param int $userid
     * @return stdClass|null
     * @throws dml_exception
     */
    public function find_open_session_for_user(int $userid): ?stdClass {
        global $DB;
        $records = $DB->get_records_select(
            self::TABLE,
            'userid = :userid AND session_end IS NULL',
            ['userid' => $userid],
            'session_start DESC',
            '*',
            0,
            1
        );
        $record = $records ? reset($records) : false;
        return $record ?: null;
    }

    /**
     * Return every open session older than a threshold.
     *
     * Used by the timeout scheduled task. "Older than" is measured against
     * the last heartbeat when available, falling back to the session start
     * (records that never received a single beacon).
     *
     * @param int $before Unix timestamp. Sessions inactive since before this are returned.
     * @return stdClass[]
     * @throws dml_exception
     */
    public function find_stale_open_sessions(int $before): array {
        global $DB;
        $sql = "SELECT * FROM {" . self::TABLE . "}
                 WHERE session_end IS NULL
                   AND COALESCE(last_heartbeat, session_start) < :before";
        return $DB->get_records_sql($sql, ['before' => $before]);
    }

    /**
     * Insert a new open session.
     *
     * @param stdClass $record Must contain userid and session_start.
     * @return int The inserted record id.
     * @throws dml_exception
     */
    public function insert(stdClass $record): int {
        global $DB;
        return (int) $DB->insert_record(self::TABLE, $record);
    }

    /**
     * Update the last_heartbeat and timemodified fields of an open session.
     *
     * Only applies if the session is still open to avoid resurrecting a
     * session that a concurrent close_session or timeout task just finished.
     *
     * @param int $sessionid
     * @param int $timestamp
     * @return bool true if exactly one row was updated.
     * @throws dml_exception
     */
    public function touch_heartbeat(int $sessionid, int $timestamp): bool {
        global $DB;
        $sql = "UPDATE {" . self::TABLE . "}
                   SET last_heartbeat = :hb, timemodified = :tm
                 WHERE id = :id AND session_end IS NULL";
        return (bool) $DB->execute(
            $sql,
            ['hb' => $timestamp, 'tm' => $timestamp, 'id' => $sessionid]
        );
    }

    /**
     * Close an open session atomically.
     *
     * Performs a conditional UPDATE (`WHERE session_end IS NULL`) so two
     * concurrent close attempts (for example AJAX beacon + timeout task)
     * still end up with exactly one winning record and deterministic data.
     *
     * @param int    $sessionid
     * @param int    $endtimestamp
     * @param string $closuretype
     * @return bool true if this call actually closed the session.
     * @throws dml_exception
     */
    public function close(int $sessionid, int $endtimestamp, string $closuretype): bool {
        global $DB;

        // Load the open row first to compute duration without another round trip.
        $record = $DB->get_record_select(
            self::TABLE,
            'id = :id AND session_end IS NULL',
            ['id' => $sessionid]
        );
        if (!$record) {
            return false;
        }

        $duration = max(0, $endtimestamp - (int) $record->session_start);

        $sql = "UPDATE {" . self::TABLE . "}
                   SET session_end = :endts,
                       duration_seconds = :dur,
                       closure_type = :ctype,
                       timemodified = :tm
                 WHERE id = :id AND session_end IS NULL";

        return (bool) $DB->execute($sql, [
            'endts' => $endtimestamp,
            'dur'   => $duration,
            'ctype' => $closuretype,
            'tm'    => $endtimestamp,
            'id'    => $sessionid,
        ]);
    }

    /**
     * Fetch a session record by id.
     *
     * @param int $sessionid
     * @return stdClass|null
     * @throws dml_exception
     */
    public function get(int $sessionid): ?stdClass {
        global $DB;
        $record = $DB->get_record(self::TABLE, ['id' => $sessionid]);
        return $record ?: null;
    }
}
