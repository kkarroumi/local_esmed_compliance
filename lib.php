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
 * Public Moodle callbacks.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Standard callback invoked at the top of the body on every rendered page.
 *
 * Registers the heartbeat AMD module once per page load for authenticated
 * non-guest users. Returns an empty string because the module does not
 * inject any visible markup.
 *
 * @return string
 */
function local_esmed_compliance_before_standard_top_of_body_html(): string {
    global $PAGE;

    if (!isloggedin() || isguestuser()) {
        return '';
    }

    $interval = (int) get_config('local_esmed_compliance', 'heartbeat_interval');
    if ($interval <= 0) {
        $interval = 30;
    }

    $PAGE->requires->js_call_amd(
        'local_esmed_compliance/heartbeat',
        'init',
        [[
            'interval' => $interval,
            'endpoint' => (new moodle_url('/local/esmed_compliance/ajax/heartbeat.php'))->out(false),
            'sesskey'  => sesskey(),
        ]]
    );

    return '';
}
