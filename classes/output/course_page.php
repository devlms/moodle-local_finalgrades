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
    public const BADGES = [
        'missing' => 'badge bg-light text-dark border',
        'open' => 'badge bg-secondary',
        'returned' => 'badge bg-warning text-dark',
        'waiting' => 'badge bg-info text-dark',
        'submitted' => 'badge bg-success',
        'approved' => 'badge bg-success',
        'sent' => 'badge bg-dark',
    ];

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
        $pictures = $DB->get_records_list('user', 'id', array_keys($students), '', \core_user\fields::for_userpic()->get_sql('', false, '', '', false)->selects);

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
                'badge' => self::BADGES[$status],
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
        $tiles = [];
        foreach ($names as $name) {
            $tiles[] = [
                'name' => $name,
                'label' => get_string('tile:' . $name, 'local_finalgrades'),
                'count' => $counts[$name] ?? 0,
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
}
