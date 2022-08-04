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
 * This trait includes functions to implement generic handler methods.
 *
 * @package    core
 * @author     Darren Cocco <moodle@darren.cocco.id.au>
 * @author     Trisha Milan <trishamilan@catalyst-au.net>
 * @copyright  2022 Monash University (http://www.monash.edu)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core\session\util;

defined('MOODLE_INTERNAL') || die();

/**
 * This trait includes functions to implement generic handler methods.
 *
 * @package    core
 * @author     Trisha Milan<trishamilan@catalyst-au.net>
 * @copyright  2022 Monash University (http://www.monash.edu)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait fallback_session_store {

    /**
     * Returns all session records.
     *
     * @return \Iterator
     */
    public function get_all_sessions() : \Iterator {
        global $DB;

        return $DB->get_recordset('sessions');
    }

    /**
     * Returns a single session record for this session id.
     *
     * @param string $sid
     * @return \stdClass
     */
    public function get_session_by_sid($sid) {
        global $DB;

        return $DB->get_record('sessions', ['sid' => $sid]);
    }

    /**
     * Returns all the session records for this user id.
     *
     * @param int $userid
     * @return array
     */
    public function get_sessions_by_userid($userid) {
        global $DB;

        return $DB->get_records('sessions', ['userid' => $userid]);
    }

    /**
     * Insert new empty session record.
     *
     * @param int $userid
     * @return \stdClass the new record
     */
    public function add_session($userid) {
        global $DB;

        $record = new \stdClass();
        $record->state       = 0;
        $record->sid         = session_id();
        $record->sessdata    = null;
        $record->userid      = $userid;
        $record->timecreated = $record->timemodified = time();
        $record->firstip     = $record->lastip = getremoteaddr();

        $record->id = $DB->insert_record('sessions', $record);

        return $record;
    }

    /**
     * Update a session record.
     *
     * @param \stdClass $record
     * @return bool
     */
    public function update_session($record) {
        global $DB;

        if (!$record) {
            return false;
        }

        if (!isset($record->id) && isset($record->sid)) {
            $record->id = $DB->get_field('sessions', 'id', ['sid' => $record->sid]);
        }

        return $DB->update_record('sessions', $record);
    }

    /**
     * Delete all the session data.
     *
     * @return bool
     */
    public function delete_all_sessions() {
        global $DB;

        return $DB->delete_records('sessions');
    }

    /**
     * Delete a session record for this session id.
     *
     * @param string $sid
     * @return bool
     */
    public function delete_session_by_sid($sid) {
        global $DB;

        return $DB->delete_records('sessions', array('sid' => $sid));
    }

    /**
     * Clean up expired sessions.
     *
     * @param int $maxlifetime
     * @param int $userid
     * @return void
     */
    protected function clean_up_expired_sessions($maxlifetime = null, $userid = null) {
        global $CFG;

        if (is_null($maxlifetime)) {
            $maxlifetime = $CFG->sessiontimeout;
        }

        if (is_null($userid)) {
            $this->clean_all_expired_sessions($maxlifetime);
            return;
        }
        $sessions = $this->get_sessions_by_userid($userid);
        foreach ($sessions as $session) {
            if ($session->timemodified < $maxlifetime) {
                $this->delete_session_by_sid($session->sid);
            }
        }
    }

    /**
     * Clean up all expired sessions.
     *
     * @param int $purgebefore
     * @return void
     */
    protected function clean_all_expired_sessions($purgebefore) {
        global $DB, $CFG;

        $authsequence = get_enabled_auth_plugins();
        $authsequence = array_flip($authsequence);
        unset($authsequence['nologin']); // No login means user cannot login.
        $authsequence = array_flip($authsequence);
        $authplugins = array();
        foreach ($authsequence as $authname) {
            $authplugins[$authname] = get_auth_plugin($authname);
        }
        $sql = "SELECT u.*, s.sid, s.timecreated AS s_timecreated, s.timemodified AS s_timemodified
                  FROM {user} u
                  JOIN {sessions} s ON s.userid = u.id
                 WHERE s.timemodified < :purgebefore AND u.id <> :guestid";
        $params = array('purgebefore' => $purgebefore, 'guestid' => $CFG->siteguest);

        $rs = $DB->get_recordset_sql($sql, $params);
        foreach ($rs as $user) {
            foreach ($authplugins as $authplugin) {
                /** @var \auth_plugin_base $authplugin*/
                if ($authplugin->ignore_timeout_hook($user, $user->sid, $user->s_timecreated, $user->s_timemodified)) {
                    continue 2;
                }
            }
            $this->delete_session_by_sid($user->sid);
        }
        $rs->close();
    }

    /**
     * Kill sessions of users with disabled plugins
     *
     * @param string $pluginname
     * @return void
     */
    public function kill_sessions_for_auth_plugin($pluginname) {
        global $DB;

        $rs = $DB->get_recordset('user', ['auth' => $pluginname], 'id ASC', 'id');
        foreach ($rs as $user) {
            $sessions = $this->get_sessions_by_userid($user->id);
            foreach ($sessions as $session) {
                $this->delete_session_by_sid($session->sid);
            }
        }
        $rs->close();
    }

    /**
     * Periodic timed-out session cleanup.
     */
    public function gc() {
        global $CFG, $DB;

        // This may take a long time...
        \core_php_time_limit::raise();

        $maxlifetime = $CFG->sessiontimeout;

        try {
            // Clean up expired sessions for real users only.
            $this->clean_up_expired_sessions(time() - $maxlifetime);

            // Delete expired sessions for guest user account, give them larger timeout, there is no security risk here.
            $purgebefore = time() - ($maxlifetime * 5);
            $this->clean_up_expired_sessions($purgebefore, $CFG->siteguest);

            // Delete expired sessions for userid = 0 (not logged in), better kill them asap to release memory.
            $purgebefore = time() - $maxlifetime;
            $this->clean_up_expired_sessions($purgebefore, 0);

            // Cleanup letfovers from the first browser access because it may set multiple cookies and then use only one.
            $purgebefore = time() - (60 * 3);
            $sessions = $this->get_sessions_by_userid(0);
            foreach ($sessions as $session) {
                if ($session->timemodified == $session->timecreated
                    && $session->timemodified < $purgebefore) {
                    $this->delete_session_by_sid($session->sid);
                }
            }

        } catch (\Exception $ex) {
            debugging('Error gc-ing sessions: '.$ex->getMessage(), DEBUG_NORMAL, $ex->getTrace());
        }
    }
}
