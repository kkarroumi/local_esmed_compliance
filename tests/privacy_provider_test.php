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
 * Privacy provider tests.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_esmed_compliance;

use context_user;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_esmed_compliance\privacy\provider;

/**
 * Tests for the  component.
 *
 * @covers \local_esmed_compliance\privacy\provider
 */
final class privacy_provider_test extends \core_privacy\tests\provider_testcase {
    /**
     * Metadata should cover every user-linked table shipped by the plugin.
     */
    public function test_get_metadata(): void {
        $collection = new collection('local_esmed_compliance');
        $collection = provider::get_metadata($collection);

        $tables = array_map(
            static fn ($item): string => $item->get_name(),
            $collection->get_collection()
        );

        $this->assertEqualsCanonicalizing(
            [
                'local_esmed_sessions',
                'local_esmed_activity_log',
                'local_esmed_assessment_index',
                'local_esmed_archive_index',
                'local_esmed_alerts',
            ],
            $tables
        );
    }

    /**
     * A user with no compliance data should have no context to export.
     */
    public function test_get_contexts_for_userid_without_data(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        $contextlist = provider::get_contexts_for_userid((int) $user->id);

        $this->assertCount(0, $contextlist);
    }

    /**
     * A user with a session record should surface their own user context.
     */
    public function test_get_contexts_for_userid_with_session(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        $DB->insert_record('local_esmed_sessions', (object) [
            'userid'        => $user->id,
            'session_start' => time(),
            'sealed'        => 0,
            'timecreated'   => time(),
            'timemodified'  => time(),
        ]);

        $contextlist = provider::get_contexts_for_userid((int) $user->id);

        $this->assertCount(1, $contextlist);
        $contexts = iterator_to_array($contextlist->get_contexts());
        $this->assertEquals(
            context_user::instance($user->id)->id,
            reset($contexts)->id
        );
    }

    /**
     * get_users_in_context should add the user whose CONTEXT_USER is being inspected.
     */
    public function test_get_users_in_context(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        $DB->insert_record('local_esmed_alerts', (object) [
            'userid'       => $user->id,
            'alert_type'   => 'inactivity_7d',
            'triggered_at' => time(),
        ]);

        $context = context_user::instance($user->id);
        $userlist = new userlist($context, 'local_esmed_compliance');

        provider::get_users_in_context($userlist);

        $this->assertEqualsCanonicalizing(
            [$user->id],
            $userlist->get_userids()
        );
    }

    /**
     * Export should write all tables under the expected subcontext keys.
     */
    public function test_export_user_data(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $now = time();

        $DB->insert_record('local_esmed_sessions', (object) [
            'userid'        => $user->id,
            'session_start' => $now,
            'sealed'        => 0,
            'timecreated'   => $now,
            'timemodified'  => $now,
        ]);
        $DB->insert_record('local_esmed_activity_log', (object) [
            'userid'             => $user->id,
            'courseid'           => 1,
            'cmid'               => 42,
            'modulename'         => 'quiz',
            'time_spent_seconds' => 120,
            'views_count'        => 3,
            'timemodified'       => $now,
        ]);

        $context = context_user::instance($user->id);
        $approvedcontextlist = new approved_contextlist(
            $user,
            'local_esmed_compliance',
            [$context->id]
        );

        provider::export_user_data($approvedcontextlist);

        $writer = writer::with_context($context);
        $this->assertTrue($writer->has_any_data());
    }

    /**
     * Alerts are fully deleted while session identifiers are redacted only.
     */
    public function test_delete_data_for_user_enforces_retention_policy(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $now = time();

        $sessionid = $DB->insert_record('local_esmed_sessions', (object) [
            'userid'        => $user->id,
            'session_start' => $now,
            'ip_address'    => '203.0.113.1',
            'user_agent'    => 'Mozilla/5.0',
            'sealed'        => 0,
            'timecreated'   => $now,
            'timemodified'  => $now,
        ]);
        $DB->insert_record('local_esmed_alerts', (object) [
            'userid'       => $user->id,
            'alert_type'   => 'inactivity_7d',
            'triggered_at' => $now,
        ]);

        $context = context_user::instance($user->id);
        $approvedcontextlist = new approved_contextlist(
            $user,
            'local_esmed_compliance',
            [$context->id]
        );

        provider::delete_data_for_user($approvedcontextlist);

        $session = $DB->get_record('local_esmed_sessions', ['id' => $sessionid], '*', MUST_EXIST);
        $this->assertNull($session->ip_address);
        $this->assertNull($session->user_agent);
        $this->assertEquals($user->id, $session->userid, 'Session evidence must remain linked to the user.');

        $this->assertEquals(
            0,
            $DB->count_records('local_esmed_alerts', ['userid' => $user->id]),
            'Operational alerts must be fully deleted.'
        );
    }

    /**
     * delete_data_for_users should apply the same policy for every user in the list.
     */
    public function test_delete_data_for_users(): void {
        global $DB;
        $this->resetAfterTest();
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $now = time();

        foreach ([$user1, $user2] as $user) {
            $DB->insert_record('local_esmed_sessions', (object) [
                'userid'        => $user->id,
                'session_start' => $now,
                'ip_address'    => '10.0.0.1',
                'user_agent'    => 'Mozilla/5.0',
                'sealed'        => 0,
                'timecreated'   => $now,
                'timemodified'  => $now,
            ]);
        }

        $context = context_user::instance($user1->id);
        $approveduserlist = new approved_userlist(
            $context,
            'local_esmed_compliance',
            [$user1->id]
        );

        provider::delete_data_for_users($approveduserlist);

        $this->assertNull(
            $DB->get_field('local_esmed_sessions', 'ip_address', ['userid' => $user1->id])
        );
        $this->assertEquals(
            '10.0.0.1',
            $DB->get_field('local_esmed_sessions', 'ip_address', ['userid' => $user2->id]),
            'Users outside the approved list must be untouched.'
        );
    }
}
