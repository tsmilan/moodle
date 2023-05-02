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

namespace core_form;

use context_user;
use MoodleQuickForm_editor;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/form/editor.php');

/**
 * Unit tests for MoodleQuickForm_editor
 *
 * Contains test cases for testing MoodleQuickForm_editor
 *
 * @package    core_form
 * @copyright  2023 Monash University (https://monash.edu/)
 * @author     Trisha Milan <trishamilan@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class editor_test extends \advanced_testcase {

    /**
     * Tests the getFrozenHtml() method with different input scenarios and text formats.
     *
     * @dataProvider getfrozenhtml_provider
     * @param array $editorvalue An array containing values to be set in the editor.
     * @param string $expected The expected output of the getFrozenHtml().
     */
    public function test_getfrozenhtml($editorvalue, $expected) {
        $this->resetAfterTest();
        $this->setAdminUser();

        $editor = new MoodleQuickForm_editor('testeditor', 'Test Editor', null);
        $editor->setValue($editorvalue);
        $editor->freeze();
        $this->assertEquals($expected, $editor->getFrozenHtml());
    }

    /**
     * Data provider for the test_getfrozenhtml testcase.
     *
     * @return array of testcases (string)testcasename => [(array)editorvalue, (string)expected]
     */
    public function getfrozenhtml_provider() {
        global $CFG, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();

        $usercontext = context_user::instance($USER->id);
        $draftfileurl = "{$CFG->wwwroot}/draftfile.php/{$usercontext->id}/user/draft/296288392/image.jpg";
        $pluginfileurl = "{$CFG->wwwroot}/pluginfile.php/{$usercontext->id}/editor/frozen/296288392/image.jpg";

        return [
            'FORMAT_HTML with simple text' => [
                ['text' => '<p>Hello world!</p>', 'format' => FORMAT_HTML],
                '<div class="no-overflow"><p>Hello world!</p></div>',
            ],
            'FORMAT_HTML with images' => [
                [
                    'text' => 'Embedded file <img src="' . $draftfileurl . '" alt="image.jpg">' .
                        ' External image <img src="https://www.example.com/external.jpg" alt="external.jpg">',
                    'format' => FORMAT_HTML,
                ],
                '<div class="no-overflow">Embedded file <img src="' . $pluginfileurl . '" alt="image.jpg"' .
                    ' /> External image <img src="https://www.example.com/external.jpg" alt="external.jpg" /></div>',
            ],
            'FORMAT_MOODLE with simple text' => [
                ['text' => '<p>Hello world!</p>', 'format' => FORMAT_MOODLE],
                '<div class="no-overflow"><div class="text_to_html"><p>Hello world!</p></div></div>',
            ],
            'FORMAT_MOODLE with images' => [
                [
                    'text' => 'Embedded file <img src="' . $draftfileurl . '" alt="image.jpg">' .
                        ' External image <img src="https://www.example.com/external.jpg" alt="external.jpg">',
                    'format' => FORMAT_MOODLE,
                ],
                '<div class="no-overflow"><div class="text_to_html">Embedded file <img src="' . $pluginfileurl .
                    '" alt="image.jpg" /> External image <img src="https://www.example.com/external.jpg' .
                    '" alt="external.jpg" /></div></div>',
            ],
            'FORMAT_MARKDOWN with simple text' => [
                ['text' => 'Hello *world*!', 'format' => FORMAT_MARKDOWN],
                '<div class="no-overflow"><p>Hello <em>world</em>!</p>' . "\n" . '</div>',
            ],
            'FORMAT_MARKDOWN with images' => [
                [
                    'text' => 'Embedded file ![image.jpg](' . $draftfileurl . ')' .
                        ' External image ![external.jpg](https://www.example.com/external.jpg)',
                    'format' => FORMAT_MARKDOWN,
                ],
                '<div class="no-overflow"><p>Embedded file <img src="' . $pluginfileurl .
                    '" alt="image.jpg" /> External image <img src="https://www.example.com/external.jpg' .
                    '" alt="external.jpg" /></p>' . "\n" . '</div>',
            ],
            'FORMAT_PLAIN with simple text' => [
                ['text' => 'Hello world!', 'format' => FORMAT_PLAIN],
                '<div class="no-overflow">Hello world!</div>',
            ],
            'FORMAT_PLAIN with link' => [
                ['text' => "Hello world! {$draftfileurl}", 'format' => FORMAT_PLAIN],
                '<div class="no-overflow">Hello world! ' . $pluginfileurl . '</div>',
            ],
        ];
    }
}
