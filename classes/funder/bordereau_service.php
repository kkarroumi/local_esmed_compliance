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
 * Bordereau orchestrator.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_esmed_compliance\funder;

use local_esmed_compliance\archive\archive_repository;
use local_esmed_compliance\archive\local_storage_adapter;
use local_esmed_compliance\archive\storage_adapter;
use stdClass;

/**
 * Build, render, seal and index a bordereau financeur as a PDF+CSV pair.
 *
 * The two renditions share the same payload snapshot and are tied by a
 * common `bordereau_group` identifier stored in metadata_json so a later
 * audit can pull both formats of the same reconciliation event.
 *
 * Each rendition has its own verification token (the token identifies
 * one sealed blob) but the pair is always produced atomically: either
 * both rows land, or the second insert bubbles up and the caller
 * decides whether to rollback.
 */
class bordereau_service {
    /** @var bordereau_builder */
    private bordereau_builder $builder;

    /** @var bordereau_renderer */
    private bordereau_renderer $pdfrenderer;

    /** @var bordereau_renderer */
    private bordereau_renderer $csvrenderer;

    /** @var storage_adapter */
    private storage_adapter $storage;

    /** @var archive_repository */
    private archive_repository $archive;

    /**
     * Constructor.
     *
     * @param bordereau_builder|null    $builder
     * @param bordereau_renderer|null   $pdfrenderer
     * @param bordereau_renderer|null   $csvrenderer
     * @param storage_adapter|null      $storage
     * @param archive_repository|null   $archive
     */
    public function __construct(
        ?bordereau_builder $builder = null,
        ?bordereau_renderer $pdfrenderer = null,
        ?bordereau_renderer $csvrenderer = null,
        ?storage_adapter $storage = null,
        ?archive_repository $archive = null
    ) {
        $this->builder     = $builder ?? new bordereau_builder();
        $this->pdfrenderer = $pdfrenderer ?? new tcpdf_bordereau_renderer();
        $this->csvrenderer = $csvrenderer ?? new csv_bordereau_renderer();
        $this->storage     = $storage ?? new local_storage_adapter();
        $this->archive     = $archive ?? new archive_repository();
    }

    /**
     * Full pipeline: build -> group id -> (render+seal) x 2 formats.
     *
     * Returns both inserted archive-index records keyed by extension.
     *
     * @param int      $funderlinkid
     * @param int|null $now Override for tests.
     * @return array{pdf: stdClass, csv: stdClass, group_id: string}
     */
    public function generate(int $funderlinkid, ?int $now = null): array {
        $now = $now ?? time();

        $payload = $this->builder->build($funderlinkid, $now);
        $groupid = bin2hex(random_bytes(8));

        $pdfrow = $this->seal_one($payload, $funderlinkid, $groupid, $this->pdfrenderer, $now);
        $csvrow = $this->seal_one($payload, $funderlinkid, $groupid, $this->csvrenderer, $now);

        return [
            'pdf'      => $pdfrow,
            'csv'      => $csvrow,
            'group_id' => $groupid,
        ];
    }

    /**
     * Seal one rendition (PDF or CSV) of the payload and index it.
     *
     * @param bordereau_payload  $payload
     * @param int                $funderlinkid
     * @param string             $groupid
     * @param bordereau_renderer $renderer
     * @param int                $now
     * @return stdClass Inserted archive index row.
     */
    private function seal_one(
        bordereau_payload $payload,
        int $funderlinkid,
        string $groupid,
        bordereau_renderer $renderer,
        int $now
    ): stdClass {
        $token = $this->archive->generate_unique_token();
        $verificationurl = self::verification_url($token);

        $bytes = $renderer->render($payload, $token, $verificationurl);
        $hash = hash('sha256', $bytes);

        $relativename = self::relative_filename(
            $funderlinkid,
            (int) $payload->course['id'],
            $token,
            $renderer->extension(),
            $now
        );
        $storedpath = $this->storage->store($bytes, $relativename);

        $metadata = $payload->to_array();
        $metadata['bordereau_group'] = $groupid;
        $metadata['format']          = $renderer->extension();
        $metadata['mime_type']       = $renderer->mime_type();

        $record = new stdClass();
        $record->userid             = null;
        $record->courseid           = (int) $payload->course['id'];
        $record->funderid           = $funderlinkid;
        $record->archive_type       = archive_repository::TYPE_BORDEREAU_FINANCEUR;
        $record->file_path          = $storedpath;
        $record->storage_adapter    = $this->storage->name();
        $record->sha256_hash        = $hash;
        $record->verification_token = $token;
        $record->timestamp_sealed   = $now;
        $record->retention_until    = self::retention_until($now);
        $record->metadata_json      = json_encode($metadata, JSON_UNESCAPED_UNICODE);

        $record->id = $this->archive->insert($record);
        return $record;
    }

    /**
     * Build a deterministic relative filename for a sealed bordereau.
     *
     * @param int    $funderlinkid
     * @param int    $courseid
     * @param string $token
     * @param string $extension
     * @param int    $now
     * @return string
     */
    public static function relative_filename(
        int $funderlinkid,
        int $courseid,
        string $token,
        string $extension,
        int $now
    ): string {
        $yyyy = date('Y', $now);
        $mm = date('m', $now);
        return sprintf(
            'bordereau/%s/%s/f%d_c%d_%s.%s',
            $yyyy,
            $mm,
            $funderlinkid,
            $courseid,
            substr($token, 0, 16),
            $extension
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
     * Bordereaux inherit the same retention window as attestations: the
     * default is five years per Qualiopi's record-keeping guidance but
     * the admin can extend it in plugin settings.
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
