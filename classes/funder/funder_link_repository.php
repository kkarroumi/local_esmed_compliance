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
 * Data access layer for {local_esmed_compliance_funder_link}.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_esmed_compliance\funder;

use dml_exception;
use stdClass;

/**
 * CRUD for the (course → funder) link.
 *
 * A course may carry at most one active funder link per iteration 6 —
 * the unique constraint is enforced here rather than at schema level
 * so re-linking a course behaves like an update.
 */
class funder_link_repository {
    /** @var string Table name. */
    public const TABLE = 'local_esmed_compliance_funder_link';

    /** @var string Funder type: Compte Personnel de Formation. */
    public const FUNDER_CPF = 'CPF';
    /** @var string Funder type: France Travail (ex-Pôle Emploi). */
    public const FUNDER_FT = 'FT';
    /** @var string Funder type: Opérateur de compétences. */
    public const FUNDER_OPCO = 'OPCO';
    /** @var string Funder type: Conseil régional. */
    public const FUNDER_REGION = 'REGION';
    /** @var string Funder type: other / out-of-scheme. */
    public const FUNDER_AUTRE = 'AUTRE';

    /**
     * Every funder type supported by this iteration.
     *
     * @return string[]
     */
    public static function valid_funders(): array {
        return [
            self::FUNDER_CPF,
            self::FUNDER_FT,
            self::FUNDER_OPCO,
            self::FUNDER_REGION,
            self::FUNDER_AUTRE,
        ];
    }

    /**
     * Create or update the funder link attached to a course.
     *
     * @param int      $courseid
     * @param string   $fundertype Must be one of the FUNDER_* constants.
     * @param array    $attributes Optional attributes: dossier_number, total_hours_planned,
     *                             start_date, end_date, action_intitule, opco_name.
     * @param int|null $now
     * @return int Link id.
     * @throws dml_exception
     * @throws \coding_exception
     */
    public function upsert(int $courseid, string $fundertype, array $attributes = [], ?int $now = null): int {
        global $DB;

        if (!in_array($fundertype, self::valid_funders(), true)) {
            throw new \coding_exception('Unknown funder type: ' . $fundertype);
        }
        $now = $now ?? time();

        $existing = $DB->get_record(self::TABLE, ['courseid' => $courseid]);
        $record = $existing ?: new stdClass();
        $record->courseid            = $courseid;
        $record->funder_type         = $fundertype;
        $record->dossier_number      = $attributes['dossier_number'] ?? ($existing->dossier_number ?? null);
        $record->total_hours_planned = $attributes['total_hours_planned'] ?? ($existing->total_hours_planned ?? null);
        $record->start_date          = $attributes['start_date'] ?? ($existing->start_date ?? null);
        $record->end_date            = $attributes['end_date'] ?? ($existing->end_date ?? null);
        $record->action_intitule     = $attributes['action_intitule'] ?? ($existing->action_intitule ?? null);
        $record->opco_name           = $attributes['opco_name'] ?? ($existing->opco_name ?? null);
        $record->timemodified        = $now;

        if ($existing) {
            $DB->update_record(self::TABLE, $record);
            return (int) $existing->id;
        }

        $record->timecreated = $now;
        return (int) $DB->insert_record(self::TABLE, $record);
    }

    /**
     * Look up a link by id.
     *
     * @param int $linkid
     * @return stdClass|null
     * @throws dml_exception
     */
    public function get(int $linkid): ?stdClass {
        global $DB;
        $record = $DB->get_record(self::TABLE, ['id' => $linkid]);
        return $record ?: null;
    }

    /**
     * Look up a link by course id.
     *
     * @param int $courseid
     * @return stdClass|null
     * @throws dml_exception
     */
    public function get_for_course(int $courseid): ?stdClass {
        global $DB;
        $record = $DB->get_record(self::TABLE, ['courseid' => $courseid]);
        return $record ?: null;
    }

    /**
     * Remove the funder link for a course, if any.
     *
     * @param int $courseid
     * @return void
     * @throws dml_exception
     */
    public function remove_for_course(int $courseid): void {
        global $DB;
        $DB->delete_records(self::TABLE, ['courseid' => $courseid]);
    }
}
