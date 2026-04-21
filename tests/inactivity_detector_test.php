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
 * Tests for the inactivity detector.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_esmed_compliance;

use local_esmed_compliance\alert\alert_repository;
use local_esmed_compliance\alert\inactivity_detector;
use local_esmed_compliance\session\tracker;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \local_esmed_compliance\alert\inactivity_detector
 * @covers \local_esmed_compliance\alert\alert_repository
 */
final class inactivity_detector_test extends \advanced_testcase {

    /**
     * Enrolled learner with no session and no enrolment newer than threshold raises an alert.
     */
    public function test_run_raises_alert_for_silent_learner(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        $detector = new inactivity_detector();
        $tally = $detector->run(7, 1700000000);

        $this->assertEquals(1, $tally['raised']);
        $this->assertEquals(1, $DB->count_records(alert_repository::TABLE, [
            'userid' => $user->id,
            'courseid' => $course->id,
            'alert_type' => alert_repository::TYPE_INACTIVITY_7D,
        ]));
    }

    /**
     * A recently-active learner (session ended within threshold) is not flagged.
     */
    public function test_run_skips_recently_active_learner(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        $now = 1700000000;
        $tracker = new tracker();
        $tracker->open_session((int) $user->id, null, null, (int) $course->id, $now - 3600);
        $tracker->close_session((int) $user->id, tracker::CLOSURE_LOGOUT, $now - 1800);

        $tally = (new inactivity_detector())->run(7, $now);
        $this->assertEquals(0, $tally['raised']);
        $this->assertEquals(0, $DB->count_records(alert_repository::TABLE));
    }

    /**
     * A learner with a currently open session is considered active regardless of last close.
     */
    public function test_run_skips_learner_with_open_session(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        $now = 1700000000;
        (new tracker())->open_session((int) $user->id, null, null, (int) $course->id, $now - 60);

        $tally = (new inactivity_detector())->run(7, $now);
        $this->assertEquals(0, $tally['raised']);
        $this->assertEquals(0, $DB->count_records(alert_repository::TABLE));
    }

    /**
     * Running the detector twice does not create a duplicate open alert.
     */
    public function test_run_is_idempotent_for_open_alerts(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        $detector = new inactivity_detector();
        $detector->run(7, 1700000000);
        $tally = $detector->run(7, 1700086400);

        $this->assertEquals(1, $DB->count_records(alert_repository::TABLE));
        $this->assertEquals(0, $tally['raised']);
        $this->assertEquals(1, $tally['skipped_open']);
    }

    /**
     * Acknowledge is idempotent: a second acknowledge preserves the first author/time.
     */
    public function test_acknowledge_is_idempotent(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();

        $repo = new alert_repository();
        $id = $repo->raise((int) $user->id, (int) $course->id, alert_repository::TYPE_INACTIVITY_7D, [], 1700000000);

        $this->assertTrue($repo->acknowledge($id, 42, 1700001000));
        $first = $repo->get($id);
        $this->assertTrue($repo->acknowledge($id, 99, 1700002000));
        $second = $repo->get($id);

        $this->assertEquals(1700001000, (int) $second->acknowledged_at);
        $this->assertEquals(42, (int) $second->acknowledged_by);
        $this->assertEquals($first->acknowledged_at, $second->acknowledged_at);
    }
}
