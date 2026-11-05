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
 * Withdraw or return a submitted final grade, with a reason.
 *
 * @package    local_finalgrades
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use core\output\notification;
use local_finalgrades\local\submission;

$id = required_param('id', PARAM_INT);
$userid = required_param('userid', PARAM_INT);
$action = required_param('action', PARAM_ALPHA);

$course = get_course($id);
require_login($course);
$context = context_course::instance($course->id);
if ($action === 'return') {
    require_capability('local/finalgrades:approve', $context);
} else {
    $action = 'withdraw';
    require_capability('local/finalgrades:submit', $context);
}

$back = new moodle_url('/local/finalgrades/index.php', ['id' => $course->id]);
$row = submission::current($course->id, $userid);
if (!$row) {
    redirect($back, get_string('notsubmitted', 'local_finalgrades'), null, notification::NOTIFY_WARNING);
}
$student = core_user::get_user($userid, '*', MUST_EXIST);

$url = new moodle_url('/local/finalgrades/reason.php', ['id' => $course->id, 'userid' => $userid, 'action' => $action]);
$PAGE->set_url($url);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string($action . 'title', 'local_finalgrades', fullname($student)));
$PAGE->set_heading(format_string($course->fullname));

$form = new \local_finalgrades\form\reason_form($url, ['action' => $action]);
if ($form->is_cancelled()) {
    redirect($back);
}
if ($data = $form->get_data()) {
    if ($action === 'return') {
        submission::return_grade($course, $userid, $data->reason, $USER->id);
    } else {
        submission::withdraw($course, $userid, $data->reason, $USER->id);
    }
    redirect($back, get_string($action . 'done', 'local_finalgrades', fullname($student)), null, notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string($action . 'title', 'local_finalgrades', fullname($student)));
echo html_writer::tag('p', get_string($action . 'help', 'local_finalgrades'), ['class' => 'local-finalgrades-intro']);
$facts = [
    get_string('coursetotal', 'local_finalgrades') => format_float((float) $row->grade, 2),
    get_string('percentage', 'local_finalgrades') => $row->percentage,
    get_string('letter', 'local_finalgrades') => $row->letter,
    get_string('submittedon', 'local_finalgrades') => userdate($row->timesubmitted, get_string('strftimedatetime', 'langconfig')),
    get_string('sentlabel', 'local_finalgrades') => get_string($row->exportid ? 'yes' : 'no'),
];
echo html_writer::start_tag('dl', ['class' => 'local-finalgrades-summary']);
foreach ($facts as $label => $value) {
    echo html_writer::tag('dt', $label) . html_writer::tag('dd', s($value));
}
echo html_writer::end_tag('dl');
$form->display();
echo $OUTPUT->footer();
