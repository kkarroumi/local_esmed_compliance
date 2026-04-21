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
 * Data access layer for {local_esmed_activity_log}.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_esmed_compliance\activity;

use dml_exception;
use stdClass;

/**
 * Incremental upsert on the activity aggregation table.
 */
class activity_repository {
    /** @var string Table name. */
    public const TABLE = 'local_esmed_activity_log';

    /**
     * Increment the aggregates for a (user, cmid) pair, creating the row on first write.
     *
     * All four counters are accumulated: `time_spent_seconds` and
     * `views_count` are added to the current value; `first_access` keeps
     * the earliest observation, `last_access` the latest.
     *
     * @param int    $userid
     * @param int    $courseid
     * @param int    $cmid
     * @param string $modulename
     * @param int    $firstaccess
     * @param int    $lastaccess
     * @param int    $timespent
     * @param int    $views
     * @param int    $now
     * @return void
     * @throws dml_exception
     */
    public function upsert(
        int $userid,
        int $courseid,
        int $cmid,
        string $modulename,
        int $firstaccess,
        int $lastaccess,
        int $timespent,
        int $views,
        int $now
    ): void {
        global $DB;

        $existing = $DB->get_record(
            self::TABLE,
            ['userid' => $userid, 'cmid' => $cmid],
            '*',
            IGNORE_MISSING
        );

        if ($existing) {
            $existing->time_spent_seconds = (int) $existing->time_spent_seconds + $timespent;
            $existing->views_count        = (int) $existing->views_count + $views;
            $existing->first_access       = $existing->first_access === null
                ? $firstaccess
                : min((int) $existing->first_access, $firstaccess);
            $existing->last_access        = $existing->last_access === null
                ? $lastaccess
                : max((int) $existing->last_access, $lastaccess);
            $existing->modulename         = $modulename;
            $existing->timemodified       = $now;
            $DB->update_record(self::TABLE, $existing);
            return;
        }

        $record = new stdClass();
        $record->userid             = $userid;
        $record->courseid           = $courseid;
        $record->cmid               = $cmid;
        $record->modulename         = $modulename;
        $record->first_access       = $firstaccess;
        $record->last_access        = $lastaccess;
        $record->time_spent_seconds = $timespent;
        $record->views_count        = $views;
        $record->completion_state   = null;
        $record->timemodified       = $now;

        $DB->insert_record(self::TABLE, $record);
    }

    /**
     * Record the completion state for a (user, cmid) pair.
     *
     * Creates a bare row if the aggregate is not yet tracked, so that a
     * learner who completes a module before ever "viewing" it (the edge
     * case of an auto-complete rule) still appears in the log.
     *
     * @param int    $userid
     * @param int    $courseid
     * @param int    $cmid
     * @param string $modulename
     * @param int    $state
     * @param int    $now
     * @return void
     * @throws dml_exception
     */
    public function set_completion_state(
        int $userid,
        int $courseid,
        int $cmid,
        string $modulename,
        int $state,
        int $now
    ): void {
        global $DB;

        $existing = $DB->get_record(self::TABLE, ['userid' => $userid, 'cmid' => $cmid]);
        if ($existing) {
            $existing->completion_state = $state;
            $existing->modulename       = $modulename;
            $existing->timemodified     = $now;
            $DB->update_record(self::TABLE, $existing);
            return;
        }

        $record = new stdClass();
        $record->userid             = $userid;
        $record->courseid           = $courseid;
        $record->cmid               = $cmid;
        $record->modulename         = $modulename;
        $record->first_access       = null;
        $record->last_access        = null;
        $record->time_spent_seconds = 0;
        $record->views_count        = 0;
        $record->completion_state   = $state;
        $record->timemodified       = $now;

        $DB->insert_record(self::TABLE, $record);
    }

    /**
     * Fetch the aggregate row for a (user, cmid) pair.
     *
     * @param int $userid
     * @param int $cmid
     * @return stdClass|null
     * @throws dml_exception
     */
    public function find_by_user_cmid(int $userid, int $cmid): ?stdClass {
        global $DB;
        $record = $DB->get_record(self::TABLE, ['userid' => $userid, 'cmid' => $cmid]);
        return $record ?: null;
    }
}
