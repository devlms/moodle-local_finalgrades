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

namespace local_finalgrades\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider: submitted final grades about students, who submitted and reviewed them, and who made exports.
 *
 * Deleting a teacher's or approver's data keeps the grades without their id, because the grade is the
 * student's record. Export files are copies of what was already sent to the student records system.
 *
 * @package    local_finalgrades
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe the stored and sent data.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_finalgrades_sub', [
            'userid' => 'privacy:metadata:sub:userid',
            'grade' => 'privacy:metadata:sub:grade',
            'percentage' => 'privacy:metadata:sub:percentage',
            'letter' => 'privacy:metadata:sub:letter',
            'useridnumber' => 'privacy:metadata:sub:useridnumber',
            'status' => 'privacy:metadata:sub:status',
            'reason' => 'privacy:metadata:sub:reason',
            'submittedby' => 'privacy:metadata:sub:submittedby',
            'timesubmitted' => 'privacy:metadata:sub:timesubmitted',
            'reviewedby' => 'privacy:metadata:sub:reviewedby',
            'timereviewed' => 'privacy:metadata:sub:timereviewed',
        ], 'privacy:metadata:sub');
        $collection->add_database_table('local_finalgrades_export', [
            'createdby' => 'privacy:metadata:export:createdby',
            'timecreated' => 'privacy:metadata:export:timecreated',
        ], 'privacy:metadata:export');
        $collection->add_external_location_link('studentrecords', [
            'useridnumber' => 'privacy:metadata:studentrecords:useridnumber',
            'fullname' => 'privacy:metadata:studentrecords:fullname',
            'grade' => 'privacy:metadata:studentrecords:grade',
            'submittedby' => 'privacy:metadata:studentrecords:submittedby',
        ], 'privacy:metadata:studentrecords');
        $collection->add_subsystem_link('core_files', [], 'privacy:metadata:files');
        return $collection;
    }

    /**
     * Course contexts where the user is a student, submitter or reviewer; the system context for exports.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $list = new contextlist();
        $list->add_from_sql(
            "SELECT ctx.id FROM {local_finalgrades_sub} s
            JOIN {context} ctx ON ctx.instanceid = s.courseid AND ctx.contextlevel = :level
            WHERE s.userid = :u1 OR s.submittedby = :u2 OR s.reviewedby = :u3",
            ['level' => CONTEXT_COURSE, 'u1' => $userid, 'u2' => $userid, 'u3' => $userid]
        );
        $list->add_from_sql(
            "SELECT ctx.id FROM {context} ctx
            WHERE ctx.contextlevel = :level
              AND EXISTS (SELECT 1 FROM {local_finalgrades_export} e WHERE e.createdby = :userid)",
            ['level' => CONTEXT_SYSTEM, 'userid' => $userid]
        );
        return $list;
    }

    /**
     * Users with data in a context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if ($context instanceof \context_course) {
            foreach (['userid', 'submittedby', 'reviewedby'] as $column) {
                $userlist->add_from_sql(
                    $column,
                    "SELECT $column FROM {local_finalgrades_sub} WHERE courseid = :courseid AND $column > 0",
                    ['courseid' => $context->instanceid]
                );
            }
        } else if ($context instanceof \context_system) {
            $userlist->add_from_sql('createdby', 'SELECT createdby FROM {local_finalgrades_export} WHERE createdby > 0', []);
        }
    }

    /**
     * Export the user's grades as a student, and what they did as staff.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        $userid = (int) $contextlist->get_user()->id;
        $folder = [get_string('pluginname', 'local_finalgrades')];
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof \context_system) {
                $exports = $DB->get_records('local_finalgrades_export', ['createdby' => $userid], 'id');
                writer::with_context($context)->export_data($folder, (object) ['exports' => array_values(array_map(fn($e) => [
                    'filename' => $e->filename,
                    'rowcount' => (int) $e->rowcount,
                    'timecreated' => transform::datetime($e->timecreated),
                ], $exports))]);
                continue;
            }
            if (!$context instanceof \context_course) {
                continue;
            }
            $rows = $DB->get_records('local_finalgrades_sub', ['courseid' => $context->instanceid], 'id');
            $grades = $actions = [];
            foreach ($rows as $row) {
                if ((int) $row->userid === $userid) {
                    $grades[] = [
                        'grade' => $row->grade,
                        'percentage' => $row->percentage,
                        'letter' => $row->letter,
                        'idnumber' => $row->useridnumber,
                        'status' => $row->status,
                        'reason' => $row->reason,
                        'timesubmitted' => transform::datetime($row->timesubmitted),
                        'timereviewed' => $row->timereviewed ? transform::datetime($row->timereviewed) : null,
                        'sent' => transform::yesno($row->exportid > 0),
                    ];
                }
                if ((int) $row->submittedby === $userid) {
                    $actions[] = ['action' => 'submitted', 'student' => (int) $row->userid,
                        'time' => transform::datetime($row->timesubmitted)];
                }
                if ((int) $row->reviewedby === $userid) {
                    $actions[] = ['action' => $row->status, 'student' => (int) $row->userid,
                        'reason' => $row->reason, 'time' => transform::datetime($row->timereviewed)];
                }
            }
            writer::with_context($context)->export_data($folder, (object) ['grades' => $grades, 'actions' => $actions]);
        }
    }

    /**
     * Delete everything in a course context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if ($context instanceof \context_course) {
            $DB->delete_records('local_finalgrades_sub', ['courseid' => $context->instanceid]);
        }
    }

    /**
     * Delete one user's data in the approved contexts.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        $userid = (int) $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            self::delete_users($context, [$userid]);
        }
    }

    /**
     * Delete several users' data in one context.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        self::delete_users($userlist->get_context(), array_map('intval', $userlist->get_userids()));
    }

    /**
     * Students lose their rows; staff ids are cleared from rows and exports.
     *
     * @param \context $context
     * @param int[] $userids
     */
    protected static function delete_users(\context $context, array $userids): void {
        global $DB;
        if (!$userids) {
            return;
        }
        [$in, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        if ($context instanceof \context_system) {
            $DB->set_field_select('local_finalgrades_export', 'createdby', 0, "createdby $in", $params);
            return;
        }
        if (!$context instanceof \context_course) {
            return;
        }
        $params['courseid'] = $context->instanceid;
        $DB->delete_records_select('local_finalgrades_sub', "courseid = :courseid AND userid $in", $params);
        foreach (['submittedby', 'reviewedby'] as $column) {
            $DB->set_field_select('local_finalgrades_sub', $column, 0, "courseid = :courseid AND $column $in", $params);
        }
    }
}
