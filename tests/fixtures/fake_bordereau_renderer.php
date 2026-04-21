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
 * Deterministic bordereau renderer test double.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_esmed_compliance\tests\fixtures;

use local_esmed_compliance\funder\bordereau_payload;
use local_esmed_compliance\funder\bordereau_renderer;

/**
 * Tunable fake renderer so bordereau_service tests can run without TCPDF.
 *
 * Each instance reports its own extension and mime type so a single
 * service instantiation can exercise the PDF + CSV pairing logic.
 */
final class fake_bordereau_renderer implements bordereau_renderer {
    /** @var string */
    private string $extension;

    /** @var string */
    private string $mimetype;

    /** @var string|null */
    public ?string $lasttoken = null;

    /** @var string|null */
    public ?string $lasturl = null;

    /**
     * Build the fake renderer announcing the given extension and mime type.
     *
     * @param string $extension
     * @param string $mimetype
     */
    public function __construct(string $extension = 'pdf', string $mimetype = 'application/pdf') {
        $this->extension = $extension;
        $this->mimetype = $mimetype;
    }

    /**
     * Return the fixture document extension.
     */
    public function extension(): string {
        return $this->extension;
    }

    /**
     * Return the fixture document mime type.
     */
    public function mime_type(): string {
        return $this->mimetype;
    }

    /**
     * Render a deterministic fake bordereau payload as a string.
     *
     * @param bordereau_payload $payload
     * @param string|null $verificationtoken
     * @param string|null $verificationurl
     * @return string
     */
    public function render(
        bordereau_payload $payload,
        ?string $verificationtoken = null,
        ?string $verificationurl = null
    ): string {
        $this->lasttoken = $verificationtoken;
        $this->lasturl = $verificationurl;
        $envelope = $payload->to_array();
        $envelope['verification_token'] = $verificationtoken;
        $envelope['format'] = $this->extension;
        return 'FAKEBORDEREAU:' . $this->extension . ':' . json_encode($envelope, JSON_UNESCAPED_UNICODE);
    }
}
