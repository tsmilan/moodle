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
 * An adhoc task for indexing a single global search area.
 *
 * @package    core
 * @author     Trisha Milan <trishamilan@catalyst-au.net>
 * @copyright  2026 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace core\task;

/**
 * Indexes a single global search area as an adhoc task, enabling parallel indexing.
 *
 * Dispatched by search_index_task when searchmaxparallelindexing > 0. Each task
 * processes one area. If the area is only partially indexed within the time limit,
 * the task re-queues itself for continuation.
 *
 * @package    core
 * @author     Trisha Milan <trishamilan@catalyst-au.net>
 * @copyright  2026 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class search_index_area_task extends adhoc_task {
    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('taskglobalsearchindexarea', 'admin');
    }

    /**
     * Index one search area, re-queuing if the time limit is hit before completion.
     */
    public function execute(): void {
        if (!\core_search\manager::is_indexing_enabled()) {
            return;
        }

        $data = $this->get_custom_data();
        $areaid = $data->areaid;

        $area = \core_search\manager::get_search_area($areaid);
        if (!$area || !$area->is_enabled()) {
            return;
        }

        $timelimit = (int) get_config('core', 'searchindextime');
        $manager = \core_search\manager::instance();
        $result = $manager->index_area($areaid, $timelimit, new \text_progress_trace());

        if ($result->partial) {
            // Re-queue for continuation. $checkforexisting must be false here: at the time
            // this call is made, the current task is still in task_adhoc (it is removed after
            // execute() returns successfully), so deduplication would incorrectly block the
            // new entry. It is safe to skip dedup because this is the only running task for
            // this area — the dispatcher honours the concurrency limit and uses dedup itself.
            $task = new self();
            $task->set_custom_data(['areaid' => $areaid]);
            \core\task\manager::queue_adhoc_task($task, false);
        }
    }
}
