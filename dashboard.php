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
 * Compliance dashboard page.
 *
 * Gated by the `local/esmed_compliance:viewdashboard` capability.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_esmed_compliance\dashboard\metrics_provider;

require_login();
$context = context_system::instance();
require_capability('local/esmed_compliance:viewdashboard', $context);

$PAGE->set_url(new moodle_url('/local/esmed_compliance/dashboard.php'));
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('dashboard', 'local_esmed_compliance'));
$PAGE->set_heading(get_string('dashboard', 'local_esmed_compliance'));

$metrics = (new metrics_provider())->collect();

/** @var \local_esmed_compliance\output\renderer $renderer */
$renderer = $PAGE->get_renderer('local_esmed_compliance');

$refreshseconds = (int) get_config('local_esmed_compliance', 'dashboard_refresh_seconds');
if ($refreshseconds <= 0) {
    $refreshseconds = 30;
}

$PAGE->requires->js_call_amd(
    'local_esmed_compliance/dashboard',
    'init',
    [[
        'endpoint' => (new moodle_url('/local/esmed_compliance/ajax/metrics.php'))->out(false),
        'sesskey'  => sesskey(),
        'interval' => $refreshseconds,
    ]]
);

echo $OUTPUT->header();
echo $renderer->render_dashboard($metrics);
echo $OUTPUT->footer();
