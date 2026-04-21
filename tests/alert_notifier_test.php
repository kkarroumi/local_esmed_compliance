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
 * Tests for the alert notifier.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_esmed_compliance;

use local_esmed_compliance\alert\alert_repository;
use local_esmed_compliance\alert\notifier;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \local_esmed_compliance\alert\notifier
 */
final class alert_notifier_test extends \advanced_testcase {

    /**
     * Operators with managealerts at the course context receive the message;
     * the learner themselves is excluded; notified_at is stamped.
     */
    public function test_notify_sends_to_course_operators_and_stamps_row(): void {
        global $DB;
        $this->resetAfterTest();

        $learner = $this->getDataGenerator()->create_user();
        $teacher = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($learner->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');

        $repo = new alert_repository();
        $id = $repo->raise((int) $learner->id, (int) $course->id, alert_repository::TYPE_INACTIVITY_7D, [], 1700000000);

        $sent = [];
        $notifier = new notifier($repo, function ($msg) use (&$sent) {
            $sent[] = (int) $msg->userto;
            return 12345;
        });

        $recipients = $notifier->notify($id, 1700001000);

        $this->assertEquals(1, $recipients);
        $this->assertEquals([(int) $teacher->id], $sent);

        $row = $DB->get_record(alert_repository::TABLE, ['id' => $id]);
        $this->assertEquals(1700001000, (int) $row->notified_at);
    }

    /**
     * A second notify call on the same alert is a no-op (idempotent) and sends no messages.
     */
    public function test_notify_is_idempotent(): void {
        $this->resetAfterTest();

        $learner = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->create_user(); // Extra user to ensure some lookups exist.
        $repo = new alert_repository();
        $id = $repo->raise((int) $learner->id, null, alert_repository::TYPE_INACTIVITY_7D, [], 1700000000);
        $this->setAdminUser(); // Admin has managealerts system-wide via manager archetype.

        $count = 0;
        $notifier = new notifier($repo, function ($msg) use (&$count) {
            $count++;
            return 1;
        });

        $first = $notifier->notify($id, 1700001000);
        $this->assertGreaterThanOrEqual(1, $first);
        $firstsent = $count;

        $second = $notifier->notify($id, 1700002000);
        $this->assertEquals(0, $second);
        $this->assertEquals($firstsent, $count);
    }

    /**
     * Unknown alert ids are a safe no-op, not a crash.
     */
    public function test_notify_no_op_for_unknown_alert(): void {
        $this->resetAfterTest();
        $notifier = new notifier(new alert_repository(), function ($msg) {
            return 1;
        });
        $this->assertEquals(0, $notifier->notify(999999, 1700000000));
    }
}
