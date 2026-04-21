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
 * AWS S3 / S3-compatible archive storage adapter.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_esmed_compliance\archive;

use RuntimeException;

/**
 * Writes sealed documents to an S3 (or S3-compatible) bucket using
 * path-style addressing and AWS Signature Version 4.
 *
 * Path-style is chosen so the adapter also talks to OVH, Scaleway,
 * MinIO, Ceph, etc. — Amazon still honours it for existing buckets.
 *
 * The adapter matches the semantics of `local_storage_adapter`:
 *   - `store()` is idempotent when the key already holds identical bytes,
 *     and raises when asked to overwrite divergent bytes.
 *   - `fetch()` returns null when the object is missing (HTTP 404).
 *
 * HTTP is injected as a callable `($method, $url, $headers, $body): array`
 * returning `['status' => int, 'body' => string]`, which lets tests
 * assert signatures and shape responses without touching the network.
 */
class s3_storage_adapter implements storage_adapter {
    /** @var string S3 signing service id. */
    private const SERVICE = 's3';

    /** @var string Signing algorithm label. */
    private const ALGORITHM = 'AWS4-HMAC-SHA256';

    /** @var string */
    private string $endpoint;
    /** @var string */
    private string $region;
    /** @var string */
    private string $bucket;
    /** @var string */
    private string $accesskey;
    /** @var string */
    private string $secretkey;
    /** @var callable */
    private $httpclient;
    /** @var int|null Fixed clock override for tests. */
    private ?int $now;

    /**
     * Constructor.
     *
     * @param string        $endpoint  Base URL (e.g. https://s3.eu-west-3.amazonaws.com).
     *                                 Empty string resolves to `https://s3.{region}.amazonaws.com`.
     * @param string        $region    AWS region identifier (e.g. `eu-west-3`).
     * @param string        $bucket    Bucket name.
     * @param string        $accesskey IAM access key id.
     * @param string        $secretkey IAM secret access key.
     * @param callable|null $httpclient Injectable HTTP callable; defaults to curl.
     * @param int|null      $now        Fixed signing timestamp; null uses time().
     */
    public function __construct(
        string $endpoint,
        string $region,
        string $bucket,
        string $accesskey,
        string $secretkey,
        ?callable $httpclient = null,
        ?int $now = null
    ) {
        if ($region === '' || $bucket === '' || $accesskey === '' || $secretkey === '') {
            throw new RuntimeException('s3_storage_adapter: region, bucket, access key and secret key are required');
        }
        $this->endpoint   = $endpoint !== '' ? rtrim($endpoint, '/') : "https://s3.{$region}.amazonaws.com";
        $this->region     = $region;
        $this->bucket     = $bucket;
        $this->accesskey  = $accesskey;
        $this->secretkey  = $secretkey;
        $this->httpclient = $httpclient ?? [$this, 'default_http_client'];
        $this->now        = $now;
    }

    /**
     * Inherits from parent.
     */
    public function name(): string {
        return 's3';
    }

    /**
     * Inherits from parent.
     */
    public function store(string $bytes, string $relativename): string {
        $key = self::safe_key($relativename);

        $existing = $this->get_object($key);
        if ($existing !== null) {
            if ($existing === $bytes) {
                return $key;
            }
            throw new RuntimeException('s3_storage_adapter: refusing to overwrite ' . $key);
        }

        $this->put_object($key, $bytes);
        return $key;
    }

    /**
     * Inherits from parent.
     */
    public function fetch(string $relativename): ?string {
        return $this->get_object(self::safe_key($relativename));
    }

    /**
     * GET an object; returns null on 404, raises on other non-2xx responses.
     *
     * @param string $key
     * @return string|null
     */
    private function get_object(string $key): ?string {
        $response = $this->request('GET', $key, [], '');
        if ($response['status'] === 404) {
            return null;
        }
        if ($response['status'] < 200 || $response['status'] >= 300) {
            throw new RuntimeException("s3_storage_adapter: GET {$key} failed with HTTP {$response['status']}");
        }
        return (string) $response['body'];
    }

    /**
     * PUT an object; raises on any non-2xx response.
     *
     * @param string $key
     * @param string $bytes
     */
    private function put_object(string $key, string $bytes): void {
        $response = $this->request('PUT', $key, ['Content-Type' => 'application/octet-stream'], $bytes);
        if ($response['status'] < 200 || $response['status'] >= 300) {
            throw new RuntimeException("s3_storage_adapter: PUT {$key} failed with HTTP {$response['status']}");
        }
    }

    /**
     * Sign and dispatch a request. Returns `['status' => int, 'body' => string]`.
     *
     * @param string               $method
     * @param string               $key
     * @param array<string,string> $extraheaders
     * @param string               $body
     * @return array{status:int, body:string}
     */
    private function request(string $method, string $key, array $extraheaders, string $body): array {
        $now      = $this->now ?? time();
        $datetime = gmdate('Ymd\THis\Z', $now);
        $date     = substr($datetime, 0, 8);

        $url  = $this->endpoint . '/' . $this->bucket . '/' . self::encode_key($key);
        $host = (string) parse_url($this->endpoint, PHP_URL_HOST);
        $port = parse_url($this->endpoint, PHP_URL_PORT);
        if ($port !== null && !in_array((int) $port, [80, 443], true)) {
            $host .= ':' . (int) $port;
        }
        $path = '/' . $this->bucket . '/' . self::encode_key($key);

        $payloadhash = hash('sha256', $body);

        $headers = array_merge($extraheaders, [
            'Host'                 => $host,
            'x-amz-content-sha256' => $payloadhash,
            'x-amz-date'           => $datetime,
        ]);

        $authorization = $this->build_authorization($method, $path, $headers, $payloadhash, $datetime, $date);
        $headers['Authorization'] = $authorization;

        return ($this->httpclient)($method, $url, $headers, $body);
    }

    /**
     * Compose the SigV4 `Authorization` header value.
     *
     * @param string               $method
     * @param string               $path
     * @param array<string,string> $headers
     * @param string               $payloadhash
     * @param string               $datetime
     * @param string               $date
     * @return string
     */
    private function build_authorization(
        string $method,
        string $path,
        array $headers,
        string $payloadhash,
        string $datetime,
        string $date
    ): string {
        // Canonical headers: lowercase-name, trimmed-value, sorted by name.
        $canon = [];
        foreach ($headers as $name => $value) {
            $canon[strtolower($name)] = trim($value);
        }
        ksort($canon);
        $canonicalheaders = '';
        $signednames = [];
        foreach ($canon as $name => $value) {
            $canonicalheaders .= $name . ':' . $value . "\n";
            $signednames[] = $name;
        }
        $signedheaders = implode(';', $signednames);

        $canonicalrequest = $method . "\n"
                          . $path . "\n"
                          . '' . "\n"                  // No query string.
                          . $canonicalheaders . "\n"
                          . $signedheaders . "\n"
                          . $payloadhash;

        $scope = $date . '/' . $this->region . '/' . self::SERVICE . '/aws4_request';
        $stringtosign = self::ALGORITHM . "\n"
                      . $datetime . "\n"
                      . $scope . "\n"
                      . hash('sha256', $canonicalrequest);

        $kdate    = hash_hmac('sha256', $date, 'AWS4' . $this->secretkey, true);
        $kregion  = hash_hmac('sha256', $this->region, $kdate, true);
        $kservice = hash_hmac('sha256', self::SERVICE, $kregion, true);
        $ksigning = hash_hmac('sha256', 'aws4_request', $kservice, true);
        $signature = hash_hmac('sha256', $stringtosign, $ksigning);

        return self::ALGORITHM
             . ' Credential=' . $this->accesskey . '/' . $scope
             . ', SignedHeaders=' . $signedheaders
             . ', Signature=' . $signature;
    }

    /**
     * Default curl-based HTTP client. Kept at the bottom so tests can swap it wholesale.
     *
     * @param string               $method
     * @param string               $url
     * @param array<string,string> $headers
     * @param string               $body
     * @return array{status:int, body:string}
     */
    private function default_http_client(string $method, string $url, array $headers, string $body): array {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);

        $lines = [];
        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $lines);

        if ($method === 'PUT' || $method === 'POST') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException('s3_storage_adapter: curl error — ' . $err);
        }
        return ['status' => $status, 'body' => (string) $response];
    }

    /**
     * Normalise a caller-provided relative path to a clean S3 key.
     *
     * Mirrors {@see local_storage_adapter::safe_relative_path()} so the two
     * adapters accept the same input.
     *
     * @param string $relative
     * @return string
     */
    private static function safe_key(string $relative): string {
        $relative = ltrim($relative, '/');
        $parts = [];
        foreach (explode('/', $relative) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                continue;
            }
            $parts[] = $segment;
        }
        if (empty($parts)) {
            throw new RuntimeException('s3_storage_adapter: empty relative path');
        }
        return implode('/', $parts);
    }

    /**
     * Percent-encode a key for use as an S3 URL path, preserving `/` separators.
     *
     * @param string $key
     * @return string
     */
    private static function encode_key(string $key): string {
        return implode('/', array_map('rawurlencode', explode('/', $key)));
    }
}
