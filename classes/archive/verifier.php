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
 * Archive verification service.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_esmed_compliance\archive;

defined('MOODLE_INTERNAL') || die();

/**
 * Look up a sealed document by its public verification token and
 * confirm the stored file still matches the hash that was sealed.
 */
class verifier {

    /** @var string Verification status: token unknown. */
    public const STATUS_UNKNOWN = 'unknown';
    /** @var string Verification status: file missing from storage. */
    public const STATUS_MISSING = 'missing';
    /** @var string Verification status: file hash mismatches what was sealed. */
    public const STATUS_TAMPERED = 'tampered';
    /** @var string Verification status: all checks pass. */
    public const STATUS_VALID = 'valid';

    /** @var archive_repository */
    private archive_repository $archive;

    /** @var array<string, storage_adapter> */
    private array $adapters;

    /**
     * Constructor.
     *
     * @param archive_repository|null          $archive  Injectable for tests.
     * @param array<string, storage_adapter>|null $adapters Map of adapter name => adapter.
     */
    public function __construct(
        ?archive_repository $archive = null,
        ?array $adapters = null
    ) {
        $this->archive = $archive ?? new archive_repository();
        $this->adapters = $adapters ?? ['local' => new local_storage_adapter()];
    }

    /**
     * Verify a sealed document by its public token.
     *
     * Returns an associative array with:
     *   - status  : one of STATUS_* constants
     *   - record  : the matched archive index row, or null
     *   - sealed_hash   : the hash persisted at seal time, or null
     *   - computed_hash : the hash recomputed from current storage, or null
     *
     * @param string $token
     * @return array{status:string, record:\stdClass|null, sealed_hash:string|null, computed_hash:string|null}
     */
    public function verify(string $token): array {
        $record = $this->archive->find_by_token($token);
        if (!$record) {
            return ['status' => self::STATUS_UNKNOWN, 'record' => null, 'sealed_hash' => null, 'computed_hash' => null];
        }

        $adapter = $this->adapters[$record->storage_adapter] ?? null;
        if ($adapter === null) {
            return [
                'status'        => self::STATUS_MISSING,
                'record'        => $record,
                'sealed_hash'   => (string) $record->sha256_hash,
                'computed_hash' => null,
            ];
        }

        $bytes = $adapter->fetch((string) $record->file_path);
        if ($bytes === null) {
            return [
                'status'        => self::STATUS_MISSING,
                'record'        => $record,
                'sealed_hash'   => (string) $record->sha256_hash,
                'computed_hash' => null,
            ];
        }

        $computed = hash('sha256', $bytes);
        $valid = hash_equals((string) $record->sha256_hash, $computed);
        return [
            'status'        => $valid ? self::STATUS_VALID : self::STATUS_TAMPERED,
            'record'        => $record,
            'sealed_hash'   => (string) $record->sha256_hash,
            'computed_hash' => $computed,
        ];
    }
}
