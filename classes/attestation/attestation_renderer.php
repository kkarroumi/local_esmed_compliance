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
 * Attestation renderer contract.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_esmed_compliance\attestation;

defined('MOODLE_INTERNAL') || die();

/**
 * Renders an attestation payload into a byte blob.
 *
 * The interface exists so tests can inject a deterministic stub and so
 * future templates (different layouts per funder) can be swapped in
 * without touching the orchestrating service.
 */
interface attestation_renderer {

    /**
     * Render a payload.
     *
     * @param attestation_payload $payload
     * @param string|null $verificationtoken Token to embed in the footer / QR.
     * @param string|null $verificationurl   Absolute URL the QR code should point to.
     * @return string Raw bytes of the rendered document.
     */
    public function render(attestation_payload $payload, ?string $verificationtoken = null, ?string $verificationurl = null): string;
}
