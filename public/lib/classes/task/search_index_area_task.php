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

use context_system;
use core\event\search_indexed;
use core\output\progress_trace\text_progress_trace;

/**
 * Indexes a single global search area as an adhoc task.
 *
 * Dispatched by search_index_task on every scheduled run. One task per enabled area,
 * deduped so only one instance per area is ever queued.
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
    public function get_name(): string {
        return get_string('taskglobalsearchindexarea', 'admin');
    }

    /**
     * Index one search area within the configured time limit.
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
        $manager->get_engine()->index_starting();
        $result = $manager->index_area($areaid, $timelimit, new text_progress_trace());
        $manager->get_engine()->index_complete($result->indexed);

        if ($result->indexed > 0) {
            $event = search_indexed::create(['context' => context_system::instance()]);
            $event->trigger();
        }
    }
}
