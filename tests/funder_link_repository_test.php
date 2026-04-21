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
 * Funder link repository tests.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_esmed_compliance;

use local_esmed_compliance\funder\funder_link_repository;

/**
 * Tests for the  component.
 *
 * @covers \local_esmed_compliance\funder\funder_link_repository
 */
final class funder_link_repository_test extends \advanced_testcase {
    /**
     * Upsert inserts when no link exists for the course.
     */
    public function test_upsert_creates_new_link(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();

        $repo = new funder_link_repository();
        $id = $repo->upsert(
            (int) $course->id,
            funder_link_repository::FUNDER_CPF,
            [
                'dossier_number'      => 'CPF-12345',
                'total_hours_planned' => 21.0,
                'start_date'          => 1700000000,
                'end_date'            => 1710000000,
                'action_intitule'     => 'Formation initiale',
            ],
            1700000000
        );

        $this->assertGreaterThan(0, $id);
        $link = $repo->get($id);
        $this->assertNotNull($link);
        $this->assertEquals($course->id, $link->courseid);
        $this->assertEquals('CPF', $link->funder_type);
        $this->assertEquals('CPF-12345', $link->dossier_number);
        $this->assertEqualsWithDelta(21.0, (float) $link->total_hours_planned, 0.001);
        $this->assertEquals(1700000000, (int) $link->timecreated);
    }

    /**
     * Upsert updates the existing link rather than creating a second row.
     */
    public function test_upsert_updates_existing_link_in_place(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $repo = new funder_link_repository();

        $first = $repo->upsert(
            (int) $course->id,
            funder_link_repository::FUNDER_OPCO,
            ['dossier_number' => 'OPCO-A', 'opco_name' => 'Atlas'],
            1700000000
        );
        $second = $repo->upsert(
            (int) $course->id,
            funder_link_repository::FUNDER_OPCO,
            ['dossier_number' => 'OPCO-B'],
            1700001000
        );

        $this->assertSame($first, $second);
        $link = $repo->get($first);
        $this->assertNotNull($link);
        $this->assertEquals('OPCO-B', $link->dossier_number);
        // Attributes not supplied in the second call are preserved.
        $this->assertEquals('Atlas', $link->opco_name);
        $this->assertEquals(1700001000, (int) $link->timemodified);
    }

    /**
     * Unknown funder types are rejected at the coding layer.
     */
    public function test_upsert_rejects_unknown_funder_type(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();

        $this->expectException(\coding_exception::class);
        (new funder_link_repository())->upsert((int) $course->id, 'MECENAT');
    }

    /**
     * get_for_course returns the record if linked, or null otherwise.
     */
    public function test_get_for_course(): void {
        $this->resetAfterTest();
        $linkedcourse = $this->getDataGenerator()->create_course();
        $othercourse = $this->getDataGenerator()->create_course();
        $repo = new funder_link_repository();

        $repo->upsert((int) $linkedcourse->id, funder_link_repository::FUNDER_FT);
        $this->assertNotNull($repo->get_for_course((int) $linkedcourse->id));
        $this->assertNull($repo->get_for_course((int) $othercourse->id));
    }

    /**
     * remove_for_course deletes the row and subsequent lookups return null.
     */
    public function test_remove_for_course(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $repo = new funder_link_repository();

        $repo->upsert((int) $course->id, funder_link_repository::FUNDER_REGION);
        $this->assertNotNull($repo->get_for_course((int) $course->id));

        $repo->remove_for_course((int) $course->id);
        $this->assertNull($repo->get_for_course((int) $course->id));
    }

    /**
     * valid_funders exposes the five supported codes.
     */
    public function test_valid_funders(): void {
        $codes = funder_link_repository::valid_funders();
        $this->assertContains('CPF', $codes);
        $this->assertContains('FT', $codes);
        $this->assertContains('OPCO', $codes);
        $this->assertContains('REGION', $codes);
        $this->assertContains('AUTRE', $codes);
        $this->assertCount(5, $codes);
    }
}
