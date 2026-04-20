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
 * Indexer turning Moodle assessment attempts into categorised rows.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_esmed_compliance\assessment;

use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Takes in a raw attempt descriptor and writes a compliance-ready row.
 *
 * The indexer refuses to index attempts on untagged modules. Admins must
 * explicitly assign a regulatory category to each cmid before any of its
 * attempts become compliance evidence. This keeps the plugin from
 * guessing which quiz is a summative assessment versus a warm-up drill.
 */
class indexer {

    /** @var tag_repository */
    private tag_repository $tags;

    /** @var assessment_repository */
    private assessment_repository $assessments;

    /**
     * Constructor.
     *
     * @param tag_repository|null        $tags        Injectable for tests.
     * @param assessment_repository|null $assessments Injectable for tests.
     */
    public function __construct(
        ?tag_repository $tags = null,
        ?assessment_repository $assessments = null
    ) {
        $this->tags = $tags ?? new tag_repository();
        $this->assessments = $assessments ?? new assessment_repository();
    }

    /**
     * Index a single attempt.
     *
     * Returns the id of the row that ended up in the index (new or
     * existing), or null if the cmid has no regulatory tag.
     *
     * @param int      $userid
     * @param int      $courseid
     * @param int      $cmid
     * @param float    $score
     * @param float    $maxscore   Must be > 0 for percent computation.
     * @param int      $attemptdate
     * @param string   $sourcetable Name of the Moodle table the attempt comes from ("quiz_attempts", ...).
     * @param int|null $sourceattemptid
     * @param int|null $now
     * @return int|null
     */
    public function index_attempt(
        int $userid,
        int $courseid,
        int $cmid,
        float $score,
        float $maxscore,
        int $attemptdate,
        string $sourcetable,
        ?int $sourceattemptid,
        ?int $now = null
    ): ?int {
        $type = $this->tags->get_type_for_cmid($cmid);
        if ($type === null) {
            return null;
        }

        $now = $now ?? time();

        $record = new stdClass();
        $record->userid               = $userid;
        $record->courseid             = $courseid;
        $record->cmid                 = $cmid;
        $record->assessment_type      = $type;
        $record->score                = $score;
        $record->max_score            = $maxscore;
        $record->grade_percent        = self::grade_percent($score, $maxscore);
        $record->attempt_date         = $attemptdate;
        $record->attempt_id_moodle    = $sourceattemptid;
        $record->attempt_source_table = $sourcetable;
        $record->timecreated          = $now;

        return $this->assessments->insert_unique($record);
    }

    /**
     * Compute a percentage rounded to two decimals, or null when max is not positive.
     *
     * @param float $score
     * @param float $max
     * @return float|null
     */
    private static function grade_percent(float $score, float $max): ?float {
        if ($max <= 0.0) {
            return null;
        }
        return round(($score / $max) * 100.0, 2);
    }
}
