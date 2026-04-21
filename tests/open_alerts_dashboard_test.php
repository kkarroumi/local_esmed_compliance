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
 * Tests for the open-alerts dashboard section.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_esmed_compliance;

use local_esmed_compliance\alert\alert_repository;
use local_esmed_compliance\dashboard\metrics_provider;
use local_esmed_compliance\output\renderer;

/**
 * Tests for the  component.
 *
 * @covers \local_esmed_compliance\alert\alert_repository::find_open_alerts
 * @covers \local_esmed_compliance\dashboard\metrics_provider::collect
 * @covers \local_esmed_compliance\output\renderer::build_template_context
 */
final class open_alerts_dashboard_test extends \advanced_testcase {
    /**
     * Only unacknowledged alerts appear, ordered by triggered_at DESC,
     * and each row carries user + course display data.
     */
    public function test_find_open_alerts_returns_unacked_newest_first(): void {
        $this->resetAfterTest();
        $alice = $this->getDataGenerator()->create_user(['firstname' => 'Alice', 'lastname' => 'Doe']);
        $bob = $this->getDataGenerator()->create_user(['firstname' => 'Bob', 'lastname' => 'Roe']);
        $course = $this->getDataGenerator()->create_course(['fullname' => 'DemoCourse']);

        $repo = new alert_repository();
        $old = $repo->raise((int) $alice->id, (int) $course->id, alert_repository::TYPE_INACTIVITY_7D, [], 1700000000);
        $new = $repo->raise((int) $bob->id, (int) $course->id, alert_repository::TYPE_INACTIVITY_7D, [], 1700001000);
        $acked = $repo->raise((int) $alice->id, null, alert_repository::TYPE_INACTIVITY_7D, [], 1699000000);
        $repo->acknowledge($acked, 1, 1699500000);

        $rows = $repo->find_open_alerts(50);

        $this->assertCount(2, $rows);
        $this->assertEquals($new, $rows[0]['id']);
        $this->assertEquals($old, $rows[1]['id']);
        $this->assertStringContainsString('Bob', $rows[0]['user_fullname']);
        $this->assertEquals('DemoCourse', $rows[0]['course_fullname']);
    }

    /**
     * The provider exposes open alerts and the renderer preformats triggered_at.
     */
    public function test_metrics_provider_and_renderer_surface_open_alerts(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course(['fullname' => 'RenderingCourse']);
        (new alert_repository())->raise(
            (int) $user->id,
            (int) $course->id,
            alert_repository::TYPE_INACTIVITY_7D,
            [],
            1700000000
        );

        $metrics = (new metrics_provider())->collect(1700001000);
        $this->assertCount(1, $metrics['open_alerts']);

        $context = renderer::build_template_context($metrics);
        $this->assertTrue($context['has_open_alerts']);
        $this->assertCount(1, $context['open_alerts']);
        $this->assertEquals('RenderingCourse', $context['open_alerts'][0]['course_fullname']);
        $this->assertNotEmpty($context['open_alerts'][0]['triggered_at']);
    }

    /**
     * An empty set leaves the counters at zero and the context flag at false.
     */
    public function test_metrics_without_alerts_has_empty_list(): void {
        $this->resetAfterTest();
        $metrics = (new metrics_provider())->collect(1700000000);
        $context = renderer::build_template_context($metrics);
        $this->assertFalse($context['has_open_alerts']);
        $this->assertSame([], $context['open_alerts']);
    }
}
