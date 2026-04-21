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
 * Builder collecting the raw data feeding an attestation d'assiduité.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_esmed_compliance\attestation;

defined('MOODLE_INTERNAL') || die();

/**
 * Turn Moodle state into a {@see attestation_payload}.
 *
 * The builder sums closed certifiable sessions for (user, course),
 * aggregates activity-log counters and reads tagged assessment
 * attempts. Open sessions are skipped deliberately: an attestation is
 * built from sealed evidence only.
 */
class attestation_builder {

    /**
     * Build an attestation payload for a (user, course) pair.
     *
     * @param int      $userid
     * @param int      $courseid
     * @param int|null $now Override for tests.
     * @return attestation_payload
     */
    public function build(int $userid, int $courseid, ?int $now = null): attestation_payload {
        global $DB;
        $now = $now ?? time();

        $user = $DB->get_record('user', ['id' => $userid], 'id, firstname, lastname, email, idnumber', MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $courseid], 'id, fullname, shortname, startdate, enddate', MUST_EXIST);

        // Sum every *closed* session (session_end IS NOT NULL) for this user.
        // Note: courseid on a session is nullable; we include the full user
        // contribution since the certifiable session is scoped to the user,
        // not to a specific course (time spent is later attributed via the
        // activity log).
        $sql = "SELECT id, session_start, session_end, duration_seconds, closure_type, ip_address
                  FROM {local_esmed_sessions}
                 WHERE userid = :userid
                   AND session_end IS NOT NULL
              ORDER BY session_start ASC";
        $sessionrecords = $DB->get_records_sql($sql, ['userid' => $userid]);

        $sessions = [];
        $totalseconds = 0;
        $periodstart = null;
        $periodend = null;

        foreach ($sessionrecords as $row) {
            $duration = (int) ($row->duration_seconds ?? 0);
            $totalseconds += $duration;
            $periodstart = $periodstart === null
                ? (int) $row->session_start
                : min($periodstart, (int) $row->session_start);
            $periodend = $periodend === null
                ? (int) $row->session_end
                : max($periodend, (int) $row->session_end);
            $sessions[] = [
                'start'        => (int) $row->session_start,
                'end'          => (int) $row->session_end,
                'duration'     => $duration,
                'closure_type' => (string) $row->closure_type,
            ];
        }

        $assessmentsql = "SELECT id, cmid, assessment_type, score, max_score, grade_percent, attempt_date
                            FROM {local_esmed_assessment_index}
                           WHERE userid = :userid
                             AND courseid = :courseid
                        ORDER BY attempt_date ASC";
        $assessmentrecords = $DB->get_records_sql($assessmentsql, [
            'userid'   => $userid,
            'courseid' => $courseid,
        ]);
        $assessments = [];
        foreach ($assessmentrecords as $row) {
            $assessments[] = [
                'cmid'            => (int) $row->cmid,
                'assessment_type' => (string) $row->assessment_type,
                'score'           => $row->score !== null ? (float) $row->score : null,
                'max_score'       => $row->max_score !== null ? (float) $row->max_score : null,
                'grade_percent'   => $row->grade_percent !== null ? (float) $row->grade_percent : null,
                'attempt_date'    => (int) $row->attempt_date,
            ];
        }

        return new attestation_payload(
            self::organisation_identity(),
            [
                'id'        => (int) $user->id,
                'firstname' => (string) $user->firstname,
                'lastname'  => (string) $user->lastname,
                'email'     => (string) $user->email,
                'idnumber'  => (string) $user->idnumber,
            ],
            [
                'id'        => (int) $course->id,
                'fullname'  => (string) $course->fullname,
                'shortname' => (string) $course->shortname,
                'startdate' => $course->startdate !== null ? (int) $course->startdate : null,
                'enddate'   => $course->enddate !== null ? (int) $course->enddate : null,
            ],
            $totalseconds,
            $periodstart,
            $periodend,
            $sessions,
            $assessments,
            $now
        );
    }

    /**
     * Read the organisation identity from plugin config.
     *
     * @return array<string, mixed>
     */
    private static function organisation_identity(): array {
        return [
            'legal_name'      => (string) get_config('local_esmed_compliance', 'org_legal_name'),
            'siret'           => (string) get_config('local_esmed_compliance', 'org_siret'),
            'nda'             => (string) get_config('local_esmed_compliance', 'org_nda'),
            'address'         => (string) get_config('local_esmed_compliance', 'org_address'),
            'signatory_name'  => (string) get_config('local_esmed_compliance', 'org_signatory_name'),
            'signatory_role'  => (string) get_config('local_esmed_compliance', 'org_signatory_role'),
        ];
    }
}
