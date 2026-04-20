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
 * Data access layer for {local_esmed_assessment_tag}.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_esmed_compliance\assessment;

use dml_exception;
use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Tags course modules with their regulatory assessment category.
 *
 * A tag is required before attempts on a module are indexed in the
 * `local_esmed_assessment_index`. Untagged modules are deliberately
 * ignored: the plugin refuses to guess what qualifies as a summative
 * assessment.
 */
class tag_repository {

    /** @var string Table name. */
    public const TABLE = 'local_esmed_assessment_tag';

    /** @var string Quiz used for pedagogical purposes (not graded for certification). */
    public const TYPE_QUIZ_PEDAGO = 'quiz_pedago';
    /** @var string Formative assignment used as a training exercise. */
    public const TYPE_DEVOIR_FORMATIF = 'devoir_formatif';
    /** @var string Mock exam ("examen blanc") simulating the final evaluation. */
    public const TYPE_EXAMEN_BLANC = 'examen_blanc';
    /** @var string Summative assessment whose result is used for certification. */
    public const TYPE_EVALUATION_SOMMATIVE = 'evaluation_sommative';

    /**
     * Return every supported regulatory category, as a list of machine codes.
     *
     * @return string[]
     */
    public static function valid_types(): array {
        return [
            self::TYPE_QUIZ_PEDAGO,
            self::TYPE_DEVOIR_FORMATIF,
            self::TYPE_EXAMEN_BLANC,
            self::TYPE_EVALUATION_SOMMATIVE,
        ];
    }

    /**
     * Assign or update the regulatory type for a course module.
     *
     * @param int      $cmid
     * @param int      $courseid
     * @param string   $type Must be one of the TYPE_* constants.
     * @param int|null $createdby Moodle user id (for audit).
     * @param int|null $now
     * @return int Tag id.
     * @throws dml_exception
     * @throws \coding_exception
     */
    public function set_type(int $cmid, int $courseid, string $type, ?int $createdby = null, ?int $now = null): int {
        global $DB;

        if (!in_array($type, self::valid_types(), true)) {
            throw new \coding_exception('Unknown assessment type: ' . $type);
        }

        $now = $now ?? time();
        $existing = $DB->get_record(self::TABLE, ['cmid' => $cmid]);

        if ($existing) {
            $existing->courseid        = $courseid;
            $existing->assessment_type = $type;
            $existing->timemodified    = $now;
            $DB->update_record(self::TABLE, $existing);
            return (int) $existing->id;
        }

        $record = new stdClass();
        $record->cmid            = $cmid;
        $record->courseid        = $courseid;
        $record->assessment_type = $type;
        $record->created_by      = $createdby;
        $record->timecreated     = $now;
        $record->timemodified    = $now;

        return (int) $DB->insert_record(self::TABLE, $record);
    }

    /**
     * Remove the regulatory tag on a module.
     *
     * @param int $cmid
     * @return void
     * @throws dml_exception
     */
    public function clear(int $cmid): void {
        global $DB;
        $DB->delete_records(self::TABLE, ['cmid' => $cmid]);
    }

    /**
     * Return the assessment type associated with a cmid, or null if untagged.
     *
     * @param int $cmid
     * @return string|null
     * @throws dml_exception
     */
    public function get_type_for_cmid(int $cmid): ?string {
        global $DB;
        $type = $DB->get_field(self::TABLE, 'assessment_type', ['cmid' => $cmid]);
        return $type === false ? null : (string) $type;
    }
}
