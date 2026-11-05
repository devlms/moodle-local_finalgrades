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

namespace local_finalgrades\event;

/**
 * A teacher withdrew a student's submitted final grade.
 *
 * @package    local_finalgrades
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grade_withdrawn extends \core\event\base {
    /**
     * Set basic properties.
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
        $this->data['objecttable'] = 'local_finalgrades_sub';
    }

    /**
     * Event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('event:withdrawn', 'local_finalgrades');
    }

    /**
     * Description for the logs.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '{$this->userid}' withdrew the final grade of the user with id '{$this->relateduserid}'"
            . " in the course with id '{$this->courseid}'.";
    }

    /**
     * Link to the course's final grades page.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/local/finalgrades/index.php', ['id' => $this->courseid]);
    }

    /**
     * Restore mapping of the submission row.
     *
     * @return array
     */
    public static function get_objectid_mapping() {
        return ['db' => 'local_finalgrades_sub', 'restore' => \core\event\base::NOT_MAPPED];
    }
}
