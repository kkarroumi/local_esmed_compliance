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
 * Dashboard metrics provider tests.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_esmed_compliance;

use local_esmed_compliance\archive\archive_repository;
use local_esmed_compliance\dashboard\metrics_provider;
use local_esmed_compliance\session\tracker;
use stdClass;

/**
 * Tests for the  component.
 *
 * @covers \local_esmed_compliance\dashboard\metrics_provider
 */
final class metrics_provider_test extends \advanced_testcase {
    /**
     * A fresh install returns zero counters everywhere.
     */
    public function test_collect_on_empty_install_returns_zeroes(): void {
        $this->resetAfterTest();
        $metrics = (new metrics_provider())->collect(1700000000);

        $this->assertEquals(0, $metrics['sessions']['open']);
        $this->assertEquals(0, $metrics['sessions']['closed_last_24h']);
        $this->assertEquals(0, $metrics['sessions']['seconds_today']);
        $this->assertEquals(0, $metrics['archives']['total']);
        $this->assertEquals(0, $metrics['alerts']['unacknowledged']);
        $this->assertEquals(0, $metrics['integrity']['tampered']);
    }

    /**
     * Open sessions and recently-closed ones are counted separately.
     */
    public function test_session_counters(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $tracker = new tracker();

        $now = 1700000000;
        // One open session.
        $tracker->open_session((int) $user->id, null, null, (int) $course->id, $now);

        // One closed session within the last 24 hours.
        $other = $this->getDataGenerator()->create_user();
        $tracker->open_session((int) $other->id, null, null, (int) $course->id, $now - 3600);
        $tracker->close_session((int) $other->id, tracker::CLOSURE_LOGOUT, $now - 1800);

        $metrics = (new metrics_provider())->collect($now);
        $this->assertEquals(1, $metrics['sessions']['open']);
        $this->assertEquals(1, $metrics['sessions']['closed_last_24h']);
        $this->assertEquals(1800, $metrics['sessions']['seconds_today']);
    }

    /**
     * Archive totals split by archive type.
     */
    public function test_archive_counters_split_by_type(): void {
        $this->resetAfterTest();
        $repo = new archive_repository();
        $repo->insert($this->make_archive(archive_repository::TYPE_ATTESTATION_ASSIDUITE));
        $repo->insert($this->make_archive(archive_repository::TYPE_ATTESTATION_ASSIDUITE));
        $repo->insert($this->make_archive(archive_repository::TYPE_BORDEREAU_FINANCEUR));

        $metrics = (new metrics_provider())->collect(1700000000);
        $this->assertEquals(3, $metrics['archives']['total']);
        $this->assertEquals(2, $metrics['archives']['attestations']);
        $this->assertEquals(1, $metrics['archives']['bordereaux']);
    }

    /**
     * Alert counters filter unacknowledged and last-7-days separately.
     */
    public function test_alert_counters(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        // Unacknowledged, triggered yesterday.
        $DB->insert_record('local_esmed_compliance_alerts', (object) [
            'userid'          => $user->id,
            'alert_type'      => 'inactivity_7d',
            'triggered_at'    => time() - 86400,
            'acknowledged_at' => null,
        ]);
        // Acknowledged but still within the 7-day window.
        $DB->insert_record('local_esmed_compliance_alerts', (object) [
            'userid'          => $user->id,
            'alert_type'      => 'inactivity_7d',
            'triggered_at'    => time() - 2 * 86400,
            'acknowledged_at' => time() - 86400,
        ]);

        $metrics = (new metrics_provider())->collect();
        $this->assertEquals(1, $metrics['alerts']['unacknowledged']);
        $this->assertEquals(2, $metrics['alerts']['last_7_days']);
    }

    /**
     * Build an archive index row fixture for use in the tests.
     *
     * @param string $type
     * @return stdClass
     */
    private function make_archive(string $type): stdClass {
        $r = new stdClass();
        $r->userid             = null;
        $r->courseid           = null;
        $r->funderid           = null;
        $r->archive_type       = $type;
        $r->file_path          = 'x/' . bin2hex(random_bytes(4)) . '.pdf';
        $r->storage_adapter    = 'local';
        $r->sha256_hash        = str_repeat('a', 64);
        $r->verification_token = bin2hex(random_bytes(32));
        $r->timestamp_sealed   = 1700000000;
        $r->retention_until    = 1700000000 + 5 * 31536000;
        $r->metadata_json      = null;
        return $r;
    }
}
