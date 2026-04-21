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
 * Value object describing a funder bordereau.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_esmed_compliance\funder;

/**
 * Immutable snapshot of a bordereau financeur.
 *
 * One payload corresponds to one `local_esmed_funder_link` row, rolled
 * over all enrolled learners within the (optional) funder period.
 * Hashing the payload is enough to detect after-the-fact mutations of
 * any learner's contribution; both the PDF and CSV renderers are
 * deterministic functions of this object.
 */
final class bordereau_payload {
    /**
     * Build a new bordereau payload with every field the renderer needs.
     *
     * @param array $organisation Training organisation identity.
     * @param array $funder Funder type, dossier, period, action label.
     * @param array $course Course metadata.
     * @param array $learners Per-learner rows.
     * @param int $totalseconds Sum of certifiable seconds across all learners.
     * @param int $learnercount Number of enrolled learners in the payload.
     * @param int|null $periodstart Start of the funder period (unix timestamp).
     * @param int|null $periodend End of the funder period (unix timestamp).
     * @param int $generatedat When the payload was built (unix timestamp).
     */
    public function __construct(
        /** @var array Training organisation identity. */
        public readonly array $organisation,
        /** @var array Funder type, dossier, period, action label. */
        public readonly array $funder,
        /** @var array Course metadata. */
        public readonly array $course,
        /** @var array Per-learner rows. */
        public readonly array $learners,
        /** @var int Sum of certifiable seconds across all learners. */
        public readonly int $totalseconds,
        /** @var int Number of enrolled learners in the payload. */
        public readonly int $learnercount,
        /** @var int|null Start of the funder period (unix timestamp). */
        public readonly ?int $periodstart,
        /** @var int|null End of the funder period (unix timestamp). */
        public readonly ?int $periodend,
        /** @var int When the payload was built (unix timestamp). */
        public readonly int $generatedat
    ) {
    }

    /**
     * Convert to a plain array for storage in metadata_json.
     *
     * @return array
     */
    public function to_array(): array {
        return [
            'organisation' => $this->organisation,
            'funder'       => $this->funder,
            'course'       => $this->course,
            'learners'     => $this->learners,
            'totalseconds' => $this->totalseconds,
            'learnercount' => $this->learnercount,
            'periodstart'  => $this->periodstart,
            'periodend'    => $this->periodend,
            'generatedat'  => $this->generatedat,
        ];
    }
}
