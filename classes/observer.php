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
 * Event observers dispatcher.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_esmed_compliance;

use core\event\user_loggedin;
use core\event\user_loggedout;
use local_esmed_compliance\session\tracker;

defined('MOODLE_INTERNAL') || die();

/**
 * Static dispatcher. Each method is registered in {@see db/events.php}.
 */
class observer {

    /**
     * Open a certifiable session when a user logs in.
     *
     * @param user_loggedin $event
     * @return void
     */
    public static function user_loggedin(user_loggedin $event): void {
        $userid = (int) $event->userid;
        if ($userid <= 0 || isguestuser($userid)) {
            return;
        }

        $ipaddress = null;
        $useragent = null;
        if (!CLI_SCRIPT && !PHPUNIT_TEST) {
            $ipaddress = getremoteaddr(null);
            $useragent = isset($_SERVER['HTTP_USER_AGENT'])
                ? (string) $_SERVER['HTTP_USER_AGENT']
                : null;
        }

        (new tracker())->open_session($userid, $ipaddress, $useragent);
    }

    /**
     * Close the user's session on explicit logout.
     *
     * @param user_loggedout $event
     * @return void
     */
    public static function user_loggedout(user_loggedout $event): void {
        $userid = (int) $event->userid;
        if ($userid <= 0 || isguestuser($userid)) {
            return;
        }
        (new tracker())->close_session($userid, tracker::CLOSURE_LOGOUT);
    }
}
