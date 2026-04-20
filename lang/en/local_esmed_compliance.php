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

// Scheduled tasks.
$string['task_session_timeout'] = 'Close idle compliance sessions';
$string['task_aggregate_activity'] = 'Aggregate learner activity into the compliance log';

// Assessment regulatory categories.
$string['assessment_type_quiz_pedago'] = 'Pedagogical quiz';
$string['assessment_type_devoir_formatif'] = 'Formative assignment';
$string['assessment_type_examen_blanc'] = 'Mock exam';
$string['assessment_type_evaluation_sommative'] = 'Summative assessment';

// Session closure types (exposed in reports).
$string['closure_logout'] = 'Logout';
$string['closure_timeout'] = 'Timeout';
$string['closure_beacon'] = 'Browser closed';
$string['closure_crash'] = 'Crash';
$string['closure_manual'] = 'Manual';

// Settings - General.
$string['settings_general'] = 'General';
$string['setting_heartbeat_interval'] = 'Heartbeat interval (seconds)';
$string['setting_heartbeat_interval_desc'] = 'Frequency at which the browser sends a keep-alive signal while the user is on a Moodle page.';
$string['setting_session_timeout_minutes'] = 'Session idle timeout (minutes)';
$string['setting_session_timeout_minutes_desc'] = 'Open sessions with no heartbeat for longer than this are automatically closed as timeouts.';
$string['setting_activity_delta_cap_minutes'] = 'Per-module delta cap (minutes)';
$string['setting_activity_delta_cap_minutes_desc'] = 'Maximum duration counted between two consecutive module views.';
$string['setting_retention_years'] = 'Retention period (years)';
$string['setting_retention_years_desc'] = 'How long sealed documents are kept before they become eligible for purge.';
$string['setting_funder_default'] = 'Default funder';
$string['setting_funder_default_desc'] = 'Funder type pre-selected when linking a new course. Leave empty to force an explicit choice.';
$string['funder_none'] = 'None';
$string['funder_cpf'] = 'CPF (Mon Compte Formation)';
$string['funder_ft'] = 'France Travail';
$string['funder_opco'] = 'OPCO';
$string['funder_region'] = 'Region';
$string['funder_autre'] = 'Other';

// Settings - Branding.
$string['settings_branding'] = 'Branding';
$string['setting_org_logo'] = 'Organisation logo';
$string['setting_org_logo_desc'] = 'Logo printed at the top of attendance certificates and funder statements.';
$string['setting_org_legal_name'] = 'Legal name';
$string['setting_org_legal_name_desc'] = 'Registered name of the training organisation.';
$string['setting_org_siret'] = 'SIRET';
$string['setting_org_siret_desc'] = '14-digit French business identifier.';
$string['setting_org_nda'] = 'Declaration of activity number (NDA)';
$string['setting_org_nda_desc'] = 'Number of the declaration of activity filed with the regional authority (DREETS).';
$string['setting_org_address'] = 'Postal address';
$string['setting_org_address_desc'] = 'Headquarters address printed on legal documents.';
$string['setting_org_signatory_name'] = 'Signatory name';
$string['setting_org_signatory_name_desc'] = 'Name of the person signing the certificates.';
$string['setting_org_signatory_role'] = 'Signatory role';
$string['setting_org_signatory_role_desc'] = 'Role or title of the signatory.';

// Settings - Archive.
$string['settings_archive'] = 'Archive';
$string['setting_archive_storage_adapter'] = 'Archive storage adapter';
$string['setting_archive_storage_adapter_desc'] = 'Where sealed documents are written (local filesystem or S3 object storage).';
$string['adapter_local'] = 'Local filesystem';
$string['adapter_s3'] = 'S3 object storage';
$string['setting_archive_local_path'] = 'Local archive path';
$string['setting_archive_local_path_desc'] = 'Absolute filesystem path outside the web root. Leave empty to fall back on Moodle dataroot.';
$string['settings_archive_s3_heading'] = 'S3 credentials';
$string['settings_archive_s3_heading_desc'] = 'Required only when the storage adapter is set to S3. Use an Object Lock-enabled bucket configured in compliance mode.';
$string['setting_s3_endpoint'] = 'S3 endpoint URL';
$string['setting_s3_endpoint_desc'] = 'Leave empty to use the AWS default. Set for OVH, Scaleway or any S3-compatible provider.';
$string['setting_s3_region'] = 'S3 region';
$string['setting_s3_region_desc'] = 'AWS region identifier (for example eu-west-3 for Paris).';
$string['setting_s3_bucket'] = 'S3 bucket';
$string['setting_s3_bucket_desc'] = 'Bucket name dedicated to sealed compliance documents.';
$string['setting_s3_access_key'] = 'S3 access key';
$string['setting_s3_access_key_desc'] = 'IAM access key identifier authorised to write to the bucket.';
$string['setting_s3_secret_key'] = 'S3 secret key';
$string['setting_s3_secret_key_desc'] = 'IAM secret access key paired with the access key above.';

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
$string['privacy:metadata:local_esmed_sessions:duration_seconds'] = 'Effective duration of the session, in seconds.';
$string['privacy:metadata:local_esmed_sessions:closure_type'] = 'How the session was closed (logout, timeout, beacon, crash, manual).';

$string['privacy:metadata:local_esmed_activity_log'] = 'Aggregated time spent and view counts per course module.';
$string['privacy:metadata:local_esmed_activity_log:userid'] = 'The Moodle user whose module activity is aggregated.';
$string['privacy:metadata:local_esmed_activity_log:courseid'] = 'The course the module belongs to.';
$string['privacy:metadata:local_esmed_activity_log:cmid'] = 'The course module identifier.';
$string['privacy:metadata:local_esmed_activity_log:time_spent_seconds'] = 'Total time the learner spent on this module, in seconds.';
$string['privacy:metadata:local_esmed_activity_log:views_count'] = 'Number of times the learner opened this module.';
$string['privacy:metadata:local_esmed_activity_log:completion_state'] = 'Completion state as seen by the plugin.';

$string['privacy:metadata:local_esmed_assessment_index'] = 'Categorised assessment attempts (pedagogical quizzes, mock exams, summative assessments).';
$string['privacy:metadata:local_esmed_assessment_index:userid'] = 'The Moodle user who made the attempt.';
$string['privacy:metadata:local_esmed_assessment_index:courseid'] = 'The course the assessment belongs to.';
$string['privacy:metadata:local_esmed_assessment_index:cmid'] = 'The course module identifier of the assessment.';
$string['privacy:metadata:local_esmed_assessment_index:assessment_type'] = 'Regulatory classification of the assessment.';
$string['privacy:metadata:local_esmed_assessment_index:score'] = 'Raw score obtained.';
$string['privacy:metadata:local_esmed_assessment_index:grade_percent'] = 'Score normalised to a 0-100 scale.';
$string['privacy:metadata:local_esmed_assessment_index:attempt_date'] = 'Date of the attempt.';

$string['privacy:metadata:local_esmed_archive_index'] = 'Index of sealed documents (certificates, funder statements) attached to a user.';
$string['privacy:metadata:local_esmed_archive_index:userid'] = 'The Moodle user the sealed document is about.';
$string['privacy:metadata:local_esmed_archive_index:courseid'] = 'The course the sealed document relates to.';
$string['privacy:metadata:local_esmed_archive_index:archive_type'] = 'Type of sealed document.';
$string['privacy:metadata:local_esmed_archive_index:file_path'] = 'Storage path of the sealed file.';
$string['privacy:metadata:local_esmed_archive_index:sha256_hash'] = 'SHA-256 fingerprint of the sealed file.';
$string['privacy:metadata:local_esmed_archive_index:verification_token'] = 'Public token used for third-party verification.';
$string['privacy:metadata:local_esmed_archive_index:timestamp_sealed'] = 'Timestamp at which the document was sealed.';
$string['privacy:metadata:local_esmed_archive_index:retention_until'] = 'Timestamp after which the document becomes eligible for purge.';

$string['privacy:metadata:local_esmed_alerts'] = 'History of learner dropout and inactivity alerts.';
$string['privacy:metadata:local_esmed_alerts:userid'] = 'The Moodle user the alert concerns.';
$string['privacy:metadata:local_esmed_alerts:courseid'] = 'The course the alert relates to, if any.';
$string['privacy:metadata:local_esmed_alerts:alert_type'] = 'Type of alert raised.';
$string['privacy:metadata:local_esmed_alerts:alert_data_json'] = 'Structured payload attached to the alert.';
$string['privacy:metadata:local_esmed_alerts:triggered_at'] = 'When the alert was generated.';

$string['privacy:subcontext:sessions'] = 'Compliance sessions';
$string['privacy:subcontext:activity'] = 'Compliance activity log';
$string['privacy:subcontext:assessments'] = 'Compliance assessments';
$string['privacy:subcontext:archives'] = 'Compliance archives';
$string['privacy:subcontext:alerts'] = 'Compliance alerts';

$string['privacy:legalretention'] = 'Attendance evidence is kept for the legal retention period required by article L.6353-1 of the French Labour Code and the Qualiopi framework. Requests for erasure covering data still within this window are partially honoured: only data outside the legal perimeter is deleted, and direct identifiers such as IP address and user-agent are redacted.';
