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
 * Archive integrity checker.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_esmed_compliance\archive;

/**
 * Periodically re-hashes stored archives and appends a verdict to
 * `{local_esmed_compliance_integrity_event}` so tampering or loss is detected
 * independently of the (on-demand) public verifier.
 *
 * The integrity log is append-only: every run emits one event per
 * checked archive, even when nothing has changed, so the history
 * itself constitutes an audit trail. Oldest-first ordering picks
 * the rows that have been checked least recently — or never at all.
 */
class integrity_checker {
    /** @var string Integrity event table. */
    public const EVENT_TABLE = 'local_esmed_compliance_integrity_event';

    /** @var string Status: bytes present and match the sealed hash. */
    public const STATUS_VALID = 'valid';
    /** @var string Status: bytes present but hash diverges from the sealed hash. */
    public const STATUS_TAMPERED = 'tampered';
    /** @var string Status: file no longer retrievable from its declared adapter. */
    public const STATUS_MISSING = 'missing';

    /** @var archive_repository */
    private archive_repository $archive;

    /** @var array */
    private array $adapters;

    /**
     * Constructor.
     *
     * @param archive_repository|null             $archive  Injectable for tests.
     * @param array|null $adapters Map of adapter name => adapter.
     */
    public function __construct(
        ?archive_repository $archive = null,
        ?array $adapters = null
    ) {
        $this->archive = $archive ?? new archive_repository();
        $this->adapters = $adapters ?? adapter_registry::from_config();
    }

    /**
     * Check up to $batchsize archives, starting with the least recently checked.
     *
     * Returns an aggregate tally so scheduled-task output can surface
     * missing / tampered counts without re-querying the log.
     *
     * @param int      $batchsize Max number of archives processed in this run.
     * @param int|null $now       Override for tests.
     * @return array{checked:int, valid:int, tampered:int, missing:int}
     */
    public function run(int $batchsize = 50, ?int $now = null): array {
        global $DB;

        $now = $now ?? time();
        $tally = ['checked' => 0, 'valid' => 0, 'tampered' => 0, 'missing' => 0];
        if ($batchsize <= 0) {
            return $tally;
        }

        $sql = "SELECT a.id, a.storage_adapter, a.file_path, a.sha256_hash,
                       COALESCE(
                           (SELECT MAX(e.checked_at) FROM {" . self::EVENT_TABLE . "} e
                             WHERE e.archive_id = a.id),
                           0
                       ) AS last_checked
                  FROM {" . archive_repository::TABLE . "} a
              ORDER BY last_checked ASC, a.id ASC";
        $candidates = $DB->get_records_sql($sql, [], 0, $batchsize);

        foreach ($candidates as $row) {
            $status = $this->check_one(
                (int) $row->id,
                (string) $row->storage_adapter,
                (string) $row->file_path,
                (string) $row->sha256_hash,
                $now
            );
            $tally['checked']++;
            $tally[$status]++;
        }
        return $tally;
    }

    /**
     * Check a single archive row and append the verdict to the integrity log.
     *
     * @param int    $archiveid
     * @param string $adaptername
     * @param string $filepath
     * @param string $sealedhash
     * @param int    $now
     * @return string One of STATUS_VALID, STATUS_TAMPERED, STATUS_MISSING.
     */
    public function check_one(int $archiveid, string $adaptername, string $filepath, string $sealedhash, int $now): string {
        $adapter = $this->adapters[$adaptername] ?? null;
        if ($adapter === null) {
            return $this->log($archiveid, self::STATUS_MISSING, null, $now);
        }
        $bytes = $adapter->fetch($filepath);
        if ($bytes === null) {
            return $this->log($archiveid, self::STATUS_MISSING, null, $now);
        }
        $observed = hash('sha256', $bytes);
        $status = hash_equals($sealedhash, $observed) ? self::STATUS_VALID : self::STATUS_TAMPERED;
        return $this->log($archiveid, $status, $observed, $now);
    }

    /**
     * Append one integrity event and return the status for chaining.
     *
     * @param int         $archiveid
     * @param string      $status
     * @param string|null $observedhash
     * @param int         $now
     * @return string
     */
    private function log(int $archiveid, string $status, ?string $observedhash, int $now): string {
        global $DB;
        $DB->insert_record(self::EVENT_TABLE, (object) [
            'archive_id'    => $archiveid,
            'checked_at'    => $now,
            'status'        => $status,
            'observed_hash' => $observedhash,
        ]);
        return $status;
    }

    /**
     * Count the archives whose most recent integrity event is of a given status.
     *
     * Used by the dashboard to surface tampered / missing archives without
     * scanning the whole event log every request.
     *
     * @param string $status
     * @return int
     */
    public function count_archives_in_status(string $status): int {
        global $DB;
        $sql = "SELECT COUNT(DISTINCT e.archive_id)
                  FROM {" . self::EVENT_TABLE . "} e
                 WHERE e.status = :status
                   AND NOT EXISTS (
                       SELECT 1 FROM {" . self::EVENT_TABLE . "} e2
                        WHERE e2.archive_id = e.archive_id
                          AND (e2.checked_at > e.checked_at
                               OR (e2.checked_at = e.checked_at AND e2.id > e.id))
                   )";
        return (int) $DB->count_records_sql($sql, ['status' => $status]);
    }
}
