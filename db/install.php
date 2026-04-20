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
 * Post-installation hook.
 *
 * Executed once after the tables defined in install.xml have been created.
 * Used to seed the default configuration values that the upcoming settings
 * page will manage.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Seed default configuration on fresh install.
 *
 * @return bool
 */
function xmldb_local_esmed_compliance_install() {
    // Heartbeat window in seconds used by the AMD module (client side).
    set_config('heartbeat_interval', 30, 'local_esmed_compliance');
    // Server side idle window after which an open session is force-closed as a timeout.
    set_config('session_timeout_minutes', 10, 'local_esmed_compliance');
    // Cap applied to each view-to-view delta when computing time spent per module.
    set_config('activity_delta_cap_minutes', 15, 'local_esmed_compliance');
    // Retention period in years before archived documents become purgeable.
    set_config('retention_years', 5, 'local_esmed_compliance');
    // Default storage adapter for sealed documents.
    set_config('archive_storage_adapter', 'local', 'local_esmed_compliance');

    return true;
}
