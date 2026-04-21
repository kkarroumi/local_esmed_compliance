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
 * Tests for the get_learner_summary external function.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_esmed_compliance\external;

use core_external\external_api;
use local_esmed_compliance\session\tracker;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \local_esmed_compliance\external\get_learner_summary
 */
final class get_learner_summary_test extends \advanced_testcase {

    /**
     * The learner's own summary is reachable via viewownreports and reflects closed-session totals.
     */
    public function test_execute_returns_learner_own_summary(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $tracker = new tracker();
        $tracker->open_session((int) $user->id, null, null, (int) $course->id, 1700000000);
        $tracker->close_session((int) $user->id, tracker::CLOSURE_LOGOUT, 1700001800);

        $this->setUser($user);
        $result = get_learner_summary::execute((int) $user->id, (int) $course->id);
        $result = external_api::clean_returnvalue(get_learner_summary::execute_returns(), $result);

        $this->assertEquals($user->id, $result['userid']);
        $this->assertEquals($course->id, $result['courseid']);
        $this->assertEquals(1800, $result['session_seconds']);
        $this->assertEquals(0, $result['activity_seconds']);
        $this->assertEquals(0, $result['attestations']);
    }

    /**
     * A learner cannot peek at another learner's summary.
     */
    public function test_execute_blocks_cross_user_access(): void {
        $this->resetAfterTest();
        $alice = $this->getDataGenerator()->create_user();
        $bob = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($alice->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($bob->id, $course->id, 'student');

        $this->setUser($alice);
        $this->expectException(\required_capability_exception::class);
        get_learner_summary::execute((int) $bob->id, (int) $course->id);
    }

    /**
     * A manager/admin can fetch anyone's summary thanks to viewdashboard.
     */
    public function test_admin_can_fetch_anyones_summary(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        $this->setAdminUser();
        $result = get_learner_summary::execute((int) $user->id, (int) $course->id);
        $result = external_api::clean_returnvalue(get_learner_summary::execute_returns(), $result);

        $this->assertEquals($user->id, $result['userid']);
        $this->assertEquals(0, $result['session_seconds']);
    }
}
