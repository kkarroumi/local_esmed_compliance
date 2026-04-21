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
 * Activity aggregation driver.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_esmed_compliance\activity;

defined('MOODLE_INTERNAL') || die();

/**
 * Consume new module-view events from the standard logstore and fold them
 * into {local_esmed_activity_log}.
 *
 * The aggregator is idempotent and resumable: it persists the highest
 * log id it has processed in plugin config, so repeated runs pick up
 * strictly new events.
 */
class aggregator {

    /** @var string Config key for the logstore checkpoint. */
    public const CHECKPOINT_CONFIG_KEY = 'activity_aggregator_checkpoint';

    /** @var string Logstore table used as the event source. */
    public const LOGSTORE_TABLE = 'logstore_standard_log';

    /** @var activity_repository */
    private activity_repository $repository;

    /**
     * Constructor.
     *
     * @param activity_repository|null $repository Injectable for tests.
     */
    public function __construct(?activity_repository $repository = null) {
        $this->repository = $repository ?? new activity_repository();
    }

    /**
     * Fold a user's ordered view stream into the activity log.
     *
     * This is the unit-testable entry point. It takes an already-ordered
     * list of events for a single user, runs the pure time calculator and
     * upserts the aggregates. The caller is responsible for filtering
     * events to a single learner.
     *
     * @param int   $userid
     * @param array<int, array{cmid:int,courseid:int,modulename:string,timestamp:int}> $events
     * @param int   $capseconds
     * @param int   $now
     * @param int   $tailseconds
     * @return array<int, array<string, mixed>>  Aggregates applied, keyed by cmid.
     */
    public function aggregate_from_events(
        int $userid,
        array $events,
        int $capseconds,
        int $now,
        int $tailseconds = 60
    ): array {
        $aggregates = time_calculator::aggregate($events, $capseconds, $tailseconds);
        foreach ($aggregates as $cmid => $row) {
            $this->repository->upsert(
                $userid,
                (int) $row['courseid'],
                (int) $cmid,
                (string) $row['modulename'],
                (int) $row['first_access'],
                (int) $row['last_access'],
                (int) $row['time_spent_seconds'],
                (int) $row['views_count'],
                $now
            );
        }
        return $aggregates;
    }

    /**
     * Run a full pass against the standard logstore.
     *
     * Reads module-view events strictly newer than the stored checkpoint,
     * groups them per-user, folds each group into the aggregation table
     * and advances the checkpoint to the last processed log id.
     *
     * Returns the number of (user, module) aggregates touched in this
     * pass so scheduled task output is meaningful.
     *
     * @param int $capseconds  Per-module delta cap in seconds.
     * @param int $now
     * @param int $batchlimit  Maximum rows to read from logstore in one pass.
     * @return int
     */
    public function run(int $capseconds, int $now, int $batchlimit = 5000): int {
        global $DB;

        if (!$DB->get_manager()->table_exists(self::LOGSTORE_TABLE)) {
            // Standard log store disabled: nothing to do, no-op cleanly.
            return 0;
        }

        $checkpoint = (int) get_config('local_esmed_compliance', self::CHECKPOINT_CONFIG_KEY);

        $sql = "SELECT id, userid, courseid, contextinstanceid, component, target, action, timecreated
                  FROM {" . self::LOGSTORE_TABLE . "}
                 WHERE id > :checkpoint
                   AND target = :target
                   AND action = :action
                   AND userid > 0
                   AND contextinstanceid > 0
              ORDER BY userid ASC, timecreated ASC, id ASC";

        $rows = $DB->get_records_sql(
            $sql,
            [
                'checkpoint' => $checkpoint,
                'target'     => 'course_module',
                'action'     => 'viewed',
            ],
            0,
            $batchlimit
        );

        if (!$rows) {
            return 0;
        }

        $touched = 0;
        $maxid = $checkpoint;
        $currentuser = null;
        $buffer = [];

        foreach ($rows as $row) {
            $maxid = max($maxid, (int) $row->id);
            $userid = (int) $row->userid;

            if ($currentuser !== null && $currentuser !== $userid) {
                $touched += count($this->aggregate_from_events($currentuser, $buffer, $capseconds, $now));
                $buffer = [];
            }
            $currentuser = $userid;
            $buffer[] = [
                'cmid'       => (int) $row->contextinstanceid,
                'courseid'   => (int) $row->courseid,
                'modulename' => self::module_name_from_component((string) $row->component),
                'timestamp'  => (int) $row->timecreated,
            ];
        }
        if ($currentuser !== null) {
            $touched += count($this->aggregate_from_events($currentuser, $buffer, $capseconds, $now));
        }

        set_config(self::CHECKPOINT_CONFIG_KEY, $maxid, 'local_esmed_compliance');

        return $touched;
    }

    /**
     * Reset the logstore checkpoint. Exposed for tests and admin tooling.
     *
     * @return void
     */
    public function reset_checkpoint(): void {
        set_config(self::CHECKPOINT_CONFIG_KEY, 0, 'local_esmed_compliance');
    }

    /**
     * Extract the module name ("quiz", "resource", ...) from a log component.
     *
     * @param string $component Frankenstyle component like "mod_quiz".
     * @return string
     */
    private static function module_name_from_component(string $component): string {
        if (strpos($component, 'mod_') === 0) {
            return substr($component, 4);
        }
        return $component;
    }
}
