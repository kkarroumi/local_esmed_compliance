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
 * Integrity checker tests.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_esmed_compliance;

use local_esmed_compliance\archive\archive_repository;
use local_esmed_compliance\archive\integrity_checker;
use local_esmed_compliance\tests\fixtures\fake_storage_adapter;
use stdClass;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixtures/fake_storage_adapter.php');

/**
 * Tests for the  component.
 *
 * @covers \local_esmed_compliance\archive\integrity_checker
 */
final class integrity_checker_test extends \advanced_testcase {
    /**
     * A sealed archive with intact bytes logs a `valid` event.
     */
    public function test_run_marks_intact_archive_as_valid(): void {
        $this->resetAfterTest();

        $storage = new fake_storage_adapter();
        $bytes = 'hello world';
        $storage->store($bytes, 'doc/a.pdf');
        $this->insert_archive((int) 0, 'doc/a.pdf', hash('sha256', $bytes));

        $checker = new integrity_checker(new archive_repository(), ['local' => $storage]);
        $tally = $checker->run(10, 1700000000);

        $this->assertEquals(['checked' => 1, 'valid' => 1, 'tampered' => 0, 'missing' => 0], $tally);
        $this->assertEquals(1, $checker->count_archives_in_status(integrity_checker::STATUS_VALID));
    }

    /**
     * Bytes mutated after sealing are caught as `tampered`.
     */
    public function test_run_detects_tampering(): void {
        $this->resetAfterTest();
        $storage = new fake_storage_adapter();
        $bytes = 'original';
        $storage->store($bytes, 'doc/t.pdf');
        $sealedhash = hash('sha256', $bytes);
        $this->insert_archive((int) 0, 'doc/t.pdf', $sealedhash);

        $storage->tamper('doc/t.pdf', 'corrupted');

        $checker = new integrity_checker(new archive_repository(), ['local' => $storage]);
        $tally = $checker->run(10, 1700000000);

        $this->assertEquals(1, $tally['tampered']);
        $this->assertEquals(1, $checker->count_archives_in_status(integrity_checker::STATUS_TAMPERED));
    }

    /**
     * A storage fetch returning null is reported as `missing`.
     */
    public function test_run_detects_missing_file(): void {
        $this->resetAfterTest();
        $storage = new fake_storage_adapter();
        $storage->store('payload', 'doc/m.pdf');
        $this->insert_archive((int) 0, 'doc/m.pdf', hash('sha256', 'payload'));

        $storage->remove('doc/m.pdf');

        $checker = new integrity_checker(new archive_repository(), ['local' => $storage]);
        $tally = $checker->run(10, 1700000000);

        $this->assertEquals(1, $tally['missing']);
        $this->assertEquals(1, $checker->count_archives_in_status(integrity_checker::STATUS_MISSING));
    }

    /**
     * Unknown adapters (e.g. s3 not configured) surface as `missing` rather than crashing.
     */
    public function test_unknown_adapter_marked_missing(): void {
        $this->resetAfterTest();
        $record = $this->make_record('foreign/x.pdf', str_repeat('a', 64));
        $record->storage_adapter = 's3';
        (new archive_repository())->insert($record);

        $checker = new integrity_checker(new archive_repository(), ['local' => new fake_storage_adapter()]);
        $tally = $checker->run(10, 1700000000);
        $this->assertEquals(1, $tally['missing']);
    }

    /**
     * The batch scheduler picks never-checked rows before ones already verified.
     */
    public function test_run_prioritises_never_checked_rows(): void {
        global $DB;
        $this->resetAfterTest();

        $storage = new fake_storage_adapter();

        // Archive A: check it immediately (pre-seeded integrity event).
        $storage->store('a-bytes', 'a.pdf');
        $aid = $this->insert_archive((int) 0, 'a.pdf', hash('sha256', 'a-bytes'));
        $DB->insert_record(integrity_checker::EVENT_TABLE, (object) [
            'archive_id' => $aid,
            'checked_at' => 1699000000,
            'status'     => integrity_checker::STATUS_VALID,
        ]);

        // Archive B: never checked.
        $storage->store('b-bytes', 'b.pdf');
        $bid = $this->insert_archive((int) 0, 'b.pdf', hash('sha256', 'b-bytes'));

        $checker = new integrity_checker(new archive_repository(), ['local' => $storage]);
        $checker->run(1, 1700000000);

        // After a batch of 1, B (never checked) should have an event and A should not get a second one.
        $this->assertEquals(1, $DB->count_records(integrity_checker::EVENT_TABLE, ['archive_id' => $bid]));
        $this->assertEquals(1, $DB->count_records(integrity_checker::EVENT_TABLE, ['archive_id' => $aid]));
    }

    /**
     * Helper: insert an archive row with the given file path and sealed hash.
     *
     * @param int $userid
     * @param string $filepath
     * @param string $hash
     * @return int
     */
    private function insert_archive(int $userid, string $filepath, string $hash): int {
        $record = $this->make_record($filepath, $hash);
        $record->userid = $userid ?: null;
        return (new archive_repository())->insert($record);
    }

    /**
     * Build an archive index row fixture for use in the tests.
     *
     * @param string $filepath
     * @param string $hash
     * @return stdClass
     */
    private function make_record(string $filepath, string $hash): stdClass {
        $r = new stdClass();
        $r->userid             = null;
        $r->courseid           = null;
        $r->funderid           = null;
        $r->archive_type       = archive_repository::TYPE_ATTESTATION_ASSIDUITE;
        $r->file_path          = $filepath;
        $r->storage_adapter    = 'local';
        $r->sha256_hash        = $hash;
        $r->verification_token = bin2hex(random_bytes(32));
        $r->timestamp_sealed   = 1700000000;
        $r->retention_until    = 1700000000 + 5 * 31536000;
        $r->metadata_json      = null;
        return $r;
    }
}
