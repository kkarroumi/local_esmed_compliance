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
 * Tests for the acknowledge_alert external function.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_esmed_compliance\external;

use core_external\external_api;
use local_esmed_compliance\alert\alert_repository;

/**
 * Tests for the  component.
 *
 * @covers \local_esmed_compliance\external\acknowledge_alert
 */
final class acknowledge_alert_test extends \advanced_testcase {
    /**
     * Admin with managealerts can acknowledge an open alert and the payload reports it.
     */
    public function test_admin_can_acknowledge_open_alert(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $user = $this->getDataGenerator()->create_user();
        $repo = new alert_repository();
        $id = $repo->raise((int) $user->id, null, alert_repository::TYPE_INACTIVITY_7D, [], 1700000000);

        $result = acknowledge_alert::execute($id);
        $result = external_api::clean_returnvalue(acknowledge_alert::execute_returns(), $result);

        $this->assertTrue($result['acknowledged']);
        $this->assertNotNull($result['acknowledged_at']);
        $this->assertGreaterThan(0, $result['acknowledged_by']);
    }

    /**
     * A plain student (no managealerts capability) is rejected.
     */
    public function test_execute_requires_managealerts_capability(): void {
        $this->resetAfterTest();
        $student = $this->getDataGenerator()->create_user();
        $this->setUser($student);
        $repo = new alert_repository();
        $id = $repo->raise((int) $student->id, null, alert_repository::TYPE_INACTIVITY_7D, [], 1700000000);

        $this->expectException(\required_capability_exception::class);
        acknowledge_alert::execute($id);
    }

    /**
     * Unknown alert ids surface as a moodle_exception, not a silent success.
     */
    public function test_execute_throws_on_unknown_alert(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->expectException(\moodle_exception::class);
        acknowledge_alert::execute(999999);
    }
}
