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

namespace local_finalgrades\output;

use local_finalgrades\local\exporter;

/**
 * Tests for the data of the exports page.
 *
 * @package    local_finalgrades
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_finalgrades\output\exports_page
 */
final class exports_page_test extends \advanced_testcase {
    public static function setUpBeforeClass(): void {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');
        parent::setUpBeforeClass();
    }

    public function test_pending_by_course_and_past_exports(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['fullname' => 'History 101', 'idnumber' => 'HIST101']);
        $teacher = $gen->create_and_enrol($course, 'editingteacher');
        $item = $gen->create_grade_item(['courseid' => $course->id]);
        $students = [];
        foreach ([70, 80, 90] as $grade) {
            $student = $gen->create_and_enrol($course, 'student');
            \grade_item::fetch(['id' => $item->id])->update_final_grade($student->id, $grade);
            $students[] = $student->id;
        }
        \local_finalgrades\local\submission::submit($course, [$students[0]], $teacher->id);
        $first = exporter::run();
        \local_finalgrades\local\submission::submit($course, [$students[1], $students[2]], $teacher->id);

        $data = json_decode(json_encode((new exports_page())->export_for_template($PAGE->get_renderer('core'))), true);
        $this->assertSame(2, $data['pendingcount']);
        $this->assertSame([['course' => 'History 101', 'idnumber' => 'HIST101', 'new' => 2, 'change' => 0, 'withdrawn' => 0]],
            array_map(fn($c) => array_intersect_key($c, array_flip(['course', 'idnumber', 'new', 'change', 'withdrawn'])),
                $data['pending']));
        $this->assertCount(1, $data['exports']);
        $this->assertSame($first->filename, $data['exports'][0]['filename']);
        $this->assertSame(get_string('bytask', 'local_finalgrades'), $data['exports'][0]['by']);
        $this->assertStringContainsString('/local_finalgrades/export/' . $first->id . '/', $data['exports'][0]['url']);
    }
}
