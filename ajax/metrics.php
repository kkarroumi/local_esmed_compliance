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
 * AJAX endpoint returning dashboard metrics as JSON.
 *
 * Authenticated by Moodle session cookie and sesskey; gated by the
 * `local/esmed_compliance:viewdashboard` capability. Emits the same
 * context the renderer consumes so the browser can update counters
 * in place without re-rendering the page.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
define('NO_DEBUG_DISPLAY', true);

require_once(__DIR__ . '/../../../config.php');

use local_esmed_compliance\dashboard\metrics_provider;
use local_esmed_compliance\output\renderer;

require_login(null, false, null, false, true);
require_sesskey();
require_capability('local/esmed_compliance:viewdashboard', context_system::instance());

\core\session\manager::write_close();

$metrics = (new metrics_provider())->collect();
$context = renderer::build_template_context($metrics);

header('Content-Type: application/json; charset=utf-8');
echo json_encode($context, JSON_UNESCAPED_UNICODE);
