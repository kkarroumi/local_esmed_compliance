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
$string['task_verify_archive_integrity'] = 'Verify sealed archive integrity (re-hash batch)';

// Dashboard.
$string['dashboard_heading'] = 'Compliance dashboard';
$string['dashboard_generated_at'] = 'Last refreshed:';
$string['dashboard_sessions'] = 'Sessions';
$string['dashboard_sessions_open'] = 'Open now';
$string['dashboard_sessions_closed_24h'] = 'Closed in the last 24h';
$string['dashboard_sessions_hours_today'] = 'Hours recorded today';
$string['dashboard_archives'] = 'Sealed archives';
$string['dashboard_archives_total'] = 'Total';
$string['dashboard_archives_attestations'] = 'Attendance certificates';
$string['dashboard_archives_bordereaux'] = 'Funder statements';
$string['dashboard_alerts'] = 'Alerts';
$string['dashboard_alerts_unacknowledged'] = 'Unacknowledged';
$string['dashboard_alerts_last_7_days'] = 'Last 7 days';
$string['dashboard_integrity'] = 'Archive integrity';
$string['dashboard_integrity_valid'] = 'Valid';
$string['dashboard_integrity_tampered'] = 'Tampered';
$string['dashboard_integrity_missing'] = 'Missing';

// Public attestation verification endpoint.
$string['verify_title'] = 'Compliance document verification';
$string['verify_heading'] = 'Verify a sealed compliance document';
$string['verify_missing_token'] = 'No verification token was supplied. Scan the QR code on the document or paste the token in the URL.';
$string['verify_unknown'] = 'This verification token does not match any sealed document. It may have been mistyped or revoked.';
$string['verify_missing'] = 'The sealed document is referenced in our archive but its file is no longer retrievable. Please contact the training organisation.';
$string['verify_tampered'] = 'The sealed document was retrieved but its current hash does not match the hash sealed at issuance. Integrity check failed.';
$string['verify_valid'] = 'This document is valid: its current hash matches the hash sealed at issuance.';
$string['verify_archive_type'] = 'Document type';
$string['verify_sealed_at'] = 'Sealed at';
$string['verify_sealed_hash'] = 'Sealed SHA-256';
$string['verify_computed_hash'] = 'Recomputed SHA-256';

// Archive errors.
$string['tokengenfailed'] = 'Unable to generate a unique verification token after several attempts.';

// Funder bordereau.
$string['funder_link_notfound'] = 'The requested funder link no longer exists.';
$string['bordereau_heading'] = 'Funder statement';
$string['bordereau_subtitle'] = 'Summary of hours completed per learner';
$string['bordereau_funder'] = 'Funder';
$string['bordereau_dossier'] = 'Dossier';
$string['bordereau_opco'] = 'OPCO';
$string['bordereau_action_intitule'] = 'Action title';
$string['bordereau_hours_planned'] = 'Planned hours';
$string['bordereau_period'] = 'Period';
$string['bordereau_course_code'] = 'Course code';
$string['bordereau_learner_count'] = 'Number of learners';
$string['bordereau_total_duration'] = 'Total realised duration';
$string['bordereau_col_rank'] = '#';
$string['bordereau_col_lastname'] = 'Last name';
$string['bordereau_col_firstname'] = 'First name';
$string['bordereau_col_email'] = 'Email';
$string['bordereau_col_idnumber'] = 'ID number';
$string['bordereau_col_sessions'] = 'Sessions';
$string['bordereau_col_duration'] = 'Duration';
$string['bordereau_total_row'] = 'Grand total';
$string['bordereau_empty'] = 'No enrolled learners';
$string['bordereau_generated_at'] = 'Generated on';

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
$string['setting_dashboard_refresh_seconds'] = 'Dashboard refresh interval (seconds)';
$string['setting_dashboard_refresh_seconds_desc'] = 'How often the compliance dashboard polls the metrics endpoint while the tab is visible.';
$string['setting_integrity_batch_size'] = 'Integrity check batch size';
$string['setting_integrity_batch_size_desc'] = 'Maximum number of sealed archives re-hashed per run of the integrity task.';
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

$string['webservice_verify_token'] = 'Verify a sealed document by its public token.';
$string['webservice_get_dashboard_metrics'] = 'Return point-in-time compliance dashboard counters.';
$string['webservice_get_learner_summary'] = 'Return the compliance summary for one learner in one course.';
$string['webservice_acknowledge_alert'] = 'Acknowledge an open compliance alert.';
$string['webservice_service_name'] = 'ESMED compliance service';

$string['task_detect_inactivity'] = 'Detect inactive learners and raise compliance alerts';
$string['setting_inactivity_threshold_days'] = 'Inactivity threshold (days)';
$string['setting_inactivity_threshold_days_desc'] = 'Number of days without a certifiable session before an inactivity alert is raised against an enrolled learner.';
$string['alertnotfound'] = 'Alert not found.';
$string['esmed_compliance:managealerts'] = 'Acknowledge compliance alerts';

$string['messageprovider:alert_inactivity'] = 'Learner inactivity alert';
$string['message_alert_inactivity_subject'] = 'Compliance alert: learner inactivity detected';
$string['message_alert_inactivity_body'] = 'An inactivity alert (#{$a->alertid}) was raised for user {$a->userid} on course {$a->courseid} at {$a->triggered_at}. Review it in the compliance dashboard.';

$string['dashboard_open_alerts'] = 'Open alerts';
$string['dashboard_open_alerts_learner'] = 'Learner';
$string['dashboard_open_alerts_course'] = 'Course';
$string['dashboard_open_alerts_type'] = 'Type';
$string['dashboard_open_alerts_triggered_at'] = 'Triggered at';
$string['dashboard_open_alerts_empty'] = 'No open alerts — nothing to acknowledge.';
$string['dashboard_open_alerts_action'] = 'Action';
$string['dashboard_open_alerts_ack'] = 'Acknowledge';

// Attestation operator screen.
$string['attestations_page_title'] = 'Attendance certificates';
$string['attestations_picker_heading'] = 'Pick a course';
$string['attestations_picker_intro'] = 'Select a course to review enrolled learners and generate an attendance certificate.';
$string['attestations_picker_open'] = 'Open';
$string['attestations_picker_empty'] = 'No course available — you need the "Generate attendance certificates" capability on at least one course.';
$string['attestations_col_course'] = 'Course';
$string['attestations_col_shortname'] = 'Short name';
$string['attestations_col_actions'] = 'Actions';
$string['attestations_col_learner'] = 'Learner';
$string['attestations_col_email'] = 'Email';
$string['attestations_col_idnumber'] = 'ID number';
$string['attestations_col_hours'] = 'Hours';
$string['attestations_col_count'] = 'Sealed';
$string['attestations_col_last'] = 'Last generated';
$string['attestations_action_generate'] = 'Generate';
$string['attestations_action_download'] = 'Download';
$string['attestations_back_to_courses'] = 'Back to courses';
$string['attestations_empty'] = 'No enrolled learners — nothing to attest yet.';
$string['attestations_generated_ok'] = 'Attendance certificate sealed (token {$a->token}…).';
$string['attestations_generated_error'] = 'Could not seal the attendance certificate: {$a}';
$string['attestations_archive_not_found'] = 'Archive not found.';
$string['attestations_storage_unavailable'] = 'Storage adapter unavailable for this archive.';
$string['attestations_archive_missing_bytes'] = 'The sealed file is no longer retrievable from storage.';
$string['attestations_method_not_allowed'] = 'This action must be submitted through the form.';
$string['attestations_user_not_enrolled'] = 'The selected user is not actively enrolled in this course.';

// Funder links operator screen.
$string['funders_page_title'] = 'Funder links';
$string['funders_link_new'] = 'Link a new course';
$string['funders_back_to_list'] = 'Back to funder links';
$string['funders_col_course'] = 'Course';
$string['funders_col_type'] = 'Funder';
$string['funders_col_dossier'] = 'Dossier';
$string['funders_col_hours'] = 'Planned hours';
$string['funders_col_period'] = 'Period';
$string['funders_col_actions'] = 'Actions';
$string['funders_action_edit'] = 'Edit';
$string['funders_action_delete'] = 'Remove';
$string['funders_action_save'] = 'Save link';
$string['funders_picker_link'] = 'Link';
$string['funders_picker_all_linked'] = 'Every course already carries a funder link.';
$string['funders_empty'] = 'No course is currently linked to a funder.';
$string['funders_create_heading'] = 'Link a course to a funder';
$string['funders_edit_heading'] = 'Edit funder link';
$string['funders_field_course'] = 'Course';
$string['funders_field_type'] = 'Funder';
$string['funders_field_dossier'] = 'Dossier number';
$string['funders_field_action'] = 'Official action title';
$string['funders_field_opco_name'] = 'OPCO name';
$string['funders_field_hours'] = 'Planned hours';
$string['funders_field_start_date'] = 'Start date';
$string['funders_field_end_date'] = 'End date';
$string['funders_saved_ok'] = 'Funder link saved.';
$string['funders_deleted_ok'] = 'Funder link removed.';
$string['funders_confirm_delete'] = 'Remove the funder link for this course?';
$string['funders_method_not_allowed'] = 'This action must be submitted through the form.';
$string['funders_error_end_before_start'] = 'End date cannot be earlier than start date.';
$string['funders_error_negative_hours'] = 'Planned hours must be zero or positive.';
$string['funders_error_bad_type'] = 'Unknown funder type.';
