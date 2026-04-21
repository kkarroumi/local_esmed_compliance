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
 * Local filesystem archive storage adapter.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_esmed_compliance\archive;

use RuntimeException;

/**
 * Writes sealed documents under a configurable directory on the local
 * filesystem.
 *
 * Falls back to `<dataroot>/local_esmed_compliance/archive` when the
 * admin has not set an explicit path. Each payload is written once: a
 * second `store()` with the same name but divergent bytes raises,
 * reflecting the write-once spirit of the contract.
 */
class local_storage_adapter implements storage_adapter {
    /** @var string Absolute root directory managed by this adapter. */
    private string $root;

    /**
     * Constructor.
     *
     * @param string|null $root Absolute path. When null the adapter uses the default dataroot location.
     */
    public function __construct(?string $root = null) {
        global $CFG;
        if ($root === null || trim($root) === '') {
            $configured = (string) get_config('local_esmed_compliance', 'archive_local_path');
            $root = $configured !== '' ? $configured : $CFG->dataroot . '/local_esmed_compliance/archive';
        }
        $this->root = rtrim($root, '/');
    }

    /**
     * Inherits from parent.
     */
    public function name(): string {
        return 'local';
    }

    /**
     * Inherits from parent.
     */
    public function store(string $bytes, string $relativename): string {
        $safe = self::safe_relative_path($relativename);
        $target = $this->root . '/' . $safe;
        $dir = dirname($target);

        if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) {
            throw new RuntimeException('local_storage_adapter: unable to create ' . $dir);
        }

        if (file_exists($target)) {
            $existing = file_get_contents($target);
            if ($existing !== false && $existing === $bytes) {
                return $safe;
            }
            throw new RuntimeException('local_storage_adapter: refusing to overwrite ' . $safe);
        }

        // Atomic: write to a sibling temp file then rename so partial writes are never observable.
        $tmp = $target . '.tmp-' . bin2hex(random_bytes(6));
        if (file_put_contents($tmp, $bytes, LOCK_EX) === false) {
            throw new RuntimeException('local_storage_adapter: unable to write ' . $tmp);
        }
        @chmod($tmp, 0640);
        if (!rename($tmp, $target)) {
            @unlink($tmp);
            throw new RuntimeException('local_storage_adapter: unable to finalise ' . $target);
        }

        return $safe;
    }

    /**
     * Inherits from parent.
     */
    public function fetch(string $relativename): ?string {
        $safe = self::safe_relative_path($relativename);
        $target = $this->root . '/' . $safe;
        if (!is_file($target)) {
            return null;
        }
        $bytes = file_get_contents($target);
        return $bytes === false ? null : $bytes;
    }

    /**
     * Strip components that would escape the archive root.
     *
     * @param string $relative
     * @return string
     */
    private static function safe_relative_path(string $relative): string {
        $relative = ltrim($relative, '/');
        $parts = [];
        foreach (explode('/', $relative) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                // Drop any attempt to walk up.
                continue;
            }
            $parts[] = $segment;
        }
        if (empty($parts)) {
            throw new RuntimeException('local_storage_adapter: empty relative path');
        }
        return implode('/', $parts);
    }
}
