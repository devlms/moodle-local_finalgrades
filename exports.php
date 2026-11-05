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
 * Final grade exports: what waits to be sent, export now, and the past files.
 *
 * @package    local_finalgrades
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use core\output\notification;

admin_externalpage_setup('local_finalgrades_exports');
$url = new moodle_url('/local/finalgrades/exports.php');

if (optional_param('action', '', PARAM_ALPHA) === 'export' && confirm_sesskey()) {
    $export = \local_finalgrades\local\exporter::run($USER->id);
    redirect(
        $url,
        $export ? get_string('exportmade', 'local_finalgrades', $export) : get_string('nothingtoexport', 'local_finalgrades'),
        null,
        $export ? notification::NOTIFY_SUCCESS : notification::NOTIFY_INFO
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('exports', 'local_finalgrades'));
echo $OUTPUT->render_from_template(
    'local_finalgrades/exports_page',
    (new \local_finalgrades\output\exports_page())->export_for_template($OUTPUT)
);
echo $OUTPUT->footer();
