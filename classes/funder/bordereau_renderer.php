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
 * Bordereau renderer contract.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_esmed_compliance\funder;

/**
 * Renders a bordereau payload into a concrete format (PDF or CSV).
 */
interface bordereau_renderer {
    /**
     * File extension the renderer produces, without the leading dot.
     *
     * @return string
     */
    public function extension(): string;

    /**
     * MIME type of the produced blob.
     *
     * @return string
     */
    public function mime_type(): string;

    /**
     * Render the payload into bytes.
     *
     * @param bordereau_payload $payload
     * @param string|null       $verificationtoken
     * @param string|null       $verificationurl
     * @return string
     */
    public function render(
        bordereau_payload $payload,
        ?string $verificationtoken = null,
        ?string $verificationurl = null
    ): string;
}
