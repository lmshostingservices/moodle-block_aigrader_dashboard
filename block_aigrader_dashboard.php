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
 * AI Essay Grader dashboard block - Main block class
 *
 * @package    block_aigrader_dashboard
 * @copyright  2024 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Load shared data-fetching functions from locallib.php so that viewall.php.
// can call aigrader_dashboard_fetch_all_data() WITHOUT ever needing to load this
// class file (which extends block_base and requires blocklib.php).
require_once(__DIR__ . '/locallib.php');

/**
 * Displays a summary of essays waiting for grading in the AI Essay Grader.
 */
class block_aigrader_dashboard extends block_base {
    /**
     * Initialize the block
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_aigrader_dashboard');
    }

    /**
     * Allow multiple instances
     */
    public function instance_allow_multiple() {
        return false;
    }

    /**
     * Has configuration
     */
    public function has_config() {
        return true;
    }

    /**
     * Applicable formats
     */
    public function applicable_formats() {
        return [
            'my' => true,
            'site-index' => true,
            'course-view' => true,
            'admin' => true,
        ];
    }

    /**
     * Get block content
     */
    public function get_content() {
        global $USER, $OUTPUT;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->footer = '';

        // RC1+RC2 fix: compute the set of gradable course IDs ONCE here.
        // This replaces the old pattern where enrol_get_users_courses() was called
        // twice (once in user_can_view() and once in get_ungraded_data()) and
        // capability checks were repeated per result row after the SQL ran.
        $gradablecourseids = $this->get_gradable_course_ids();

        if (empty($gradablecourseids)) {
            $this->content->text = '';
            return $this->content;
        }

        // Get ungraded data using the pre-computed list; no second enrollment query needed.
        $data = $this->get_ungraded_data($gradablecourseids);

        // Render the block content.
        $this->content->text = $this->render_dashboard($data);

        return $this->content;
    }

    /**
     * Public static entry point used by viewall.php to fetch ungraded data for all
     * courses the current user can grade (no limit applied — caller sees everything).
     */
    public static function fetch_all_data(): array {
        // Proxy to the standalone function in locallib.php so that viewall.php.
        // can call aigrader_dashboard_fetch_all_data() directly without loading this class.
        return aigrader_dashboard_fetch_all_data();
    }

    /**
     * RC1+RC2 fix: returns the IDs of every course where the current user can grade.
     * For site admins, returns all visible course IDs (capped at 500 most recently
     * modified to avoid runaway queries on huge sites).
     * Called once per page load; result is passed to get_ungraded_data().
     */
    private function get_gradable_course_ids() {
        // Delegated to locallib.php (v2.1.0) so the block and viewall.php resolve the
        // same course set from the same code.
        return aigrader_dashboard_get_gradable_course_ids();
    }

    /**
     * Get ungraded essay data for the supplied list of course IDs.
     *
     * v2.1.0: the query itself now lives in locallib.php and nowhere else. This class
     * previously carried its own copy, near-identical to the locallib version used by
     * viewall.php and different again from the one in the notification task — so the
     * block, the "view all" page and the daily email could each report a different
     * number. Delegating keeps them in step, including the inactive-student filter.
     *
     * @param int[] $gradablecourseids
     * @return array{courses: array, total: int, overdue: int}
     */
    private function get_ungraded_data(array $gradablecourseids) {
        return aigrader_dashboard_get_ungraded_data($gradablecourseids);
    }

    /**
     * Render the dashboard HTML.
     *
     * @param array $data Dashboard data.
     * @return string Dashboard HTML.
     */
    private function render_dashboard($data) {
        return \block_aigrader_dashboard\output\dashboard::render($data);
    }
}
