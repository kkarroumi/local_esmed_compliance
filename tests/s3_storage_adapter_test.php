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
 * S3 storage adapter tests.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_esmed_compliance;

use local_esmed_compliance\archive\s3_storage_adapter;
use RuntimeException;

/**
 * Tests for the  component.
 *
 * @covers \local_esmed_compliance\archive\s3_storage_adapter
 */
final class s3_storage_adapter_test extends \advanced_testcase {
    /**
     * store() PUTs the bytes to the expected URL and emits a SigV4 Authorization header.
     */
    public function test_store_signs_and_dispatches_put(): void {
        $calls = [];
        $client = function (string $method, string $url, array $headers, string $body) use (&$calls) {
            $calls[] = compact('method', 'url', 'headers', 'body');
            // First call is the GET probe for idempotency — answer 404.
            if ($method === 'GET') {
                return ['status' => 404, 'body' => ''];
            }
            return ['status' => 200, 'body' => ''];
        };

        $adapter = new s3_storage_adapter(
            'https://s3.eu-west-3.amazonaws.com',
            'eu-west-3',
            'mybucket',
            'AKIAIOSFODNN7EXAMPLE',
            'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY',
            $client,
            1700000000
        );

        $key = $adapter->store('hello world', '2024/01/attestation.pdf');

        $this->assertSame('2024/01/attestation.pdf', $key);
        $this->assertCount(2, $calls);
        [$probe, $put] = $calls;
        $this->assertSame('GET', $probe['method']);
        $this->assertSame('PUT', $put['method']);
        $this->assertSame(
            'https://s3.eu-west-3.amazonaws.com/mybucket/2024/01/attestation.pdf',
            $put['url']
        );
        $this->assertSame('hello world', $put['body']);
        $this->assertSame(
            hash('sha256', 'hello world'),
            $put['headers']['x-amz-content-sha256']
        );
        $this->assertMatchesRegularExpression(
            '#^AWS4-HMAC-SHA256 Credential=AKIAIOSFODNN7EXAMPLE/\d{8}/eu-west-3/s3/aws4_request,'
            . ' SignedHeaders=content-type;host;x-amz-content-sha256;x-amz-date,'
            . ' Signature=[0-9a-f]{64}$#',
            $put['headers']['Authorization']
        );
    }

    /**
     * fetch() returns the body bytes verbatim on 200.
     */
    public function test_fetch_returns_body_on_200(): void {
        $client = function (string $method, string $url, array $headers, string $body) {
            return ['status' => 200, 'body' => 'payload-bytes'];
        };
        $adapter = $this->make_adapter($client);

        $this->assertSame('payload-bytes', $adapter->fetch('doc/a.pdf'));
    }

    /**
     * fetch() returns null on 404.
     */
    public function test_fetch_returns_null_on_404(): void {
        $client = function (string $method, string $url, array $headers, string $body) {
            return ['status' => 404, 'body' => ''];
        };
        $adapter = $this->make_adapter($client);

        $this->assertNull($adapter->fetch('doc/missing.pdf'));
    }

    /**
     * Storing twice with identical bytes is a no-op (no second PUT).
     */
    public function test_store_is_idempotent_for_identical_bytes(): void {
        $bytes = 'twice-the-same';
        $calls = [];
        $client = function (string $method, string $url, array $headers, string $body) use ($bytes, &$calls) {
            $calls[] = $method;
            if ($method === 'GET') {
                return ['status' => 200, 'body' => $bytes];
            }
            return ['status' => 200, 'body' => ''];
        };

        $adapter = $this->make_adapter($client);
        $key = $adapter->store($bytes, 'doc/x.pdf');

        $this->assertSame('doc/x.pdf', $key);
        $this->assertSame(['GET'], $calls, 'A second PUT must not be issued when bytes already match');
    }

    /**
     * Storing divergent bytes at an existing key raises rather than overwriting.
     */
    public function test_store_refuses_to_overwrite_divergent_bytes(): void {
        $client = function (string $method, string $url, array $headers, string $body) {
            if ($method === 'GET') {
                return ['status' => 200, 'body' => 'original'];
            }
            return ['status' => 200, 'body' => ''];
        };
        $adapter = $this->make_adapter($client);

        $this->expectException(RuntimeException::class);
        $adapter->store('different', 'doc/x.pdf');
    }

    /**
     * Non-2xx/404 on GET raises so verification does not silently report MISSING for 500s.
     */
    public function test_fetch_raises_on_unexpected_status(): void {
        $client = function (string $method, string $url, array $headers, string $body) {
            return ['status' => 503, 'body' => 'Service Unavailable'];
        };
        $adapter = $this->make_adapter($client);

        $this->expectException(RuntimeException::class);
        $adapter->fetch('doc/x.pdf');
    }

    /**
     * Empty endpoint configuration falls back to the AWS regional default.
     */
    public function test_empty_endpoint_resolves_to_aws_default(): void {
        $captured = null;
        $client = function (string $method, string $url, array $headers, string $body) use (&$captured) {
            $captured = $url;
            return ['status' => 404, 'body' => ''];
        };
        $adapter = new s3_storage_adapter('', 'eu-west-3', 'b', 'AK', 'SK', $client, 1700000000);
        $adapter->fetch('k.bin');

        $this->assertSame('https://s3.eu-west-3.amazonaws.com/b/k.bin', $captured);
    }

    /**
     * Constructor rejects empty required credentials.
     */
    public function test_constructor_rejects_empty_required_fields(): void {
        $this->expectException(RuntimeException::class);
        new s3_storage_adapter('https://s3.amazonaws.com', 'eu-west-3', '', 'AK', 'SK');
    }

    /**
     * Build an adapter wired to the given HTTP client callable.
     *
     * @param callable $client
     * @return s3_storage_adapter
     */
    private function make_adapter(callable $client): s3_storage_adapter {
        return new s3_storage_adapter(
            'https://s3.eu-west-3.amazonaws.com',
            'eu-west-3',
            'mybucket',
            'AKIAIOSFODNN7EXAMPLE',
            'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY',
            $client,
            1700000000
        );
    }
}
