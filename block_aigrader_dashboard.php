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
 * AI Grader Dashboard Block - Main block class
 *
 * @package    block_aigrader_dashboard
 * @copyright  2024 AI Grader
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// v2.0.4: Load shared data-fetching functions from locallib.php so that viewall.php
// can call aigrader_dashboard_fetch_all_data() WITHOUT ever needing to load this
// class file (which extends block_base and requires blocklib.php).
require_once(__DIR__ . '/locallib.php');

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
        $gradable_course_ids = $this->get_gradable_course_ids();

        if (empty($gradable_course_ids)) {
            $this->content->text = '';
            return $this->content;
        }

        // Get ungraded data using the pre-computed list — no second enrollment query needed.
        $data = $this->get_ungraded_data($gradable_course_ids);

        // Render the block content
        $this->content->text = $this->render_dashboard($data);

        return $this->content;
    }

    /**
     * Public static entry point used by viewall.php to fetch ungraded data for all
     * courses the current user can grade (no limit applied — caller sees everything).
     */
    public static function fetch_all_data(): array {
        // v2.0.4: Proxy to the standalone function in locallib.php so that viewall.php
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
     * @param int[] $gradable_course_ids
     * @return array{courses: array, total: int, overdue: int}
     */
    private function get_ungraded_data(array $gradable_course_ids) {
        return aigrader_dashboard_get_ungraded_data($gradable_course_ids);
    }

    /**
     * Render a proctoring review quicklink for site admins.
     * Shows flagged + pending attempt count across all quizzes (if plugin installed).
     * Links to the quiz listing page so admins can navigate to individual quiz reports.
     */
    private function render_proctoring_quicklink(): string {
        global $DB;

        if (!$DB->get_manager()->table_exists('quizaccess_webcamproctor_attempts')) {
            return '';
        }

        $flaggedCount = (int)$DB->count_records_select(
            'quizaccess_webcamproctor_attempts',
            "status = 'flagged'"
        );
        $pendingCount = (int)$DB->count_records_select(
            'quizaccess_webcamproctor_attempts',
            "status IN ('pending', 'processing')"
        );
        $reviewCount = $flaggedCount + $pendingCount;

        $html = '<div class="agd-report-link" style="margin-top:6px;">';
        $html .= '<a href="' . (new moodle_url('/mod/quiz/index.php'))->out() . '" class="agd-btn agd-btn-secondary">';
        // Camera/video icon.
        $html .= '<svg class="agd-icon agd-icon-sm" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 7l-7 5 7 5V7z"/><rect x="1" y="5" width="15" height="14" rx="2" ry="2"/></svg>';
        $html .= 'Proctoring Reports';
        if ($reviewCount > 0) {
            $badgeClass = $flaggedCount > 0 ? 'agd-badge-danger' : 'agd-badge-warning';
            $html .= ' <span class="agd-badge ' . $badgeClass . '" style="margin-left:4px;">' . $reviewCount . ' to review</span>';
        }
        $html .= '</a>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Render the dashboard HTML
     */
    private function render_dashboard($data) {
        $strings = [
            'no_quizzes' => get_string('no_quizzes', 'block_aigrader_dashboard'),
            'all_graded' => get_string('all_graded', 'block_aigrader_dashboard'),
            'ungraded' => get_string('ungraded', 'block_aigrader_dashboard'),
            'grade_now' => get_string('grade_now', 'block_aigrader_dashboard'),
            'essays' => get_string('essays', 'block_aigrader_dashboard'),
            'essay' => get_string('essay', 'block_aigrader_dashboard'),
            'overdue' => get_string('overdue', 'block_aigrader_dashboard'),
            'total_ungraded' => get_string('total_ungraded', 'block_aigrader_dashboard'),
        ];

        $html = '<div class="agd-container">';

        // Content wrapper
        $html .= '<div class="agd-content">';

        // Summary header
        $html .= '<div class="agd-summary">';
        if ($data['total'] > 0) {
            $html .= '<div class="agd-summary-stat agd-stat-warning">';
            $html .= '<span class="agd-stat-number">' . $data['total'] . '</span>';
            $html .= '<span class="agd-stat-label">' . $strings['total_ungraded'] . '</span>';
            $html .= '</div>';
            
            if ($data['overdue'] > 0) {
                $html .= '<div class="agd-summary-stat agd-stat-danger">';
                $html .= '<span class="agd-stat-number">' . $data['overdue'] . '</span>';
                $html .= '<span class="agd-stat-label">' . $strings['overdue'] . '</span>';
                $html .= '</div>';
            }
        } else {
            $html .= '<div class="agd-summary-stat agd-stat-success">';
            $html .= '<svg class="agd-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>';
            $html .= '<span class="agd-stat-label">' . $strings['all_graded'] . '</span>';
            $html .= '</div>';
        }
        $html .= '</div>';

        // Course list
        if (empty($data['courses'])) {
            $html .= '<div class="agd-empty">' . $strings['no_quizzes'] . '</div>';
        } else {
            $block_limit = 10;
            $all_courses = $data['courses'];
            $total_courses = count($all_courses);
            $displayed_courses = array_slice($all_courses, 0, $block_limit);

            $html .= '<div class="agd-courses">';
            
            foreach ($displayed_courses as $course) {
                $html .= '<div class="agd-course">';
                
                // Course header
                $html .= '<div class="agd-course-header">';
                $html .= '<svg class="agd-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>';
                $html .= '<span class="agd-course-name">' . format_string($course['name']) . '</span>';
                $html .= '<span class="agd-badge agd-badge-warning">' . $course['total_ungraded'] . '</span>';
                $html .= '</div>';
                
                // Quiz list
                $html .= '<div class="agd-quizzes">';
                foreach ($course['quizzes'] as $quiz) {
                    $html .= '<div class="agd-quiz">';
                    
                    $html .= '<div class="agd-quiz-info">';
                    $html .= '<svg class="agd-icon agd-icon-sm" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>';
                    $html .= '<span class="agd-quiz-name">' . format_string($quiz['name']) . '</span>';
                    $html .= '</div>';
                    
                    $html .= '<div class="agd-quiz-actions">';
                    
                    // Badge
                    $badge_class = $quiz['is_overdue'] ? 'agd-badge-danger' : 'agd-badge-warning';
                    $html .= '<span class="agd-badge ' . $badge_class . '">';
                    if ($quiz['is_overdue']) {
                        $html .= '<svg class="agd-icon agd-icon-xs" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>';
                    }
                    $html .= $quiz['ungraded'] . ' ' . ($quiz['ungraded'] == 1 ? $strings['essay'] : $strings['essays']);
                    $html .= '</span>';
                    
                    // Grade now button
                    $html .= '<a href="' . $quiz['link'] . '" class="agd-btn agd-btn-primary">';
                    $html .= '<svg class="agd-icon agd-icon-sm" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>';
                    $html .= $strings['grade_now'];
                    $html .= '</a>';
                    
                    $html .= '</div>';
                    $html .= '</div>';
                }
                $html .= '</div>';
                
                $html .= '</div>';
            }
            
            $html .= '</div>'; // agd-courses

            // Warning banner + View All button.
            // When more courses exist than the block limit, show a clear amber warning
            // so teachers know some courses with ungraded marking are not visible above.
            // The "View All" button is always shown (regardless of count) so teachers can
            // always reach the full-page view — useful for a complete overview and to
            // confirm they are not missing any marking requirements.
            $viewallurl = new moodle_url('/blocks/aigrader_dashboard/viewall.php');

            if ($total_courses > $block_limit) {
                $hidden = $total_courses - $block_limit;
                $html .= '<div class="agd-overflow-warning">';
                $html .= '<svg class="agd-icon agd-icon-sm" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>';
                $html .= get_string('courses_overflow_warning', 'block_aigrader_dashboard',
                    ['shown' => $block_limit, 'total' => $total_courses, 'hidden' => $hidden]);
                $html .= '</div>';
            }

            $html .= '<div class="agd-viewall-link' . ($total_courses > $block_limit ? ' agd-viewall-overflow' : '') . '">';
            $html .= '<a href="' . $viewallurl->out() . '" class="agd-btn agd-btn-' . ($total_courses > $block_limit ? 'primary' : 'secondary') . '">';
            $html .= '<svg class="agd-icon agd-icon-sm" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>';
            $html .= get_string('view_all_courses', 'block_aigrader_dashboard', ['total' => $total_courses]);
            $html .= '</a>';
            $html .= '</div>';
        }
        
        // Activity Report link - prominent CTA for admins
        if (is_siteadmin()) {
            $reporturl = new moodle_url('/mod/quiz/report/aigrader/grader_report.php');
            $html .= '<div class="agd-report-link">';
            $html .= '<a href="' . $reporturl->out() . '" class="agd-btn agd-btn-secondary">';
            $html .= '<svg class="agd-icon agd-icon-sm" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>';
            $html .= get_string('view_activity_report', 'block_aigrader_dashboard');
            $html .= '</a>';
            $html .= '</div>';

            // Proctoring quicklink — show count of flagged/pending attempts requiring review.
            $html .= $this->render_proctoring_quicklink();
        }

        // Close content wrapper and container
        $html .= '</div>'; // agd-content
        $html .= '</div>'; // agd-container

        return $html;
    }
}
