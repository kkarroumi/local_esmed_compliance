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
 * In-memory storage adapter test double.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_esmed_compliance\tests\fixtures;

use local_esmed_compliance\archive\storage_adapter;

/**
 * In-memory storage: keeps everything in a hash so tests can mutate the
 * persisted bytes to exercise tampered / missing outcomes without
 * touching the filesystem.
 */
final class fake_storage_adapter implements storage_adapter {
    /** @var array<string, string> */
    public array $files = [];

    /**
     * Return the fixture storage adapter name.
     */
    public function name(): string {
        return 'local';
    }

    /**
     * Store bytes in-memory for the test.
     */
    public function store(string $bytes, string $relativename): string {
        $this->files[$relativename] = $bytes;
        return $relativename;
    }

    /**
     * Fetch previously stored bytes.
     */
    public function fetch(string $relativename): ?string {
        return $this->files[$relativename] ?? null;
    }

    /**
     * Mutate a stored blob to simulate tampering.
     *
     * @param string $relativename
     * @param string $newbytes
     */
    public function tamper(string $relativename, string $newbytes): void {
        $this->files[$relativename] = $newbytes;
    }

    /**
     * Drop a stored blob to simulate a missing file.
     *
     * @param string $relativename
     */
    public function remove(string $relativename): void {
        unset($this->files[$relativename]);
    }
}
