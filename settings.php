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
 * Settings and the exports page.
 *
 * @package    local_finalgrades
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_finalgrades', new lang_string('pluginname', 'local_finalgrades'));
    $settings->add(new admin_setting_configcheckbox(
        'local_finalgrades/requireapproval',
        new lang_string('requireapproval', 'local_finalgrades'),
        new lang_string('requireapproval_desc', 'local_finalgrades'),
        0
    ));
    $settings->add(new admin_setting_configtext(
        'local_finalgrades/dropfolder',
        new lang_string('dropfolder', 'local_finalgrades'),
        new lang_string('dropfolder_desc', 'local_finalgrades'),
        '',
        PARAM_PATH
    ));
    $ADMIN->add('localplugins', $settings);
}

$ADMIN->add('grades', new admin_externalpage(
    'local_finalgrades_exports',
    new lang_string('exports', 'local_finalgrades'),
    new moodle_url('/local/finalgrades/exports.php'),
    'local/finalgrades:export'
));
