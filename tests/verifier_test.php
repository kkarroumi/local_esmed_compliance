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
 * Archive verifier tests.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_esmed_compliance;

use local_esmed_compliance\archive\archive_repository;
use local_esmed_compliance\archive\verifier;
use local_esmed_compliance\attestation\attestation_builder;
use local_esmed_compliance\attestation\attestation_service;
use local_esmed_compliance\tests\fixtures\fake_attestation_renderer;
use local_esmed_compliance\tests\fixtures\fake_storage_adapter;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixtures/fake_attestation_renderer.php');
require_once(__DIR__ . '/fixtures/fake_storage_adapter.php');

/**
 * @covers \local_esmed_compliance\archive\verifier
 */
final class verifier_test extends \advanced_testcase {

    /**
     * A token that has never been sealed returns STATUS_UNKNOWN.
     */
    public function test_unknown_token(): void {
        $this->resetAfterTest();
        $result = (new verifier())->verify('doesnotexist');
        $this->assertEquals(verifier::STATUS_UNKNOWN, $result['status']);
        $this->assertNull($result['record']);
    }

    /**
     * A freshly sealed attestation is reported VALID by the verifier.
     */
    public function test_valid_seal(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();

        $storage = new fake_storage_adapter();
        $service = new attestation_service(
            new attestation_builder(),
            new fake_attestation_renderer(),
            $storage,
            new archive_repository()
        );
        $record = $service->generate((int) $user->id, (int) $course->id, 1700000000);

        $result = (new verifier(null, ['local' => $storage]))->verify((string) $record->verification_token);
        $this->assertEquals(verifier::STATUS_VALID, $result['status']);
        $this->assertEquals($record->sha256_hash, $result['sealed_hash']);
        $this->assertEquals($record->sha256_hash, $result['computed_hash']);
    }

    /**
     * Mutating the stored bytes is detected as tampering.
     */
    public function test_tampered_bytes_are_detected(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();

        $storage = new fake_storage_adapter();
        $service = new attestation_service(
            new attestation_builder(),
            new fake_attestation_renderer(),
            $storage,
            new archive_repository()
        );
        $record = $service->generate((int) $user->id, (int) $course->id, 1700000000);

        $storage->tamper((string) $record->file_path, 'TAMPERED BYTES');

        $result = (new verifier(null, ['local' => $storage]))->verify((string) $record->verification_token);
        $this->assertEquals(verifier::STATUS_TAMPERED, $result['status']);
        $this->assertEquals($record->sha256_hash, $result['sealed_hash']);
        $this->assertNotEquals($record->sha256_hash, $result['computed_hash']);
    }

    /**
     * Removing the stored bytes yields STATUS_MISSING.
     */
    public function test_missing_file_reports_missing(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();

        $storage = new fake_storage_adapter();
        $service = new attestation_service(
            new attestation_builder(),
            new fake_attestation_renderer(),
            $storage,
            new archive_repository()
        );
        $record = $service->generate((int) $user->id, (int) $course->id, 1700000000);

        $storage->remove((string) $record->file_path);

        $result = (new verifier(null, ['local' => $storage]))->verify((string) $record->verification_token);
        $this->assertEquals(verifier::STATUS_MISSING, $result['status']);
        $this->assertNull($result['computed_hash']);
    }

    /**
     * An unknown storage adapter name is reported as STATUS_MISSING.
     */
    public function test_unknown_adapter_is_missing(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();

        $storage = new fake_storage_adapter();
        $service = new attestation_service(
            new attestation_builder(),
            new fake_attestation_renderer(),
            $storage,
            new archive_repository()
        );
        $record = $service->generate((int) $user->id, (int) $course->id, 1700000000);

        // Verifier knows only about "s3" — not the adapter the record was sealed with.
        $result = (new verifier(null, ['s3' => $storage]))->verify((string) $record->verification_token);
        $this->assertEquals(verifier::STATUS_MISSING, $result['status']);
    }
}
