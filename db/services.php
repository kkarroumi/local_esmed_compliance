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
 * Webservice function definitions.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [

    'local_esmed_compliance_verify_token' => [
        'classname'   => \local_esmed_compliance\external\verify_token::class,
        'methodname'  => 'execute',
        'description' => 'Verify a sealed document by its public verification token.',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => '',
        'services'    => ['local_esmed_compliance_service'],
    ],

    'local_esmed_compliance_get_dashboard_metrics' => [
        'classname'   => \local_esmed_compliance\external\get_dashboard_metrics::class,
        'methodname'  => 'execute',
        'description' => 'Return point-in-time compliance dashboard counters.',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => 'local/esmed_compliance:viewdashboard',
        'services'    => ['local_esmed_compliance_service'],
    ],

    'local_esmed_compliance_get_learner_summary' => [
        'classname'   => \local_esmed_compliance\external\get_learner_summary::class,
        'methodname'  => 'execute',
        'description' => 'Return the compliance summary for one learner in one course.',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => 'local/esmed_compliance:viewownreports, local/esmed_compliance:viewdashboard',
        'services'    => ['local_esmed_compliance_service'],
    ],

    'local_esmed_compliance_acknowledge_alert' => [
        'classname'   => \local_esmed_compliance\external\acknowledge_alert::class,
        'methodname'  => 'execute',
        'description' => 'Acknowledge an open compliance alert.',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => 'local/esmed_compliance:managealerts',
        'services'    => ['local_esmed_compliance_service'],
    ],
];

$services = [
    'ESMED compliance service' => [
        'functions' => [
            'local_esmed_compliance_verify_token',
            'local_esmed_compliance_get_dashboard_metrics',
            'local_esmed_compliance_get_learner_summary',
            'local_esmed_compliance_acknowledge_alert',
        ],
        'restrictedusers' => 1,
        'enabled'         => 0,
        'shortname'       => 'local_esmed_compliance_service',
        'downloadfiles'   => 0,
        'uploadfiles'     => 0,
    ],
];
