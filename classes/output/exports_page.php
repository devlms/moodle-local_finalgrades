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

use local_finalgrades\local\exporter;
use renderer_base;
use stdClass;

/**
 * The registrar's page: what waits to be sent, per course, and the past export files.
 *
 * @package    local_finalgrades
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class exports_page implements \renderable, \templatable {
    /** How many past exports to list. */
    public const LIMIT = 50;

    /**
     * Data for the template.
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output): stdClass {
        global $DB;
        $pending = exporter::lines(exporter::pending());
        $bycourse = [];
        foreach ($pending as $row) {
            $bycourse[$row->courseid] ??= ['new' => 0, 'change' => 0, 'withdrawn' => 0];
            $bycourse[$row->courseid][$row->action]++;
        }
        $courses = $DB->get_records_list('course', 'id', array_keys($bycourse), 'fullname', 'id, fullname, idnumber');
        $pendingrows = [];
        foreach ($courses as $course) {
            $pendingrows[] = [
                'course' => format_string($course->fullname),
                'idnumber' => $course->idnumber,
                'url' => (new \moodle_url('/local/finalgrades/index.php', ['id' => $course->id]))->out(false),
            ] + $bycourse[$course->id];
        }

        $exports = [];
        $records = $DB->get_records('local_finalgrades_export', null, 'id DESC', '*', 0, self::LIMIT);
        $makers = $DB->get_records_list('user', 'id', array_filter(array_column($records, 'createdby')), '',
            'id, ' . implode(', ', \core_user\fields::get_name_fields()));
        $system = \context_system::instance();
        foreach ($records as $export) {
            $exports[] = [
                'filename' => $export->filename,
                'rowcount' => (int) $export->rowcount,
                'time' => userdate($export->timecreated, get_string('strftimedatetimeshort', 'langconfig')),
                'by' => isset($makers[$export->createdby])
                    ? fullname($makers[$export->createdby]) : get_string('bytask', 'local_finalgrades'),
                'url' => \moodle_url::make_pluginfile_url(
                    $system->id, 'local_finalgrades', exporter::FILEAREA, $export->id, '/', $export->filename, true
                )->out(false),
            ];
        }

        $task = \core\task\manager::get_scheduled_task(\local_finalgrades\task\export_grades::class);
        $next = $task && !$task->get_disabled() ? $task->get_next_run_time() : 0;
        $folder = (string) get_config('local_finalgrades', 'dropfolder');
        return (object) [
            'pendingcount' => count($pending),
            'pending' => $pendingrows,
            'haspending' => (bool) $pendingrows,
            'exports' => $exports,
            'hasexports' => (bool) $exports,
            'pendinglabel' => get_string('pendingcount', 'local_finalgrades', count($pending)),
            'nextrun' => $next
                ? get_string('nextrun', 'local_finalgrades', userdate($next, get_string('strftimedatetimeshort', 'langconfig')))
                : '',
            'requireapproval' => (bool) get_config('local_finalgrades', 'requireapproval'),
            'dropfolder' => $folder === '' ? '' : get_string('dropfolderis', 'local_finalgrades', $folder),
            'actionurl' => (new \moodle_url('/local/finalgrades/exports.php'))->out(false),
            'sesskey' => sesskey(),
        ];
    }
}
