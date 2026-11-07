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

#[\PHPUnit\Framework\Attributes\CoversClass(\local_finalgrades\local\exporter::class)]
/**
 * Tests for the export of final grades to the student records system.
 *
 * @package    local_finalgrades
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_finalgrades\local\exporter
 */
final class exporter_test extends \advanced_testcase {
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
        $this->course = $gen->create_course(['idnumber' => 'HIST101-2026', 'shortname' => 'HIST101']);
        $this->teacher = $gen->create_and_enrol($this->course, 'editingteacher');
        $this->item = new \grade_item($gen->create_grade_item(['courseid' => $this->course->id, 'grademax' => 100]), false);
    }

    /**
     * Enrol a graded student.
     *
     * @param float $grade
     * @param string $idnumber
     * @return \stdClass
     */
    private function student(float $grade, string $idnumber): \stdClass {
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student', [
            'idnumber' => $idnumber, 'firstname' => 'Ana', 'lastname' => $idnumber,
        ]);
        $this->grade($student->id, $grade);
        return $student;
    }

    /**
     * Set an item grade on a freshly fetched item.
     *
     * @param int $userid
     * @param float $grade
     */
    private function grade(int $userid, float $grade): void {
        \grade_item::fetch(['id' => $this->item->id])->update_final_grade($userid, $grade);
    }

    /**
     * The rows of an export's CSV file, keyed by header.
     *
     * @param \stdClass $export
     * @return array[]
     */
    private function csv(\stdClass $export): array {
        $lines = array_map(fn($l) => str_getcsv($l, ',', '"', ''), explode("\n", trim(exporter::file($export)->get_content())));
        $header = array_shift($lines);
        return array_map(fn($line) => array_combine($header, $line), $lines);
    }

    public function test_nothing_to_send_makes_no_export(): void {
        $this->assertNull(exporter::run());
    }

    public function test_exports_submitted_grades_once(): void {
        global $DB;
        $student = $this->student(72.5, 'S1001');
        submission::submit($this->course, [$student->id], $this->teacher->id);

        $export = exporter::run();
        $this->assertSame(1, (int) $export->rowcount);
        $rows = $this->csv($export);
        $this->assertSame([
            'action' => 'new',
            'course_idnumber' => 'HIST101-2026',
            'course_shortname' => 'HIST101',
            'student_idnumber' => 'S1001',
            'student_name' => 'Ana S1001',
            'grade' => '72.50',
            'percentage' => '72.50 %',
            'letter' => 'C-',
            'submitted_by' => fullname($this->teacher),
            'submitted_at' => $rows[0]['submitted_at'],
        ], $rows[0]);
        $this->assertMatchesRegularExpression('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\dZ$/', $rows[0]['submitted_at']);
        $this->assertEquals($export->id, $DB->get_field('local_finalgrades_sub', 'exportid', ['userid' => $student->id]));

        // Sent rows are not sent again.
        $this->assertNull(exporter::run());
    }

    public function test_only_approved_grades_go_when_approval_is_required(): void {
        set_config('requireapproval', 1, 'local_finalgrades');
        $first = $this->student(80, 'S1');
        $second = $this->student(60, 'S2');
        submission::submit($this->course, [$first->id, $second->id], $this->teacher->id);
        $this->assertNull(exporter::run());

        submission::approve($this->course, [$second->id], $this->teacher->id);
        $rows = $this->csv(exporter::run());
        $this->assertSame(['S2'], array_column($rows, 'student_idnumber'));
    }

    public function test_withdrawn_and_changed_grades_are_reported(): void {
        $student = $this->student(60, 'S1');
        submission::submit($this->course, [$student->id], $this->teacher->id);
        exporter::run();

        submission::withdraw($this->course, $student->id, 'Resit passed', $this->teacher->id);
        $rows = $this->csv(exporter::run());
        $this->assertSame('withdrawn', $rows[0]['action']);
        $this->assertSame('60.00', $rows[0]['grade']);

        $this->grade($student->id, 68);
        submission::submit($this->course, [$student->id], $this->teacher->id);
        $rows = $this->csv(exporter::run());
        $this->assertSame(['change'], array_column($rows, 'action'));
        $this->assertSame('68.00', $rows[0]['grade']);
    }

    public function test_withdrawn_before_sending_is_never_reported(): void {
        $student = $this->student(60, 'S1');
        submission::submit($this->course, [$student->id], $this->teacher->id);
        submission::withdraw($this->course, $student->id, 'Typo', $this->teacher->id);
        $this->assertNull(exporter::run());
    }

    public function test_copies_the_file_to_the_drop_folder(): void {
        $dir = make_request_directory();
        set_config('dropfolder', $dir, 'local_finalgrades');
        $student = $this->student(60, 'S1');
        submission::submit($this->course, [$student->id], $this->teacher->id);
        $export = exporter::run();

        $this->assertFileExists($dir . '/' . $export->filename);
        $this->assertSame(exporter::file($export)->get_content(), file_get_contents($dir . '/' . $export->filename));
    }

    public function test_a_missing_drop_folder_does_not_lose_the_export(): void {
        set_config('dropfolder', '/nonexistent/finalgrades', 'local_finalgrades');
        $student = $this->student(60, 'S1');
        submission::submit($this->course, [$student->id], $this->teacher->id);
        $export = exporter::run();
        $this->assertDebuggingCalled();
        $this->assertNotNull(exporter::file($export));
    }

    public function test_export_event(): void {
        $student = $this->student(60, 'S1');
        submission::submit($this->course, [$student->id], $this->teacher->id);
        $sink = $this->redirectEvents();
        $export = exporter::run();
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(\local_finalgrades\event\grades_exported::class, $events[0]);
        $this->assertSame(1, $events[0]->other['rowcount']);
        $this->assertEquals($export->id, $events[0]->objectid);
    }

    public function test_a_resubmitted_grade_is_sent_as_one_change(): void {
        global $DB;
        $student = $this->student(60, 'S1');
        submission::submit($this->course, [$student->id], $this->teacher->id);
        exporter::run();
        submission::withdraw($this->course, $student->id, 'Re-marked', $this->teacher->id);
        $this->grade($student->id, 66);
        submission::submit($this->course, [$student->id], $this->teacher->id);

        $export = exporter::run();
        $this->assertSame(1, (int) $export->rowcount);
        $this->assertSame(['change'], array_column($this->csv($export), 'action'));
        // The withdrawal is settled too, so it is not sent later.
        $open = ['status' => submission::WITHDRAWN, 'closeexportid' => 0];
        $this->assertSame(0, $DB->count_records('local_finalgrades_sub', $open));
        $this->assertNull(exporter::run());
    }
}
