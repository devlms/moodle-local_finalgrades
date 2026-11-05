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
 * A course's final grades: check, submit, approve.
 *
 * @package    local_finalgrades
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use core\output\notification;
use local_finalgrades\local\submission;

$id = required_param('id', PARAM_INT);
$filter = optional_param('filter', 'all', PARAM_ALPHA);
$action = optional_param('action', '', PARAM_ALPHA);

$course = get_course($id);
require_login($course);
$context = context_course::instance($course->id);
if (!has_capability('local/finalgrades:approve', $context)) {
    require_capability('local/finalgrades:submit', $context);
}

$url = new moodle_url('/local/finalgrades/index.php', ['id' => $course->id, 'filter' => $filter]);
$PAGE->set_url($url);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('pluginname', 'local_finalgrades') . ': ' . format_string($course->shortname));
$PAGE->set_heading(format_string($course->fullname));

if ($action !== '' && confirm_sesskey()) {
    $userids = optional_param_array('userids', [], PARAM_INT);
    if (!$userids) {
        redirect($url, get_string('choosestudents', 'local_finalgrades'), null, notification::NOTIFY_WARNING);
    }
    if ($action === 'submit') {
        require_capability('local/finalgrades:submit', $context);
        $count = submission::submit($course, $userids, $USER->id);
        redirect($url, get_string('submitted', 'local_finalgrades', $count), null, notification::NOTIFY_SUCCESS);
    }
    if ($action === 'approve' && get_config('local_finalgrades', 'requireapproval')) {
        require_capability('local/finalgrades:approve', $context);
        $count = submission::approve($course, $userids, $USER->id);
        redirect($url, get_string('approved', 'local_finalgrades', $count), null, notification::NOTIFY_SUCCESS);
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'local_finalgrades'));
echo $OUTPUT->render_from_template(
    'local_finalgrades/course_page',
    (new \local_finalgrades\output\course_page($course, $filter))->export_for_template($OUTPUT)
);
echo $OUTPUT->footer();
