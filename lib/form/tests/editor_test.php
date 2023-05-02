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
use moodleform;
use moodle_url;
use MoodleQuickForm_editor;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/form/editor.php');
require_once($CFG->libdir . '/formslib.php');

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

    protected function create_draftfileurl_and_pluginfileurl_from_sample_image() {
        global $USER, $CFG;

        $this->resetAfterTest();
        $this->setAdminUser();

        $usercontext = context_user::instance($USER->id);
        $fs = get_file_storage();
        $filerecord = array(
            'contextid' => $usercontext->id,
            'component' => 'user',
            'filearea'  => 'draft',
            'itemid'    => file_get_unused_draft_itemid(),
            'filepath'  => '/',
            'filename'  => 'black.png'
        );
        $pngdata = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
        $fs->create_file_from_string($filerecord, $pngdata);

        return [
            moodle_url::make_draftfile_url($filerecord['itemid'], $filerecord['filepath'], $filerecord['filename'])->out(),
            $CFG->wwwroot . '/pluginfile.php/' . $usercontext->id . '/editor/frozen/' . $filerecord['itemid'] . '/black.png'
        ];
    }

    /**
     * Tests the getFrozenHtml() method with different input scenarios and text formats.
     *
     * @dataProvider getfrozenhtml_provider
     * @param array $editorconfig An array containing config to be set in the editor.
     * @param string $expectedoutput The expected output of the getFrozenHtml().
     */
    public function test_getFrozenHtml($editorconfig, $expectedoutput) {
        global $USER;

        $this->resetAfterTest();
        $this->setAdminUser();

        list($draftfileurl, $pluginfileurl) = $this->create_draftfileurl_and_pluginfileurl_from_sample_image();
        $editorconfig['text'] = str_replace('[draftfileurl]', $draftfileurl, $editorconfig['text']);
        $expectedoutput = str_replace('[pluginfileurl]', $pluginfileurl, $expectedoutput);

        $form = new test_editor_form();
        $data['editorfield'] = $editorconfig;
        $form->set_data($data);

        // Simulate the process of saving the content.
        $mform = $form->get_mform();
        if ($form->is_submitted() && $form->is_validated()) {
            $data = $form->get_data();
            // Verify that the saved content matches the text and draftfile url.
            $this->assertEquals($editorconfig['text'], $data->editorfield['text']);
        }

        // Freeze the editor element.
        $mform->getElement('editorfield')->freeze();

        // Verify that the saved content matches the text and pluginfile url.
        $frozenhtml = $mform->getElement('editorfield')->getFrozenHtml();
        $this->assertEquals($expectedoutput, $frozenhtml);
    }

    /**
     * Data provider for the test_getfrozenhtml testcase.
     *
     * @return array of testcases (string)testcasename => [(array)editorconfig, (string)expectedoutput]
     */
    public function getfrozenhtml_provider() {
        return [
            'FORMAT_HTML with simple text' => [
                [
                    'text' => '<p>Hello world!</p>',
                    'format' => FORMAT_HTML
                ],
                '<div class="no-overflow"><p>Hello world!</p></div>',
            ],
            'FORMAT_HTML with images' => [
                [
                    'text' => 'Embedded file <img src="[draftfileurl]" alt="black.png" />' .
                        ' External image <img src="https://www.example.com/external.jpg" alt="external.jpg" />',
                    'format' => FORMAT_HTML,
                ],
                '<div class="no-overflow">Embedded file <img src="[pluginfileurl]" alt="black.png" />' .
                ' External image <img src="https://www.example.com/external.jpg" alt="external.jpg" /></div>',
            ],
            'FORMAT_MOODLE with simple text' => [
                [
                    'text' => '<p>Hello world!</p>',
                    'format' => FORMAT_MOODLE
                ],
                '<div class="no-overflow"><div class="text_to_html"><p>Hello world!</p></div></div>',
            ],
            'FORMAT_MOODLE with images' => [
                [
                    'text' => 'Embedded file <img src="[draftfileurl]" alt="black.png">' .
                        ' External image <img src="https://www.example.com/external.jpg" alt="external.jpg">',
                    'format' => FORMAT_MOODLE,
                ],
                '<div class="no-overflow"><div class="text_to_html">Embedded file <img src="[pluginfileurl]" alt="black.png" />' .
                ' External image <img src="https://www.example.com/external.jpg" alt="external.jpg" /></div></div>',
            ],
            'FORMAT_MARKDOWN with simple text' => [
                [
                    'text' => 'Hello *world*!',
                    'format' => FORMAT_MARKDOWN
                ],
                '<div class="no-overflow"><p>Hello <em>world</em>!</p>' . "\n" . '</div>',
            ],
            'FORMAT_MARKDOWN with images' => [
                [
                    'text' => 'Embedded file ![black.png]([draftfileurl])' .
                        ' External image ![external.jpg](https://www.example.com/external.jpg)',
                    'format' => FORMAT_MARKDOWN,
                ],
                '<div class="no-overflow"><p>Embedded file <img src="[pluginfileurl]" alt="black.png" />' .
                ' External image <img src="https://www.example.com/external.jpg" alt="external.jpg" /></p>' . "\n" . '</div>',
            ],
            'FORMAT_PLAIN with simple text' => [
                [
                    'text' => 'Hello world!',
                    'format' => FORMAT_PLAIN
                ],
                '<div class="no-overflow">Hello world!</div>',
            ],
            'FORMAT_PLAIN with link' => [
                [
                    'text' => "Hello world! [draftfileurl]",
                    'format' => FORMAT_PLAIN
                ],
                '<div class="no-overflow">Hello world! [pluginfileurl]</div>',
            ],
        ];
    }
}

/**
 * Form object to be used in test case.
 */
class test_editor_form extends moodleform {
    /**
     * Form definition.
     */
    public function definition() {
        $mform = $this->_form;

        $editoroptions = array('trusttext' => true, 'maxfiles' => EDITOR_UNLIMITED_FILES, 'subdirs' => false);
        $mform->addElement('editor', 'editorfield', 'Simple label', null, $editoroptions);
    }

    /**
     * Returns form reference.
     *
     * @return \MoodleQuickForm
     */
    public function get_mform() {
        // Set submitted flag, to simulate submission
        $this->_form->_flagSubmitted = true;
        return $this->_form;
    }
}
