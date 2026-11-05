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

namespace local_finalgrades\local;

/**
 * Tests for submitting, withdrawing and approving final grades.
 *
 * @package    local_finalgrades
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_finalgrades\local\submission
 */
final class submission_test extends \advanced_testcase {
    /** @var \stdClass */
    private $course;
    /** @var \grade_item */
    private $item;
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
        $this->item = new \grade_item($gen->create_grade_item(['courseid' => $this->course->id, 'grademax' => 100]), false);
    }

    /**
     * Enrol a student and give them a grade out of 100.
     *
     * @param float|null $grade
     * @param array $fields User fields.
     * @return \stdClass
     */
    private function student(?float $grade, array $fields = []): \stdClass {
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student', $fields);
        if ($grade !== null) {
            $this->grade($student->id, $grade);
        }
        return $student;
    }

    /**
     * Set a student's item grade, as the gradebook does: on a freshly fetched item.
     *
     * @param int $userid
     * @param float $grade
     */
    private function grade(int $userid, float $grade): void {
        \grade_item::fetch(['id' => $this->item->id])->update_final_grade($userid, $grade);
    }

    /**
     * The student's course total now.
     *
     * @param int $userid
     * @return float|null
     */
    private function total(int $userid): ?float {
        grade_regrade_final_grades($this->course->id);
        $courseitem = \grade_item::fetch_course_item($this->course->id);
        $grade = \grade_grade::fetch(['itemid' => $courseitem->id, 'userid' => $userid]);
        return $grade && $grade->finalgrade !== null ? (float) $grade->finalgrade : null;
    }

    public function test_submitted_total_is_frozen_until_withdrawn(): void {
        $student = $this->student(70, ['idnumber' => 'S1001']);
        $this->assertEquals(70, $this->total($student->id));

        $this->assertSame(1, submission::submit($this->course, [$student->id], $this->teacher->id));

        // A late change to an item does not move the submitted total.
        $this->grade($student->id, 90);
        $this->assertEquals(70, $this->total($student->id));

        submission::withdraw($this->course, $student->id, 'Late coursework marked', $this->teacher->id);
        $this->assertEquals(90, $this->total($student->id));
    }

    public function test_submit_stores_a_snapshot(): void {
        global $DB;
        $student = $this->student(72.5, ['idnumber' => 'S1001']);
        submission::submit($this->course, [$student->id], $this->teacher->id);

        $row = $DB->get_record('local_finalgrades_sub', ['courseid' => $this->course->id, 'userid' => $student->id]);
        $this->assertEquals(72.5, $row->grade);
        $this->assertSame('72.50 %', $row->percentage);
        $this->assertSame('C-', $row->letter);
        $this->assertSame('S1001', $row->useridnumber);
        $this->assertSame('HIST101-2026', $row->courseidnumber);
        $this->assertSame(submission::SUBMITTED, $row->status);
        $this->assertEquals($this->teacher->id, $row->submittedby);
    }

    public function test_submit_skips_missing_grades_already_submitted_and_non_students(): void {
        $graded = $this->student(60);
        $ungraded = $this->student(null);
        $stranger = $this->getDataGenerator()->create_user();

        $this->assertSame(1, submission::submit($this->course, [$graded->id, $ungraded->id, $stranger->id], $this->teacher->id));
        $this->assertSame(0, submission::submit($this->course, [$graded->id], $this->teacher->id));
    }

    public function test_withdraw_needs_a_reason_and_a_current_submission(): void {
        $student = $this->student(60);
        try {
            submission::withdraw($this->course, $student->id, 'x', $this->teacher->id);
            $this->fail('Withdrew a grade that was never submitted');
        } catch (\moodle_exception $e) {
            $this->assertSame('notsubmitted', $e->errorcode);
        }
        submission::submit($this->course, [$student->id], $this->teacher->id);
        try {
            submission::withdraw($this->course, $student->id, '  ', $this->teacher->id);
            $this->fail('Withdrew without a reason');
        } catch (\moodle_exception $e) {
            $this->assertSame('reasonrequired', $e->errorcode);
        }
    }

    public function test_withdrawn_grade_can_be_submitted_again_and_keeps_history(): void {
        global $DB;
        $student = $this->student(60);
        submission::submit($this->course, [$student->id], $this->teacher->id);
        submission::withdraw($this->course, $student->id, 'Resit', $this->teacher->id);
        $this->grade($student->id, 65);
        $this->assertSame(1, submission::submit($this->course, [$student->id], $this->teacher->id));

        $rows = $DB->get_records('local_finalgrades_sub', ['userid' => $student->id], 'id');
        $this->assertSame([submission::WITHDRAWN, submission::SUBMITTED], array_values(array_column($rows, 'status')));
        $this->assertSame('Resit', reset($rows)->reason);
        $this->assertEquals(65, submission::current($this->course->id, $student->id)->grade);
    }
}
