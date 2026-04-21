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
 * Attestation service tests.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_esmed_compliance;

use local_esmed_compliance\archive\archive_repository;
use local_esmed_compliance\attestation\attestation_builder;
use local_esmed_compliance\attestation\attestation_service;
use local_esmed_compliance\session\tracker;
use local_esmed_compliance\tests\fixtures\fake_attestation_renderer;
use local_esmed_compliance\tests\fixtures\fake_storage_adapter;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixtures/fake_attestation_renderer.php');
require_once(__DIR__ . '/fixtures/fake_storage_adapter.php');

/**
 * @covers \local_esmed_compliance\attestation\attestation_service
 * @covers \local_esmed_compliance\attestation\attestation_builder
 * @covers \local_esmed_compliance\attestation\attestation_payload
 */
final class attestation_service_test extends \advanced_testcase {

    /**
     * Generate a sealed attestation end-to-end and assert the hash is bound to the stored bytes.
     */
    public function test_generate_seals_and_hashes_consistently(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();

        // Close one session to feed the builder with non-empty evidence.
        $tracker = new tracker();
        $tracker->open_session((int) $user->id, null, null, (int) $course->id, 1700000000);
        $tracker->close_session((int) $user->id, tracker::CLOSURE_LOGOUT, 1700000600);

        $renderer = new fake_attestation_renderer();
        $storage = new fake_storage_adapter();

        $service = new attestation_service(
            new attestation_builder(),
            $renderer,
            $storage,
            new archive_repository()
        );

        $record = $service->generate((int) $user->id, (int) $course->id, 1700001000);

        $this->assertEquals(archive_repository::TYPE_ATTESTATION_ASSIDUITE, $record->archive_type);
        $this->assertEquals('local', $record->storage_adapter);
        $this->assertEquals(1700001000, (int) $record->timestamp_sealed);
        $this->assertSame(64, strlen((string) $record->verification_token));
        $this->assertSame(64, strlen((string) $record->sha256_hash));

        // The stored bytes exactly match the hash in the index row.
        $bytes = $storage->fetch((string) $record->file_path);
        $this->assertNotNull($bytes);
        $this->assertSame(hash('sha256', $bytes), (string) $record->sha256_hash);

        // The renderer received the same token that was persisted.
        $this->assertSame((string) $record->verification_token, $renderer->lasttoken);
        $this->assertStringContainsString(
            'verify.php?t=' . $record->verification_token,
            (string) $renderer->lasturl
        );

        // The archive row is persisted and findable by token.
        $found = (new archive_repository())->find_by_token((string) $record->verification_token);
        $this->assertNotNull($found);
        $this->assertEquals($record->id, $found->id);
    }

    /**
     * Closed session durations contribute to the attestation total.
     */
    public function test_generate_captures_closed_session_duration(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();

        $tracker = new tracker();
        $tracker->open_session((int) $user->id, null, null, (int) $course->id, 1700000000);
        $tracker->close_session((int) $user->id, tracker::CLOSURE_LOGOUT, 1700001800);

        $storage = new fake_storage_adapter();
        $service = new attestation_service(
            new attestation_builder(),
            new fake_attestation_renderer(),
            $storage,
            new archive_repository()
        );
        $record = $service->generate((int) $user->id, (int) $course->id, 1700002000);

        $metadata = json_decode((string) $record->metadata_json, true);
        $this->assertEquals(1800, $metadata['totalseconds']);
        $this->assertCount(1, $metadata['sessions']);
        $this->assertEquals(1700000000, $metadata['sessions'][0]['start']);
        $this->assertEquals(1700001800, $metadata['sessions'][0]['end']);
    }

    /**
     * Retention defaults to five years when the setting is unset.
     */
    public function test_generate_defaults_retention_to_five_years(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();

        set_config('retention_years', 0, 'local_esmed_compliance');

        $service = new attestation_service(
            new attestation_builder(),
            new fake_attestation_renderer(),
            new fake_storage_adapter(),
            new archive_repository()
        );
        $now = 1700000000;
        $record = $service->generate((int) $user->id, (int) $course->id, $now);

        $expected = strtotime('+5 years', $now);
        $this->assertEquals($expected, (int) $record->retention_until);
    }

    /**
     * The relative filename embeds year, month, user, course and a token prefix.
     */
    public function test_relative_filename_layout(): void {
        $name = attestation_service::relative_filename(
            42,
            7,
            str_repeat('c', 64),
            strtotime('2026-04-20 12:00:00 UTC')
        );
        $this->assertStringStartsWith('attestation/2026/04/', $name);
        $this->assertStringContainsString('u42_c7_cccccccccccccccc', $name);
        $this->assertStringEndsWith('.pdf', $name);
    }
}
