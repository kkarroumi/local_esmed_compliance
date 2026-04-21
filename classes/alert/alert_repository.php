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
 * Data access layer for {local_esmed_alerts}.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_esmed_compliance\alert;

use dml_exception;
use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Persist alert rows and support idempotent creation and acknowledgement.
 *
 * Detectors are expected to be run repeatedly (daily) on overlapping
 * windows, so the "has an open alert of this type already been raised?"
 * question is central to avoiding duplicates.
 */
class alert_repository {

    /** @var string Table name. */
    public const TABLE = 'local_esmed_alerts';

    /** @var string Alert type: learner has had no session in seven days. */
    public const TYPE_INACTIVITY_7D = 'inactivity_7d';
    /** @var string Alert type: learner has never logged a first session in 72 hours after enrol. */
    public const TYPE_NO_FIRST_LOGIN_72H = 'no_first_login_72h';
    /** @var string Alert type: learner enrolled, expected completion date passed. */
    public const TYPE_COMPLETION_LATE = 'completion_late';

    /**
     * Is there already an unacknowledged alert of this type for this user/course?
     *
     * @param int      $userid
     * @param int|null $courseid
     * @param string   $alerttype
     * @return bool
     * @throws dml_exception
     */
    public function has_open_alert(int $userid, ?int $courseid, string $alerttype): bool {
        global $DB;
        $where = 'userid = :userid AND alert_type = :alerttype AND acknowledged_at IS NULL';
        $params = ['userid' => $userid, 'alerttype' => $alerttype];
        if ($courseid === null) {
            $where .= ' AND courseid IS NULL';
        } else {
            $where .= ' AND courseid = :courseid';
            $params['courseid'] = $courseid;
        }
        return $DB->record_exists_select(self::TABLE, $where, $params);
    }

    /**
     * Insert a new alert row.
     *
     * @param int      $userid
     * @param int|null $courseid
     * @param string   $alerttype
     * @param array    $payload     Caller-supplied structured payload (json-encoded here).
     * @param int      $triggeredat
     * @return int Row id.
     * @throws dml_exception
     */
    public function raise(int $userid, ?int $courseid, string $alerttype, array $payload, int $triggeredat): int {
        global $DB;
        $record = new stdClass();
        $record->userid          = $userid;
        $record->courseid        = $courseid;
        $record->alert_type      = $alerttype;
        $record->alert_data_json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $record->triggered_at    = $triggeredat;
        $record->notified_at     = null;
        $record->acknowledged_at = null;
        $record->acknowledged_by = null;
        return (int) $DB->insert_record(self::TABLE, $record);
    }

    /**
     * Mark an alert as acknowledged by a specific user at a specific time.
     *
     * Idempotent: re-acknowledging a row is a no-op and returns true.
     *
     * @param int $alertid
     * @param int $byuserid
     * @param int $at
     * @return bool
     * @throws dml_exception
     */
    public function acknowledge(int $alertid, int $byuserid, int $at): bool {
        global $DB;
        $row = $DB->get_record(self::TABLE, ['id' => $alertid], '*', IGNORE_MISSING);
        if (!$row) {
            return false;
        }
        if ($row->acknowledged_at !== null) {
            return true;
        }
        $update = new stdClass();
        $update->id              = $alertid;
        $update->acknowledged_at = $at;
        $update->acknowledged_by = $byuserid;
        return (bool) $DB->update_record(self::TABLE, $update);
    }

    /**
     * Fetch an alert row by id, or null if it does not exist.
     *
     * @param int $alertid
     * @return stdClass|null
     * @throws dml_exception
     */
    public function get(int $alertid): ?stdClass {
        global $DB;
        $row = $DB->get_record(self::TABLE, ['id' => $alertid]);
        return $row ?: null;
    }

    /**
     * Return ids of open alerts that have never been notified.
     *
     * @param int $limit
     * @return int[]
     * @throws dml_exception
     */
    public function find_pending_notification(int $limit = 200): array {
        global $DB;
        $rows = $DB->get_records_select(
            self::TABLE,
            'notified_at IS NULL',
            [],
            'triggered_at ASC, id ASC',
            'id',
            0,
            $limit
        );
        $ids = [];
        foreach ($rows as $row) {
            $ids[] = (int) $row->id;
        }
        return $ids;
    }
}
