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
 * Value object describing an attestation d'assiduité.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_esmed_compliance\attestation;

/**
 * Immutable snapshot of everything the renderer needs to produce an attestation.
 *
 * The payload is what gets hashed and archived, so it must contain
 * enough fields to make the resulting PDF self-sufficient for audit:
 * identity of the training organisation, learner identity, course
 * title, period of training, sum of certifiable durations, list of
 * assessments, and the signatory.
 */
final class attestation_payload {
    /**
     * Build a new payload instance with all fields required by the renderer.
     *
     * @param array $organisation Training organisation identity.
     * @param array $learner Learner identity.
     * @param array $course Course metadata.
     * @param int $totalseconds Aggregated certifiable duration.
     * @param int|null $periodstart Start of the training period (unix timestamp).
     * @param int|null $periodend End of the training period (unix timestamp).
     * @param array $sessions Per-session breakdown.
     * @param array $assessments Per-assessment breakdown.
     * @param int $generatedat When the payload was built.
     */
    public function __construct(
        /** @var array Training organisation identity. */
        public readonly array $organisation,
        /** @var array Learner identity. */
        public readonly array $learner,
        /** @var array Course metadata. */
        public readonly array $course,
        /** @var int Aggregated certifiable duration in seconds. */
        public readonly int $totalseconds,
        /** @var int|null Start of the training period (unix timestamp). */
        public readonly ?int $periodstart,
        /** @var int|null End of the training period (unix timestamp). */
        public readonly ?int $periodend,
        /** @var array Per-session breakdown. */
        public readonly array $sessions,
        /** @var array Per-assessment breakdown. */
        public readonly array $assessments,
        /** @var int When the payload was built (unix timestamp). */
        public readonly int $generatedat
    ) {
    }

    /**
     * Convert to a plain array for serialisation / storage in metadata_json.
     *
     * @return array
     */
    public function to_array(): array {
        return [
            'organisation' => $this->organisation,
            'learner'      => $this->learner,
            'course'       => $this->course,
            'totalseconds' => $this->totalseconds,
            'periodstart'  => $this->periodstart,
            'periodend'    => $this->periodend,
            'sessions'     => $this->sessions,
            'assessments'  => $this->assessments,
            'generatedat'  => $this->generatedat,
        ];
    }
}
