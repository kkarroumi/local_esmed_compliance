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
 * Time calculator tests.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_esmed_compliance;

use local_esmed_compliance\activity\time_calculator;

/**
 * Tests for the  component.
 *
 * @covers \local_esmed_compliance\activity\time_calculator
 */
final class time_calculator_test extends \advanced_testcase {
    /**
     * An empty event stream produces no aggregates.
     */
    public function test_empty_stream_returns_empty(): void {
        $this->assertSame([], time_calculator::aggregate([], 900));
    }

    /**
     * A single event is credited with the tail allowance only.
     */
    public function test_single_event_uses_tail_allowance(): void {
        $events = [
            $this->event(101, 10, 'resource', 1700000000),
        ];
        $result = time_calculator::aggregate($events, 900, 60);

        $this->assertArrayHasKey(101, $result);
        $this->assertEquals(60, $result[101]['time_spent_seconds']);
        $this->assertEquals(1, $result[101]['views_count']);
        $this->assertEquals(1700000000, $result[101]['first_access']);
        $this->assertEquals(1700000000, $result[101]['last_access']);
    }

    /**
     * Consecutive events within the cap accumulate the full delta on the previous module.
     */
    public function test_within_cap_attribution(): void {
        $events = [
            $this->event(101, 10, 'resource', 1700000000),
            $this->event(102, 10, 'quiz', 1700000120), // Credits +120s to 101.
            $this->event(102, 10, 'quiz', 1700000300), // Credits +180s to 102.
        ];
        $result = time_calculator::aggregate($events, 900, 60);

        $this->assertEquals(120, $result[101]['time_spent_seconds']);
        // 102 receives the 180s transition delta plus the 60s tail allowance for the last view.
        $this->assertEquals(240, $result[102]['time_spent_seconds']);
        $this->assertEquals(1, $result[101]['views_count']);
        $this->assertEquals(2, $result[102]['views_count']);
    }

    /**
     * A gap larger than the cap falls back to the tail allowance and does not leak overnight time.
     */
    public function test_gap_beyond_cap_falls_back_to_tail(): void {
        $events = [
            $this->event(101, 10, 'resource', 1700000000),
            // 2 hours later, same user comes back on a different module.
            $this->event(102, 10, 'quiz', 1700007200),
        ];
        $result = time_calculator::aggregate($events, 900, 60);

        // 101 gets the tail allowance (run closed by the overlarge gap), not 7200s.
        $this->assertEquals(60, $result[101]['time_spent_seconds']);
        // 102 is the terminal event and also gets a tail allowance.
        $this->assertEquals(60, $result[102]['time_spent_seconds']);
    }

    /**
     * A negative or zero cap degrades to the tail allowance only.
     */
    public function test_zero_cap_credits_tail_only(): void {
        $events = [
            $this->event(101, 10, 'resource', 1700000000),
            $this->event(102, 10, 'quiz', 1700000060),
        ];
        $result = time_calculator::aggregate($events, 0, 0);
        $this->assertEquals(0, $result[101]['time_spent_seconds']);
        $this->assertEquals(0, $result[102]['time_spent_seconds']);
    }

    /**
     * First and last access bounds reflect min/max timestamps when the learner revisits.
     */
    public function test_first_and_last_access_track_bounds(): void {
        $events = [
            $this->event(101, 10, 'resource', 1700000300),
            $this->event(101, 10, 'resource', 1700000000),
            $this->event(101, 10, 'resource', 1700000600),
        ];
        $result = time_calculator::aggregate($events, 900, 60);
        $this->assertEquals(1700000000, $result[101]['first_access']);
        $this->assertEquals(1700000600, $result[101]['last_access']);
        $this->assertEquals(3, $result[101]['views_count']);
    }

    /**
     * Helper: build an event payload.
     *
     * @param int    $cmid
     * @param int    $courseid
     * @param string $modulename
     * @param int    $timestamp
     * @return array<string, mixed>
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
