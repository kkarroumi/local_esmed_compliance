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
 * Scheduled task folding module views into the activity log.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_esmed_compliance\task;

use core\task\scheduled_task;
use local_esmed_compliance\activity\aggregator;

/**
 * Reads new module-view events from the standard logstore and folds them
 * into {local_esmed_compliance_activity_log}. Runs every fifteen minutes by default.
 */
class aggregate_activity_task extends scheduled_task {
    /**
     * Task display name shown on the Scheduled tasks admin page.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_aggregate_activity', 'local_esmed_compliance');
    }

    /**
     * Execute the task.
     *
     * @return void
     */
    public function execute(): void {
        $capminutes = (int) get_config('local_esmed_compliance', 'activity_delta_cap_minutes');
        if ($capminutes <= 0) {
            $capminutes = 15;
        }
        $capseconds = $capminutes * 60;

        $touched = (new aggregator())->run($capseconds, time());

        mtrace("local_esmed_compliance: aggregate_activity_task updated {$touched} (user, module) aggregate(s).");
    }
}
