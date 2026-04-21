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
 * Session tracker.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_esmed_compliance\session;

use stdClass;

/**
 * Business logic orchestrating the certifiable session lifecycle.
 *
 * The tracker is the single entry point for the observer, the AJAX
 * heartbeat endpoint and the timeout scheduled task. It delegates
 * persistence to {@see session_repository}.
 */
class tracker {
    /** @var string Session closed on explicit Moodle logout. */
    public const CLOSURE_LOGOUT = 'logout';
    /** @var string Session closed by the scheduled task after the idle window. */
    public const CLOSURE_TIMEOUT = 'timeout';
    /** @var string Session closed by a navigator.sendBeacon call on tab unload. */
    public const CLOSURE_BEACON = 'beacon';
    /** @var string Session closed by the scheduled task for a record that never received a single beacon. */
    public const CLOSURE_CRASH = 'crash';
    /** @var string Session closed explicitly from the admin UI. */
    public const CLOSURE_MANUAL = 'manual';

    /** @var session_repository */
    private session_repository $repository;

    /**
     * Constructor.
     *
     * @param session_repository|null $repository Injectable for tests.
     */
    public function __construct(?session_repository $repository = null) {
        $this->repository = $repository ?? new session_repository();
    }

    /**
     * Open a new session for a user if none is currently open.
     *
     * If an open session already exists the call is a no-op and returns
     * the existing session id. This keeps observer + heartbeat semantics
     * idempotent even across crashed pages.
     *
     * @param int         $userid
     * @param string|null $ipaddress
     * @param string|null $useragent
     * @param int|null    $courseid
     * @param int|null    $now       Override for tests.
     * @return int The session id.
     */
    public function open_session(
        int $userid,
        ?string $ipaddress = null,
        ?string $useragent = null,
        ?int $courseid = null,
        ?int $now = null
    ): int {
        $now = $now ?? time();

        $existing = $this->repository->find_open_session_for_user($userid);
        if ($existing) {
            return (int) $existing->id;
        }

        $record = new stdClass();
        $record->userid           = $userid;
        $record->courseid         = $courseid;
        $record->session_start    = $now;
        $record->session_end      = null;
        $record->duration_seconds = null;
        $record->last_heartbeat   = $now;
        $record->ip_address       = $ipaddress ? self::truncate($ipaddress, 45) : null;
        $record->user_agent       = $useragent ? self::truncate($useragent, 255) : null;
        $record->closure_type     = null;
        $record->funderid         = null;
        $record->sealed           = 0;
        $record->timecreated      = $now;
        $record->timemodified     = $now;

        return $this->repository->insert($record);
    }

    /**
     * Record a heartbeat for the user's currently open session.
     *
     * Returns the session id that was touched, or null if the user has no
     * open session (for example after a timeout while the browser kept
     * beating).
     *
     * @param int      $userid
     * @param int|null $now Override for tests.
     * @return int|null
     */
    public function record_heartbeat(int $userid, ?int $now = null): ?int {
        $now = $now ?? time();
        $session = $this->repository->find_open_session_for_user($userid);
        if (!$session) {
            return null;
        }
        if ($this->repository->touch_heartbeat((int) $session->id, $now)) {
            return (int) $session->id;
        }
        return null;
    }

    /**
     * Close the currently open session of a user.
     *
     * Returns true when this call is the one that actually performed the
     * close (not a subsequent duplicate), false otherwise.
     *
     * @param int      $userid
     * @param string   $closuretype One of the CLOSURE_* constants.
     * @param int|null $now         Override for tests.
     * @return bool
     */
    public function close_session(int $userid, string $closuretype, ?int $now = null): bool {
        $now = $now ?? time();
        $session = $this->repository->find_open_session_for_user($userid);
        if (!$session) {
            return false;
        }
        return $this->repository->close((int) $session->id, $now, $closuretype);
    }

    /**
     * Close every session that has been silent for longer than the timeout.
     *
     * Records with a `last_heartbeat` at all are closed as `timeout`;
     * records that never received a beacon (browser closed immediately)
     * are closed as `crash`.
     *
     * @param int $timeoutseconds Idle seconds after which a session is considered stale.
     * @param int|null $now       Override for tests.
     * @return int Number of sessions that were closed by this call.
     */
    public function close_stale_sessions(int $timeoutseconds, ?int $now = null): int {
        $now = $now ?? time();
        $threshold = $now - max(0, $timeoutseconds);

        $closed = 0;
        foreach ($this->repository->find_stale_open_sessions($threshold) as $session) {
            $closuretype = $session->last_heartbeat === null
                ? self::CLOSURE_CRASH
                : self::CLOSURE_TIMEOUT;
            $endtimestamp = $session->last_heartbeat !== null
                ? (int) $session->last_heartbeat
                : (int) $session->session_start;
            if ($this->repository->close((int) $session->id, $endtimestamp, $closuretype)) {
                $closed++;
            }
        }
        return $closed;
    }

    /**
     * Truncate a string safely for storage in a CHAR column.
     *
     * @param string $value
     * @param int    $max
     * @return string
     */
    private static function truncate(string $value, int $max): string {
        if (class_exists('core_text')) {
            return \core_text::substr($value, 0, $max);
        }
        return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
    }
}
