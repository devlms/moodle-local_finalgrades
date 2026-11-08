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

use moodle_exception;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/gradelib.php');

/**
 * Submitting, withdrawing, approving and returning a course's final grades.
 *
 * A submission stores what the teacher saw (course total, percentage, letter, ID numbers) and locks the
 * student's course total in the gradebook, so later item changes cannot move it. Withdrawn and returned rows
 * stay as history; the current submission is the latest submitted or approved row.
 *
 * @package    local_finalgrades
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class submission {
    /** Submitted by the teacher. */
    public const SUBMITTED = 'submitted';
    /** Approved, when approval is required. */
    public const APPROVED = 'approved';
    /** Sent back to the teacher by an approver. */
    public const RETURNED = 'returned';
    /** Taken back by the teacher. */
    public const WITHDRAWN = 'withdrawn';
    /** Statuses that hold the gradebook total. */
    public const CURRENT = [self::SUBMITTED, self::APPROVED];

    /**
     * The course's students: active enrolments in a gradebook role, by last name.
     *
     * @param int $courseid
     * @return stdClass[] User records keyed by id.
     */
    public static function students(int $courseid): array {
        global $CFG, $DB;
        $context = \context_course::instance($courseid);
        $roles = array_filter(explode(',', (string) $CFG->gradebookroles));
        if (!$roles) {
            return [];
        }
        [$in, $params] = $DB->get_in_or_equal(array_map('intval', $roles), SQL_PARAMS_NAMED, 'role');
        [$enrolsql, $enrolparams] = get_enrolled_sql($context, '', 0, true);
        $names = implode(', ', array_map(fn($f) => "u.$f", \core_user\fields::get_name_fields()));
        $sql = "SELECT u.id, u.idnumber, u.email, u.username, $names
                  FROM {user} u
                  JOIN ($enrolsql) je ON je.id = u.id
                 WHERE u.deleted = 0
                   AND EXISTS (SELECT 1 FROM {role_assignments} ra
                                WHERE ra.userid = u.id AND ra.contextid = :contextid AND ra.roleid $in)
              ORDER BY u.lastname, u.firstname, u.id";
        return $DB->get_records_sql($sql, $params + $enrolparams + ['contextid' => $context->id]);
    }

    /**
     * The course total grade of a student, refreshed.
     *
     * @param int $courseid
     * @param int $userid
     * @return \grade_grade|null Null when the course has no total yet.
     */
    public static function total(int $courseid, int $userid): ?\grade_grade {
        $item = \grade_item::fetch_course_item($courseid);
        $grade = \grade_grade::fetch(['itemid' => $item->id, 'userid' => $userid]);
        if (!$grade) {
            return null;
        }
        $grade->grade_item = $item;
        return $grade;
    }

    /**
     * The student's current submission: the latest one that is submitted or approved.
     *
     * @param int $courseid
     * @param int $userid
     * @return stdClass|null
     */
    public static function current(int $courseid, int $userid): ?stdClass {
        global $DB;
        [$in, $params] = $DB->get_in_or_equal(self::CURRENT, SQL_PARAMS_NAMED);
        $rows = $DB->get_records_select(
            'local_finalgrades_sub',
            "courseid = :courseid AND userid = :userid AND status $in",
            $params + ['courseid' => $courseid, 'userid' => $userid],
            'id DESC',
            '*',
            0,
            1
        );
        return $rows ? reset($rows) : null;
    }

    /**
     * Submit the course totals of these students and lock them in the gradebook.
     *
     * Students without a course total, already submitted, or not students of the course are skipped.
     *
     * @param stdClass $course
     * @param int[] $userids
     * @param int $by The teacher.
     * @return int How many grades were submitted.
     */
    public static function submit(stdClass $course, array $userids, int $by): int {
        // One submit per course at a time: a double click or two teachers must not create two rows.
        $lock = \core\lock\lock_config::get_lock_factory('local_finalgrades')->get_lock('submit_' . $course->id, 30);
        if (!$lock) {
            throw new \moodle_exception('error:busy', 'local_finalgrades');
        }
        try {
            return self::submit_locked($course, $userids, $by);
        } finally {
            $lock->release();
        }
    }

    /**
     * The work of submit(), while holding the course lock.
     *
     * @param stdClass $course
     * @param int[] $userids
     * @param int $by
     * @return int
     */
    private static function submit_locked(stdClass $course, array $userids, int $by): int {
        global $DB;
        grade_regrade_final_grades($course->id);
        $students = self::students($course->id);
        $count = 0;
        foreach (array_unique(array_map('intval', $userids)) as $userid) {
            if (!isset($students[$userid]) || self::current($course->id, $userid)) {
                continue;
            }
            $grade = self::total($course->id, $userid);
            if (!$grade || $grade->finalgrade === null) {
                continue;
            }
            $value = (float) $grade->finalgrade;
            $id = $DB->insert_record('local_finalgrades_sub', (object) [
                'courseid' => $course->id,
                'userid' => $userid,
                'grade' => $value,
                'percentage' => grade_format_gradevalue($value, $grade->grade_item, true, GRADE_DISPLAY_TYPE_PERCENTAGE),
                'letter' => grade_format_gradevalue($value, $grade->grade_item, true, GRADE_DISPLAY_TYPE_LETTER),
                'useridnumber' => $students[$userid]->idnumber,
                'courseidnumber' => $course->idnumber,
                'status' => self::SUBMITTED,
                'submittedby' => $by,
                'timesubmitted' => time(),
            ]);
            // Submitted means locked. If the gradebook refuses the lock, the grade is not submitted.
            if (!$grade->set_locked(1, false, false)) {
                $DB->delete_records('local_finalgrades_sub', ['id' => $id]);
                continue;
            }
            self::log('grade_submitted', $course->id, $id, $userid);
            $count++;
        }
        return $count;
    }

    /**
     * Take back a submitted grade: unlock the total so it follows the gradebook again.
     *
     * @param stdClass $course
     * @param int $userid
     * @param string $reason Required, kept with the history.
     * @param int $by
     */
    public static function withdraw(stdClass $course, int $userid, string $reason, int $by): void {
        $row = self::close($course->id, $userid, self::WITHDRAWN, $reason, $by);
        self::log('grade_withdrawn', $course->id, $row->id, $userid, ['reason' => $row->reason]);
    }

    /**
     * Approve submitted grades. Approved grades keep the gradebook total locked.
     *
     * @param stdClass $course
     * @param int[] $userids
     * @param int $by The approver.
     * @return int How many grades were approved.
     */
    public static function approve(stdClass $course, array $userids, int $by): int {
        global $DB;
        $count = 0;
        foreach (array_unique(array_map('intval', $userids)) as $userid) {
            $row = self::current($course->id, $userid);
            if (!$row || $row->status !== self::SUBMITTED) {
                continue;
            }
            $DB->update_record('local_finalgrades_sub', (object) [
                'id' => $row->id,
                'status' => self::APPROVED,
                'reviewedby' => $by,
                'timereviewed' => time(),
            ]);
            self::log('grade_approved', $course->id, $row->id, $userid);
            $count++;
        }
        return $count;
    }

    /**
     * Send a submitted grade back to the teacher with a reason, and unlock the total.
     *
     * @param stdClass $course
     * @param int $userid
     * @param string $reason
     * @param int $by The approver.
     */
    public static function return_grade(stdClass $course, int $userid, string $reason, int $by): void {
        $row = self::close($course->id, $userid, self::RETURNED, $reason, $by);
        self::log('grade_returned', $course->id, $row->id, $userid, ['reason' => $row->reason]);
    }

    /**
     * Trigger one of this plugin's events about a student's grade.
     *
     * @param string $name Event class name.
     * @param int $courseid
     * @param int $rowid
     * @param int $userid The student.
     * @param array $other
     */
    protected static function log(string $name, int $courseid, int $rowid, int $userid, array $other = []): void {
        $class = '\\local_finalgrades\\event\\' . $name;
        $class::create([
            'context' => \context_course::instance($courseid),
            'objectid' => $rowid,
            'relateduserid' => $userid,
            'other' => $other,
        ])->trigger();
    }

    /**
     * Close the current submission with a status and a reason, and unlock the total.
     *
     * @param int $courseid
     * @param int $userid
     * @param string $status
     * @param string $reason
     * @param int $by
     * @return stdClass The closed row.
     */
    protected static function close(int $courseid, int $userid, string $status, string $reason, int $by): stdClass {
        global $DB;
        $row = self::current($courseid, $userid);
        if (!$row) {
            throw new moodle_exception('notsubmitted', 'local_finalgrades');
        }
        $reason = trim($reason);
        if ($reason === '') {
            throw new moodle_exception('reasonrequired', 'local_finalgrades');
        }
        $row->status = $status;
        $row->reason = $reason;
        $row->reviewedby = $by;
        $row->timereviewed = time();
        $DB->update_record('local_finalgrades_sub', $row);
        self::unlock($courseid, $userid);
        return $row;
    }

    /**
     * Unlock a student's course total and let the gradebook recalculate it.
     *
     * @param int $courseid
     * @param int $userid
     */
    protected static function unlock(int $courseid, int $userid): void {
        $grade = self::total($courseid, $userid);
        if ($grade && $grade->is_locked()) {
            $grade->set_locked(0, false, false);
            $grade->grade_item->force_regrading();
            grade_regrade_final_grades($courseid);
        }
    }
}
