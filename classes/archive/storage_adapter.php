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
 * Archive storage adapter contract.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_esmed_compliance\archive;

defined('MOODLE_INTERNAL') || die();

/**
 * Pluggable backend that writes sealed documents somewhere durable.
 *
 * Implementations are expected to be write-once: two calls with the same
 * `$relativename` must either succeed idempotently (identical bytes) or
 * fail rather than overwrite. The iteration 5 cycle ships the
 * filesystem adapter; the S3 adapter is added in a later iteration.
 */
interface storage_adapter {

    /**
     * Short identifier persisted in `local_esmed_archive_index.storage_adapter`.
     *
     * @return string
     */
    public function name(): string;

    /**
     * Store a byte payload and return the adapter-relative path.
     *
     * @param string $bytes        Binary payload to persist.
     * @param string $relativename Relative filename suggested by the caller.
     * @return string Adapter-relative path that can be fed back to {@see fetch}.
     */
    public function store(string $bytes, string $relativename): string;

    /**
     * Read back the bytes previously written at `$relativename`.
     *
     * @param string $relativename
     * @return string|null Raw bytes, or null if the object is missing.
     */
    public function fetch(string $relativename): ?string;
}
