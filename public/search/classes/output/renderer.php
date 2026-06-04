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
 * Search renderer.
 *
 * @package    core_search
 * @copyright  2015 David Monllao {@link http://www.davidmonllao.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_search\output;

defined('MOODLE_INTERNAL') || die();

/**
 * Search renderer.
 *
 * @package    core_search
 * @copyright  2015 David Monllao {@link http://www.davidmonllao.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends \plugin_renderer_base {

    /**
     * @var int Max number chars to display of a string value
     */
    const SEARCH_RESULT_STRING_SIZE = 100;

    /**
     * @var int Max number chars to display of a text value
     */

    const SEARCH_RESULT_TEXT_SIZE = 500;

    /**
     * Renders search results.
     *
     * @param \core_search\document[] $results
     * @param int $page Zero based page number.
     * @param int $totalcount Total number of results available.
     * @param \moodle_url $url
     * @param \core_search\area_category|null $cat Selected search are category or null if category functionality is disabled.
     * @return string HTML
     */
    public function render_results($results, $page, $totalcount, $url, $cat = null) {
        $content = '';

        if (\core_search\manager::is_search_area_categories_enabled() && !empty($cat)) {
            $toprow = [];
            foreach (\core_search\manager::get_search_area_categories() as $category) {
                $taburl = clone $url;
                $taburl->param('cat', $category->get_name());
                $taburl->param('page', 0);
                $taburl->remove_params(['page', 'areaids']);
                $toprow[$category->get_name()] = new \tabobject($category->get_name(), $taburl, $category->get_visiblename());
            }

            if (\core_search\manager::should_hide_all_results_category()) {
                unset($toprow[\core_search\manager::SEARCH_AREA_CATEGORY_ALL]);
            }

            $content .= $this->tabtree($toprow, $cat->get_name());
        }

        // Paging bar.
        $perpage = \core_search\manager::DISPLAY_RESULTS_PER_PAGE;
        $content .= $this->output->paging_bar($totalcount, $page, $perpage, $url);

        // Results.
        $resultshtml = array();
        foreach ($results as $hit) {
            $resultshtml[] = $this->render_result($hit);
        }
        $content .= \html_writer::tag('div', implode('<hr/>', $resultshtml), array('class' => 'search-results'));

        // Paging bar.
        $content .= $this->output->paging_bar($totalcount, $page, $perpage, $url);

        return $content;
    }

    /**
     * Top results content
     *
     * @param \core_search\document[] $results Search Results
     * @return string content of the top result section
     */
    public function render_top_results($results): string {
        $content = $this->output->box_start('topresults');
        $content .= $this->output->heading(get_string('topresults', 'core_search'));
        $content .= \html_writer::tag('hr', '');
        $resultshtml = array();
        foreach ($results as $hit) {
            $resultshtml[] = $this->render_result($hit);
        }
        $content .= \html_writer::tag('div', implode('<hr/>', $resultshtml), array('class' => 'search-results'));
        $content .= $this->output->box_end();
        return $content;
    }

    /**
     * Displaying search results.
     *
     * @param \core_search\document Containing a single search response to be displayed.a
     * @return string HTML
     */
    public function render_result(\core_search\document $doc) {
        $docdata = $doc->export_for_template($this);

        // Limit text fields size.
        $docdata['title'] = shorten_text($docdata['title'], static::SEARCH_RESULT_STRING_SIZE, true);
        $docdata['content'] = $docdata['content'] ? shorten_text($docdata['content'], static::SEARCH_RESULT_TEXT_SIZE, true) : '';
        $docdata['description1'] = $docdata['description1'] ? shorten_text($docdata['description1'], static::SEARCH_RESULT_TEXT_SIZE, true) : '';
        $docdata['description2'] = $docdata['description2'] ? shorten_text($docdata['description2'], static::SEARCH_RESULT_TEXT_SIZE, true) : '';

        return $this->output->render_from_template('core_search/result', $docdata);
    }

    /**
     * Returns a box with a search disabled lang string.
     *
     * @return string HTML
     */
    public function render_search_disabled() {
        $content = $this->output->box_start();
        $content .= $this->output->notification(get_string('globalsearchdisabled', 'search'), 'notifymessage');
        $content .= $this->output->box_end();
        return $content;
    }

    /**
     * Returns information about queued index requests.
     *
     * @param \stdClass $info Info object from get_index_requests_info
     * @return string HTML
     * @throws \moodle_exception Any error with template
     */
    public function render_index_requests_info(\stdClass $info) {
        return $this->output->render_from_template('core_search/index_requests', $info);
    }

    /**
     * Renders the indexing progress cell for a single search area.
     *
     * @param \stdClass $stat  From manager::get_areas_stats(), with docsource, docsindexed, docsneedindex.
     * @return string HTML
     */
    public function render_area_index_progress(\stdClass $stat): string {
        $unknown = ($stat->docsource === null);
        $percent = null;
        if (!$unknown) {
            // Use floor so the bar never overstates progress (e.g. 20080/20081 shows 99%, not 100%).
            $percent = ($stat->docsource > 0)
                ? max(0, min(100, (int) floor(100 * $stat->docsindexed / $stat->docsource)))
                : 0;
        }
        $context = [
            'unknown'       => $unknown,
            'percent'       => $percent,
            'complete'      => ($percent === 100),
            'docsource'     => $stat->docsource,
            'docsindexed'   => $stat->docsindexed,
            'docsneedindex' => $stat->docsneedindex,
            'hasneedindex'  => ($stat->docsneedindex !== null && $stat->docsneedindex > 0),
        ];
        return $this->output->render_from_template('core_search/area_stats', $context);
    }

    /**
     * Renders the overall indexing progress bar across all areas.
     *
     * @param \stdClass[] $areastats  From manager::get_areas_stats().
     * @return string HTML
     */
    public function render_overall_index_progress(array $areastats): string {
        $totalsource   = 0;
        $totalindexed  = 0;
        $unknownareas  = 0;
        foreach ($areastats as $stat) {
            if ($stat->docsource === null) {
                $unknownareas++;
                continue;
            }
            $totalsource  += $stat->docsource;
            $totalindexed += $stat->docsindexed;
        }
        // Use floor so the bar never overstates progress.
        $percent = ($totalsource > 0) ? (int) floor(100 * $totalindexed / $totalsource) : 0;
        $percent = max(0, min(100, $percent));
        $idnumber = 'core_search_overall_index_progress';
        $msg = $totalindexed . ' / ' . $totalsource . ' ' . get_string('indexed', 'search');

        $barhtml  = $this->output->render_from_template('core/progress_bar', [
            'id'       => '',
            'idnumber' => $idnumber,
            'width'    => 500,
            'class'    => 'search-overall-index-progress',
            'style'    => 'max-width: none; width: 100%;',
            'value'    => 0,
            'error'    => 0,
            'message'  => '',
        ]);
        $barhtml .= $this->output->render_progress_bar_update($idnumber, (float) $percent, $msg, '');

        $context = [
            'progressbar'  => $barhtml,
            'unknownareas' => $unknownareas,
        ];
        return $this->output->render_from_template('core_search/overall_index_progress', $context);
    }
}
