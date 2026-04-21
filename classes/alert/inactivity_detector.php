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
 * Inactivity detector: flags learners with no recent certifiable session.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_esmed_compliance\alert;

defined('MOODLE_INTERNAL') || die();

/**
 * Scan active enrolments for learners who have not logged a certifiable
 * session in the last N days and raise one `inactivity_7d` alert per
 * (user, course) pair. Repeated runs skip pairs that already have an
 * unacknowledged open alert of the same type.
 */
class inactivity_detector {

    /** @var alert_repository */
    private alert_repository $alerts;

    /**
     * Constructor.
     *
     * @param alert_repository|null $alerts Injectable for tests.
     */
    public function __construct(?alert_repository $alerts = null) {
        $this->alerts = $alerts ?? new alert_repository();
    }

    /**
     * Detect stale learners and raise alerts, returning a tally.
     *
     * "Stale" means either:
     *   - the learner has never logged a session in this course; or
     *   - their last session ended strictly before `now - thresholddays * 86400`.
     *
     * The SQL pulls one row per (user, course) enrolment via
     * {user_enrolments}/{enrol}, so only genuinely enrolled learners are
     * considered — users who drift into courses through cohort sync will
     * be picked up naturally.
     *
     * @param int $thresholddays  How long without a session counts as inactive.
     * @param int $now
     * @return array{scanned:int, raised:int, skipped_open:int}
     */
    public function run(int $thresholddays = 7, ?int $now = null): array {
        global $DB;
        $now = $now ?? time();
        $cutoff = $now - ($thresholddays * 86400);

        $sql = "SELECT ue.userid AS userid,
                       e.courseid AS courseid,
                       (SELECT MAX(s.session_end)
                          FROM {local_esmed_sessions} s
                         WHERE s.userid = ue.userid
                           AND s.courseid = e.courseid
                           AND s.session_end IS NOT NULL) AS last_session_end,
                       (SELECT COUNT(1)
                          FROM {local_esmed_sessions} s
                         WHERE s.userid = ue.userid
                           AND s.courseid = e.courseid
                           AND s.session_end IS NULL) AS open_sessions
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                 WHERE ue.status = 0
                   AND e.status = 0";

        $rows = $DB->get_records_sql($sql);

        $scanned = 0;
        $raised = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $scanned++;
            // An open session means the learner is active right now.
            if ((int) $row->open_sessions > 0) {
                continue;
            }
            $lastend = $row->last_session_end !== null ? (int) $row->last_session_end : null;
            if ($lastend !== null && $lastend >= $cutoff) {
                continue;
            }
            if ($this->alerts->has_open_alert((int) $row->userid, (int) $row->courseid, alert_repository::TYPE_INACTIVITY_7D)) {
                $skipped++;
                continue;
            }
            $this->alerts->raise(
                (int) $row->userid,
                (int) $row->courseid,
                alert_repository::TYPE_INACTIVITY_7D,
                [
                    'threshold_days'   => $thresholddays,
                    'last_session_end' => $lastend,
                    'detected_at'      => $now,
                ],
                $now
            );
            $raised++;
        }

        return ['scanned' => $scanned, 'raised' => $raised, 'skipped_open' => $skipped];
    }
}
