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
 * English language strings for local_esmed_compliance.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'ESMED Compliance';
$string['pluginname_desc'] = 'Regulatory compliance toolkit for French training organisations (Qualiopi, CPF, France Travail): certifiable session tracking, attendance certificates (D.6353-4), funder statements and WORM archiving.';

// Generic.
$string['dashboard'] = 'Compliance dashboard';
$string['yes'] = 'Yes';
$string['no'] = 'No';

// Settings (seeded here, UI added in iteration 2).
$string['settings_general'] = 'General';
$string['setting_heartbeat_interval'] = 'Heartbeat interval (seconds)';
$string['setting_heartbeat_interval_desc'] = 'Frequency at which the browser sends a keep-alive signal while the user is on a Moodle page.';
$string['setting_session_timeout_minutes'] = 'Session idle timeout (minutes)';
$string['setting_session_timeout_minutes_desc'] = 'Open sessions with no heartbeat for longer than this are automatically closed as timeouts.';
$string['setting_activity_delta_cap_minutes'] = 'Per-module delta cap (minutes)';
$string['setting_activity_delta_cap_minutes_desc'] = 'Maximum duration counted between two consecutive module views.';
$string['setting_retention_years'] = 'Retention period (years)';
$string['setting_retention_years_desc'] = 'How long sealed documents are kept before they become eligible for purge.';
$string['setting_archive_storage_adapter'] = 'Archive storage adapter';
$string['setting_archive_storage_adapter_desc'] = 'Where sealed documents are written (local filesystem or S3 object storage).';

// Capabilities.
$string['esmed_compliance:viewdashboard'] = 'View the compliance dashboard';
$string['esmed_compliance:generateattestation'] = 'Generate attendance certificates';
$string['esmed_compliance:manageconfig'] = 'Manage funder links and plugin configuration';
$string['esmed_compliance:viewownreports'] = 'View own compliance reports';
$string['esmed_compliance:exportfundedata'] = 'Export funder statements';
$string['esmed_compliance:managearchive'] = 'Access sealed archives';

// Privacy metadata.
$string['privacy:metadata:local_esmed_sessions'] = 'Certifiable session records used as attendance evidence under article D.6353-4 of the French Labour Code.';
$string['privacy:metadata:local_esmed_sessions:userid'] = 'The Moodle user whose session is recorded.';
$string['privacy:metadata:local_esmed_sessions:courseid'] = 'The course the session is attached to, if any.';
$string['privacy:metadata:local_esmed_sessions:session_start'] = 'The timestamp when the session was opened.';
$string['privacy:metadata:local_esmed_sessions:session_end'] = 'The timestamp when the session was closed.';
$string['privacy:metadata:local_esmed_sessions:ip_address'] = 'The IP address the session originated from.';
$string['privacy:metadata:local_esmed_sessions:user_agent'] = 'The browser user-agent string at session opening.';
$string['privacy:metadata:local_esmed_activity_log'] = 'Aggregated time spent and view counts per course module.';
$string['privacy:metadata:local_esmed_assessment_index'] = 'Categorised assessment attempts (pedagogical quizzes, mock exams, summative assessments).';
$string['privacy:metadata:local_esmed_archive_index'] = 'Index of sealed documents (certificates, funder statements) attached to a user.';
$string['privacy:metadata:local_esmed_alerts'] = 'History of learner dropout and inactivity alerts.';
$string['privacy:legalretention'] = 'Attendance evidence is kept for the legal retention period required by article L.6353-1 of the French Labour Code and the Qualiopi framework. Requests for erasure covering data still within this window are partially honoured: only data outside the legal perimeter is deleted.';
