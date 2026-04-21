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
 * External function: compliance dashboard metrics.
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
use local_esmed_compliance\dashboard\metrics_provider;

/**
 * Expose the compliance dashboard counters over webservice.
 *
 * Gated by `local/esmed_compliance:viewdashboard` at the system context —
 * same capability that guards the in-Moodle dashboard page so API consumers
 * and UI users see the same data under the same authorisation rules.
 */
class get_dashboard_metrics extends external_api {
    /**
     * Declare the input parameters accepted by the webservice.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Execute the webservice call and return the dashboard metrics snapshot.
     *
     * @return array<string, mixed>
     */
    public static function execute(): array {
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/esmed_compliance:viewdashboard', $context);

        $metrics = (new metrics_provider())->collect();

        return [
            'generated_at' => (int) $metrics['generated_at'],
            'sessions'     => [
                'open'            => (int) $metrics['sessions']['open'],
                'closed_last_24h' => (int) $metrics['sessions']['closed_last_24h'],
                'seconds_today'   => (int) $metrics['sessions']['seconds_today'],
            ],
            'archives'     => [
                'total'        => (int) $metrics['archives']['total'],
                'attestations' => (int) $metrics['archives']['attestations'],
                'bordereaux'   => (int) $metrics['archives']['bordereaux'],
            ],
            'alerts'       => [
                'unacknowledged' => (int) $metrics['alerts']['unacknowledged'],
                'last_7_days'    => (int) $metrics['alerts']['last_7_days'],
            ],
            'integrity'    => [
                'valid'    => (int) $metrics['integrity']['valid'],
                'tampered' => (int) $metrics['integrity']['tampered'],
                'missing'  => (int) $metrics['integrity']['missing'],
            ],
        ];
    }

    /**
     * Declare the shape of the value returned by the webservice.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'generated_at' => new external_value(PARAM_INT, 'Unix timestamp when the payload was computed.'),
            'sessions' => new external_single_structure([
                'open'            => new external_value(PARAM_INT, 'Currently open certifiable sessions.'),
                'closed_last_24h' => new external_value(PARAM_INT, 'Sessions closed in the last 24 hours.'),
                'seconds_today'   => new external_value(PARAM_INT, 'Total session seconds logged since midnight.'),
            ]),
            'archives' => new external_single_structure([
                'total'        => new external_value(PARAM_INT, 'Total sealed archives of any type.'),
                'attestations' => new external_value(PARAM_INT, 'Sealed attestations d\'assiduité.'),
                'bordereaux'   => new external_value(PARAM_INT, 'Sealed bordereaux financeur.'),
            ]),
            'alerts' => new external_single_structure([
                'unacknowledged' => new external_value(PARAM_INT, 'Unacknowledged alerts.'),
                'last_7_days'    => new external_value(PARAM_INT, 'Alerts triggered in the last 7 days.'),
            ]),
            'integrity' => new external_single_structure([
                'valid'    => new external_value(PARAM_INT, 'Archives last confirmed valid.'),
                'tampered' => new external_value(PARAM_INT, 'Archives whose bytes no longer match the sealed hash.'),
                'missing'  => new external_value(PARAM_INT, 'Archives whose file has gone missing from storage.'),
            ]),
        ]);
    }
}
