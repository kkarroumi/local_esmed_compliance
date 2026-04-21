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
 * Create / edit form for {local_esmed_funder_link}.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_esmed_compliance\form;

use local_esmed_compliance\funder\funder_link_repository;
use moodleform;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Operator form for linking a Moodle course to a funding body.
 *
 * The form is either pre-filled with the existing link row (editing)
 * or left blank (first attachment). `customdata` is expected to carry:
 *   - `courseid`:  int (required when creating, optional when editing)
 *   - `coursename`: formatted course fullname, rendered read-only
 *   - `existing`:  stdClass|null — the current funder_link row, if any
 */
class funder_link_form extends moodleform {
    /**
     * Inherits from parent.
     */
    protected function definition() {
        $mform = $this->_form;

        $coursename = $this->_customdata['coursename'] ?? '';
        $courseid   = (int) ($this->_customdata['courseid'] ?? 0);
        $existing   = $this->_customdata['existing'] ?? null;

        $mform->addElement(
            'static',
            'course_display',
            get_string('funders_field_course', 'local_esmed_compliance'),
            format_string($coursename)
        );
        $mform->addElement('hidden', 'courseid', $courseid);
        $mform->setType('courseid', PARAM_INT);

        $options = [];
        foreach (funder_link_repository::valid_funders() as $code) {
            $options[$code] = get_string('funder_' . strtolower($code), 'local_esmed_compliance');
        }
        $mform->addElement(
            'select',
            'funder_type',
            get_string('funders_field_type', 'local_esmed_compliance'),
            $options
        );
        $mform->addRule('funder_type', null, 'required', null, 'client');
        $mform->setDefault('funder_type', funder_link_repository::FUNDER_CPF);

        $mform->addElement(
            'text',
            'dossier_number',
            get_string('funders_field_dossier', 'local_esmed_compliance'),
            ['size' => 40, 'maxlength' => 100]
        );
        $mform->setType('dossier_number', PARAM_TEXT);

        $mform->addElement(
            'text',
            'action_intitule',
            get_string('funders_field_action', 'local_esmed_compliance'),
            ['size' => 60, 'maxlength' => 500]
        );
        $mform->setType('action_intitule', PARAM_TEXT);

        $mform->addElement(
            'text',
            'opco_name',
            get_string('funders_field_opco_name', 'local_esmed_compliance'),
            ['size' => 40, 'maxlength' => 255]
        );
        $mform->setType('opco_name', PARAM_TEXT);
        $mform->hideIf('opco_name', 'funder_type', 'neq', funder_link_repository::FUNDER_OPCO);

        $mform->addElement(
            'text',
            'total_hours_planned',
            get_string('funders_field_hours', 'local_esmed_compliance'),
            ['size' => 8]
        );
        $mform->setType('total_hours_planned', PARAM_FLOAT);

        $mform->addElement(
            'date_selector',
            'start_date',
            get_string('funders_field_start_date', 'local_esmed_compliance'),
            ['optional' => true]
        );
        $mform->addElement(
            'date_selector',
            'end_date',
            get_string('funders_field_end_date', 'local_esmed_compliance'),
            ['optional' => true]
        );

        $this->add_action_buttons(true, get_string('funders_action_save', 'local_esmed_compliance'));

        if ($existing) {
            $this->set_data([
                'courseid'            => $courseid,
                'funder_type'         => (string) $existing->funder_type,
                'dossier_number'      => (string) ($existing->dossier_number ?? ''),
                'action_intitule'     => (string) ($existing->action_intitule ?? ''),
                'opco_name'           => (string) ($existing->opco_name ?? ''),
                'total_hours_planned' => $existing->total_hours_planned !== null
                    ? (float) $existing->total_hours_planned : '',
                'start_date'          => $existing->start_date !== null ? (int) $existing->start_date : 0,
                'end_date'            => $existing->end_date !== null ? (int) $existing->end_date : 0,
            ]);
        }
    }

    /**
     * Validate submitted funder link data.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (
            !empty($data['start_date']) && !empty($data['end_date'])
            && (int) $data['start_date'] > (int) $data['end_date']
        ) {
            $errors['end_date'] = get_string('funders_error_end_before_start', 'local_esmed_compliance');
        }

        if (
            isset($data['total_hours_planned']) && $data['total_hours_planned'] !== ''
            && (float) $data['total_hours_planned'] < 0
        ) {
            $errors['total_hours_planned'] = get_string('funders_error_negative_hours', 'local_esmed_compliance');
        }

        if (!in_array($data['funder_type'] ?? '', funder_link_repository::valid_funders(), true)) {
            $errors['funder_type'] = get_string('funders_error_bad_type', 'local_esmed_compliance');
        }

        return $errors;
    }
}
