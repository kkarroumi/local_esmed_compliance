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
 * Compliance dashboard metrics provider.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_esmed_compliance\dashboard;

use local_esmed_compliance\archive\archive_repository;
use local_esmed_compliance\archive\integrity_checker;

defined('MOODLE_INTERNAL') || die();

/**
 * Compute point-in-time compliance counters backing the dashboard.
 *
 * The provider deliberately speaks in terms of totals rather than
 * deltas so the dashboard can be re-polled at any cadence without
 * having to carry state across requests. Each returned associative
 * array is ready to pass straight to a Mustache renderer.
 */
class metrics_provider {

    /**
     * Build the full metrics bundle.
     *
     * @param int|null $now Override for tests.
     * @return array<string, mixed>
     */
    public function collect(?int $now = null): array {
        $now = $now ?? time();
        return [
            'generated_at' => $now,
            'sessions'     => $this->session_metrics($now),
            'archives'     => $this->archive_metrics(),
            'alerts'       => $this->alert_metrics(),
            'integrity'    => $this->integrity_metrics(),
        ];
    }

    /**
     * Session counters: currently open, closed in the last 24h and total seconds today.
     *
     * @param int $now
     * @return array<string, int>
     */
    private function session_metrics(int $now): array {
        global $DB;

        $open = (int) $DB->count_records_sql(
            'SELECT COUNT(1) FROM {local_esmed_sessions} WHERE session_end IS NULL'
        );

        $since24h = $now - 86400;
        $closedrecently = (int) $DB->count_records_select(
            'local_esmed_sessions',
            'session_end IS NOT NULL AND session_end >= :since',
            ['since' => $since24h]
        );

        $todaystart = strtotime('today', $now) ?: $now - ($now % 86400);
        $secondstoday = (int) $DB->get_field_sql(
            "SELECT COALESCE(SUM(duration_seconds), 0)
               FROM {local_esmed_sessions}
              WHERE session_end IS NOT NULL
                AND session_end >= :from",
            ['from' => $todaystart]
        );

        return [
            'open'              => $open,
            'closed_last_24h'   => $closedrecently,
            'seconds_today'     => $secondstoday,
        ];
    }

    /**
     * Archive counters grouped by archive type and a total.
     *
     * @return array<string, int>
     */
    private function archive_metrics(): array {
        global $DB;
        $total = (int) $DB->count_records(archive_repository::TABLE);
        $attestations = (int) $DB->count_records(
            archive_repository::TABLE,
            ['archive_type' => archive_repository::TYPE_ATTESTATION_ASSIDUITE]
        );
        $bordereaux = (int) $DB->count_records(
            archive_repository::TABLE,
            ['archive_type' => archive_repository::TYPE_BORDEREAU_FINANCEUR]
        );
        return [
            'total'        => $total,
            'attestations' => $attestations,
            'bordereaux'   => $bordereaux,
        ];
    }

    /**
     * Alert counters: unacknowledged and total in the last 7 days.
     *
     * @return array<string, int>
     */
    private function alert_metrics(): array {
        global $DB;
        $unacked = (int) $DB->count_records_select(
            'local_esmed_alerts',
            'acknowledged_at IS NULL'
        );
        $weekago = time() - 7 * 86400;
        $lastweek = (int) $DB->count_records_select(
            'local_esmed_alerts',
            'triggered_at >= :since',
            ['since' => $weekago]
        );
        return [
            'unacknowledged' => $unacked,
            'last_7_days'    => $lastweek,
        ];
    }

    /**
     * Integrity counters: tampered / missing archives as last checked.
     *
     * @return array<string, int>
     */
    private function integrity_metrics(): array {
        $checker = new integrity_checker();
        return [
            'tampered' => $checker->count_archives_in_status(integrity_checker::STATUS_TAMPERED),
            'missing'  => $checker->count_archives_in_status(integrity_checker::STATUS_MISSING),
            'valid'    => $checker->count_archives_in_status(integrity_checker::STATUS_VALID),
        ];
    }
}
