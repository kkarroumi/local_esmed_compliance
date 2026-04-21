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
 * Session timeout scheduled task.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_esmed_compliance\task;

use core\task\scheduled_task;
use local_esmed_compliance\session\tracker;

/**
 * Closes open sessions whose last heartbeat is older than the configured idle window.
 *
 * Runs every five minutes by default. A session that never received a
 * heartbeat is closed as `crash`; a session whose last heartbeat predates
 * the threshold is closed as `timeout`.
 */
class session_timeout_task extends scheduled_task {
    /**
     * Task display name shown on the Scheduled tasks admin page.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_session_timeout', 'local_esmed_compliance');
    }

    /**
     * Execute the task.
     *
     * @return void
     */
    public function execute(): void {
        $timeoutminutes = (int) get_config('local_esmed_compliance', 'session_timeout_minutes');
        if ($timeoutminutes <= 0) {
            $timeoutminutes = 10;
        }
        $timeoutseconds = $timeoutminutes * 60;

        $closed = (new tracker())->close_stale_sessions($timeoutseconds);

        mtrace("local_esmed_compliance: session_timeout_task closed {$closed} stale session(s).");
    }
}
