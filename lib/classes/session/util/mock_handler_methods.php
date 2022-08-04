<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Mock handler methods class.
 *
 * @package    core
 * @author     Darren Cocco <moodle@darren.cocco.id.au>
 * @author     Trisha Milan <trishamilan@catalyst-au.net>
 * @copyright  2022 Monash University (http://www.monash.edu)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core\session\util;

defined('MOODLE_INTERNAL') || die();

class mock_handler_methods implements handler_mocking_interface {

    /**
     * Insert a new session record to be used in unit tests.
     *
     * @param \stdClass $record
     * @return int Inserted record id.
     */
    public function add_test_session($record) {
        global $DB, $USER;

        $data = new \stdClass();
        $data->state = isset($record->state) ? $record->state : 0;
        $data->sid = isset($record->sid) ? $record->sid : session_id();
        $data->sessdata = isset($record->sessdata) ? $record->sessdata : null;
        $data->userid = isset($record->userid) ? $record->userid : $USER->id;
        $data->timecreated = isset($record->timecreated) ? $record->timecreated : time();
        $data->timemodified = isset($record->timemodified) ? $record->timemodified : time();
        $data->firstip = isset($record->firstip) ? $record->firstip : getremoteaddr();
        $data->lastip = isset($record->lastip) ? $record->lastip : getremoteaddr();

        return $DB->insert_record('sessions', $data);
    }

    /**
     * Returns all sessions records.
     *
     * @return \Iterator
     */
    public function get_all_sessions() : \Iterator {
        global $DB;

        $records = $DB->get_records('sessions');
        return new \ArrayIterator($records);
    }

    /**
     * Returns the number of all sessions stored.
     *
     * @return int
     */
    public function count_sessions() {
        global $DB;

        return $DB->count_records('sessions');
    }
}
