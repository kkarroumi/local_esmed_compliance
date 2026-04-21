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
 * List enrolled learners together with attestation status for one course.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_esmed_compliance\attestation;

use context_course;
use core_user;
use local_esmed_compliance\archive\archive_repository;

/**
 * Gather the rows displayed on the attestation operator screen.
 *
 * For each actively enrolled learner of a course, surface:
 *   - the user identity;
 *   - the total closed-session duration they accumulated;
 *   - the number of attestations already sealed for that (user, course);
 *   - the most recent sealed attestation, if any.
 *
 * Open sessions are excluded on purpose: attestations quote sealed
 * evidence only, so the header number the operator sees must match what
 * `attestation_builder` will include when the document is generated.
 */
class attestation_listing {
    /**
     * List enrolled learners and their attestation status for `$courseid`.
     *
     * @param int $courseid
     * @return array<int, array{
     *     userid:int,
     *     fullname:string,
     *     email:string,
     *     idnumber:string,
     *     total_seconds:int,
     *     attestation_count:int,
     *     last_sealed_at:int|null,
     *     last_archive_id:int|null
     * }>
     */
    public function list_for_course(int $courseid): array {
        global $DB;

        $context = context_course::instance($courseid);
        $users = get_enrolled_users(
            $context,
            '',
            0,
            'u.id, u.firstname, u.lastname, u.email, u.idnumber',
            'u.lastname, u.firstname'
        );
        if (empty($users)) {
            return [];
        }

        [$useridsql, $params] = $DB->get_in_or_equal(array_map('intval', array_keys($users)), SQL_PARAMS_NAMED, 'u');

        // Aggregate total closed-session seconds per user.
        $sumsql = "SELECT userid, COALESCE(SUM(duration_seconds), 0) AS total_seconds
                     FROM {local_esmed_sessions}
                    WHERE userid {$useridsql}
                      AND session_end IS NOT NULL
                 GROUP BY userid";
        $totals = $DB->get_records_sql($sumsql, $params);

        // Count and summarise existing attestations per user for THIS course.
        $archiveparams = $params + [
            'courseid' => $courseid,
            'type'     => archive_repository::TYPE_ATTESTATION_ASSIDUITE,
        ];
        $archivesql = "SELECT userid,
                              COUNT(id) AS cnt,
                              MAX(timestamp_sealed) AS last_sealed_at
                         FROM {" . archive_repository::TABLE . "}
                        WHERE userid {$useridsql}
                          AND courseid = :courseid
                          AND archive_type = :type
                     GROUP BY userid";
        $archives = $DB->get_records_sql($archivesql, $archiveparams);

        // The row id of the most recent attestation per user (for one-click download).
        $latestsql = "SELECT a.userid, a.id, a.timestamp_sealed
                        FROM {" . archive_repository::TABLE . "} a
                       WHERE a.userid {$useridsql}
                         AND a.courseid = :courseid
                         AND a.archive_type = :type
                         AND a.timestamp_sealed = (
                             SELECT MAX(a2.timestamp_sealed)
                               FROM {" . archive_repository::TABLE . "} a2
                              WHERE a2.userid = a.userid
                                AND a2.courseid = a.courseid
                                AND a2.archive_type = a.archive_type
                         )";
        $latestbyuser = [];
        foreach ($DB->get_records_sql($latestsql, $archiveparams) as $row) {
            // When several rows share the same max timestamp, keep the highest id.
            $uid = (int) $row->userid;
            if (!isset($latestbyuser[$uid]) || (int) $row->id > $latestbyuser[$uid]) {
                $latestbyuser[$uid] = (int) $row->id;
            }
        }

        $rows = [];
        foreach ($users as $user) {
            $uid = (int) $user->id;
            $total = isset($totals[$uid]) ? (int) $totals[$uid]->total_seconds : 0;
            $count = isset($archives[$uid]) ? (int) $archives[$uid]->cnt : 0;
            $lastsealed = isset($archives[$uid]) && $archives[$uid]->last_sealed_at !== null
                ? (int) $archives[$uid]->last_sealed_at
                : null;
            $lastid = $latestbyuser[$uid] ?? null;

            $rows[] = [
                'userid'            => $uid,
                'fullname'          => fullname($user),
                'email'             => (string) $user->email,
                'idnumber'          => (string) $user->idnumber,
                'total_seconds'     => $total,
                'attestation_count' => $count,
                'last_sealed_at'    => $lastsealed,
                'last_archive_id'   => $lastid,
            ];
        }

        return $rows;
    }

    /**
     * List courses the current user can generate attestations for.
     *
     * @return array<int, array{id:int, fullname:string, shortname:string}>
     */
    public static function courses_for_current_user(): array {
        $courseids = get_user_capability_course(
            'local/esmed_compliance:generateattestation',
            null,
            true,
            'fullname, shortname'
        );
        if (!is_array($courseids)) {
            return [];
        }

        $rows = [];
        foreach ($courseids as $course) {
            if ((int) $course->id === SITEID) {
                continue;
            }
            $rows[] = [
                'id'        => (int) $course->id,
                'fullname'  => format_string($course->fullname),
                'shortname' => format_string($course->shortname),
            ];
        }
        return $rows;
    }
}
