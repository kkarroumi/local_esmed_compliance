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
 * Adapter registry tests.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_esmed_compliance;

use local_esmed_compliance\archive\adapter_registry;
use local_esmed_compliance\archive\local_storage_adapter;
use local_esmed_compliance\archive\s3_storage_adapter;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \local_esmed_compliance\archive\adapter_registry
 */
final class adapter_registry_test extends \advanced_testcase {

    /**
     * With no S3 credentials set, only the local adapter is registered.
     */
    public function test_from_config_returns_local_only_by_default(): void {
        $this->resetAfterTest();

        $adapters = adapter_registry::from_config();

        $this->assertArrayHasKey('local', $adapters);
        $this->assertInstanceOf(local_storage_adapter::class, $adapters['local']);
        $this->assertArrayNotHasKey('s3', $adapters);
    }

    /**
     * With all S3 credentials provided, both adapters are registered side by side
     * so historical `local` rows stay verifiable after switching fresh writes to S3.
     */
    public function test_from_config_registers_s3_when_credentials_complete(): void {
        $this->resetAfterTest();
        set_config('s3_region', 'eu-west-3', 'local_esmed_compliance');
        set_config('s3_bucket', 'my-compliance-bucket', 'local_esmed_compliance');
        set_config('s3_access_key', 'AKIAIOSFODNN7EXAMPLE', 'local_esmed_compliance');
        set_config('s3_secret_key', 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY', 'local_esmed_compliance');

        $adapters = adapter_registry::from_config();

        $this->assertArrayHasKey('local', $adapters);
        $this->assertArrayHasKey('s3', $adapters);
        $this->assertInstanceOf(s3_storage_adapter::class, $adapters['s3']);
    }

    /**
     * A missing secret key disables S3 registration even when the rest of the config is set.
     */
    public function test_from_config_skips_s3_when_credentials_incomplete(): void {
        $this->resetAfterTest();
        set_config('s3_region', 'eu-west-3', 'local_esmed_compliance');
        set_config('s3_bucket', 'my-compliance-bucket', 'local_esmed_compliance');
        set_config('s3_access_key', 'AKIAIOSFODNN7EXAMPLE', 'local_esmed_compliance');
        // No secret key.

        $adapters = adapter_registry::from_config();

        $this->assertArrayNotHasKey('s3', $adapters);
    }

    /**
     * active() resolves to the adapter named by `archive_storage_adapter`.
     */
    public function test_active_resolves_selected_adapter(): void {
        $this->resetAfterTest();
        set_config('s3_region', 'eu-west-3', 'local_esmed_compliance');
        set_config('s3_bucket', 'b', 'local_esmed_compliance');
        set_config('s3_access_key', 'AK', 'local_esmed_compliance');
        set_config('s3_secret_key', 'SK', 'local_esmed_compliance');
        set_config('archive_storage_adapter', 's3', 'local_esmed_compliance');

        $active = adapter_registry::active();

        $this->assertInstanceOf(s3_storage_adapter::class, $active);
    }

    /**
     * active() returns null when the selected adapter is not registered (misconfigured site).
     */
    public function test_active_returns_null_for_unknown_selection(): void {
        $this->resetAfterTest();
        set_config('archive_storage_adapter', 's3', 'local_esmed_compliance');
        // No S3 credentials, so registry will not expose the s3 adapter.

        $this->assertNull(adapter_registry::active());
    }

    /**
     * Unset selection defaults to the local adapter.
     */
    public function test_active_defaults_to_local(): void {
        $this->resetAfterTest();
        $this->assertInstanceOf(local_storage_adapter::class, adapter_registry::active());
    }
}
