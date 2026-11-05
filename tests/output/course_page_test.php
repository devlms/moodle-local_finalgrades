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
use local_finalgrades\local\submission;

/**
 * Tests for the data of the course's final grades page.
 *
 * @package    local_finalgrades
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_finalgrades\output\course_page
 */
final class course_page_test extends \advanced_testcase {
    /** @var \stdClass */
    private $course;
    /** @var int */
    private $itemid;
    /** @var \stdClass */
    private $teacher;

    public static function setUpBeforeClass(): void {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');
        parent::setUpBeforeClass();
    }

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $this->course = $gen->create_course(['idnumber' => 'HIST101-2026']);
        $this->teacher = $gen->create_and_enrol($this->course, 'editingteacher');
        $this->itemid = $gen->create_grade_item(['courseid' => $this->course->id, 'grademax' => 100])->id;
    }

    /**
     * Enrol a student, graded when a grade is given.
     *
     * @param string $lastname
     * @param float|null $grade
     * @param string $idnumber
     * @return \stdClass
     */
    private function student(string $lastname, ?float $grade, string $idnumber = 'S1'): \stdClass {
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student', [
            'firstname' => 'Ana', 'lastname' => $lastname, 'idnumber' => $idnumber,
        ]);
        if ($grade !== null) {
            \grade_item::fetch(['id' => $this->itemid])->update_final_grade($student->id, $grade);
        }
        return $student;
    }

    /**
     * Export the page as the teacher sees it.
     *
     * @param string $filter
     * @return array
     */
    private function export(string $filter = 'all'): array {
        global $PAGE;
        $this->setUser($this->teacher);
        $page = new course_page($this->course, $filter);
        return json_decode(json_encode($page->export_for_template($PAGE->get_renderer('core'))), true);
    }

    /**
     * Rows keyed by last name.
     *
     * @param array $data
     * @return array
     */
    private function rows(array $data): array {
        return array_column($data['rows'], null, 'lastname');
    }

    public function test_each_student_shows_where_their_grade_is(): void {
        $open = $this->student('Open', 81);
        $missing = $this->student('Missing', null);
        $submitted = $this->student('Submitted', 64.25);
        $returned = $this->student('Returned', 50);
        $sent = $this->student('Sent', 90);
        submission::submit($this->course, [$submitted->id, $returned->id, $sent->id], $this->teacher->id);
        submission::return_grade($this->course, $returned->id, 'Late penalty missing', $this->teacher->id);
        // The sent student goes out in an export, then the submitted one is added later.
        submission::withdraw($this->course, $submitted->id, 'Hold', $this->teacher->id);
        exporter::run();
        submission::submit($this->course, [$submitted->id], $this->teacher->id);

        $rows = $this->rows($this->export());
        $this->assertSame('open', $rows['Open']['status']);
        $this->assertSame('B-', $rows['Open']['letter']);
        $this->assertTrue($rows['Open']['cansubmit']);
        $this->assertSame('missing', $rows['Missing']['status']);
        $this->assertFalse($rows['Missing']['cansubmit']);
        $this->assertSame('submitted', $rows['Submitted']['status']);
        $this->assertSame('64.25', $rows['Submitted']['total']);
        $this->assertTrue($rows['Submitted']['canwithdraw']);
        $this->assertSame('returned', $rows['Returned']['status']);
        $this->assertSame('Late penalty missing', $rows['Returned']['reason']);
        $this->assertTrue($rows['Returned']['cansubmit']);
        $this->assertSame('sent', $rows['Sent']['status']);
        $this->assertSame('badge bg-dark', $rows['Sent']['badge']);
    }

    public function test_tiles_count_and_filter(): void {
        $this->student('Open', 81);
        $done = $this->student('Done', 70, 'S2');
        submission::submit($this->course, [$done->id], $this->teacher->id);

        $data = $this->export();
        $tiles = array_column($data['tiles'], 'count', 'name');
        $this->assertSame(['todo' => 1, 'ready' => 1, 'sent' => 0], $tiles);
        $this->assertSame(1, $data['submitcount']);

        $this->assertSame(['Done'], array_column($this->export('ready')['rows'], 'lastname'));
    }

    public function test_approval_adds_a_waiting_tile_and_approve_action(): void {
        set_config('requireapproval', 1, 'local_finalgrades');
        $done = $this->student('Done', 70);
        submission::submit($this->course, [$done->id], $this->teacher->id);

        $data = $this->export();
        $this->assertSame(['todo', 'waiting', 'ready', 'sent'], array_column($data['tiles'], 'name'));
        $this->assertTrue($data['requireapproval']);
        // Teachers are not approvers by default.
        $this->assertFalse($data['canapprove']);
        $this->assertSame('waiting', $this->rows($data)['Done']['status']);
    }

    public function test_warns_about_missing_id_numbers(): void {
        global $DB;
        $DB->set_field('course', 'idnumber', '', ['id' => $this->course->id]);
        $this->course->idnumber = '';
        $this->student('Noid', 70, '');

        $data = $this->export();
        $this->assertTrue($data['nocourseidnumber']);
        $this->assertTrue($this->rows($data)['Noid']['noidnumber']);
    }
}
