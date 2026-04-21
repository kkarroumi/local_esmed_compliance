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
 * Builder that turns a funder link into a bordereau payload.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_esmed_compliance\funder;

use context_course;

/**
 * Aggregate certifiable session time for every enrolled learner on a
 * given funder link, clipped to the funder period when set.
 *
 * Learners with zero tracked time are still included: the funder
 * reconciliation documents must reflect the nominal enrolment, even
 * when attendance is nil.
 */
class bordereau_builder {
    /** @var funder_link_repository */
    private funder_link_repository $links;

    /**
     * Constructor.
     *
     * @param funder_link_repository|null $links Injectable for tests.
     */
    public function __construct(?funder_link_repository $links = null) {
        $this->links = $links ?? new funder_link_repository();
    }

    /**
     * Build the payload for one funder link.
     *
     * @param int      $funderlinkid
     * @param int|null $now
     * @return bordereau_payload
     * @throws \moodle_exception If the funder link no longer exists.
     */
    public function build(int $funderlinkid, ?int $now = null): bordereau_payload {
        global $DB;

        $link = $this->links->get($funderlinkid);
        if ($link === null) {
            throw new \moodle_exception('funder_link_notfound', 'local_esmed_compliance');
        }

        $course = $DB->get_record('course', ['id' => $link->courseid], 'id, fullname, shortname, startdate, enddate', MUST_EXIST);

        $periodstart = $link->start_date !== null ? (int) $link->start_date : null;
        $periodend   = $link->end_date !== null ? (int) $link->end_date : null;

        $enrolled = get_enrolled_users(
            context_course::instance($link->courseid),
            '',
            0,
            'u.id, u.firstname, u.lastname, u.email, u.idnumber'
        );

        $learners = [];
        $totalseconds = 0;
        foreach ($enrolled as $user) {
            $contribution = self::learner_contribution((int) $user->id, $periodstart, $periodend);
            $totalseconds += $contribution['duration'];
            $learners[] = [
                'userid'        => (int) $user->id,
                'firstname'     => (string) $user->firstname,
                'lastname'      => (string) $user->lastname,
                'email'         => (string) $user->email,
                'idnumber'      => (string) $user->idnumber,
                'duration'      => $contribution['duration'],
                'sessions'      => $contribution['sessions'],
                'first_session' => $contribution['first_session'],
                'last_session'  => $contribution['last_session'],
            ];
        }

        // Stable ordering: by lastname, firstname, userid so regenerated
        // bordereaux hash identically when the data has not changed.
        usort($learners, static function ($a, $b): int {
            return [$a['lastname'], $a['firstname'], $a['userid']]
                <=> [$b['lastname'], $b['firstname'], $b['userid']];
        });

        return new bordereau_payload(
            self::organisation_identity(),
            [
                'type'            => (string) $link->funder_type,
                'dossier_number'  => (string) ($link->dossier_number ?? ''),
                'action_intitule' => (string) ($link->action_intitule ?? ''),
                'opco_name'       => (string) ($link->opco_name ?? ''),
                'hours_planned'   => $link->total_hours_planned !== null ? (float) $link->total_hours_planned : null,
            ],
            [
                'id'        => (int) $course->id,
                'fullname'  => (string) $course->fullname,
                'shortname' => (string) $course->shortname,
            ],
            $learners,
            $totalseconds,
            count($learners),
            $periodstart,
            $periodend,
            $now ?? time()
        );
    }

    /**
     * Sum a single learner's closed-session durations, optionally clipped to a period.
     *
     * When a period is set, every session is clipped to the intersection
     * of `[session_start, session_end]` and `[periodstart, periodend]`
     * before its contribution is added. Sessions with no overlap return
     * zero.
     *
     * @param int      $userid
     * @param int|null $periodstart
     * @param int|null $periodend
     * @return array{duration:int, sessions:int, first_session:?int, last_session:?int}
     */
    private static function learner_contribution(int $userid, ?int $periodstart, ?int $periodend): array {
        global $DB;

        $sql = "SELECT id, session_start, session_end
                  FROM {local_esmed_sessions}
                 WHERE userid = :userid
                   AND session_end IS NOT NULL";
        $rows = $DB->get_records_sql($sql, ['userid' => $userid]);

        $total = 0;
        $count = 0;
        $first = null;
        $last = null;
        foreach ($rows as $row) {
            $start = (int) $row->session_start;
            $end   = (int) $row->session_end;

            if ($periodstart !== null) {
                $start = max($start, $periodstart);
            }
            if ($periodend !== null) {
                $end = min($end, $periodend);
            }
            if ($end <= $start) {
                continue;
            }

            $total += $end - $start;
            $count++;
            $first = $first === null ? $start : min($first, $start);
            $last  = $last === null ? $end : max($last, $end);
        }

        return [
            'duration'      => $total,
            'sessions'      => $count,
            'first_session' => $first,
            'last_session'  => $last,
        ];
    }

    /**
     * Read the organisation identity from plugin config.
     *
     * @return array
     */
    private static function organisation_identity(): array {
        return [
            'legal_name'     => (string) get_config('local_esmed_compliance', 'org_legal_name'),
            'siret'          => (string) get_config('local_esmed_compliance', 'org_siret'),
            'nda'            => (string) get_config('local_esmed_compliance', 'org_nda'),
            'address'        => (string) get_config('local_esmed_compliance', 'org_address'),
            'signatory_name' => (string) get_config('local_esmed_compliance', 'org_signatory_name'),
            'signatory_role' => (string) get_config('local_esmed_compliance', 'org_signatory_role'),
        ];
    }
}
