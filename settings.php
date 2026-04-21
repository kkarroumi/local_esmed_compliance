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
 * Admin settings for local_esmed_compliance.
 *
 * Three dedicated pages under a single category:
 *   - General  : tracking cadence, aggregation caps, retention.
 *   - Branding : training provider identity printed on attestations.
 *   - Archive  : storage adapter selection and S3 credentials.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $category = new admin_category(
        'local_esmed_compliance',
        get_string('pluginname', 'local_esmed_compliance')
    );
    $ADMIN->add('localplugins', $category);

    // General.
    $general = new admin_settingpage(
        'local_esmed_compliance_general',
        get_string('settings_general', 'local_esmed_compliance')
    );

    $general->add(new admin_setting_configtext(
        'local_esmed_compliance/heartbeat_interval',
        get_string('setting_heartbeat_interval', 'local_esmed_compliance'),
        get_string('setting_heartbeat_interval_desc', 'local_esmed_compliance'),
        30,
        PARAM_INT
    ));

    $general->add(new admin_setting_configtext(
        'local_esmed_compliance/session_timeout_minutes',
        get_string('setting_session_timeout_minutes', 'local_esmed_compliance'),
        get_string('setting_session_timeout_minutes_desc', 'local_esmed_compliance'),
        10,
        PARAM_INT
    ));

    $general->add(new admin_setting_configtext(
        'local_esmed_compliance/activity_delta_cap_minutes',
        get_string('setting_activity_delta_cap_minutes', 'local_esmed_compliance'),
        get_string('setting_activity_delta_cap_minutes_desc', 'local_esmed_compliance'),
        15,
        PARAM_INT
    ));

    $general->add(new admin_setting_configtext(
        'local_esmed_compliance/retention_years',
        get_string('setting_retention_years', 'local_esmed_compliance'),
        get_string('setting_retention_years_desc', 'local_esmed_compliance'),
        5,
        PARAM_INT
    ));

    $general->add(new admin_setting_configtext(
        'local_esmed_compliance/dashboard_refresh_seconds',
        get_string('setting_dashboard_refresh_seconds', 'local_esmed_compliance'),
        get_string('setting_dashboard_refresh_seconds_desc', 'local_esmed_compliance'),
        30,
        PARAM_INT
    ));

    $general->add(new admin_setting_configtext(
        'local_esmed_compliance/integrity_batch_size',
        get_string('setting_integrity_batch_size', 'local_esmed_compliance'),
        get_string('setting_integrity_batch_size_desc', 'local_esmed_compliance'),
        50,
        PARAM_INT
    ));

    $general->add(new admin_setting_configtext(
        'local_esmed_compliance/inactivity_threshold_days',
        get_string('setting_inactivity_threshold_days', 'local_esmed_compliance'),
        get_string('setting_inactivity_threshold_days_desc', 'local_esmed_compliance'),
        7,
        PARAM_INT
    ));

    $funderoptions = [
        ''       => get_string('funder_none', 'local_esmed_compliance'),
        'CPF'    => get_string('funder_cpf', 'local_esmed_compliance'),
        'FT'     => get_string('funder_ft', 'local_esmed_compliance'),
        'OPCO'   => get_string('funder_opco', 'local_esmed_compliance'),
        'REGION' => get_string('funder_region', 'local_esmed_compliance'),
        'AUTRE'  => get_string('funder_autre', 'local_esmed_compliance'),
    ];
    $general->add(new admin_setting_configselect(
        'local_esmed_compliance/funder_default',
        get_string('setting_funder_default', 'local_esmed_compliance'),
        get_string('setting_funder_default_desc', 'local_esmed_compliance'),
        '',
        $funderoptions
    ));

    $category->add('local_esmed_compliance', $general);

    // Branding.
    $branding = new admin_settingpage(
        'local_esmed_compliance_branding',
        get_string('settings_branding', 'local_esmed_compliance')
    );

    $branding->add(new admin_setting_configstoredfile(
        'local_esmed_compliance/org_logo',
        get_string('setting_org_logo', 'local_esmed_compliance'),
        get_string('setting_org_logo_desc', 'local_esmed_compliance'),
        'org_logo',
        0,
        ['maxfiles' => 1, 'accepted_types' => ['.png', '.jpg', '.jpeg', '.svg']]
    ));

    $branding->add(new admin_setting_configtext(
        'local_esmed_compliance/org_legal_name',
        get_string('setting_org_legal_name', 'local_esmed_compliance'),
        get_string('setting_org_legal_name_desc', 'local_esmed_compliance'),
        'ESMED',
        PARAM_TEXT
    ));

    $branding->add(new admin_setting_configtext(
        'local_esmed_compliance/org_siret',
        get_string('setting_org_siret', 'local_esmed_compliance'),
        get_string('setting_org_siret_desc', 'local_esmed_compliance'),
        '',
        PARAM_ALPHANUM
    ));

    $branding->add(new admin_setting_configtext(
        'local_esmed_compliance/org_nda',
        get_string('setting_org_nda', 'local_esmed_compliance'),
        get_string('setting_org_nda_desc', 'local_esmed_compliance'),
        '',
        PARAM_TEXT
    ));

    $branding->add(new admin_setting_configtextarea(
        'local_esmed_compliance/org_address',
        get_string('setting_org_address', 'local_esmed_compliance'),
        get_string('setting_org_address_desc', 'local_esmed_compliance'),
        '',
        PARAM_TEXT,
        60,
        4
    ));

    $branding->add(new admin_setting_configtext(
        'local_esmed_compliance/org_signatory_name',
        get_string('setting_org_signatory_name', 'local_esmed_compliance'),
        get_string('setting_org_signatory_name_desc', 'local_esmed_compliance'),
        '',
        PARAM_TEXT
    ));

    $branding->add(new admin_setting_configtext(
        'local_esmed_compliance/org_signatory_role',
        get_string('setting_org_signatory_role', 'local_esmed_compliance'),
        get_string('setting_org_signatory_role_desc', 'local_esmed_compliance'),
        '',
        PARAM_TEXT
    ));

    $category->add('local_esmed_compliance', $branding);

    // Archive.
    $archive = new admin_settingpage(
        'local_esmed_compliance_archive',
        get_string('settings_archive', 'local_esmed_compliance')
    );

    $adapteroptions = [
        'local' => get_string('adapter_local', 'local_esmed_compliance'),
        's3'    => get_string('adapter_s3', 'local_esmed_compliance'),
    ];
    $archive->add(new admin_setting_configselect(
        'local_esmed_compliance/archive_storage_adapter',
        get_string('setting_archive_storage_adapter', 'local_esmed_compliance'),
        get_string('setting_archive_storage_adapter_desc', 'local_esmed_compliance'),
        'local',
        $adapteroptions
    ));

    $archive->add(new admin_setting_configdirectory(
        'local_esmed_compliance/archive_local_path',
        get_string('setting_archive_local_path', 'local_esmed_compliance'),
        get_string('setting_archive_local_path_desc', 'local_esmed_compliance'),
        ''
    ));

    $archive->add(new admin_setting_heading(
        'local_esmed_compliance/s3_heading',
        get_string('settings_archive_s3_heading', 'local_esmed_compliance'),
        get_string('settings_archive_s3_heading_desc', 'local_esmed_compliance')
    ));

    $archive->add(new admin_setting_configtext(
        'local_esmed_compliance/s3_endpoint',
        get_string('setting_s3_endpoint', 'local_esmed_compliance'),
        get_string('setting_s3_endpoint_desc', 'local_esmed_compliance'),
        '',
        PARAM_URL
    ));

    $archive->add(new admin_setting_configtext(
        'local_esmed_compliance/s3_region',
        get_string('setting_s3_region', 'local_esmed_compliance'),
        get_string('setting_s3_region_desc', 'local_esmed_compliance'),
        'eu-west-3',
        PARAM_TEXT
    ));

    $archive->add(new admin_setting_configtext(
        'local_esmed_compliance/s3_bucket',
        get_string('setting_s3_bucket', 'local_esmed_compliance'),
        get_string('setting_s3_bucket_desc', 'local_esmed_compliance'),
        '',
        PARAM_TEXT
    ));

    $archive->add(new admin_setting_configtext(
        'local_esmed_compliance/s3_access_key',
        get_string('setting_s3_access_key', 'local_esmed_compliance'),
        get_string('setting_s3_access_key_desc', 'local_esmed_compliance'),
        '',
        PARAM_TEXT
    ));

    $archive->add(new admin_setting_configpasswordunmask(
        'local_esmed_compliance/s3_secret_key',
        get_string('setting_s3_secret_key', 'local_esmed_compliance'),
        get_string('setting_s3_secret_key_desc', 'local_esmed_compliance'),
        ''
    ));

    $category->add('local_esmed_compliance', $archive);

    // External page: compliance dashboard.
    $category->add('local_esmed_compliance', new admin_externalpage(
        'local_esmed_compliance_dashboard',
        get_string('dashboard', 'local_esmed_compliance'),
        new moodle_url('/local/esmed_compliance/dashboard.php'),
        'local/esmed_compliance:viewdashboard'
    ));

    // External page: attestation operator screen.
    $category->add('local_esmed_compliance', new admin_externalpage(
        'local_esmed_compliance_attestations',
        get_string('attestations_page_title', 'local_esmed_compliance'),
        new moodle_url('/local/esmed_compliance/attestations.php'),
        'local/esmed_compliance:viewdashboard'
    ));

    // External page: funder links management.
    $category->add('local_esmed_compliance', new admin_externalpage(
        'local_esmed_compliance_funders',
        get_string('funders_page_title', 'local_esmed_compliance'),
        new moodle_url('/local/esmed_compliance/funders.php'),
        'local/esmed_compliance:manageconfig'
    ));
}
