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
 * Scheduled task raising inactivity alerts for stale learners.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_esmed_compliance\task;

use core\task\scheduled_task;
use local_esmed_compliance\alert\alert_repository;
use local_esmed_compliance\alert\inactivity_detector;
use local_esmed_compliance\alert\notifier;

defined('MOODLE_INTERNAL') || die();

/**
 * Runs the inactivity detector on a daily cadence.
 *
 * The threshold is read from plugin config so deployments can tune it
 * to the training programme's definition of "at risk".
 */
class detect_inactivity_task extends scheduled_task {

    /**
     * @return string
     */
    public function get_name(): string {
        return get_string('task_detect_inactivity', 'local_esmed_compliance');
    }

    /**
     * Execute the task.
     *
     * @return void
     */
    public function execute(): void {
        $thresholddays = (int) get_config('local_esmed_compliance', 'inactivity_threshold_days');
        if ($thresholddays <= 0) {
            $thresholddays = 7;
        }

        $now = time();
        $tally = (new inactivity_detector())->run($thresholddays, $now);

        $repo = new alert_repository();
        $notifier = new notifier($repo);
        $notified = 0;
        foreach ($repo->find_pending_notification() as $alertid) {
            $notified += $notifier->notify($alertid, $now);
        }

        mtrace(sprintf(
            'local_esmed_compliance: detect_inactivity_task scanned %d (raised=%d, skipped_open=%d, recipients_notified=%d).',
            $tally['scanned'],
            $tally['raised'],
            $tally['skipped_open'],
            $notified
        ));
    }
}
