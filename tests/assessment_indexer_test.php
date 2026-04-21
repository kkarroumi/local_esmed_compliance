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
 * Assessment indexer tests.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_esmed_compliance;

use local_esmed_compliance\assessment\assessment_repository;
use local_esmed_compliance\assessment\indexer;
use local_esmed_compliance\assessment\tag_repository;

/**
 * Tests for the  component.
 *
 * @covers \local_esmed_compliance\assessment\indexer
 * @covers \local_esmed_compliance\assessment\assessment_repository
 * @covers \local_esmed_compliance\assessment\tag_repository
 */
final class assessment_indexer_test extends \advanced_testcase {
    /**
     * An attempt on an untagged module is ignored.
     */
    public function test_untagged_module_is_not_indexed(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        $id = (new indexer())->index_attempt(
            (int) $user->id,
            10,
            555,
            7.0,
            10.0,
            1700000000,
            'quiz_attempts',
            9001,
            1700000100
        );

        $this->assertNull($id);
        $this->assertEquals(
            0,
            $DB->count_records(assessment_repository::TABLE, ['userid' => $user->id])
        );
    }

    /**
     * Tagged modules record categorised attempts with a computed percent.
     */
    public function test_tagged_module_indexes_attempt_with_percent(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        $tags = new tag_repository();
        $tags->set_type(555, 10, tag_repository::TYPE_EVALUATION_SOMMATIVE, (int) $user->id, 1700000000);

        $id = (new indexer())->index_attempt(
            (int) $user->id,
            10,
            555,
            7.0,
            10.0,
            1700000500,
            'quiz_attempts',
            9001,
            1700000600
        );

        $this->assertNotNull($id);
        $record = $DB->get_record(assessment_repository::TABLE, ['id' => $id], '*', MUST_EXIST);
        $this->assertEquals($user->id, $record->userid);
        $this->assertEquals(555, $record->cmid);
        $this->assertEquals(tag_repository::TYPE_EVALUATION_SOMMATIVE, $record->assessment_type);
        $this->assertEquals(7.0, (float) $record->score);
        $this->assertEquals(10.0, (float) $record->max_score);
        $this->assertEquals(70.0, (float) $record->grade_percent);
        $this->assertEquals('quiz_attempts', $record->attempt_source_table);
        $this->assertEquals(9001, (int) $record->attempt_id_moodle);
    }

    /**
     * Reindexing the same (source table, source attempt id) yields the existing row.
     */
    public function test_index_attempt_is_deduplicated(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        (new tag_repository())->set_type(555, 10, tag_repository::TYPE_EXAMEN_BLANC, null, 1700000000);

        $indexer = new indexer();
        $first = $indexer->index_attempt((int) $user->id, 10, 555, 5.0, 10.0, 1700000500, 'quiz_attempts', 9001);
        $second = $indexer->index_attempt((int) $user->id, 10, 555, 5.0, 10.0, 1700000500, 'quiz_attempts', 9001);

        $this->assertEquals($first, $second);
        $this->assertEquals(
            1,
            $DB->count_records(assessment_repository::TABLE, ['userid' => $user->id])
        );
    }

    /**
     * A max score of zero produces a null grade_percent rather than dividing by zero.
     */
    public function test_zero_max_score_yields_null_percent(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        (new tag_repository())->set_type(555, 10, tag_repository::TYPE_DEVOIR_FORMATIF, null, 1700000000);

        $id = (new indexer())->index_attempt(
            (int) $user->id,
            10,
            555,
            0.0,
            0.0,
            1700000500,
            'assign_submission',
            4242
        );

        $record = $DB->get_record(assessment_repository::TABLE, ['id' => $id], '*', MUST_EXIST);
        $this->assertNull($record->grade_percent);
    }

    /**
     * set_type rejects a category outside the whitelist.
     */
    public function test_set_type_rejects_unknown_category(): void {
        $this->resetAfterTest();
        $this->expectException(\coding_exception::class);
        (new tag_repository())->set_type(555, 10, 'random_category', null, 1700000000);
    }

    /**
     * Clearing a tag makes subsequent attempts untagged again.
     */
    public function test_clear_tag_stops_indexing(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        $tags = new tag_repository();
        $tags->set_type(555, 10, tag_repository::TYPE_QUIZ_PEDAGO, null, 1700000000);
        $tags->clear(555);

        $id = (new indexer())->index_attempt(
            (int) $user->id,
            10,
            555,
            8.0,
            10.0,
            1700000500,
            'quiz_attempts',
            9001
        );

        $this->assertNull($id);
        $this->assertEquals(
            0,
            $DB->count_records(assessment_repository::TABLE, ['userid' => $user->id])
        );
    }
}
