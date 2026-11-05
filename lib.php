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
 * Library functions for local_finalgrades.
 *
 * @package    local_finalgrades
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Add "Final grades" to the course menu for teachers and approvers.
 *
 * @param navigation_node $navigation
 * @param stdClass $course
 * @param context $context
 */
function local_finalgrades_extend_navigation_course(navigation_node $navigation, stdClass $course, context $context) {
    if ($course->id == SITEID) {
        return;
    }
    if (!has_any_capability(['local/finalgrades:submit', 'local/finalgrades:approve'], $context)) {
        return;
    }
    $navigation->add(
        get_string('finalgrades', 'local_finalgrades'),
        new moodle_url('/local/finalgrades/index.php', ['id' => $course->id]),
        navigation_node::TYPE_SETTING,
        null,
        'local_finalgrades',
        new pix_icon('i/grades', '')
    );
}

/**
 * Serve the export files to users who may download them.
 *
 * @param stdClass $course
 * @param stdClass|null $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool False when the file is not found.
 */
function local_finalgrades_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel != CONTEXT_SYSTEM || $filearea !== \local_finalgrades\local\exporter::FILEAREA) {
        return false;
    }
    require_login(null, false);
    require_capability('local/finalgrades:export', $context);
    $itemid = (int) array_shift($args);
    $filename = array_pop($args);
    $file = get_file_storage()->get_file($context->id, 'local_finalgrades', $filearea, $itemid, '/', $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }
    send_stored_file($file, 0, 0, true, $options);
}
