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
 * Strings for local_finalgrades.
 *
 * @package    local_finalgrades
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['action:change'] = 'Changed';
$string['action:new'] = 'New';
$string['action:withdrawn'] = 'Withdrawn';
$string['actions'] = 'Actions';
$string['approved'] = 'Approved grades: {$a}.';
$string['approvehelp'] = 'Approved grades go to the student records system with the next export.';
$string['approveselected'] = 'Approve selected grades';
$string['bytask'] = 'Scheduled task';
$string['choosestudents'] = 'Tick at least one student first.';
$string['coursetotal'] = 'Course total';
$string['deselectall'] = 'Deselect all';
$string['dropfolder'] = 'Drop folder';
$string['dropfolder_desc'] = 'A folder on the Moodle server where each export file is also copied, for a job that loads it into the student records system. Leave empty to only keep the files in Moodle for download. The web server must be able to write to it.';
$string['dropfolderis'] = 'Each file is also copied to {$a}.';
$string['event:approved'] = 'Final grade approved';
$string['event:exported'] = 'Final grades exported';
$string['event:returned'] = 'Final grade returned';
$string['event:submitted'] = 'Final grade submitted';
$string['event:withdrawn'] = 'Final grade withdrawn';
$string['exportmade'] = 'Export made with {$a->rowcount} rows: {$a->filename}.';
$string['exportnow'] = 'Export now';
$string['exports'] = 'Final grade exports';
$string['exportsintro'] = 'Submitted final grades are collected into one CSV file per export, for the student records system. Each grade is sent once; a changed or withdrawn grade is sent again with its action.';
$string['exportsintro:approval'] = 'Approved final grades are collected into one CSV file per export, for the student records system. Each grade is sent once; a changed or withdrawn grade is sent again with its action.';
$string['file'] = 'File';
$string['filter'] = 'Show students';
$string['finalgrades'] = 'Final grades';
$string['finalgrades:approve'] = 'Approve or return submitted final grades';
$string['finalgrades:export'] = 'See all final grade submissions and download export files';
$string['finalgrades:submit'] = 'Submit and withdraw final grades';
$string['idnumber'] = 'ID number';
$string['intro'] = 'Check each course total, then submit. Submitted grades are locked in the gradebook and go to the student records system with the next export.';
$string['intro:approval'] = 'Check each course total, then submit. Submitted grades are locked in the gradebook. Once approved, they go to the student records system with the next export.';
$string['letter'] = 'Letter';
$string['made'] = 'Made';
$string['madeby'] = 'By';
$string['nextrun'] = 'The next scheduled export runs {$a}.';
$string['nocourseidnumber'] = 'This course has no ID number, so the student records system cannot match its grades. Ask a site administrator to set it in the course settings before you submit.';
$string['noexports'] = 'No exports yet.';
$string['noidnumber'] = 'No ID number';
$string['nostudents'] = 'This course has no students.';
$string['nostudentsinfilter'] = 'No students here.';
$string['nothingtoexport'] = 'Nothing is waiting to be sent.';
$string['notsubmitted'] = 'This grade has not been submitted.';
$string['pastexports'] = 'Past exports';
$string['pendingcount'] = 'Grades waiting to be sent: {$a}';
$string['percentage'] = 'Percentage';
$string['pluginname'] = 'Final grade submission';
$string['privacy:metadata:export'] = 'Export files of final grades.';
$string['privacy:metadata:export:createdby'] = 'Who made the export, when made by hand.';
$string['privacy:metadata:export:timecreated'] = 'When the export was made.';
$string['privacy:metadata:files'] = 'Export files hold students\' names, ID numbers and final grades as sent to the student records system.';
$string['privacy:metadata:studentrecords'] = 'Final grades are exported to the institution\'s student records system as a CSV file.';
$string['privacy:metadata:studentrecords:fullname'] = 'The student\'s name.';
$string['privacy:metadata:studentrecords:grade'] = 'The final grade, percentage and letter.';
$string['privacy:metadata:studentrecords:submittedby'] = 'The name of the teacher who submitted the grade.';
$string['privacy:metadata:studentrecords:useridnumber'] = 'The student\'s ID number.';
$string['privacy:metadata:sub'] = 'Final grades submitted for students, with their history.';
$string['privacy:metadata:sub:grade'] = 'The course total at submission.';
$string['privacy:metadata:sub:letter'] = 'The letter grade at submission.';
$string['privacy:metadata:sub:percentage'] = 'The percentage at submission.';
$string['privacy:metadata:sub:reason'] = 'Why the grade was withdrawn or returned.';
$string['privacy:metadata:sub:reviewedby'] = 'Who approved, returned or withdrew the grade.';
$string['privacy:metadata:sub:status'] = 'Whether the grade is submitted, approved, returned or withdrawn.';
$string['privacy:metadata:sub:submittedby'] = 'Who submitted the grade.';
$string['privacy:metadata:sub:timereviewed'] = 'When the grade was approved, returned or withdrawn.';
$string['privacy:metadata:sub:timesubmitted'] = 'When the grade was submitted.';
$string['privacy:metadata:sub:userid'] = 'The student.';
$string['privacy:metadata:sub:useridnumber'] = 'The student\'s ID number at submission.';
$string['reason'] = 'Reason';
$string['reason:returned'] = '{$a}';
$string['reason:withdrawn'] = 'Withdrawn: {$a}';
$string['reasonrequired'] = 'Give a reason.';
$string['requireapproval'] = 'Require approval';
$string['requireapproval_desc'] = 'Submitted grades wait for someone with the "Approve or return submitted final grades" permission, for example a head of department given it in a category, before they are exported.';
$string['return'] = 'Return';
$string['returndone'] = 'Grade returned to the teacher: {$a}.';
$string['returnhelp'] = 'The teacher sees your reason next to the student. The course total is unlocked so the teacher can fix it and submit again.';
$string['returntitle'] = 'Return the grade of {$a}';
$string['rows'] = 'Rows';
$string['selectall'] = 'Select all';
$string['sentlabel'] = 'Sent to records';
$string['status'] = 'Status';
$string['status:approved'] = 'Approved';
$string['status:missing'] = 'No course total';
$string['status:open'] = 'Not submitted';
$string['status:returned'] = 'Returned';
$string['status:sent'] = 'Sent';
$string['status:submitted'] = 'Submitted';
$string['status:waiting'] = 'Waiting for approval';
$string['student'] = 'Student';
$string['submithelp'] = 'Tick the students whose course total is final. Submitting locks the total in the gradebook; you can withdraw it later with a reason.';
$string['submitselected'] = 'Submit selected grades';
$string['submitted'] = 'Submitted grades: {$a}.';
$string['submittedon'] = 'Submitted';
$string['task:export'] = 'Export final grades for the student records system';
$string['tile:ready'] = 'Ready to send';
$string['tile:sent'] = 'Sent to records';
$string['tile:todo'] = 'Not submitted';
$string['tile:waiting'] = 'Waiting for approval';
$string['ungraded'] = 'Items not graded: {$a}';
$string['waiting'] = 'Waiting to be sent';
$string['withdraw'] = 'Withdraw';
$string['withdrawdone'] = 'Grade withdrawn: {$a}. Its course total follows the gradebook again.';
$string['withdrawhelp'] = 'The course total is unlocked and follows the gradebook again. If the grade was already sent, the next export tells the student records system it was withdrawn. Your reason is kept with the grade history.';
$string['withdrawtitle'] = 'Withdraw the grade of {$a}';
