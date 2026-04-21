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
 * Funder link listing tests.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_esmed_compliance;

use local_esmed_compliance\funder\funder_link_listing;
use local_esmed_compliance\funder\funder_link_repository;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \local_esmed_compliance\funder\funder_link_listing
 */
final class funder_link_listing_test extends \advanced_testcase {

    /**
     * all() returns one row per linked course, ordered by course full name.
     */
    public function test_all_returns_rows_joined_with_course(): void {
        $this->resetAfterTest();

        $courseb = $this->getDataGenerator()->create_course(['fullname' => 'Zeta Course', 'shortname' => 'Z1']);
        $coursea = $this->getDataGenerator()->create_course(['fullname' => 'Alpha Course', 'shortname' => 'A1']);

        $repo = new funder_link_repository();
        $repo->upsert((int) $courseb->id, funder_link_repository::FUNDER_CPF, [
            'dossier_number'      => 'CPF-42',
            'total_hours_planned' => 35.5,
            'start_date'          => 1700000000,
            'end_date'            => 1705000000,
            'action_intitule'     => 'SST initial',
        ], 1700000500);
        $repo->upsert((int) $coursea->id, funder_link_repository::FUNDER_OPCO, [
            'dossier_number'      => 'OPCO-7',
            'opco_name'           => 'Akto',
            'total_hours_planned' => null,
        ], 1700000600);

        $rows = (new funder_link_listing())->all();

        $this->assertCount(2, $rows);
        $this->assertSame('Alpha Course', $rows[0]['course_fullname'], 'Alphabetical ordering');
        $this->assertSame('Zeta Course', $rows[1]['course_fullname']);

        $alpha = $rows[0];
        $this->assertSame(funder_link_repository::FUNDER_OPCO, $alpha['funder_type']);
        $this->assertSame('OPCO-7', $alpha['dossier_number']);
        $this->assertSame('Akto', $alpha['opco_name']);
        $this->assertNull($alpha['total_hours_planned']);

        $zeta = $rows[1];
        $this->assertSame(funder_link_repository::FUNDER_CPF, $zeta['funder_type']);
        $this->assertSame(35.5, $zeta['total_hours_planned']);
        $this->assertSame(1700000000, $zeta['start_date']);
        $this->assertSame(1705000000, $zeta['end_date']);
        $this->assertSame('SST initial', $zeta['action_intitule']);
    }

    /**
     * An empty table yields an empty array.
     */
    public function test_all_returns_empty_array_when_no_links(): void {
        $this->resetAfterTest();
        $this->assertSame([], (new funder_link_listing())->all());
    }
}
