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
 * External function: acknowledge an inactivity / dropout alert.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_esmed_compliance\external;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_esmed_compliance\alert\alert_repository;

/**
 * Mark an open alert as acknowledged by the calling user.
 *
 * Gated by `local/esmed_compliance:managealerts`. The operation is
 * idempotent — acknowledging an already-acknowledged alert is a no-op
 * and returns the existing state without raising an error.
 */
class acknowledge_alert extends external_api {
    /**
     * Declare the input parameters accepted by the webservice.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'alertid' => new external_value(PARAM_INT, 'Alert row id to acknowledge.', VALUE_REQUIRED),
        ]);
    }

    /**
     * Execute the webservice call and acknowledge the requested alert.
     *
     * @param int $alertid
     * @return array{acknowledged:bool, acknowledged_at:?int, acknowledged_by:?int}
     */
    public static function execute(int $alertid): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), ['alertid' => $alertid]);
        $alertid = (int) $params['alertid'];

        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/esmed_compliance:managealerts', $context);

        $repo = new alert_repository();
        $row = $repo->get($alertid);
        if ($row === null) {
            throw new \moodle_exception('alertnotfound', 'local_esmed_compliance');
        }

        $repo->acknowledge($alertid, (int) $USER->id, time());
        $row = $repo->get($alertid);

        return [
            'acknowledged'    => $row && $row->acknowledged_at !== null,
            'acknowledged_at' => $row && $row->acknowledged_at !== null ? (int) $row->acknowledged_at : null,
            'acknowledged_by' => $row && $row->acknowledged_by !== null ? (int) $row->acknowledged_by : null,
        ];
    }

    /**
     * Declare the shape of the value returned by the webservice.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'acknowledged' => new external_value(PARAM_BOOL, 'Whether the alert is now in the acknowledged state.'),
            'acknowledged_at' => new external_value(
                PARAM_INT,
                'Acknowledgement timestamp.',
                VALUE_OPTIONAL,
                null,
                NULL_ALLOWED
            ),
            'acknowledged_by' => new external_value(
                PARAM_INT,
                'User id who acknowledged the alert.',
                VALUE_OPTIONAL,
                null,
                NULL_ALLOWED
            ),
        ]);
    }
}
