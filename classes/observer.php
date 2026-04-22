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
 * Event observers dispatcher.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_esmed_compliance;

use core\event\base as event_base;
use core\event\course_module_completion_updated;
use core\event\user_loggedin;
use core\event\user_loggedout;
use local_esmed_compliance\activity\activity_repository;
use local_esmed_compliance\assessment\indexer;
use local_esmed_compliance\session\tracker;

/**
 * Static dispatcher. Each method is registered in db/events.php.
 */
class observer {
    /**
     * Open a certifiable session when a user logs in.
     *
     * @param user_loggedin $event
     * @return void
     */
    public static function user_loggedin(user_loggedin $event): void {
        $userid = (int) $event->userid;
        if ($userid <= 0 || isguestuser($userid)) {
            return;
        }

        $ipaddress = null;
        $useragent = null;
        if (!CLI_SCRIPT && !PHPUNIT_TEST) {
            $ipaddress = getremoteaddr(null);
            $useragent = isset($_SERVER['HTTP_USER_AGENT'])
                ? (string) $_SERVER['HTTP_USER_AGENT']
                : null;
        }

        (new tracker())->open_session($userid, $ipaddress, $useragent);
    }

    /**
     * Close the user's session on explicit logout.
     *
     * @param user_loggedout $event
     * @return void
     */
    public static function user_loggedout(user_loggedout $event): void {
        $userid = (int) $event->userid;
        if ($userid <= 0 || isguestuser($userid)) {
            return;
        }
        (new tracker())->close_session($userid, tracker::CLOSURE_LOGOUT);
    }

    /**
     * Reflect a course-module completion update in the activity log.
     *
     * @param course_module_completion_updated $event
     * @return void
     */
    public static function course_module_completion_updated(course_module_completion_updated $event): void {
        $relateduser = (int) $event->relateduserid;
        if ($relateduser <= 0 || isguestuser($relateduser)) {
            return;
        }
        $cmid = (int) $event->contextinstanceid;
        if ($cmid <= 0) {
            return;
        }

        $data = $event->other;
        $state = null;
        if (is_array($data) && array_key_exists('completionstate', $data)) {
            $state = (int) $data['completionstate'];
        } else if (is_object($data) && isset($data->completionstate)) {
            $state = (int) $data->completionstate;
        }
        if ($state === null) {
            return;
        }

        $modulename = self::resolve_module_name($cmid);
        (new activity_repository())->set_completion_state(
            $relateduser,
            (int) $event->courseid,
            $cmid,
            $modulename,
            $state,
            time()
        );
    }

    /**
     * Index a quiz attempt as compliance evidence when it is submitted.
     *
     * @param event_base $event An instance of \mod_quiz\event\attempt_submitted.
     * @return void
     */
    public static function quiz_attempt_submitted(event_base $event): void {
        global $DB;

        $userid = (int) $event->relateduserid;
        if ($userid <= 0) {
            $userid = (int) $event->userid;
        }
        if ($userid <= 0 || isguestuser($userid)) {
            return;
        }

        $attemptid = (int) $event->objectid;
        if ($attemptid <= 0) {
            return;
        }

        $attempt = $DB->get_record('quiz_attempts', ['id' => $attemptid]);
        if (!$attempt) {
            return;
        }
        $quiz = $DB->get_record('quiz', ['id' => $attempt->quiz]);
        if (!$quiz) {
            return;
        }

        $cmid = (int) $event->contextinstanceid;
        if ($cmid <= 0) {
            return;
        }

        $score = $attempt->sumgrades !== null ? (float) $attempt->sumgrades : 0.0;
        $maxscore = $quiz->sumgrades !== null ? (float) $quiz->sumgrades : 0.0;
        $attemptdate = (int) ($attempt->timefinish ?: $event->timecreated);

        (new indexer())->index_attempt(
            $userid,
            (int) $event->courseid,
            $cmid,
            $score,
            $maxscore,
            $attemptdate,
            'quiz_attempts',
            $attemptid
        );
    }

    /**
     * Index an assign submission as compliance evidence when it has been graded.
     *
     * @param event_base $event An instance of \mod_assign\event\submission_graded.
     * @return void
     */
    public static function assign_submission_graded(event_base $event): void {
        global $DB;

        $userid = (int) $event->relateduserid;
        if ($userid <= 0 || isguestuser($userid)) {
            return;
        }

        $cmid = (int) $event->contextinstanceid;
        if ($cmid <= 0) {
            return;
        }

        $submission = $DB->get_record('assign_submission', ['id' => (int) $event->objectid]);
        if (!$submission) {
            return;
        }

        $grade = $DB->get_record('assign_grades', [
            'assignment' => $submission->assignment,
            'userid'     => $userid,
        ], '*', IGNORE_MULTIPLE);

        $score = $grade && $grade->grade !== null ? (float) $grade->grade : 0.0;

        $assign = $DB->get_record('assign', ['id' => $submission->assignment]);
        $maxscore = $assign && $assign->grade !== null ? (float) $assign->grade : 0.0;

        $attemptdate = (int) ($grade->timemodified ?? $submission->timemodified ?? $event->timecreated);

        (new indexer())->index_attempt(
            $userid,
            (int) $event->courseid,
            $cmid,
            $score,
            $maxscore,
            $attemptdate,
            'assign_submission',
            (int) $submission->id
        );
    }

    /**
     * Best-effort lookup of a module's frankenstyle short name from a cmid.
     *
     * @param int $cmid
     * @return string
     */
    private static function resolve_module_name(int $cmid): string {
        global $DB;
        $sql = "SELECT m.name
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module
                 WHERE cm.id = :cmid";
        $name = $DB->get_field_sql($sql, ['cmid' => $cmid]);
        return $name ? (string) $name : 'unknown';
    }
}
