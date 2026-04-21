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
 * Value object describing a funder bordereau.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_esmed_compliance\funder;

defined('MOODLE_INTERNAL') || die();

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
     * @param array<string, mixed> $organisation   Training organisation identity.
     * @param array<string, mixed> $funder         Funder type, dossier, period, action label.
     * @param array<string, mixed> $course         Course metadata.
     * @param array<int, array<string, mixed>> $learners
     * @param int                  $totalseconds   Sum of certifiable seconds across all learners.
     * @param int                  $learnercount   Number of enrolled learners in the payload.
     * @param int|null             $periodstart
     * @param int|null             $periodend
     * @param int                  $generatedat
     */
    public function __construct(
        public readonly array $organisation,
        public readonly array $funder,
        public readonly array $course,
        public readonly array $learners,
        public readonly int $totalseconds,
        public readonly int $learnercount,
        public readonly ?int $periodstart,
        public readonly ?int $periodend,
        public readonly int $generatedat
    ) {
    }

    /**
     * Convert to a plain array for storage in metadata_json.
     *
     * @return array<string, mixed>
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
