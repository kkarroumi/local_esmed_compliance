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
 * Scheduled task re-hashing a batch of sealed archives.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_esmed_compliance\task;

use core\task\scheduled_task;
use local_esmed_compliance\archive\integrity_checker;

defined('MOODLE_INTERNAL') || die();

/**
 * Re-hashes the least-recently-checked archives and appends a verdict
 * to {local_esmed_integrity_event}, making tamper and loss detectable
 * without waiting for someone to visit the public verify endpoint.
 */
class verify_archive_integrity_task extends scheduled_task {

    /**
     * @return string
     */
    public function get_name(): string {
        return get_string('task_verify_archive_integrity', 'local_esmed_compliance');
    }

    /**
     * Execute the task.
     *
     * @return void
     */
    public function execute(): void {
        $batchsize = (int) get_config('local_esmed_compliance', 'integrity_batch_size');
        if ($batchsize <= 0) {
            $batchsize = 50;
        }

        $tally = (new integrity_checker())->run($batchsize, time());

        mtrace(sprintf(
            'local_esmed_compliance: verify_archive_integrity_task checked %d (valid=%d, tampered=%d, missing=%d).',
            $tally['checked'],
            $tally['valid'],
            $tally['tampered'],
            $tally['missing']
        ));
    }
}
