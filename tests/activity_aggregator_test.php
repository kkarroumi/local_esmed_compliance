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
 * Activity aggregator tests.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_esmed_compliance;

use local_esmed_compliance\activity\activity_repository;
use local_esmed_compliance\activity\aggregator;

/**
 * Tests for the  component.
 *
 * @covers \local_esmed_compliance\activity\aggregator
 * @covers \local_esmed_compliance\activity\activity_repository
 */
final class activity_aggregator_test extends \advanced_testcase {
    /**
     * Folding a stream creates one row per cmid and credits time.
     */
    public function test_aggregate_from_events_inserts_rows(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        $events = [
            $this->event(101, 10, 'resource', 1700000000),
            $this->event(102, 10, 'quiz', 1700000120),
            $this->event(102, 10, 'quiz', 1700000300),
        ];

        (new aggregator())->aggregate_from_events((int) $user->id, $events, 900, 1700000400, 60);

        $row101 = $DB->get_record(activity_repository::TABLE, ['userid' => $user->id, 'cmid' => 101]);
        $row102 = $DB->get_record(activity_repository::TABLE, ['userid' => $user->id, 'cmid' => 102]);

        $this->assertNotFalse($row101);
        $this->assertEquals('resource', $row101->modulename);
        $this->assertEquals(120, $row101->time_spent_seconds);
        $this->assertEquals(1, $row101->views_count);

        $this->assertNotFalse($row102);
        $this->assertEquals('quiz', $row102->modulename);
        $this->assertEquals(240, $row102->time_spent_seconds);
        $this->assertEquals(2, $row102->views_count);
    }

    /**
     * A second aggregation pass accumulates rather than overwrites.
     */
    public function test_aggregate_from_events_accumulates(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        $aggregator = new aggregator();
        $aggregator->aggregate_from_events(
            (int) $user->id,
            [
                $this->event(101, 10, 'resource', 1700000000),
                $this->event(101, 10, 'resource', 1700000200),
            ],
            900,
            1700000300,
            60
        );
        $aggregator->aggregate_from_events(
            (int) $user->id,
            [
                $this->event(101, 10, 'resource', 1700001000),
                $this->event(101, 10, 'resource', 1700001100),
            ],
            900,
            1700001200,
            60
        );

        $row = $DB->get_record(activity_repository::TABLE, ['userid' => $user->id, 'cmid' => 101]);
        // First pass credits 200s transition + 60s tail = 260s; second pass 100 + 60 = 160s; total 420.
        $this->assertEquals(420, $row->time_spent_seconds);
        $this->assertEquals(4, $row->views_count);
        $this->assertEquals(1700000000, $row->first_access);
        $this->assertEquals(1700001100, $row->last_access);
    }

    /**
     * Completion updates create a row even when the learner never viewed the module.
     */
    public function test_set_completion_state_creates_row_if_missing(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        (new activity_repository())->set_completion_state(
            (int) $user->id,
            42,
            501,
            'quiz',
            1,
            1700000000
        );

        $row = $DB->get_record(activity_repository::TABLE, ['userid' => $user->id, 'cmid' => 501]);
        $this->assertNotFalse($row);
        $this->assertEquals(1, $row->completion_state);
        $this->assertEquals(0, $row->views_count);
        $this->assertEquals(0, $row->time_spent_seconds);
        $this->assertEquals('quiz', $row->modulename);
    }

    /**
     * Completion updates on an already tracked module only touch completion_state.
     */
    public function test_set_completion_state_preserves_counters(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        (new aggregator())->aggregate_from_events(
            (int) $user->id,
            [
                $this->event(501, 42, 'quiz', 1700000000),
                $this->event(501, 42, 'quiz', 1700000300),
            ],
            900,
            1700000400,
            60
        );
        (new activity_repository())->set_completion_state(
            (int) $user->id,
            42,
            501,
            'quiz',
            1,
            1700000500
        );

        $row = $DB->get_record(activity_repository::TABLE, ['userid' => $user->id, 'cmid' => 501]);
        // 300s transition + 60s tail = 360s, unchanged by the completion update.
        $this->assertEquals(360, $row->time_spent_seconds);
        $this->assertEquals(2, $row->views_count);
        $this->assertEquals(1, $row->completion_state);
    }

    /**
     * Helper: build an event payload.
     *
     * @param int    $cmid
     * @param int    $courseid
     * @param string $modulename
     * @param int    $timestamp
     * @return array
     */
    private function event(int $cmid, int $courseid, string $modulename, int $timestamp): array {
        return [
            'cmid'       => $cmid,
            'courseid'   => $courseid,
            'modulename' => $modulename,
            'timestamp'  => $timestamp,
        ];
    }
}
