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
 * Operator screen to manage funder links.
 *
 * Actions on the same URL:
 *   - GET                    -> list of existing funder links.
 *   - GET  action=pick       -> course selector (any course not yet linked).
 *   - GET  action=edit&courseid=N -> create/update form for that course.
 *   - POST (form submit)     -> upsert link for courseid.
 *   - POST action=delete     -> remove link for courseid.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_esmed_compliance\form\funder_link_form;
use local_esmed_compliance\funder\funder_link_listing;
use local_esmed_compliance\funder\funder_link_repository;

require_login();

$context = context_system::instance();
require_capability('local/esmed_compliance:manageconfig', $context);

$baseurl = new moodle_url('/local/esmed_compliance/funders.php');
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('funders_page_title', 'local_esmed_compliance'));
$PAGE->set_heading(get_string('funders_page_title', 'local_esmed_compliance'));

$action = optional_param('action', '', PARAM_ALPHA);

// ---------------------------------------------------------------------
// Delete branch — POST + sesskey.
// ---------------------------------------------------------------------
if ($action === 'delete') {
    require_sesskey();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new moodle_exception('funders_method_not_allowed', 'local_esmed_compliance');
    }
    $courseid = required_param('courseid', PARAM_INT);
    (new funder_link_repository())->remove_for_course($courseid);
    \core\notification::success(get_string('funders_deleted_ok', 'local_esmed_compliance'));
    redirect($baseurl);
}

// ---------------------------------------------------------------------
// Course picker — any course not yet linked.
// ---------------------------------------------------------------------
if ($action === 'pick') {
    global $DB;
    $PAGE->set_url(new moodle_url($baseurl, ['action' => 'pick']));

    $sql = "SELECT c.id, c.fullname, c.shortname
              FROM {course} c
         LEFT JOIN {" . funder_link_repository::TABLE . "} l ON l.courseid = c.id
             WHERE c.id <> :siteid
               AND l.id IS NULL
          ORDER BY c.fullname ASC";
    $rows = [];
    foreach ($DB->get_records_sql($sql, ['siteid' => SITEID]) as $row) {
        $rows[] = [
            'id'        => (int) $row->id,
            'fullname'  => format_string($row->fullname),
            'shortname' => format_string($row->shortname),
        ];
    }

    /** @var \local_esmed_compliance\output\renderer $renderer */
    $renderer = $PAGE->get_renderer('local_esmed_compliance');
    echo $OUTPUT->header();
    echo $renderer->render_funder_course_picker($rows, $baseurl);
    echo $OUTPUT->footer();
    exit;
}

// ---------------------------------------------------------------------
// Edit branch — form display + submit.
// ---------------------------------------------------------------------
if ($action === 'edit') {
    $courseid = required_param('courseid', PARAM_INT);
    $course = get_course($courseid);

    $PAGE->set_url(new moodle_url($baseurl, ['action' => 'edit', 'courseid' => $courseid]));

    $repo = new funder_link_repository();
    $existing = $repo->get_for_course($courseid);

    $form = new funder_link_form(new moodle_url($baseurl, ['action' => 'edit', 'courseid' => $courseid]), [
        'courseid'   => $courseid,
        'coursename' => $course->fullname,
        'existing'   => $existing,
    ]);

    if ($form->is_cancelled()) {
        redirect($baseurl);
    }

    if ($data = $form->get_data()) {
        $attributes = [
            'dossier_number'      => $data->dossier_number !== '' ? $data->dossier_number : null,
            'action_intitule'     => $data->action_intitule !== '' ? $data->action_intitule : null,
            'opco_name'           => $data->opco_name !== '' ? $data->opco_name : null,
            'total_hours_planned' => isset($data->total_hours_planned) && $data->total_hours_planned !== ''
                ? (float) $data->total_hours_planned : null,
            'start_date'          => !empty($data->start_date) ? (int) $data->start_date : null,
            'end_date'            => !empty($data->end_date) ? (int) $data->end_date : null,
        ];
        $repo->upsert($courseid, (string) $data->funder_type, $attributes);
        \core\notification::success(get_string('funders_saved_ok', 'local_esmed_compliance'));
        redirect($baseurl);
    }

    echo $OUTPUT->header();
    echo $OUTPUT->heading(
        get_string(
            $existing ? 'funders_edit_heading' : 'funders_create_heading',
            'local_esmed_compliance'
        )
    );
    $form->display();
    echo $OUTPUT->footer();
    exit;
}

// ---------------------------------------------------------------------
// Default — list view.
// ---------------------------------------------------------------------
$PAGE->set_url($baseurl);

$rows = (new funder_link_listing())->all();
$prepared = [];
$dateformat = get_string('strftimedateshort', 'core_langconfig');
foreach ($rows as $row) {
    $start = $row['start_date'];
    $end   = $row['end_date'];
    if ($start !== null && $end !== null) {
        $period = userdate($start, $dateformat) . ' — ' . userdate($end, $dateformat);
    } else if ($start !== null) {
        $period = userdate($start, $dateformat);
    } else if ($end !== null) {
        $period = userdate($end, $dateformat);
    } else {
        $period = '';
    }

    $prepared[] = [
        'id'                => $row['id'],
        'courseid'          => $row['courseid'],
        'course_fullname'   => $row['course_fullname'],
        'course_shortname'  => $row['course_shortname'],
        'funder_type_label' => get_string('funder_' . strtolower($row['funder_type']), 'local_esmed_compliance'),
        'dossier_number'    => $row['dossier_number'] ?? '',
        'hours'             => $row['total_hours_planned'] !== null
            ? number_format((float) $row['total_hours_planned'], 2, '.', '')
            : '',
        'period'            => $period,
    ];
}

/** @var \local_esmed_compliance\output\renderer $renderer */
$renderer = $PAGE->get_renderer('local_esmed_compliance');

echo $OUTPUT->header();
echo $renderer->render_funder_links_list($prepared, $baseurl, sesskey());
echo $OUTPUT->footer();
