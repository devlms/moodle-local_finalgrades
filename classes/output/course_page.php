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

use local_finalgrades\local\submission;
use moodle_url;
use renderer_base;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/gradelib.php');

/**
 * The course's final grades page: every student's course total and where their grade is.
 *
 * @package    local_finalgrades
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_page implements \renderable, \templatable {
    /** Badge classes per status. */
    public const STATUSES = ['missing', 'open', 'returned', 'waiting', 'submitted', 'approved', 'sent'];

    /** Which tile each status counts under. */
    public const TILES = [
        'missing' => 'todo',
        'open' => 'todo',
        'returned' => 'todo',
        'waiting' => 'waiting',
        'submitted' => 'ready',
        'approved' => 'ready',
        'sent' => 'sent',
    ];

    /** @var stdClass */
    protected $course;
    /** @var string */
    protected $filter;

    /**
     * Constructor.
     *
     * @param stdClass $course
     * @param string $filter all or a tile name.
     */
    public function __construct(stdClass $course, string $filter = 'all') {
        $this->course = $course;
        $this->filter = $filter;
    }

    /**
     * Every student with their status, unfiltered.
     *
     * @param renderer_base $output
     * @return array[]
     */
    public function rows(renderer_base $output): array {
        global $DB;
        $context = \context_course::instance($this->course->id);
        $cansubmit = has_capability('local/finalgrades:submit', $context);
        $canapprove = has_capability('local/finalgrades:approve', $context);
        $approval = (bool) get_config('local_finalgrades', 'requireapproval');

        grade_regrade_final_grades($this->course->id);
        $item = \grade_item::fetch_course_item($this->course->id);
        $students = submission::students($this->course->id);
        if (!$students) {
            return [];
        }
        $totals = $DB->get_records_menu('grade_grades', ['itemid' => $item->id], '', 'userid, finalgrade');
        $latest = $this->latest_rows();
        $ungraded = $this->ungraded_counts(array_keys($students));
        $picturefields = \core_user\fields::for_userpic()->get_sql('', false, '', '', false)->selects;
        $pictures = $DB->get_records_list('user', 'id', array_keys($students), '', $picturefields);

        $rows = [];
        foreach ($students as $userid => $student) {
            $row = $latest[$userid] ?? null;
            $current = $row && in_array($row->status, submission::CURRENT, true) ? $row : null;
            $live = isset($totals[$userid]) && $totals[$userid] !== null ? (float) $totals[$userid] : null;
            $value = $current ? (float) $current->grade : $live;

            if ($current) {
                $status = $current->exportid ? 'sent' : ($current->status === submission::APPROVED
                    ? 'approved' : ($approval ? 'waiting' : 'submitted'));
            } else if ($value === null) {
                $status = 'missing';
            } else {
                $status = $row && $row->status === submission::RETURNED ? 'returned' : 'open';
            }
            $reason = !$current && $row ? (string) $row->reason : '';
            $back = ['id' => $this->course->id, 'userid' => $userid];
            $rows[] = [
                'userid' => $userid,
                'name' => fullname($student),
                'lastname' => $student->lastname,
                'picture' => isset($pictures[$userid])
                    ? $output->user_picture($pictures[$userid], ['size' => 35, 'link' => false, 'alttext' => false])
                    : '',
                'idnumber' => $student->idnumber,
                'noidnumber' => trim((string) $student->idnumber) === '',
                'total' => $value === null ? '' : grade_format_gradevalue($value, $item, true, GRADE_DISPLAY_TYPE_REAL),
                'percentage' => $current ? $current->percentage
                    : ($value === null ? '' : grade_format_gradevalue($value, $item, true, GRADE_DISPLAY_TYPE_PERCENTAGE)),
                'letter' => $current ? $current->letter
                    : ($value === null ? '' : grade_format_gradevalue($value, $item, true, GRADE_DISPLAY_TYPE_LETTER)),
                'status' => $status,
                'statuslabel' => get_string('status:' . $status, 'local_finalgrades'),
                'badge' => 'local-finalgrades-badge is-' . $status,
                'ungraded' => $ungraded[$userid] ?? 0,
                'ungradedlabel' => empty($ungraded[$userid]) || $current
                    ? '' : get_string('ungraded', 'local_finalgrades', $ungraded[$userid]),
                'tile' => self::TILES[$status],
                'reason' => $reason,
                'reasonlabel' => $reason === '' ? '' : get_string('reason:' . $row->status, 'local_finalgrades', $reason),
                'cansubmit' => $cansubmit && in_array($status, ['open', 'returned'], true),
                'canapprove' => $canapprove && $status === 'waiting',
                'canwithdraw' => $cansubmit && $current !== null,
                'canreturn' => $canapprove && $approval && $current !== null,
                'withdrawurl' => (new moodle_url('/local/finalgrades/reason.php', $back + ['action' => 'withdraw']))->out(false),
                'returnurl' => (new moodle_url('/local/finalgrades/reason.php', $back + ['action' => 'return']))->out(false),
            ];
        }
        return $rows;
    }

    /**
     * How many graded items of the course each student has no grade in yet.
     *
     * Moodle leaves empty grades out of the course total by default, so a total can look final while an exam is
     * still unmarked.
     *
     * @param int[] $userids
     * @return int[] Keyed by user id, only students with at least one.
     */
    protected function ungraded_counts(array $userids): array {
        global $DB;
        $items = $DB->get_fieldset_select(
            'grade_items',
            'id',
            "courseid = :courseid AND itemtype NOT IN ('course', 'category') AND gradetype <> :none",
            ['courseid' => $this->course->id, 'none' => GRADE_TYPE_NONE]
        );
        if (!$items || !$userids) {
            return [];
        }
        [$itemin, $params] = $DB->get_in_or_equal($items, SQL_PARAMS_NAMED, 'item');
        [$userin, $userparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'user');
        $graded = $DB->get_records_sql_menu("SELECT userid, COUNT(1) FROM {grade_grades}
            WHERE itemid $itemin AND userid $userin AND finalgrade IS NOT NULL GROUP BY userid", $params + $userparams);
        $counts = [];
        foreach ($userids as $userid) {
            $missing = count($items) - (int) ($graded[$userid] ?? 0);
            if ($missing > 0) {
                $counts[$userid] = $missing;
            }
        }
        return $counts;
    }

    /**
     * The latest submission row of each student in the course.
     *
     * @return stdClass[] Keyed by user id.
     */
    protected function latest_rows(): array {
        global $DB;
        $rows = $DB->get_records('local_finalgrades_sub', ['courseid' => $this->course->id], 'id ASC');
        $latest = [];
        foreach ($rows as $row) {
            $latest[$row->userid] = $row;
        }
        return $latest;
    }

    /**
     * Data for the template.
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output): stdClass {
        $context = \context_course::instance($this->course->id);
        $approval = (bool) get_config('local_finalgrades', 'requireapproval');
        $rows = $this->rows($output);

        $names = $approval ? ['todo', 'waiting', 'ready', 'sent'] : ['todo', 'ready', 'sent'];
        $filter = in_array($this->filter, $names, true) ? $this->filter : 'all';
        $counts = array_count_values(array_column($rows, 'tile'));
        // The stage that waits on this user: grades to submit for a teacher, grades to approve for an approver.
        $mine = has_capability('local/finalgrades:submit', $context) ? 'todo'
            : ($approval && has_capability('local/finalgrades:approve', $context) ? 'waiting' : '');
        $tiles = [];
        foreach ($names as $name) {
            $tiles[] = [
                'name' => $name,
                'label' => get_string('tile:' . $name, 'local_finalgrades'),
                'count' => $counts[$name] ?? 0,
                'needsyou' => $name === $mine && !empty($counts[$name]),
                'active' => $filter === $name,
                'url' => (new moodle_url('/local/finalgrades/index.php', [
                    'id' => $this->course->id, 'filter' => $filter === $name ? 'all' : $name,
                ]))->out(false),
            ];
        }
        $shown = $filter === 'all' ? $rows : array_values(array_filter($rows, fn($r) => $r['tile'] === $filter));
        $submitcount = count(array_filter($rows, fn($r) => $r['cansubmit']));
        $approvecount = count(array_filter($rows, fn($r) => $r['canapprove']));

        return (object) [
            'courseid' => $this->course->id,
            'tiles' => $tiles,
            'rows' => $shown,
            'hasrows' => (bool) $shown,
            'emptymessage' => get_string($rows ? 'nostudentsinfilter' : 'nostudents', 'local_finalgrades'),
            // A total out of 100 already is a percentage: the column would only repeat it.
            'showpercentage' => !self::out_of_hundred($this->course->id),
            'requireapproval' => $approval,
            'cansubmit' => has_capability('local/finalgrades:submit', $context),
            'canapprove' => $approval && has_capability('local/finalgrades:approve', $context),
            'submitcount' => $submitcount,
            'approvecount' => $approvecount,
            'hasselectable' => $submitcount + $approvecount > 0,
            'nocourseidnumber' => trim((string) $this->course->idnumber) === '',
            'intro' => get_string($approval ? 'intro:approval' : 'intro', 'local_finalgrades'),
            'actionurl' => (new moodle_url('/local/finalgrades/index.php'))->out(false),
            'filter' => $filter,
            'sesskey' => sesskey(),
        ];
    }

    /**
     * Whether the course total runs from 0 to 100, so its value is already a percentage.
     *
     * @param int $courseid
     * @return bool
     */
    private static function out_of_hundred(int $courseid): bool {
        $item = \grade_item::fetch_course_item($courseid);
        return abs((float) $item->grademin) < 0.00001 && abs((float) $item->grademax - 100) < 0.00001;
    }
}
