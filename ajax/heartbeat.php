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
 * AJAX endpoint for the heartbeat AMD module.
 *
 * Accepts two actions, both authenticated by Moodle session cookie
 * plus sesskey:
 *   - `heartbeat`  : refreshes last_heartbeat for the user's open session.
 *   - `close`      : closes the current session (sent by navigator.sendBeacon).
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
define('NO_DEBUG_DISPLAY', true);

require_once(__DIR__ . '/../../../config.php');

require_login(null, false, null, false, true);
require_sesskey();

// Release the Moodle session lock early: heartbeats are frequent and must not
// serialise the user's other requests.
\core\session\manager::write_close();

$action = required_param('action', PARAM_ALPHA);

$tracker = new \local_esmed_compliance\session\tracker();
$response = ['ok' => false];

try {
    switch ($action) {
        case 'heartbeat':
            $sessionid = $tracker->record_heartbeat((int) $USER->id);
            $response = [
                'ok'        => $sessionid !== null,
                'sessionid' => $sessionid,
            ];
            break;

        case 'close':
            $closuretype = optional_param('closure_type', 'beacon', PARAM_ALPHA);
            if (!in_array($closuretype, ['beacon', 'manual'], true)) {
                $closuretype = 'beacon';
            }
            $closed = $tracker->close_session((int) $USER->id, $closuretype);
            $response = ['ok' => $closed];
            break;

        default:
            $response = ['ok' => false, 'error' => 'unknown_action'];
            http_response_code(400);
    }
} catch (\Throwable $e) {
    debugging($e->getMessage(), DEBUG_DEVELOPER);
    $response = ['ok' => false, 'error' => 'server_error'];
    http_response_code(500);
}

echo json_encode($response);
