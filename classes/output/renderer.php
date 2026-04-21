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
 * Plugin renderer.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_esmed_compliance\output;

use plugin_renderer_base;

/**
 * Renderer for ESMED compliance pages.
 *
 * Centralises the mapping from raw metric counters produced by
 * metrics_provider to the Mustache context the dashboard template
 * expects. Keeping the transformation here keeps the template purely
 * presentational.
 */
class renderer extends plugin_renderer_base {
    /**
     * Render the compliance dashboard.
     *
     * @param array<string, mixed> $metrics Output of metrics_provider::collect().
     * @return string
     */
    public function render_dashboard(array $metrics): string {
        return $this->render_from_template(
            'local_esmed_compliance/dashboard',
            self::build_template_context($metrics)
        );
    }

    /**
     * Transform metrics into a template context with pre-formatted labels.
     *
     * @param array<string, mixed> $metrics
     * @return array<string, mixed>
     */
    public static function build_template_context(array $metrics): array {
        $sessions = $metrics['sessions'];
        $archives = $metrics['archives'];
        $alerts = $metrics['alerts'];
        $integrity = $metrics['integrity'];
        $openalerts = $metrics['open_alerts'] ?? [];

        $alertitems = [];
        foreach ($openalerts as $alert) {
            $alertitems[] = [
                'id'              => (int) $alert['id'],
                'userid'          => (int) $alert['userid'],
                'courseid'        => $alert['courseid'] !== null ? (int) $alert['courseid'] : 0,
                'alert_type'      => (string) $alert['alert_type'],
                'user_fullname'   => (string) $alert['user_fullname'],
                'course_fullname' => $alert['course_fullname'] !== null ? (string) $alert['course_fullname'] : '',
                'triggered_at'    => userdate((int) $alert['triggered_at']),
            ];
        }

        return [
            'generated_at' => userdate((int) $metrics['generated_at']),
            'sessions' => [
                'open'              => $sessions['open'],
                'closed_last_24h'   => $sessions['closed_last_24h'],
                'hours_today'       => self::format_hours((int) $sessions['seconds_today']),
            ],
            'archives' => [
                'total'        => $archives['total'],
                'attestations' => $archives['attestations'],
                'bordereaux'   => $archives['bordereaux'],
            ],
            'alerts' => [
                'unacknowledged' => $alerts['unacknowledged'],
                'last_7_days'    => $alerts['last_7_days'],
                'has_unacked'    => $alerts['unacknowledged'] > 0,
            ],
            'integrity' => [
                'valid'         => $integrity['valid'],
                'tampered'      => $integrity['tampered'],
                'missing'       => $integrity['missing'],
                'has_problems'  => ($integrity['tampered'] + $integrity['missing']) > 0,
            ],
            'open_alerts'      => $alertitems,
            'has_open_alerts'  => !empty($alertitems),
        ];
    }

    /**
     * Render the course picker shown when no courseid is provided.
     *
     * @param array<int, array{id:int, fullname:string, shortname:string}> $courses
     * @param \moodle_url                                                  $baseurl
     * @return string
     */
    public function render_attestations_course_picker(array $courses, \moodle_url $baseurl): string {
        return $this->render_from_template('local_esmed_compliance/attestations_course_picker', [
            'baseurl'     => $baseurl->out(false),
            'courses'     => $courses,
            'has_courses' => !empty($courses),
        ]);
    }

    /**
     * Render the enrolled-users table for one course.
     *
     * @param \stdClass                $course
     * @param array<int, array<mixed>> $rows Output of {@see attestation_listing::list_for_course()}.
     * @param \moodle_url              $baseurl
     * @param string                   $sesskey
     * @return string
     */
    public function render_attestations_for_course(
        \stdClass $course,
        array $rows,
        \moodle_url $baseurl,
        string $sesskey
    ): string {
        $users = [];
        foreach ($rows as $row) {
            $users[] = [
                'userid'            => (int) $row['userid'],
                'fullname'          => (string) $row['fullname'],
                'email'             => (string) $row['email'],
                'idnumber'          => (string) $row['idnumber'],
                'hours'             => self::format_hours((int) $row['total_seconds']),
                'attestation_count' => (int) $row['attestation_count'],
                'has_attestation'   => ((int) $row['attestation_count']) > 0,
                'last_sealed_at'    => $row['last_sealed_at'] !== null
                    ? userdate((int) $row['last_sealed_at'])
                    : '',
                'last_archive_id'   => $row['last_archive_id'] !== null
                    ? (int) $row['last_archive_id']
                    : 0,
            ];
        }

        return $this->render_from_template('local_esmed_compliance/attestations_course', [
            'courseid'     => (int) $course->id,
            'coursename'   => format_string($course->fullname),
            'shortname'    => format_string($course->shortname),
            'baseurl'      => $baseurl->out(false),
            'sesskey'      => $sesskey,
            'users'        => $users,
            'has_users'    => !empty($users),
        ]);
    }

    /**
     * Render the funder links list.
     *
     * @param array<int, array<string, mixed>> $rows   Pre-formatted rows.
     * @param \moodle_url                      $baseurl
     * @param string                           $sesskey
     * @return string
     */
    public function render_funder_links_list(array $rows, \moodle_url $baseurl, string $sesskey): string {
        return $this->render_from_template('local_esmed_compliance/funder_links_list', [
            'baseurl'   => $baseurl->out(false),
            'sesskey'   => $sesskey,
            'links'     => $rows,
            'has_links' => !empty($rows),
        ]);
    }

    /**
     * Render the course picker for creating a new funder link.
     *
     * @param array<int, array{id:int, fullname:string, shortname:string}> $courses
     * @param \moodle_url                                                  $baseurl
     * @return string
     */
    public function render_funder_course_picker(array $courses, \moodle_url $baseurl): string {
        return $this->render_from_template('local_esmed_compliance/funder_course_picker', [
            'baseurl'     => $baseurl->out(false),
            'courses'     => $courses,
            'has_courses' => !empty($courses),
        ]);
    }

    /**
     * Format seconds as French-style hours "1.50 h".
     *
     * @param int $seconds
     * @return string
     */
    private static function format_hours(int $seconds): string {
        if ($seconds <= 0) {
            return '0.00';
        }
        return number_format($seconds / 3600, 2, '.', '');
    }
}
