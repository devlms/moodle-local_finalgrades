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
 * Final grades were exported for the student records system.
 *
 * @package    local_finalgrades
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grades_exported extends \core\event\base {
    /**
     * Set basic properties.
     */
    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_finalgrades_export';
    }

    /**
     * Event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('event:exported', 'local_finalgrades');
    }

    /**
     * Description for the logs.
     *
     * @return string
     */
    public function get_description() {
        return "Final grades export with id '{$this->objectid}' was made with {$this->other['rowcount']} rows.";
    }

    /**
     * Link to the exports page.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/local/finalgrades/exports.php');
    }

    /**
     * Check the event data.
     */
    protected function validate_data() {
        parent::validate_data();
        if (!isset($this->other['rowcount'])) {
            throw new \coding_exception('The \'rowcount\' value must be set in other.');
        }
    }

    /**
     * Restore mapping of the export row.
     *
     * @return array
     */
    public static function get_objectid_mapping() {
        return ['db' => 'local_finalgrades_export', 'restore' => \core\event\base::NOT_MAPPED];
    }

    /**
     * Restore mapping of other values.
     *
     * @return bool
     */
    public static function get_other_mapping() {
        return false;
    }
}
