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
 * Alert notification dispatcher.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_esmed_compliance\alert;

use core\message\message;
use core_user;

defined('MOODLE_INTERNAL') || die();

/**
 * Send a Moodle message to every operator holding `managealerts` in the
 * alert's relevant context when a new alert is raised, and stamp
 * `notified_at` on the row so the dispatch is idempotent across
 * subsequent task runs.
 *
 * Resolution rules:
 *   - if the alert has a `courseid`, recipients are users with
 *     `managealerts` at that course context (via role assignments /
 *     archetypes);
 *   - otherwise, recipients are users with `managealerts` at the system
 *     context.
 *
 * The dispatcher is deliberately a thin collaborator that accepts
 * injected collaborators so tests can swap out the message sender.
 */
class notifier {

    /** @var alert_repository */
    private alert_repository $alerts;

    /** @var callable callable(message): int|bool  Overridable sender, defaults to `message_send`. */
    private $sender;

    /**
     * Constructor.
     *
     * @param alert_repository|null $alerts Injectable for tests.
     * @param callable|null         $sender Optional callable(message): int|bool replacing `message_send`.
     */
    public function __construct(?alert_repository $alerts = null, ?callable $sender = null) {
        $this->alerts = $alerts ?? new alert_repository();
        $this->sender = $sender ?? 'message_send';
    }

    /**
     * Notify operators about a freshly-raised alert.
     *
     * No-op if the alert is already notified (`notified_at` not null)
     * or if no operator currently holds `managealerts` in the relevant
     * context. Returns the number of recipients actually messaged.
     *
     * @param int $alertid
     * @param int $now
     * @return int
     */
    public function notify(int $alertid, int $now): int {
        global $DB;

        $row = $this->alerts->get($alertid);
        if ($row === null) {
            return 0;
        }
        if ($row->notified_at !== null) {
            return 0;
        }

        $recipients = $this->resolve_recipients((int) $row->userid, $row->courseid !== null ? (int) $row->courseid : null);
        if (!$recipients) {
            return 0;
        }

        $subject = get_string('message_alert_inactivity_subject', 'local_esmed_compliance');
        $body = get_string('message_alert_inactivity_body', 'local_esmed_compliance', (object) [
            'alertid'      => (int) $row->id,
            'userid'       => (int) $row->userid,
            'courseid'     => $row->courseid !== null ? (int) $row->courseid : 0,
            'triggered_at' => userdate((int) $row->triggered_at),
        ]);

        $sent = 0;
        foreach ($recipients as $recipientid) {
            $msg = new message();
            $msg->component         = 'local_esmed_compliance';
            $msg->name              = 'alert_inactivity';
            $msg->userfrom          = core_user::get_noreply_user();
            $msg->userto            = $recipientid;
            $msg->subject           = $subject;
            $msg->fullmessage       = $body;
            $msg->fullmessageformat = FORMAT_PLAIN;
            $msg->fullmessagehtml   = nl2br(s($body));
            $msg->smallmessage      = $subject;
            $msg->notification      = 1;

            $result = call_user_func($this->sender, $msg);
            if ($result !== false) {
                $sent++;
            }
        }

        // Stamp notified_at once regardless of partial failures; the next
        // scheduled run would otherwise re-notify everyone including those
        // who already received it.
        $update = new \stdClass();
        $update->id          = (int) $row->id;
        $update->notified_at = $now;
        $DB->update_record(alert_repository::TABLE, $update);

        return $sent;
    }

    /**
     * Look up user ids holding `managealerts` in the context attached to the alert.
     *
     * @param int      $learnerid Learner the alert is about, excluded from recipients.
     * @param int|null $courseid
     * @return int[]
     */
    private function resolve_recipients(int $learnerid, ?int $courseid): array {
        if ($courseid !== null) {
            $context = \context_course::instance($courseid, IGNORE_MISSING);
        } else {
            $context = \context_system::instance();
        }
        if (!$context) {
            return [];
        }
        $users = get_users_by_capability($context, 'local/esmed_compliance:managealerts', 'u.id', 'u.id');
        $ids = [];
        foreach ($users as $user) {
            $uid = (int) $user->id;
            if ($uid === $learnerid) {
                continue;
            }
            $ids[] = $uid;
        }
        return $ids;
    }
}
