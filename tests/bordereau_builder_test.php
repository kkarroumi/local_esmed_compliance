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
 * Bordereau builder tests.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_esmed_compliance;

use local_esmed_compliance\funder\bordereau_builder;
use local_esmed_compliance\funder\funder_link_repository;
use local_esmed_compliance\session\tracker;
use stdClass;

/**
 * Tests for the  component.
 *
 * @covers \local_esmed_compliance\funder\bordereau_builder
 * @covers \local_esmed_compliance\funder\bordereau_payload
 */
final class bordereau_builder_test extends \advanced_testcase {
    /**
     * Every enrolled user appears in the payload, even those with no tracked time.
     */
    public function test_build_includes_every_enrolled_learner(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $active = $this->getDataGenerator()->create_user(['lastname' => 'Active', 'firstname' => 'Alice']);
        $silent = $this->getDataGenerator()->create_user(['lastname' => 'Silent', 'firstname' => 'Bob']);
        $this->getDataGenerator()->enrol_user($active->id, $course->id);
        $this->getDataGenerator()->enrol_user($silent->id, $course->id);

        $tracker = new tracker();
        $tracker->open_session((int) $active->id, null, null, (int) $course->id, 1700000000);
        $tracker->close_session((int) $active->id, tracker::CLOSURE_LOGOUT, 1700003600);

        $links = new funder_link_repository();
        $linkid = $links->upsert((int) $course->id, funder_link_repository::FUNDER_CPF, [
            'dossier_number' => 'CPF-42',
        ]);

        $payload = (new bordereau_builder($links))->build($linkid, 1700010000);

        $this->assertCount(2, $payload->learners);
        $this->assertEquals(2, $payload->learnercount);
        // Sorted lastname, firstname, userid -> Active before Silent.
        $this->assertEquals('Active', $payload->learners[0]['lastname']);
        $this->assertEquals(3600, $payload->learners[0]['duration']);
        $this->assertEquals('Silent', $payload->learners[1]['lastname']);
        $this->assertEquals(0, $payload->learners[1]['duration']);
        $this->assertEquals(3600, $payload->totalseconds);
    }

    /**
     * Sessions outside the funder period contribute zero; sessions straddling
     * the period boundary contribute only the overlapping slice.
     */
    public function test_build_clips_session_to_funder_period(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $tracker = new tracker();
        // Wholly before the period (ignored).
        $tracker->open_session((int) $user->id, null, null, (int) $course->id, 1699000000);
        $tracker->close_session((int) $user->id, tracker::CLOSURE_LOGOUT, 1699003600);
        // Straddles the start: 500 seconds before + 500 seconds inside -> 500 counted.
        $tracker->open_session((int) $user->id, null, null, (int) $course->id, 1699999500);
        $tracker->close_session((int) $user->id, tracker::CLOSURE_LOGOUT, 1700000500);
        // Wholly inside (3600 counted).
        $tracker->open_session((int) $user->id, null, null, (int) $course->id, 1700100000);
        $tracker->close_session((int) $user->id, tracker::CLOSURE_LOGOUT, 1700103600);
        // Straddles the end: 500 inside + 500 after -> 500 counted.
        $tracker->open_session((int) $user->id, null, null, (int) $course->id, 1700999500);
        $tracker->close_session((int) $user->id, tracker::CLOSURE_LOGOUT, 1701000500);

        $links = new funder_link_repository();
        $linkid = $links->upsert((int) $course->id, funder_link_repository::FUNDER_OPCO, [
            'start_date' => 1700000000,
            'end_date'   => 1701000000,
        ]);

        $payload = (new bordereau_builder($links))->build($linkid, 1701000001);

        $this->assertCount(1, $payload->learners);
        $this->assertEquals(500 + 3600 + 500, $payload->learners[0]['duration']);
        $this->assertEquals(3, $payload->learners[0]['sessions']);
        $this->assertEquals(1700000000, $payload->learners[0]['first_session']);
        $this->assertEquals(1701000000, $payload->learners[0]['last_session']);
        $this->assertEquals(4600, $payload->totalseconds);
    }

    /**
     * With no period set, every closed session contributes its full duration.
     */
    public function test_build_without_period_sums_every_closed_session(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $tracker = new tracker();
        $tracker->open_session((int) $user->id, null, null, (int) $course->id, 1700000000);
        $tracker->close_session((int) $user->id, tracker::CLOSURE_LOGOUT, 1700001000);
        $tracker->open_session((int) $user->id, null, null, (int) $course->id, 1700100000);
        $tracker->close_session((int) $user->id, tracker::CLOSURE_LOGOUT, 1700100500);

        $links = new funder_link_repository();
        $linkid = $links->upsert((int) $course->id, funder_link_repository::FUNDER_AUTRE);

        $payload = (new bordereau_builder($links))->build($linkid, 1700200000);

        $this->assertEquals(1500, $payload->totalseconds);
        $this->assertEquals(2, $payload->learners[0]['sessions']);
    }

    /**
     * An unknown funder link id throws a moodle_exception.
     */
    public function test_build_throws_on_missing_link(): void {
        $this->resetAfterTest();
        $this->expectException(\moodle_exception::class);
        (new bordereau_builder())->build(424242);
    }

    /**
     * Open sessions (session_end IS NULL) do not contribute.
     */
    public function test_build_ignores_open_sessions(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $tracker = new tracker();
        $tracker->open_session((int) $user->id, null, null, (int) $course->id, 1700000000);
        // Intentionally not closed.

        $links = new funder_link_repository();
        $linkid = $links->upsert((int) $course->id, funder_link_repository::FUNDER_FT);

        $payload = (new bordereau_builder($links))->build($linkid, 1700001000);

        $this->assertEquals(0, $payload->totalseconds);
        $this->assertEquals(0, $payload->learners[0]['duration']);
    }
}
