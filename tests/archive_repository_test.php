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
 * Archive repository tests.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_esmed_compliance;

use local_esmed_compliance\archive\archive_repository;
use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \local_esmed_compliance\archive\archive_repository
 */
final class archive_repository_test extends \advanced_testcase {

    /**
     * Insert + find_by_token round-trip preserves every sealed field.
     */
    public function test_insert_and_find_by_token(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        $repo = new archive_repository();

        $record = new stdClass();
        $record->userid             = (int) $user->id;
        $record->courseid           = 10;
        $record->funderid           = null;
        $record->archive_type       = archive_repository::TYPE_ATTESTATION_ASSIDUITE;
        $record->file_path          = 'attestation/2026/04/test.pdf';
        $record->storage_adapter    = 'local';
        $record->sha256_hash        = str_repeat('a', 64);
        $record->verification_token = str_repeat('b', 64);
        $record->timestamp_sealed   = 1700000000;
        $record->retention_until    = 1700000000 + 5 * 31536000;
        $record->metadata_json      = '{"dummy":true}';

        $id = $repo->insert($record);
        $this->assertGreaterThan(0, $id);

        $fetched = $repo->find_by_token(str_repeat('b', 64));
        $this->assertNotNull($fetched);
        $this->assertEquals($user->id, $fetched->userid);
        $this->assertEquals('local', $fetched->storage_adapter);
        $this->assertEquals(str_repeat('a', 64), $fetched->sha256_hash);
        $this->assertEquals(archive_repository::TYPE_ATTESTATION_ASSIDUITE, $fetched->archive_type);
    }

    /**
     * An unknown token yields null instead of an error.
     */
    public function test_find_by_token_returns_null_for_unknown(): void {
        $this->resetAfterTest();
        $this->assertNull((new archive_repository())->find_by_token('does-not-exist'));
    }

    /**
     * generate_unique_token returns a 64-character hex string that does not collide with existing rows.
     */
    public function test_generate_unique_token_format(): void {
        $this->resetAfterTest();
        $token = (new archive_repository())->generate_unique_token();
        $this->assertSame(64, strlen($token));
        $this->assertMatchesRegularExpression('/^[0-9a-f]+$/', $token);
    }

    /**
     * find_for_user_course filters on the archive type and orders by most recent first.
     */
    public function test_find_for_user_course_filters_and_orders(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $repo = new archive_repository();

        $older = $this->make_record((int) $user->id, 10, archive_repository::TYPE_ATTESTATION_ASSIDUITE, 1700000000);
        $newer = $this->make_record((int) $user->id, 10, archive_repository::TYPE_ATTESTATION_ASSIDUITE, 1710000000);
        $other = $this->make_record((int) $user->id, 10, archive_repository::TYPE_BORDEREAU_FINANCEUR, 1720000000);

        $repo->insert($older);
        $repo->insert($newer);
        $repo->insert($other);

        $found = $repo->find_for_user_course((int) $user->id, 10, archive_repository::TYPE_ATTESTATION_ASSIDUITE);
        $this->assertCount(2, $found);
        $timestamps = array_map(static fn($r) => (int) $r->timestamp_sealed, array_values($found));
        $this->assertEquals([1710000000, 1700000000], $timestamps);
    }

    /**
     * @param int    $userid
     * @param int    $courseid
     * @param string $type
     * @param int    $sealedat
     * @return stdClass
     */
    private function make_record(int $userid, int $courseid, string $type, int $sealedat): stdClass {
        $r = new stdClass();
        $r->userid             = $userid;
        $r->courseid           = $courseid;
        $r->funderid           = null;
        $r->archive_type       = $type;
        $r->file_path          = 'attestation/' . $sealedat . '/file.pdf';
        $r->storage_adapter    = 'local';
        $r->sha256_hash        = str_repeat('f', 64);
        $r->verification_token = bin2hex(random_bytes(32));
        $r->timestamp_sealed   = $sealedat;
        $r->retention_until    = $sealedat + 5 * 31536000;
        $r->metadata_json      = null;
        return $r;
    }
}
