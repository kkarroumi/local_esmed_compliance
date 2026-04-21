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
 * Operator screen for attendance certificates.
 *
 * Supports three actions on the same URL:
 *   - GET  without courseid   -> course picker (one row per course the
 *                                 operator can generate attestations for).
 *   - GET  with courseid      -> enrolled-learners table + generate / download
 *                                 buttons for that course.
 *   - POST action=generate    -> seal a fresh attestation for (courseid, userid)
 *                                 and redirect back to the course screen.
 *   - GET  action=download    -> stream the sealed PDF for an archive row.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_esmed_compliance\archive\adapter_registry;
use local_esmed_compliance\archive\archive_repository;
use local_esmed_compliance\attestation\attestation_listing;
use local_esmed_compliance\attestation\attestation_service;

require_login();

$courseid = optional_param('courseid', 0, PARAM_INT);
$action   = optional_param('action', '', PARAM_ALPHA);

$baseurl = new moodle_url('/local/esmed_compliance/attestations.php');

// ---------------------------------------------------------------------
// Download branch — stream a sealed PDF.
// ---------------------------------------------------------------------
if ($action === 'download') {
    require_sesskey();
    $archiveid = required_param('id', PARAM_INT);

    $record = (new archive_repository())->find_by_id($archiveid);
    if (!$record || $record->archive_type !== archive_repository::TYPE_ATTESTATION_ASSIDUITE) {
        throw new moodle_exception('attestations_archive_not_found', 'local_esmed_compliance');
    }

    // Capability check at the course context the attestation was sealed for.
    $recordcoursecontext = context_course::instance((int) $record->courseid);
    require_capability('local/esmed_compliance:generateattestation', $recordcoursecontext);

    $adapters = adapter_registry::from_config();
    $adapter  = $adapters[$record->storage_adapter] ?? null;
    if ($adapter === null) {
        throw new moodle_exception('attestations_storage_unavailable', 'local_esmed_compliance');
    }

    $bytes = $adapter->fetch((string) $record->file_path);
    if ($bytes === null) {
        throw new moodle_exception('attestations_archive_missing_bytes', 'local_esmed_compliance');
    }

    $filename = sprintf('attestation_u%d_c%d_%s.pdf',
        (int) $record->userid,
        (int) $record->courseid,
        date('Ymd', (int) $record->timestamp_sealed)
    );

    \core\session\manager::write_close();
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($bytes));
    header('Cache-Control: private, max-age=0, no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    echo $bytes;
    die();
}

// ---------------------------------------------------------------------
// Course-level branches need a concrete course.
// ---------------------------------------------------------------------
if ($courseid > 0) {
    $course = get_course($courseid);
    $coursecontext = context_course::instance($courseid);
    require_capability('local/esmed_compliance:generateattestation', $coursecontext);

    $PAGE->set_context($coursecontext);
    $PAGE->set_url(new moodle_url($baseurl, ['courseid' => $courseid]));
    $PAGE->set_pagelayout('admin');
    $PAGE->set_title(get_string('attestations_page_title', 'local_esmed_compliance'));
    $PAGE->set_heading(format_string($course->fullname));

    // Generate branch — POST-only to avoid CSRF from pre-fetching links.
    if ($action === 'generate') {
        require_sesskey();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            throw new moodle_exception('attestations_method_not_allowed', 'local_esmed_compliance');
        }
        $userid = required_param('userid', PARAM_INT);
        if (!is_enrolled($coursecontext, $userid, '', true)) {
            throw new moodle_exception('attestations_user_not_enrolled', 'local_esmed_compliance');
        }

        try {
            $record = (new attestation_service())->generate($userid, $courseid);
            \core\notification::success(
                get_string('attestations_generated_ok', 'local_esmed_compliance', [
                    'token' => substr((string) $record->verification_token, 0, 12),
                ])
            );
        } catch (Throwable $e) {
            \core\notification::error(
                get_string('attestations_generated_error', 'local_esmed_compliance', $e->getMessage())
            );
        }

        redirect(new moodle_url($baseurl, ['courseid' => $courseid]));
    }

    // Default: list enrolled learners with their attestation status.
    $listing = (new attestation_listing())->list_for_course($courseid);
    /** @var \local_esmed_compliance\output\renderer $renderer */
    $renderer = $PAGE->get_renderer('local_esmed_compliance');

    echo $OUTPUT->header();
    echo $renderer->render_attestations_for_course($course, $listing, $baseurl, sesskey());
    echo $OUTPUT->footer();
    exit;
}

// ---------------------------------------------------------------------
// No courseid — show the picker. Admin page at system level.
// ---------------------------------------------------------------------
$syscontext = context_system::instance();

// Any user that holds the capability somewhere in the course hierarchy
// is allowed to see the picker; the picker then lists those courses.
$allowed = (bool) get_user_capability_course(
    'local/esmed_compliance:generateattestation',
    null,
    true,
    ''
);
if (!$allowed) {
    require_capability('local/esmed_compliance:generateattestation', $syscontext);
}

$PAGE->set_context($syscontext);
$PAGE->set_url($baseurl);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('attestations_page_title', 'local_esmed_compliance'));
$PAGE->set_heading(get_string('attestations_page_title', 'local_esmed_compliance'));

$courses = attestation_listing::courses_for_current_user();

/** @var \local_esmed_compliance\output\renderer $renderer */
$renderer = $PAGE->get_renderer('local_esmed_compliance');

echo $OUTPUT->header();
echo $renderer->render_attestations_course_picker($courses, $baseurl);
echo $OUTPUT->footer();
