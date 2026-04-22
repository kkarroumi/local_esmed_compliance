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
 * List funder links joined with their attached course for operator UI.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_esmed_compliance\funder;

/**
 * Produce the row shapes consumed by `templates/funder_links_list.mustache`.
 *
 * Lookup is a single JOIN so a large site does not pay a round-trip per
 * row. Rows are ordered by course fullname to give the operator a
 * predictable alphabetical browse.
 */
class funder_link_listing {
    /**
     * Return every funder link attached to an existing course, along with
     * the course identity fields needed to display and edit the link.
     *
     * @return array
     */
    public function all(): array {
        global $DB;

        $sql = "SELECT l.id, l.courseid, l.funder_type, l.dossier_number,
                       l.total_hours_planned, l.start_date, l.end_date,
                       l.action_intitule, l.opco_name, l.timemodified,
                       c.fullname  AS course_fullname,
                       c.shortname AS course_shortname
                  FROM {" . funder_link_repository::TABLE . "} l
                  JOIN {course} c ON c.id = l.courseid
              ORDER BY c.fullname ASC";

        $rows = [];
        foreach ($DB->get_records_sql($sql) as $row) {
            $rows[] = [
                'id'                  => (int) $row->id,
                'courseid'            => (int) $row->courseid,
                'course_fullname'     => (string) $row->course_fullname,
                'course_shortname'    => (string) $row->course_shortname,
                'funder_type'         => (string) $row->funder_type,
                'dossier_number'      => $row->dossier_number !== null ? (string) $row->dossier_number : null,
                'total_hours_planned' => $row->total_hours_planned !== null ? (float) $row->total_hours_planned : null,
                'start_date'          => $row->start_date !== null ? (int) $row->start_date : null,
                'end_date'            => $row->end_date !== null ? (int) $row->end_date : null,
                'action_intitule'     => $row->action_intitule !== null ? (string) $row->action_intitule : null,
                'opco_name'           => $row->opco_name !== null ? (string) $row->opco_name : null,
                'timemodified'        => (int) $row->timemodified,
            ];
        }
        return $rows;
    }
}
