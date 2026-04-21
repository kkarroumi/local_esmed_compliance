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
 * Bordereau service tests.
 *
 * @package    local_esmed_compliance
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_esmed_compliance;

use local_esmed_compliance\archive\archive_repository;
use local_esmed_compliance\funder\bordereau_builder;
use local_esmed_compliance\funder\bordereau_service;
use local_esmed_compliance\funder\funder_link_repository;
use local_esmed_compliance\session\tracker;
use local_esmed_compliance\tests\fixtures\fake_bordereau_renderer;
use local_esmed_compliance\tests\fixtures\fake_storage_adapter;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixtures/fake_bordereau_renderer.php');
require_once(__DIR__ . '/fixtures/fake_storage_adapter.php');

/**
 * Tests for the  component.
 *
 * @covers \local_esmed_compliance\funder\bordereau_service
 */
final class bordereau_service_test extends \advanced_testcase {
    /**
     * Generate seals one PDF and one CSV row sharing a common bordereau_group.
     */
    public function test_generate_seals_pdf_and_csv_pair(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $tracker = new tracker();
        $tracker->open_session((int) $user->id, null, null, (int) $course->id, 1700000000);
        $tracker->close_session((int) $user->id, tracker::CLOSURE_LOGOUT, 1700001800);

        $links = new funder_link_repository();
        $linkid = $links->upsert((int) $course->id, funder_link_repository::FUNDER_CPF, [
            'dossier_number' => 'CPF-XYZ',
        ]);

        $pdfrenderer = new fake_bordereau_renderer('pdf', 'application/pdf');
        $csvrenderer = new fake_bordereau_renderer('csv', 'text/csv; charset=utf-8');
        $storage = new fake_storage_adapter();

        $service = new bordereau_service(
            new bordereau_builder($links),
            $pdfrenderer,
            $csvrenderer,
            $storage,
            new archive_repository()
        );

        $result = $service->generate($linkid, 1700010000);

        $this->assertNotEquals($result['pdf']->verification_token, $result['csv']->verification_token);
        $this->assertSame(
            archive_repository::TYPE_BORDEREAU_FINANCEUR,
            $result['pdf']->archive_type
        );
        $this->assertSame(
            archive_repository::TYPE_BORDEREAU_FINANCEUR,
            $result['csv']->archive_type
        );
        $this->assertEquals($linkid, $result['pdf']->funderid);
        $this->assertEquals($linkid, $result['csv']->funderid);

        $pdfmeta = json_decode((string) $result['pdf']->metadata_json, true);
        $csvmeta = json_decode((string) $result['csv']->metadata_json, true);
        $this->assertSame($result['group_id'], $pdfmeta['bordereau_group']);
        $this->assertSame($result['group_id'], $csvmeta['bordereau_group']);
        $this->assertSame('pdf', $pdfmeta['format']);
        $this->assertSame('csv', $csvmeta['format']);

        // Stored bytes match the sealed hash in each row.
        foreach (['pdf', 'csv'] as $format) {
            $bytes = $storage->fetch((string) $result[$format]->file_path);
            $this->assertNotNull($bytes);
            $this->assertSame(hash('sha256', $bytes), (string) $result[$format]->sha256_hash);
        }
    }

    /**
     * Each renderer receives its own verification token and matching URL.
     */
    public function test_generate_wires_tokens_through_to_each_renderer(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $links = new funder_link_repository();
        $linkid = $links->upsert((int) $course->id, funder_link_repository::FUNDER_OPCO);

        $pdfrenderer = new fake_bordereau_renderer('pdf');
        $csvrenderer = new fake_bordereau_renderer('csv');

        $service = new bordereau_service(
            new bordereau_builder($links),
            $pdfrenderer,
            $csvrenderer,
            new fake_storage_adapter(),
            new archive_repository()
        );

        $result = $service->generate($linkid, 1700000000);

        $this->assertSame((string) $result['pdf']->verification_token, $pdfrenderer->lasttoken);
        $this->assertSame((string) $result['csv']->verification_token, $csvrenderer->lasttoken);
        $this->assertStringContainsString('verify.php?t=' . $result['pdf']->verification_token, (string) $pdfrenderer->lasturl);
        $this->assertStringContainsString('verify.php?t=' . $result['csv']->verification_token, (string) $csvrenderer->lasturl);
    }

    /**
     * Retention defaults to five years when the setting is unset or zero.
     */
    public function test_generate_defaults_retention_to_five_years(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();

        set_config('retention_years', 0, 'local_esmed_compliance');

        $links = new funder_link_repository();
        $linkid = $links->upsert((int) $course->id, funder_link_repository::FUNDER_AUTRE);

        $service = new bordereau_service(
            new bordereau_builder($links),
            new fake_bordereau_renderer('pdf'),
            new fake_bordereau_renderer('csv'),
            new fake_storage_adapter(),
            new archive_repository()
        );

        $now = 1700000000;
        $result = $service->generate($linkid, $now);

        $expected = strtotime('+5 years', $now);
        $this->assertEquals($expected, (int) $result['pdf']->retention_until);
        $this->assertEquals($expected, (int) $result['csv']->retention_until);
    }

    /**
     * Relative filename embeds year, month, funder link id, course and token prefix.
     */
    public function test_relative_filename_layout(): void {
        $name = bordereau_service::relative_filename(
            7,
            42,
            str_repeat('d', 64),
            'csv',
            strtotime('2026-04-20 12:00:00 UTC')
        );
        $this->assertStringStartsWith('bordereau/2026/04/', $name);
        $this->assertStringContainsString('f7_c42_dddddddddddddddd', $name);
        $this->assertStringEndsWith('.csv', $name);
    }
}
