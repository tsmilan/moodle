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
 * Session handler mocking interface.
 *
 * @package    core
 * @author     Darren Cocco <moodle@darren.cocco.id.au>
 * @author     Trisha Milan <trishamilan@catalyst-au.net>
 * @copyright  2022 Monash University (http://www.monash.edu)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core\session\util;

defined('MOODLE_INTERNAL') || die();

interface session_store_interface {

    /**
     * Returns all session records.
     *
     * @return \Iterator
     */
    public function get_all_sessions() : \Iterator;

    /**
     * Returns a single session record for this session id.
     *
     * @param string $sid
     * @return \stdClass
     */
    public function get_session_by_sid($sid);

    /**
     * Returns all the session records for this user id.
     *
     * @param int $userid
     * @return array
     */
    public function get_sessions_by_userid($userid);

    /**
     * Insert new empty session record.
     *
     * @param int $userid
     * @return \stdClass
     */
    public function add_session($userid);

    /**
     * Update a session record.
     *
     * @param \stdClass $record
     * @return bool
     */
    public function update_session($record);

    /**
     * Delete all the session data.
     *
     * @return bool
     */
    public function delete_all_sessions();

    /**
     * Delete a session record for this session id.
     *
     * @param string $sid
     * @return bool
     */
    public function delete_session_by_sid($sid);

    /**
     * Kill sessions of users with disabled plugins
     *
     * @param string $pluginname
     * @return void
     */
    public function kill_sessions_for_auth_plugin($pluginname);

    /**
     * Periodic timed-out session cleanup.
     */
    public function gc();
}
