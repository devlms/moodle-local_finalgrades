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

use stdClass;

/**
 * Builds the CSV file of final grades for the student records system.
 *
 * Each run takes the grades not sent yet (approved ones only, when approval is required) and the sent grades
 * that were withdrawn or returned since, writes them to one CSV file stored with the File API, marks them as
 * sent, and copies the file to the drop folder when one is set.
 *
 * @package    local_finalgrades
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class exporter {
    /** File area of the export files, in the system context. */
    public const FILEAREA = 'export';

    /** Columns of the CSV file, in order. */
    public const COLUMNS = [
        'action', 'course_idnumber', 'course_shortname', 'student_idnumber', 'student_name',
        'grade', 'percentage', 'letter', 'submitted_by', 'submitted_at',
    ];

    /**
     * Statuses that may be sent.
     *
     * @return string[]
     */
    public static function sendable(): array {
        return get_config('local_finalgrades', 'requireapproval')
            ? [submission::APPROVED]
            : submission::CURRENT;
    }

    /**
     * Rows waiting to be sent: new or changed grades, and sent grades taken back since.
     *
     * A withdrawal is marked "superseded" when the same student's new grade goes in the same export: the
     * records system then gets one "change" row, and the withdrawal is settled without a line of its own.
     *
     * @param int $courseid 0 for every course.
     * @return stdClass[] Submission rows with "action" and "superseded" properties, oldest first.
     */
    public static function pending(int $courseid = 0): array {
        global $DB;
        [$sendin, $sendparams] = $DB->get_in_or_equal(self::sendable(), SQL_PARAMS_NAMED, 'send');
        [$closein, $closeparams] = $DB->get_in_or_equal([submission::WITHDRAWN, submission::RETURNED], SQL_PARAMS_NAMED, 'close');
        $coursesql = $courseid ? 'AND s.courseid = :courseid' : '';
        $sql = "SELECT s.*,
                       CASE WHEN s.status $closein THEN 'withdrawn'
                            WHEN EXISTS (SELECT 1 FROM {local_finalgrades_sub} p
                                          WHERE p.courseid = s.courseid AND p.userid = s.userid
                                            AND p.id < s.id AND p.exportid > 0) THEN 'change'
                            ELSE 'new' END AS action
                  FROM {local_finalgrades_sub} s
                 WHERE ((s.status $sendin AND s.exportid = 0)
                        OR (s.status IN ('" . submission::WITHDRAWN . "', '" . submission::RETURNED . "')
                            AND s.exportid > 0 AND s.closeexportid = 0))
                       $coursesql
              ORDER BY s.id";
        $rows = $DB->get_records_sql($sql, $sendparams + $closeparams + ['courseid' => $courseid]);
        $sending = [];
        foreach ($rows as $row) {
            if ($row->action !== 'withdrawn') {
                $sending[$row->courseid . '-' . $row->userid] = true;
            }
        }
        foreach ($rows as $row) {
            $row->superseded = $row->action === 'withdrawn' && isset($sending[$row->courseid . '-' . $row->userid]);
        }
        return $rows;
    }

    /**
     * The rows that get a line in the file.
     *
     * @param stdClass[] $rows Pending rows.
     * @return stdClass[]
     */
    public static function lines(array $rows): array {
        return array_filter($rows, fn($row) => !$row->superseded);
    }

    /**
     * Make one export of everything pending.
     *
     * @param int $by The user who asked for it, 0 for the scheduled task.
     * @return stdClass|null The export record, or null when nothing was pending.
     */
    public static function run(int $by = 0): ?stdClass {
        global $DB;
        $rows = self::pending();
        if (!$rows) {
            return null;
        }
        $now = time();
        $transaction = $DB->start_delegated_transaction();
        $export = (object) ['rowcount' => count(self::lines($rows)), 'filename' => '', 'createdby' => $by, 'timecreated' => $now];
        $export->id = $DB->insert_record('local_finalgrades_export', $export);
        $export->filename = 'final-grades-' . gmdate('Ymd-His', $now) . '-' . $export->id . '.csv';
        $DB->set_field('local_finalgrades_export', 'filename', $export->filename, ['id' => $export->id]);

        $file = get_file_storage()->create_file_from_string([
            'contextid' => \context_system::instance()->id,
            'component' => 'local_finalgrades',
            'filearea' => self::FILEAREA,
            'itemid' => $export->id,
            'filepath' => '/',
            'filename' => $export->filename,
        ], self::csv(self::lines($rows)));

        foreach ($rows as $row) {
            $field = $row->action === 'withdrawn' ? 'closeexportid' : 'exportid';
            $DB->set_field('local_finalgrades_sub', $field, $export->id, ['id' => $row->id]);
        }
        $transaction->allow_commit();

        self::drop($file, $export->filename);
        \local_finalgrades\event\grades_exported::create([
            'context' => \context_system::instance(),
            'objectid' => $export->id,
            'other' => ['rowcount' => (int) $export->rowcount],
        ])->trigger();
        return $export;
    }

    /**
     * The stored file of an export.
     *
     * @param stdClass $export
     * @return \stored_file|null
     */
    public static function file(stdClass $export): ?\stored_file {
        return get_file_storage()->get_file(
            \context_system::instance()->id,
            'local_finalgrades',
            self::FILEAREA,
            $export->id,
            '/',
            $export->filename
        ) ?: null;
    }

    /**
     * The CSV text for these rows.
     *
     * @param stdClass[] $rows Pending rows.
     * @return string
     */
    protected static function csv(array $rows): string {
        global $DB;
        $courses = $DB->get_records_list('course', 'id', array_unique(array_column($rows, 'courseid')), '', 'id, shortname');
        $userids = array_unique(array_merge(array_column($rows, 'userid'), array_column($rows, 'submittedby')));
        $users = $DB->get_records_list('user', 'id', $userids, '', 'id, ' . implode(', ', \core_user\fields::get_name_fields()));

        $fh = fopen('php://temp', 'w+');
        fputcsv($fh, self::COLUMNS, ',', '"', '');
        foreach ($rows as $row) {
            fputcsv($fh, [
                $row->action,
                $row->courseidnumber,
                $courses[$row->courseid]->shortname ?? '',
                $row->useridnumber,
                isset($users[$row->userid]) ? fullname($users[$row->userid]) : '',
                $row->grade === null ? '' : number_format((float) $row->grade, 2, '.', ''),
                $row->percentage,
                $row->letter,
                isset($users[$row->submittedby]) ? fullname($users[$row->submittedby]) : '',
                gmdate('Y-m-d\TH:i:s\Z', (int) $row->timesubmitted),
            ], ',', '"', '');
        }
        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);
        return $csv;
    }

    /**
     * Copy the file to the drop folder, when one is set. Written under a temporary name and renamed,
     * so a job reading the folder never sees half a file.
     *
     * @param \stored_file $file
     * @param string $filename
     */
    protected static function drop(\stored_file $file, string $filename): void {
        $folder = rtrim((string) get_config('local_finalgrades', 'dropfolder'), '/');
        if ($folder === '') {
            return;
        }
        if (!is_dir($folder) || !is_writable($folder)) {
            debugging("Final grades: the drop folder $folder does not exist or is not writable. "
                . "The export is still available to download.", DEBUG_NORMAL);
            return;
        }
        $temp = "$folder/.$filename.part";
        if ($file->copy_content_to($temp)) {
            rename($temp, "$folder/$filename");
        }
    }
}
