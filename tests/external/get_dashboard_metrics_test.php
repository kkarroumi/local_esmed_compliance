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
 * Tests for the get_dashboard_metrics external function.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_esmed_compliance\external;

use core_external\external_api;

/**
 * Tests for the  component.
 *
 * @covers \local_esmed_compliance\external\get_dashboard_metrics
 */
final class get_dashboard_metrics_test extends \advanced_testcase {
    /**
     * Managers (who have viewdashboard) receive the full shape, all zeros on empty install.
     */
    public function test_execute_returns_expected_shape_for_manager(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $result = get_dashboard_metrics::execute();
        $result = external_api::clean_returnvalue(get_dashboard_metrics::execute_returns(), $result);

        $this->assertArrayHasKey('generated_at', $result);
        $this->assertArrayHasKey('sessions', $result);
        $this->assertArrayHasKey('archives', $result);
        $this->assertArrayHasKey('alerts', $result);
        $this->assertArrayHasKey('integrity', $result);
        $this->assertEquals(0, $result['sessions']['open']);
        $this->assertEquals(0, $result['archives']['total']);
        $this->assertEquals(0, $result['alerts']['unacknowledged']);
        $this->assertEquals(0, $result['integrity']['tampered']);
    }

    /**
     * Plain students have no viewdashboard capability and must be rejected.
     */
    public function test_execute_requires_viewdashboard_capability(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->expectException(\required_capability_exception::class);
        get_dashboard_metrics::execute();
    }
}
