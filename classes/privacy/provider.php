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
 * Privacy provider.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_esmed_compliance\privacy;

use context;
use context_user;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider.
 *
 * All user-linked compliance data is anchored in the learner's own
 * {@see \context_user}. Course and module references are carried inside
 * each record but do not widen the set of contexts Moodle has to iterate
 * over during export or deletion.
 *
 * Deletion semantics observe the tension between GDPR and the legal
 * retention window required by article L.6353-1 of the French Labour
 * Code and the Qualiopi framework: evidence tables are pseudonymised
 * (direct identifiers such as IP address and user-agent are nulled out)
 * while purely operational records (alerts) are fully deleted.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Tables that contain user-linked compliance data.
     *
     * @var string[]
     */
    private const USER_TABLES = [
        'local_esmed_sessions',
        'local_esmed_activity_log',
        'local_esmed_assessment_index',
        'local_esmed_archive_index',
        'local_esmed_alerts',
    ];

    /**
     * Describe the personal data this plugin stores.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {

        $collection->add_database_table(
            'local_esmed_sessions',
            [
                'userid'           => 'privacy:metadata:local_esmed_sessions:userid',
                'courseid'         => 'privacy:metadata:local_esmed_sessions:courseid',
                'session_start'    => 'privacy:metadata:local_esmed_sessions:session_start',
                'session_end'      => 'privacy:metadata:local_esmed_sessions:session_end',
                'duration_seconds' => 'privacy:metadata:local_esmed_sessions:duration_seconds',
                'ip_address'       => 'privacy:metadata:local_esmed_sessions:ip_address',
                'user_agent'       => 'privacy:metadata:local_esmed_sessions:user_agent',
                'closure_type'     => 'privacy:metadata:local_esmed_sessions:closure_type',
            ],
            'privacy:metadata:local_esmed_sessions'
        );

        $collection->add_database_table(
            'local_esmed_activity_log',
            [
                'userid'             => 'privacy:metadata:local_esmed_activity_log:userid',
                'courseid'           => 'privacy:metadata:local_esmed_activity_log:courseid',
                'cmid'               => 'privacy:metadata:local_esmed_activity_log:cmid',
                'time_spent_seconds' => 'privacy:metadata:local_esmed_activity_log:time_spent_seconds',
                'views_count'        => 'privacy:metadata:local_esmed_activity_log:views_count',
                'completion_state'   => 'privacy:metadata:local_esmed_activity_log:completion_state',
            ],
            'privacy:metadata:local_esmed_activity_log'
        );

        $collection->add_database_table(
            'local_esmed_assessment_index',
            [
                'userid'          => 'privacy:metadata:local_esmed_assessment_index:userid',
                'courseid'        => 'privacy:metadata:local_esmed_assessment_index:courseid',
                'cmid'            => 'privacy:metadata:local_esmed_assessment_index:cmid',
                'assessment_type' => 'privacy:metadata:local_esmed_assessment_index:assessment_type',
                'score'           => 'privacy:metadata:local_esmed_assessment_index:score',
                'grade_percent'   => 'privacy:metadata:local_esmed_assessment_index:grade_percent',
                'attempt_date'    => 'privacy:metadata:local_esmed_assessment_index:attempt_date',
            ],
            'privacy:metadata:local_esmed_assessment_index'
        );

        $collection->add_database_table(
            'local_esmed_archive_index',
            [
                'userid'             => 'privacy:metadata:local_esmed_archive_index:userid',
                'courseid'           => 'privacy:metadata:local_esmed_archive_index:courseid',
                'archive_type'       => 'privacy:metadata:local_esmed_archive_index:archive_type',
                'file_path'          => 'privacy:metadata:local_esmed_archive_index:file_path',
                'sha256_hash'        => 'privacy:metadata:local_esmed_archive_index:sha256_hash',
                'verification_token' => 'privacy:metadata:local_esmed_archive_index:verification_token',
                'timestamp_sealed'   => 'privacy:metadata:local_esmed_archive_index:timestamp_sealed',
                'retention_until'    => 'privacy:metadata:local_esmed_archive_index:retention_until',
            ],
            'privacy:metadata:local_esmed_archive_index'
        );

        $collection->add_database_table(
            'local_esmed_alerts',
            [
                'userid'          => 'privacy:metadata:local_esmed_alerts:userid',
                'courseid'        => 'privacy:metadata:local_esmed_alerts:courseid',
                'alert_type'      => 'privacy:metadata:local_esmed_alerts:alert_type',
                'alert_data_json' => 'privacy:metadata:local_esmed_alerts:alert_data_json',
                'triggered_at'    => 'privacy:metadata:local_esmed_alerts:triggered_at',
            ],
            'privacy:metadata:local_esmed_alerts'
        );

        return $collection;
    }

    /**
     * Return the contexts where the given user has compliance data.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $contextlist = new contextlist();

        foreach (self::USER_TABLES as $table) {
            if ($DB->record_exists($table, ['userid' => $userid])) {
                $contextlist->add_user_context($userid);
                break;
            }
        }

        // Acknowledgements performed by staff on alerts also count as personal data.
        if ($DB->record_exists('local_esmed_alerts', ['acknowledged_by' => $userid])) {
            $contextlist->add_user_context($userid);
        }

        return $contextlist;
    }

    /**
     * List the users who have data in the supplied context.
     *
     * @param userlist $userlist
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof context_user) {
            return;
        }

        global $DB;
        $userid = $context->instanceid;

        foreach (self::USER_TABLES as $table) {
            if ($DB->record_exists($table, ['userid' => $userid])) {
                $userlist->add_user($userid);
                return;
            }
        }

        if ($DB->record_exists('local_esmed_alerts', ['acknowledged_by' => $userid])) {
            $userlist->add_user($userid);
        }
    }

    /**
     * Export all compliance data for the given contextlist.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        if (!count($contextlist)) {
            return;
        }

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_user || (int) $context->instanceid !== (int) $userid) {
                continue;
            }

            self::export_table_records(
                $context,
                'local_esmed_sessions',
                $userid,
                get_string('privacy:subcontext:sessions', 'local_esmed_compliance')
            );
            self::export_table_records(
                $context,
                'local_esmed_activity_log',
                $userid,
                get_string('privacy:subcontext:activity', 'local_esmed_compliance')
            );
            self::export_table_records(
                $context,
                'local_esmed_assessment_index',
                $userid,
                get_string('privacy:subcontext:assessments', 'local_esmed_compliance')
            );
            self::export_table_records(
                $context,
                'local_esmed_archive_index',
                $userid,
                get_string('privacy:subcontext:archives', 'local_esmed_compliance')
            );
            self::export_table_records(
                $context,
                'local_esmed_alerts',
                $userid,
                get_string('privacy:subcontext:alerts', 'local_esmed_compliance')
            );
        }
    }

    /**
     * Write one table's rows for a user inside the approved context.
     *
     * @param context $context
     * @param string  $table
     * @param int     $userid
     * @param string  $subcontext
     * @return void
     */
    private static function export_table_records(
        context $context,
        string $table,
        int $userid,
        string $subcontext
    ): void {
        global $DB;

        $records = $DB->get_records($table, ['userid' => $userid], 'id ASC');
        if (empty($records)) {
            return;
        }

        // Cast records to a plain array keyed by id to keep the export stable.
        $exportdata = [];
        foreach ($records as $record) {
            $exportdata[] = (array) $record;
        }

        writer::with_context($context)->export_data(
            [$subcontext],
            (object) ['records' => $exportdata]
        );
    }

    /**
     * Delete or pseudonymise data for every user who has data in this context.
     *
     * @param context $context
     * @return void
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        if (!$context instanceof context_user) {
            return;
        }
        self::delete_for_userid((int) $context->instanceid);
    }

    /**
     * Delete or pseudonymise data for the user described by the contextlist.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        if (!count($contextlist)) {
            return;
        }
        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof context_user && (int) $context->instanceid === (int) $userid) {
                self::delete_for_userid($userid);
                return;
            }
        }
    }

    /**
     * Delete or pseudonymise data for every user in the approved userlist.
     *
     * @param approved_userlist $userlist
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof context_user) {
            return;
        }
        foreach ($userlist->get_userids() as $userid) {
            self::delete_for_userid((int) $userid);
        }
    }

    /**
     * Apply the plugin-wide deletion policy for a single user.
     *
     * Evidence tables (sessions, activity log, assessments, sealed archive
     * index) are kept until the legal retention window elapses but direct
     * identifiers on session records are redacted. Alerts are operational
     * data and are fully removed.
     *
     * @param int $userid
     * @return void
     */
    private static function delete_for_userid(int $userid): void {
        global $DB;

        $DB->set_field(
            'local_esmed_sessions',
            'ip_address',
            null,
            ['userid' => $userid]
        );
        $DB->set_field(
            'local_esmed_sessions',
            'user_agent',
            null,
            ['userid' => $userid]
        );

        $DB->delete_records('local_esmed_alerts', ['userid' => $userid]);
        $DB->set_field(
            'local_esmed_alerts',
            'acknowledged_by',
            null,
            ['acknowledged_by' => $userid]
        );
    }
}
