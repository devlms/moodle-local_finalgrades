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

namespace local_finalgrades\privacy;

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;
use local_finalgrades\local\exporter;
use local_finalgrades\local\submission;

/**
 * Tests for the privacy provider.
 *
 * @package    local_finalgrades
 * @category   test
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_finalgrades\privacy\provider
 */
final class provider_test extends provider_testcase {
    /** @var \context_course */
    private \context_course $context;
    /** @var \stdClass[] */
    private array $u = [];

    public static function setUpBeforeClass(): void {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');
        parent::setUpBeforeClass();
    }

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $this->context = \context_course::instance($course->id);
        $this->u['teacher'] = $gen->create_and_enrol($course, 'editingteacher');
        $this->u['approver'] = $gen->create_user();
        $this->u['s'] = $gen->create_and_enrol($course, 'student');
        $this->u['other'] = $gen->create_and_enrol($course, 'student');
        $this->u['registrar'] = $gen->create_user();
        $item = $gen->create_grade_item(['courseid' => $course->id]);
        foreach (['s' => 60, 'other' => 75] as $key => $grade) {
            \grade_item::fetch(['id' => $item->id])->update_final_grade($this->u[$key]->id, $grade);
        }
        submission::submit($course, [$this->u['s']->id, $this->u['other']->id], $this->u['teacher']->id);
        submission::withdraw($course, $this->u['s']->id, 'Resit booked', $this->u['teacher']->id);
        submission::submit($course, [$this->u['s']->id], $this->u['teacher']->id);
        submission::approve($course, [$this->u['s']->id], $this->u['approver']->id);
        exporter::run($this->u['registrar']->id);
    }

    public function test_contexts(): void {
        foreach (['s', 'teacher', 'approver'] as $key) {
            $ids = provider::get_contexts_for_userid((int) $this->u[$key]->id)->get_contextids();
            $this->assertSame([(int) $this->context->id], array_map('intval', $ids), $key);
        }
        $ids = provider::get_contexts_for_userid((int) $this->u['registrar']->id)->get_contextids();
        $this->assertSame([(int) \context_system::instance()->id], array_map('intval', $ids));
    }

    public function test_users_in_context(): void {
        $list = new userlist($this->context, 'local_finalgrades');
        provider::get_users_in_context($list);
        $expected = array_map(fn($k) => (int) $this->u[$k]->id, ['teacher', 'approver', 's', 'other']);
        sort($expected);
        $ids = array_map('intval', $list->get_userids());
        sort($ids);
        $this->assertSame($expected, $ids);
    }

    public function test_export_for_a_student(): void {
        $this->export_all_data_for_user((int) $this->u['s']->id, 'local_finalgrades');
        $data = writer::with_context($this->context)->get_data([get_string('pluginname', 'local_finalgrades')]);
        $this->assertCount(2, $data->grades);
        $this->assertSame('withdrawn', $data->grades[0]['status']);
        $this->assertSame('Resit booked', $data->grades[0]['reason']);
        $this->assertSame('approved', $data->grades[1]['status']);
        $this->assertEquals(60, $data->grades[1]['grade']);
        $this->assertSame([], $data->actions);
    }

    public function test_export_for_a_teacher(): void {
        $this->export_all_data_for_user((int) $this->u['teacher']->id, 'local_finalgrades');
        $data = writer::with_context($this->context)->get_data([get_string('pluginname', 'local_finalgrades')]);
        $this->assertSame([], $data->grades);
        // Submitted both students, withdrew one, submitted it again.
        $this->assertCount(4, $data->actions);
        $this->assertSame(['submitted', 'withdrawn', 'submitted', 'submitted'], array_column($data->actions, 'action'));
    }

    public function test_delete_for_a_student_removes_their_rows_only(): void {
        global $DB;
        provider::delete_data_for_user(new approved_contextlist($this->u['s'], 'local_finalgrades', [$this->context->id]));
        $this->assertFalse($DB->record_exists('local_finalgrades_sub', ['userid' => $this->u['s']->id]));
        $this->assertTrue($DB->record_exists('local_finalgrades_sub', ['userid' => $this->u['other']->id]));
    }

    public function test_delete_for_staff_keeps_grades_without_their_name(): void {
        global $DB;
        provider::delete_data_for_user(new approved_contextlist($this->u['teacher'], 'local_finalgrades', [$this->context->id]));
        provider::delete_data_for_user(new approved_contextlist($this->u['approver'], 'local_finalgrades', [$this->context->id]));
        provider::delete_data_for_user(new approved_contextlist(
            $this->u['registrar'], 'local_finalgrades', [\context_system::instance()->id]
        ));
        $this->assertSame(3, $DB->count_records('local_finalgrades_sub'));
        $this->assertSame(0, $DB->count_records_select('local_finalgrades_sub', 'submittedby > 0 OR reviewedby > 0'));
        $this->assertSame(0, $DB->count_records_select('local_finalgrades_export', 'createdby > 0'));
    }

    public function test_delete_users_and_whole_context(): void {
        global $DB;
        provider::delete_data_for_users(new approved_userlist($this->context, 'local_finalgrades', [(int) $this->u['other']->id]));
        $this->assertSame(2, $DB->count_records('local_finalgrades_sub'));
        provider::delete_data_for_all_users_in_context($this->context);
        $this->assertSame(0, $DB->count_records('local_finalgrades_sub'));
    }
}
