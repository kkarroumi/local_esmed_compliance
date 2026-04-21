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
 * External function: compliance summary for one learner in one course.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_esmed_compliance\external;

use context_course;
use context_user;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Return the compliance summary for a given (learner, course) pair:
 * total session seconds, activity time, views, number of sealed
 * attestations. The endpoint enforces two separate authorisations so that
 * learners can fetch their own summary (via `viewownreports`) and managers
 * can fetch anyone's (via `viewdashboard`).
 */
class get_learner_summary extends external_api {
    /**
     * Declare the input parameters accepted by the webservice.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'userid'   => new external_value(PARAM_INT, 'Target user id.', VALUE_REQUIRED),
            'courseid' => new external_value(PARAM_INT, 'Target course id.', VALUE_REQUIRED),
        ]);
    }

    /**
     * Execute the webservice call and return the learner-course summary.
     *
     * @param int $userid
     * @param int $courseid
     * @return array<string, mixed>
     */
    public static function execute(int $userid, int $courseid): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'userid'   => $userid,
            'courseid' => $courseid,
        ]);
        $userid = (int) $params['userid'];
        $courseid = (int) $params['courseid'];

        $coursecontext = context_course::instance($courseid);
        self::validate_context($coursecontext);

        $isself = ((int) $USER->id === $userid);
        $canviewself = $isself
            && has_capability('local/esmed_compliance:viewownreports', context_user::instance($userid));
        $canviewany = has_capability('local/esmed_compliance:viewdashboard', $coursecontext);

        if (!$canviewself && !$canviewany) {
            throw new \required_capability_exception(
                $coursecontext,
                'local/esmed_compliance:viewdashboard',
                'nopermissions',
                ''
            );
        }

        $sessionseconds = (int) $DB->get_field_sql(
            "SELECT COALESCE(SUM(duration_seconds), 0)
               FROM {local_esmed_sessions}
              WHERE userid = :uid AND courseid = :cid AND session_end IS NOT NULL",
            ['uid' => $userid, 'cid' => $courseid]
        );
        $activityseconds = (int) $DB->get_field_sql(
            "SELECT COALESCE(SUM(time_spent_seconds), 0)
               FROM {local_esmed_activity_log}
              WHERE userid = :uid AND courseid = :cid",
            ['uid' => $userid, 'cid' => $courseid]
        );
        $totalviews = (int) $DB->get_field_sql(
            "SELECT COALESCE(SUM(views_count), 0)
               FROM {local_esmed_activity_log}
              WHERE userid = :uid AND courseid = :cid",
            ['uid' => $userid, 'cid' => $courseid]
        );
        $modulestouched = (int) $DB->count_records(
            'local_esmed_activity_log',
            ['userid' => $userid, 'courseid' => $courseid]
        );
        $attestations = (int) $DB->count_records(
            'local_esmed_archive_index',
            [
                'userid'       => $userid,
                'courseid'     => $courseid,
                'archive_type' => 'attestation_assiduite',
            ]
        );

        return [
            'userid'          => $userid,
            'courseid'        => $courseid,
            'session_seconds' => $sessionseconds,
            'activity_seconds' => $activityseconds,
            'views_count'     => $totalviews,
            'modules_touched' => $modulestouched,
            'attestations'    => $attestations,
        ];
    }

    /**
     * Declare the shape of the value returned by the webservice.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'userid'           => new external_value(PARAM_INT, 'Target user id.'),
            'courseid'         => new external_value(PARAM_INT, 'Target course id.'),
            'session_seconds'  => new external_value(PARAM_INT, 'Sum of closed-session durations.'),
            'activity_seconds' => new external_value(PARAM_INT, 'Sum of module-view dwell time.'),
            'views_count'      => new external_value(PARAM_INT, 'Total module-view events.'),
            'modules_touched'  => new external_value(PARAM_INT, 'Distinct modules visited.'),
            'attestations'     => new external_value(PARAM_INT, 'Sealed attestations d\'assiduité.'),
        ]);
    }
}
