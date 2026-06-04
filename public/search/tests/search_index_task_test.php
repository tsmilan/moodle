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

namespace core_search;

use advanced_testcase;
use coding_exception;
use core\task\search_index_area_task;
use core\task\search_index_task;
use core_mocksearch\search\mock_search_area;
use core_search_generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use testable_core_search;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixtures/testable_core_search.php');
require_once(__DIR__ . '/fixtures/mock_search_area.php');

/**
 * Unit tests for search_index_task and search_index_area_task.
 *
 * @package     core_search
 * @copyright   2026 Catalyst IT
 * @author      Trisha Milan <trishamilan@catalyst-au.net>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(manager::class)]
#[CoversClass(search_index_task::class)]
#[CoversMethod(\core_search\manager::class, 'index_area')]
#[CoversMethod(\core\task\search_index_task::class, 'execute')]
final class search_index_task_test extends advanced_testcase {
    /** @var core_search_generator|null Generator for mock search area records. */
    private ?core_search_generator $searchgenerator = null;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    protected function tearDown(): void {
        testable_core_search::fake_current_time();
        if ($this->searchgenerator !== null) {
            $this->searchgenerator->tearDown();
            $this->searchgenerator = null;
        }
        manager::clear_static();
        parent::tearDown();
    }

    /**
     * Build a testable search manager with a single mock area containing $n records.
     *
     * @param int $n Number of records to create in the mock area.
     * @return array [$search, $areaid, $componentname, $varname]
     */
    private function setup_mock_area(int $n = 3): array {
        $generator = $this->getDataGenerator();
        $search = testable_core_search::instance();
        $area = new mock_search_area();
        $areaid = 'testareaid';
        $search->add_search_area($areaid, $area);
        $this->searchgenerator = $generator->get_plugin_generator('core_search');
        $this->searchgenerator->setUp();

        $time = time() - 100;
        for ($i = 0; $i < $n; $i++) {
            $this->searchgenerator->create_record((object)['timemodified' => $time + $i]);
        }

        [$componentname, $varname] = $area->get_config_var_name();
        return [$search, $areaid, $componentname, $varname];
    }

    /**
     * Test search_index_task queues one adhoc task per enabled area.
     * @return void
     */
    public function test_queues_area_tasks(): void {
        global $DB;

        $this->setup_mock_area(2);
        manager::clear_static();

        $task = new search_index_task();
        $task->execute();

        $queued = $DB->count_records('task_adhoc', ['classname' => '\\core\\task\\search_index_area_task']);
        $this->assertGreaterThanOrEqual(1, $queued);
    }

    /**
     * Test search_index_task does nothing when indexing is disabled.
     * @return void
     */
    public function test_skips_when_indexing_disabled(): void {
        global $DB;

        set_config('enableglobalsearch', true);
        set_config('searchindexwhendisabled', 0);
        set_config('enableglobalsearch', false);

        $task = new search_index_task();
        $task->execute();

        $queued = $DB->count_records('task_adhoc', ['classname' => '\\core\\task\\search_index_area_task']);
        $this->assertEquals(0, $queued);
    }

    /**
     * Test running search_index_task twice does not create duplicate tasks for the same area.
     * @return void
     */
    public function test_dispatch_deduplicates(): void {
        global $DB;

        $this->setup_mock_area(2);
        manager::clear_static();

        $task = new search_index_task();
        $task->execute();
        $afterfirst = $DB->count_records('task_adhoc', ['classname' => '\\core\\task\\search_index_area_task']);

        // Run it again — already-queued areas must not be duplicated.
        $task->execute();
        $aftersecond = $DB->count_records('task_adhoc', ['classname' => '\\core\\task\\search_index_area_task']);

        $this->assertEquals($afterfirst, $aftersecond);
    }

    /**
     * Test search_index_area_task indexes its area and writes _lastindexrun config.
     * @return void
     */
    public function test_area_task_indexes_and_writes_config(): void {
        [$search, $areaid, $componentname, $varname] = $this->setup_mock_area(3);

        $task = new search_index_area_task();
        $task->set_custom_data(['areaid' => $areaid]);
        ob_start();
        $task->execute();
        ob_end_clean();

        $added = $search->get_engine()->get_and_clear_added_documents();
        $this->assertCount(3, $added);

        // The _lastindexrun must have been written.
        $lastrun = get_config($componentname, $varname . '_lastindexrun');
        $this->assertGreaterThan(0, $lastrun);

        // The _partial should not be set (all records indexed).
        $this->assertFalse((bool) get_config($componentname, $varname . '_partial'));
    }

    /**
     * Test search_index_area_task exits cleanly when the area is disabled.
     * @return void
     */
    public function test_area_task_ignores_disabled_area(): void {
        // The mock_search_area fixture hard-codes is_enabled() to return true, so we cannot
        // test the disabled-area guard using it. Use a real core area instead: its is_enabled()
        // reads directly from config, so set_config() to 0 makes it return false.
        testable_core_search::instance();

        $areaid = 'core_course-course';
        $area   = manager::get_search_area($areaid);
        [$componentname, $varname] = $area->get_config_var_name();
        set_config($varname . '_enabled', 0, $componentname);

        $task = new search_index_area_task();
        $task->set_custom_data(['areaid' => $areaid]);
        $task->execute();

        // If the task honoured the disabled flag it returned before calling index_area(), so
        // _indexingstart was never written. If it ran to completion, _indexingstart would be set.
        $indexingstart = get_config($componentname, $varname . '_indexingstart');
        $this->assertFalse($indexingstart, '_indexingstart must not be written when area is disabled');
    }

    /**
     * Test index_area() returns partial=false and correct indexed count when all docs are processed.
     * @return void
     */
    public function test_index_area_complete(): void {
        [$search, $areaid, $componentname, $varname] = $this->setup_mock_area(3);

        $result = $search->index_area($areaid);

        $this->assertFalse($result->partial);
        $this->assertEquals(3, $result->indexed);
        $this->assertFalse((bool) get_config($componentname, $varname . '_partial'));
    }

    /**
     * Test index_area() returns partial=true when time runs out mid-area.
     * @return void
     */
    public function test_index_area_partial(): void {
        [$search, $areaid, $componentname, $varname] = $this->setup_mock_area(4);

        $search->get_engine()->set_add_delay(1.5);
        testable_core_search::fake_current_time(time());

        // 2-second limit → should index ~1 document then stop.
        $result = $search->index_area($areaid, 2);

        $this->assertTrue($result->partial);
        $this->assertEquals(1, get_config($componentname, $varname . '_partial'));
    }

    /**
     * Test index_area() writes _indexingstart, _indexingend, _lastindexrun after a complete run.
     * @return void
     */
    public function test_index_area_writes_config_values(): void {
        [$search, $areaid, $componentname, $varname] = $this->setup_mock_area(2);

        $before = time();
        $search->index_area($areaid);
        $after = time();

        $this->assertGreaterThanOrEqual($before, (int) get_config($componentname, $varname . '_indexingstart'));
        $this->assertGreaterThanOrEqual($before, (int) get_config($componentname, $varname . '_indexingend'));
        $this->assertGreaterThan(0, (int) get_config($componentname, $varname . '_lastindexrun'));
        $this->assertLessThanOrEqual($after, (int) get_config($componentname, $varname . '_indexingend'));
    }

    /**
     * Test index_area() with fullindex=true starts from timestamp 0 (re-indexes everything).
     * @return void
     */
    public function test_index_area_fullindex_reindexes_all(): void {
        [$search, $areaid] = $this->setup_mock_area(3);

        // Do an initial index so _lastindexrun is set.
        $search->index_area($areaid);
        $search->get_engine()->get_and_clear_added_documents();

        // Full reindex should re-index all 3 records.
        $result = $search->index_area($areaid, 0, null, true);
        $added = $search->get_engine()->get_and_clear_added_documents();

        $this->assertCount(3, $added);
        $this->assertFalse($result->partial);
    }

    /**
     * Test calling index_area() with an unknown areaid throws a coding_exception.
     * @return void
     */
    public function test_index_area_unknown_area_throws(): void {
        $search = testable_core_search::instance();

        $this->expectException(coding_exception::class);
        $search->index_area('totally-made-up-areaid');
    }
}
