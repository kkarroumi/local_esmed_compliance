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
 * Attestation listing service tests.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_esmed_compliance;

use local_esmed_compliance\archive\archive_repository;
use local_esmed_compliance\attestation\attestation_listing;
use stdClass;

/**
 * Tests for the  component.
 *
 * @covers \local_esmed_compliance\attestation\attestation_listing
 */
final class attestation_listing_test extends \advanced_testcase {
    /**
     * Lists only actively enrolled users of the target course, each carrying
     * their total closed-session seconds plus the tally of existing
     * attestations sealed for (user, course).
     */
    public function test_list_for_course_aggregates_sessions_and_archives(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $other   = $this->getDataGenerator()->create_user(); // Not enrolled — must not appear.

        // Closed session worth 90 minutes.
        $DB->insert_record('local_esmed_sessions', (object) [
            'userid'           => (int) $student->id,
            'courseid'         => (int) $course->id,
            'session_start'    => 1700000000,
            'session_end'      => 1700005400,
            'duration_seconds' => 5400,
            'closure_type'     => 'logout',
            'ip_address'       => null,
            'user_agent'       => null,
        ]);
        // Open session — must be ignored by the aggregate.
        $DB->insert_record('local_esmed_sessions', (object) [
            'userid'           => (int) $student->id,
            'courseid'         => (int) $course->id,
            'session_start'    => 1700100000,
            'session_end'      => null,
            'duration_seconds' => null,
            'closure_type'     => null,
            'ip_address'       => null,
            'user_agent'       => null,
        ]);
        // Closed session attributed to a completely different user —
        // does not leak into the student's total.
        $DB->insert_record('local_esmed_sessions', (object) [
            'userid'           => (int) $other->id,
            'courseid'         => (int) $course->id,
            'session_start'    => 1700000000,
            'session_end'      => 1700010000,
            'duration_seconds' => 10000,
            'closure_type'     => 'logout',
            'ip_address'       => null,
            'user_agent'       => null,
        ]);

        // Two sealed attestations for the (student, course) — the later one wins.
        $repo = new archive_repository();
        $first = $this->make_archive((int) $student->id, (int) $course->id, 1700000100);
        $firstid = $repo->insert($first);
        $second = $this->make_archive((int) $student->id, (int) $course->id, 1700000200);
        $secondid = $repo->insert($second);

        // An attestation for another course — must not contribute to the count.
        $othercourse = $this->getDataGenerator()->create_course();
        $foreign = $this->make_archive((int) $student->id, (int) $othercourse->id, 1700000300);
        $repo->insert($foreign);

        $rows = (new attestation_listing())->list_for_course((int) $course->id);

        $this->assertCount(1, $rows, 'Only enrolled users should show up');
        $row = $rows[0];
        $this->assertEquals((int) $student->id, $row['userid']);
        $this->assertSame(5400, $row['total_seconds']);
        $this->assertSame(2, $row['attestation_count']);
        $this->assertSame(1700000200, $row['last_sealed_at']);
        $this->assertSame($secondid, $row['last_archive_id'], 'Points at the newest sealed row');
    }

    /**
     * Enrolled learner with no sessions and no archives still appears with zeroed counters.
     */
    public function test_list_for_course_reports_zero_for_fresh_learner(): void {
        $this->resetAfterTest();

        $course  = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $rows = (new attestation_listing())->list_for_course((int) $course->id);

        $this->assertCount(1, $rows);
        $row = $rows[0];
        $this->assertEquals((int) $student->id, $row['userid']);
        $this->assertSame(0, $row['total_seconds']);
        $this->assertSame(0, $row['attestation_count']);
        $this->assertNull($row['last_sealed_at']);
        $this->assertNull($row['last_archive_id']);
    }

    /**
     * Helper: build a valid archive row for a sealed attestation.
     */
    private function make_archive(int $userid, int $courseid, int $sealedat): stdClass {
        $r = new stdClass();
        $r->userid             = $userid;
        $r->courseid           = $courseid;
        $r->funderid           = null;
        $r->archive_type       = archive_repository::TYPE_ATTESTATION_ASSIDUITE;
        $r->file_path          = 'attestation/' . $userid . '/' . $courseid . '/' . $sealedat . '.pdf';
        $r->storage_adapter    = 'local';
        $r->sha256_hash        = hash('sha256', 'bytes-' . $sealedat);
        $r->verification_token = bin2hex(random_bytes(32));
        $r->timestamp_sealed   = $sealedat;
        $r->retention_until    = $sealedat + 5 * 31536000;
        $r->metadata_json      = null;
        return $r;
    }
}
