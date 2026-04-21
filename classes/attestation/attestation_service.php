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
 * Attestation orchestrator.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_esmed_compliance\attestation;

use local_esmed_compliance\archive\adapter_registry;
use local_esmed_compliance\archive\archive_repository;
use local_esmed_compliance\archive\local_storage_adapter;
use local_esmed_compliance\archive\storage_adapter;
use stdClass;

/**
 * Build, render, seal and index an attestation d'assiduité end-to-end.
 *
 * Sealing bundles three atomic guarantees:
 *   1. the bytes written to durable storage are exactly the bytes hashed;
 *   2. the verification token is globally unique so a third party can
 *      look up the record without any knowledge of the learner;
 *   3. the retention window is committed at seal time and never
 *      shortened (a later admin-configured retention change only affects
 *      future documents).
 */
class attestation_service {
    /** @var attestation_builder */
    private attestation_builder $builder;

    /** @var attestation_renderer */
    private attestation_renderer $renderer;

    /** @var storage_adapter */
    private storage_adapter $storage;

    /** @var archive_repository */
    private archive_repository $archive;

    /**
     * Constructor.
     *
     * @param attestation_builder|null  $builder
     * @param attestation_renderer|null $renderer  Pass a fake in tests.
     * @param storage_adapter|null      $storage
     * @param archive_repository|null   $archive
     */
    public function __construct(
        ?attestation_builder $builder = null,
        ?attestation_renderer $renderer = null,
        ?storage_adapter $storage = null,
        ?archive_repository $archive = null
    ) {
        $this->builder  = $builder ?? new attestation_builder();
        $this->renderer = $renderer ?? new tcpdf_attestation_renderer();
        $this->storage  = $storage ?? (adapter_registry::active() ?? new local_storage_adapter());
        $this->archive  = $archive ?? new archive_repository();
    }

    /**
     * Full pipeline: build -> token -> render -> hash -> store -> index.
     *
     * Returns the inserted archive-index record.
     *
     * @param int      $userid
     * @param int      $courseid
     * @param int|null $now Override for tests.
     * @return stdClass
     */
    public function generate(int $userid, int $courseid, ?int $now = null): stdClass {
        $now = $now ?? time();

        $payload = $this->builder->build($userid, $courseid, $now);

        $token = $this->archive->generate_unique_token();
        $verificationurl = self::verification_url($token);

        $bytes = $this->renderer->render($payload, $token, $verificationurl);
        $hash = hash('sha256', $bytes);

        $relativename = self::relative_filename($userid, $courseid, $token, $now);
        $storedpath = $this->storage->store($bytes, $relativename);

        $record = new stdClass();
        $record->userid             = $userid;
        $record->courseid           = $courseid;
        $record->funderid           = null;
        $record->archive_type       = archive_repository::TYPE_ATTESTATION_ASSIDUITE;
        $record->file_path          = $storedpath;
        $record->storage_adapter    = $this->storage->name();
        $record->sha256_hash        = $hash;
        $record->verification_token = $token;
        $record->timestamp_sealed   = $now;
        $record->retention_until    = self::retention_until($now);
        $record->metadata_json      = json_encode($payload->to_array(), JSON_UNESCAPED_UNICODE);

        $record->id = $this->archive->insert($record);
        return $record;
    }

    /**
     * Build a deterministic relative filename for a sealed attestation.
     *
     * @param int    $userid
     * @param int    $courseid
     * @param string $token
     * @param int    $now
     * @return string
     */
    public static function relative_filename(int $userid, int $courseid, string $token, int $now): string {
        $yyyy = date('Y', $now);
        $mm = date('m', $now);
        return sprintf(
            'attestation/%s/%s/u%d_c%d_%s.pdf',
            $yyyy,
            $mm,
            $userid,
            $courseid,
            substr($token, 0, 16)
        );
    }

    /**
     * Absolute public verification URL for a token.
     *
     * @param string $token
     * @return string
     */
    public static function verification_url(string $token): string {
        global $CFG;
        return $CFG->wwwroot . '/local/esmed_compliance/verify.php?t=' . $token;
    }

    /**
     * Compute the seal retention timestamp using the configured policy.
     *
     * @param int $now
     * @return int
     */
    private static function retention_until(int $now): int {
        $years = (int) get_config('local_esmed_compliance', 'retention_years');
        if ($years <= 0) {
            $years = 5;
        }
        return strtotime('+' . $years . ' years', $now) ?: ($now + $years * 31536000);
    }
}
