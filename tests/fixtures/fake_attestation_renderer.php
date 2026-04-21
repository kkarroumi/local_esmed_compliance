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
 * Deterministic attestation renderer test double.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_esmed_compliance\tests\fixtures;

use local_esmed_compliance\attestation\attestation_payload;
use local_esmed_compliance\attestation\attestation_renderer;

defined('MOODLE_INTERNAL') || die();

/**
 * Renders a predictable serialisation of the payload so tests can assert
 * the hash without pulling in TCPDF. The renderer also records the
 * verification token passed in, so tests can check the service wired
 * them through.
 */
final class fake_attestation_renderer implements attestation_renderer {

    /** @var string|null */
    public ?string $lasttoken = null;

    /** @var string|null */
    public ?string $lasturl = null;

    public function render(
        attestation_payload $payload,
        ?string $verificationtoken = null,
        ?string $verificationurl = null
    ): string {
        $this->lasttoken = $verificationtoken;
        $this->lasturl = $verificationurl;
        $envelope = $payload->to_array();
        $envelope['verification_token'] = $verificationtoken;
        return 'FAKEPDF:' . json_encode($envelope, JSON_UNESCAPED_UNICODE);
    }
}
