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
 * Tests for the verify_token external function.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_esmed_compliance\external;

use core_external\external_api;
use local_esmed_compliance\archive\archive_repository;
use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \local_esmed_compliance\external\verify_token
 */
final class verify_token_test extends \advanced_testcase {

    /**
     * Unknown tokens resolve to status=unknown without leaking any record fields.
     */
    public function test_execute_returns_unknown_for_missing_token(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());

        $result = verify_token::execute('deadbeef');
        $result = external_api::clean_returnvalue(verify_token::execute_returns(), $result);

        $this->assertEquals('unknown', $result['status']);
        $this->assertArrayHasKey('archive_type', $result);
        $this->assertNull($result['archive_type']);
        $this->assertNull($result['sealed_hash']);
    }

    /**
     * A record whose storage adapter is not configured is reported as missing,
     * not valid, and the sealed hash is still returned.
     */
    public function test_execute_reports_missing_when_storage_adapter_absent(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());
        $repo = new archive_repository();
        $token = $repo->generate_unique_token();

        $record = new stdClass();
        $record->userid             = null;
        $record->courseid           = null;
        $record->funderid           = null;
        $record->archive_type       = archive_repository::TYPE_ATTESTATION_ASSIDUITE;
        $record->file_path          = 'missing/path.pdf';
        $record->storage_adapter    = 's3'; // Not configured in the default adapter list.
        $record->sha256_hash        = str_repeat('a', 64);
        $record->verification_token = $token;
        $record->timestamp_sealed   = 1700000000;
        $record->retention_until    = 1700000000 + 5 * 31536000;
        $record->metadata_json      = null;
        $repo->insert($record);

        $result = verify_token::execute($token);
        $result = external_api::clean_returnvalue(verify_token::execute_returns(), $result);

        $this->assertEquals('missing', $result['status']);
        $this->assertEquals(archive_repository::TYPE_ATTESTATION_ASSIDUITE, $result['archive_type']);
        $this->assertEquals(1700000000, $result['timestamp_sealed']);
        $this->assertEquals(str_repeat('a', 64), $result['sealed_hash']);
        $this->assertNull($result['computed_hash']);
    }

    /**
     * Parameter validation strips non-alphanumextrange characters.
     */
    public function test_execute_rejects_invalid_token(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());
        $this->expectException(\invalid_parameter_exception::class);
        verify_token::execute("bad token with spaces");
    }
}
