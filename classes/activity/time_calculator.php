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
 * Pure time-on-module calculator.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_esmed_compliance\activity;

/**
 * Convert a stream of module-view events into per-module time deltas.
 *
 * Time on a module is approximated as the gap between two consecutive
 * events of the same learner, attributed to the module that was being
 * viewed before the transition. Each delta is capped to defuse pages
 * left open overnight.
 *
 * The implementation is deliberately self-contained: it takes a plain
 * array and returns a plain array so the aggregator can batch results
 * per-user without touching the database, and so we can unit-test the
 * rules without a Moodle bootstrap.
 */
final class time_calculator {
    /**
     * Compute per-module aggregates from a chronological list of view events.
     *
     * The input is an ordered list (oldest first) of events belonging to
     * a single user. Each event must carry:
     *   - int    $cmid
     *   - int    $courseid
     *   - string $modulename (for example "quiz", "resource", "scorm")
     *   - int    $timestamp
     *
     * Events are grouped into contiguous "runs" separated by a gap larger
     * than the cap. Between two events less than `$capseconds` apart the
     * full delta is attributed to the module that was being viewed before
     * the transition. Any trailing event receives a fixed `$tailseconds`
     * credit so a single view is not counted as zero.
     *
     * The returned array maps `cmid` to an associative array:
     *   - courseid
     *   - modulename
     *   - first_access
     *   - last_access
     *   - time_spent_seconds
     *   - views_count
     *
     * @param array $events
     * @param int $capseconds   Maximum delta attributed to a single transition.
     * @param int $tailseconds  Credit assigned to the final view of a run (<= cap).
     * @return array
     */
    public static function aggregate(array $events, int $capseconds, int $tailseconds = 60): array {
        if ($capseconds < 0) {
            $capseconds = 0;
        }
        if ($tailseconds < 0) {
            $tailseconds = 0;
        }
        if ($tailseconds > $capseconds) {
            $tailseconds = $capseconds;
        }

        $result = [];
        $previous = null;

        foreach ($events as $event) {
            $cmid = (int) $event['cmid'];
            $ts   = (int) $event['timestamp'];

            if (!isset($result[$cmid])) {
                $result[$cmid] = [
                    'courseid'           => (int) $event['courseid'],
                    'modulename'         => (string) $event['modulename'],
                    'first_access'       => $ts,
                    'last_access'        => $ts,
                    'time_spent_seconds' => 0,
                    'views_count'        => 0,
                ];
            }
            $result[$cmid]['views_count']++;
            $result[$cmid]['first_access'] = min($result[$cmid]['first_access'], $ts);
            $result[$cmid]['last_access']  = max($result[$cmid]['last_access'], $ts);

            if ($previous !== null) {
                $delta = $ts - (int) $previous['timestamp'];
                if ($delta > 0 && $delta <= $capseconds) {
                    $prevcmid = (int) $previous['cmid'];
                    $result[$prevcmid]['time_spent_seconds'] += $delta;
                } else {
                    // Gap exceeds the cap: credit a tail to the previous event
                    // so the last view of the run is not worth zero seconds.
                    $prevcmid = (int) $previous['cmid'];
                    $result[$prevcmid]['time_spent_seconds'] += $tailseconds;
                }
            }

            $previous = $event;
        }

        // The very last event of the stream closes with a tail credit.
        if ($previous !== null) {
            $prevcmid = (int) $previous['cmid'];
            $result[$prevcmid]['time_spent_seconds'] += $tailseconds;
        }

        return $result;
    }
}
