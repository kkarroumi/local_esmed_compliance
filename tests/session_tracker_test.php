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
 * Session tracker tests.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_esmed_compliance;

use local_esmed_compliance\session\session_repository;
use local_esmed_compliance\session\tracker;

/**
 * Tests for the  component.
 *
 * @covers \local_esmed_compliance\session\tracker
 * @covers \local_esmed_compliance\session\session_repository
 */
final class session_tracker_test extends \advanced_testcase {
    /**
     * Opening a session for a user with none open must create one record.
     */
    public function test_open_session_creates_record(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        $tracker = new tracker();
        $sessionid = $tracker->open_session(
            (int) $user->id,
            '203.0.113.9',
            'PHPUnit',
            null,
            1700000000
        );

        $record = $DB->get_record('local_esmed_compliance_sessions', ['id' => $sessionid], '*', MUST_EXIST);
        $this->assertEquals($user->id, $record->userid);
        $this->assertEquals(1700000000, $record->session_start);
        $this->assertNull($record->session_end);
        $this->assertEquals(1700000000, $record->last_heartbeat);
        $this->assertEquals('203.0.113.9', $record->ip_address);
        $this->assertEquals('PHPUnit', $record->user_agent);
        $this->assertEquals(0, $record->sealed);
    }

    /**
     * Calling open_session twice in a row is idempotent.
     */
    public function test_open_session_is_idempotent(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        $tracker = new tracker();
        $first = $tracker->open_session((int) $user->id, null, null, null, 1700000000);
        $second = $tracker->open_session((int) $user->id, null, null, null, 1700000100);

        $this->assertEquals($first, $second);
        $this->assertEquals(
            1,
            $DB->count_records('local_esmed_compliance_sessions', ['userid' => $user->id])
        );
    }

    /**
     * Heartbeats on an open session update last_heartbeat.
     */
    public function test_record_heartbeat_updates_last_heartbeat(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        $tracker = new tracker();
        $sessionid = $tracker->open_session((int) $user->id, null, null, null, 1700000000);

        $result = $tracker->record_heartbeat((int) $user->id, 1700000050);
        $this->assertEquals($sessionid, $result);

        $record = $DB->get_record('local_esmed_compliance_sessions', ['id' => $sessionid], '*', MUST_EXIST);
        $this->assertEquals(1700000050, $record->last_heartbeat);
        $this->assertNull($record->session_end);
    }

    /**
     * Heartbeat with no open session returns null and does not create one.
     */
    public function test_record_heartbeat_without_open_session_returns_null(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        $result = (new tracker())->record_heartbeat((int) $user->id, 1700000000);

        $this->assertNull($result);
        $this->assertEquals(
            0,
            $DB->count_records('local_esmed_compliance_sessions', ['userid' => $user->id])
        );
    }

    /**
     * Closing a session sets session_end, duration and closure_type.
     */
    public function test_close_session_sets_end_and_duration(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        $tracker = new tracker();
        $sessionid = $tracker->open_session((int) $user->id, null, null, null, 1700000000);

        $closed = $tracker->close_session(
            (int) $user->id,
            tracker::CLOSURE_LOGOUT,
            1700000600
        );
        $this->assertTrue($closed);

        $record = $DB->get_record('local_esmed_compliance_sessions', ['id' => $sessionid], '*', MUST_EXIST);
        $this->assertEquals(1700000600, $record->session_end);
        $this->assertEquals(600, $record->duration_seconds);
        $this->assertEquals(tracker::CLOSURE_LOGOUT, $record->closure_type);
    }

    /**
     * Closing twice returns false on the second call (idempotent).
     */
    public function test_close_session_is_idempotent(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        $tracker = new tracker();
        $tracker->open_session((int) $user->id, null, null, null, 1700000000);

        $this->assertTrue($tracker->close_session((int) $user->id, tracker::CLOSURE_LOGOUT, 1700000600));
        $this->assertFalse($tracker->close_session((int) $user->id, tracker::CLOSURE_LOGOUT, 1700000700));
    }

    /**
     * Stale sessions with a heartbeat are closed as timeout; silent ones as crash.
     */
    public function test_close_stale_sessions_categorises_correctly(): void {
        global $DB;
        $this->resetAfterTest();
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $tracker = new tracker();

        // User1 opened a session and received one heartbeat, but is now silent.
        $s1 = $tracker->open_session((int) $user1->id, null, null, null, 1700000000);
        $tracker->record_heartbeat((int) $user1->id, 1700000050);

        // User2 opened a session and never beat (crash).
        $s2 = $tracker->open_session((int) $user2->id, null, null, null, 1700000000);
        $DB->set_field('local_esmed_compliance_sessions', 'last_heartbeat', null, ['id' => $s2]);

        // Now = 1700001000, timeout = 10 minutes (600s). Threshold = 1700000400.
        $closed = $tracker->close_stale_sessions(600, 1700001000);
        $this->assertEquals(2, $closed);

        $r1 = $DB->get_record('local_esmed_compliance_sessions', ['id' => $s1], '*', MUST_EXIST);
        $this->assertEquals(tracker::CLOSURE_TIMEOUT, $r1->closure_type);
        $this->assertEquals(1700000050, $r1->session_end, 'Timeout must end at last heartbeat, not now.');

        $r2 = $DB->get_record('local_esmed_compliance_sessions', ['id' => $s2], '*', MUST_EXIST);
        $this->assertEquals(tracker::CLOSURE_CRASH, $r2->closure_type);
        $this->assertEquals(1700000000, $r2->session_end, 'Crash must end at session_start (no heartbeat).');
    }

    /**
     * Sessions still within the idle window are left alone.
     */
    public function test_close_stale_sessions_leaves_fresh_sessions_open(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        $tracker = new tracker();
        $sessionid = $tracker->open_session((int) $user->id, null, null, null, 1700000000);
        $tracker->record_heartbeat((int) $user->id, 1700000800);

        $closed = $tracker->close_stale_sessions(600, 1700001000);

        $this->assertEquals(0, $closed);
        $record = $DB->get_record('local_esmed_compliance_sessions', ['id' => $sessionid], '*', MUST_EXIST);
        $this->assertNull($record->session_end);
    }

    /**
     * The repository enforces WHERE session_end IS NULL on heartbeat writes.
     */
    public function test_heartbeat_on_closed_session_is_noop(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        $tracker = new tracker();
        $tracker->open_session((int) $user->id, null, null, null, 1700000000);
        $tracker->close_session((int) $user->id, tracker::CLOSURE_LOGOUT, 1700000600);

        $result = $tracker->record_heartbeat((int) $user->id, 1700000900);
        $this->assertNull($result);
    }
}
