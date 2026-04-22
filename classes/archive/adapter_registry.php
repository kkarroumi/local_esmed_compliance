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
 * Build the adapter map from site configuration.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_esmed_compliance\archive;

/**
 * Resolves the installed storage adapters.
 *
 * The local adapter is always present, so archives sealed before S3
 * was enabled remain verifiable. The S3 adapter is added on top when
 * the admin has supplied enough credentials to construct a client.
 * Nothing here decides which adapter gets fresh writes — that choice
 * belongs to the sealing path and is driven by `archive_storage_adapter`.
 */
class adapter_registry {
    /**
     * Build the `[name => adapter]` map from current site config.
     *
     * @return array
     */
    public static function from_config(): array {
        $adapters = ['local' => new local_storage_adapter()];

        $region    = trim((string) get_config('local_esmed_compliance', 's3_region'));
        $bucket    = trim((string) get_config('local_esmed_compliance', 's3_bucket'));
        $accesskey = trim((string) get_config('local_esmed_compliance', 's3_access_key'));
        $secretkey = (string) get_config('local_esmed_compliance', 's3_secret_key');
        $endpoint  = trim((string) get_config('local_esmed_compliance', 's3_endpoint'));

        if ($region !== '' && $bucket !== '' && $accesskey !== '' && $secretkey !== '') {
            $adapters['s3'] = new s3_storage_adapter(
                $endpoint,
                $region,
                $bucket,
                $accesskey,
                $secretkey
            );
        }

        return $adapters;
    }

    /**
     * Return the adapter selected for new writes by site config, or null when misconfigured.
     *
     * Callers that seal fresh documents (attestation_service, bordereau_service)
     * use this to pick a backend without caring which adapters also happen to
     * be registered for read-back.
     *
     * @return storage_adapter|null
     */
    public static function active(): ?storage_adapter {
        $selected = (string) get_config('local_esmed_compliance', 'archive_storage_adapter') ?: 'local';
        $adapters = self::from_config();
        return $adapters[$selected] ?? null;
    }
}
