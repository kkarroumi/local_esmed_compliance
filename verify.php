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
 * Public verification endpoint for sealed compliance documents.
 *
 * Accepts a `t` query parameter carrying the 64-character verification
 * token printed on the attestation. Responds with a minimal, anonymous
 * confirmation: no learner identity, no course title, just whether the
 * document is valid, when it was sealed and the SHA-256 it was sealed
 * with.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:disable moodle.Files.RequireLogin.Missing
define('NO_MOODLE_COOKIES', true);

require_once(__DIR__ . '/../../config.php');

use local_esmed_compliance\archive\verifier;

$token = trim((string) optional_param('t', '', PARAM_ALPHANUM));

$PAGE->set_url(new moodle_url('/local/esmed_compliance/verify.php', $token !== '' ? ['t' => $token] : []));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('popup');
$PAGE->set_title(get_string('verify_title', 'local_esmed_compliance'));
$PAGE->set_heading(get_string('verify_heading', 'local_esmed_compliance'));

echo $OUTPUT->header();

if ($token === '' || strlen($token) < 16) {
    echo $OUTPUT->notification(get_string('verify_missing_token', 'local_esmed_compliance'), 'notifyproblem');
    echo $OUTPUT->footer();
    exit;
}

$result = (new verifier())->verify($token);

switch ($result['status']) {
    case verifier::STATUS_UNKNOWN:
        echo $OUTPUT->notification(get_string('verify_unknown', 'local_esmed_compliance'), 'notifyproblem');
        break;

    case verifier::STATUS_MISSING:
        echo $OUTPUT->notification(get_string('verify_missing', 'local_esmed_compliance'), 'notifyproblem');
        break;

    case verifier::STATUS_TAMPERED:
        echo $OUTPUT->notification(get_string('verify_tampered', 'local_esmed_compliance'), 'notifyproblem');
        break;

    case verifier::STATUS_VALID:
        echo $OUTPUT->notification(get_string('verify_valid', 'local_esmed_compliance'), 'notifysuccess');
        break;
}

if ($result['record']) {
    $record = $result['record'];
    $rows = [
        get_string('verify_archive_type', 'local_esmed_compliance') => s((string) $record->archive_type),
        get_string('verify_sealed_at', 'local_esmed_compliance')    => userdate((int) $record->timestamp_sealed),
        get_string('verify_sealed_hash', 'local_esmed_compliance')  => s((string) $record->sha256_hash),
    ];
    if ($result['computed_hash']) {
        $rows[get_string('verify_computed_hash', 'local_esmed_compliance')] = s($result['computed_hash']);
    }

    echo html_writer::start_tag('dl', ['class' => 'row']);
    foreach ($rows as $label => $value) {
        echo html_writer::tag('dt', $label, ['class' => 'col-sm-4']);
        echo html_writer::tag('dd', $value, ['class' => 'col-sm-8']);
    }
    echo html_writer::end_tag('dl');
}

echo $OUTPUT->footer();
