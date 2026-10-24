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

// Load shared data-fetching functions from locallib.php so that viewall.php.
// can call aigrader_dashboard_fetch_all_data() WITHOUT ever needing to load this
// class file (which extends block_base and requires blocklib.php).
require_once(__DIR__ . '/locallib.php');

/**
 * Displays a summary of ungraded AI Grader essays.
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
     * Render a proctoring review quicklink for site admins.
     * Shows flagged + pending attempt count across all quizzes (if plugin installed).
     * Links to the quiz listing page so admins can navigate to individual quiz reports.
     */
    private function render_proctoring_quicklink(): string {
        global $DB;

        if (!$DB->get_manager()->table_exists('quizaccess_webcamproctor_attempts')) {
            return '';
        }

        $flaggedcount = (int) $DB->count_records_select(
            'quizaccess_webcamproctor_attempts',
            "status = 'flagged'"
        );
        $pendingcount = (int) $DB->count_records_select(
            'quizaccess_webcamproctor_attempts',
            "status IN ('pending', 'processing')"
        );
        $reviewcount = $flaggedcount + $pendingcount;

        $html = '<div class="agd-report-link" style="margin-top:6px;">';
        $html .= '<a href="' . (new moodle_url('/mod/quiz/index.php'))->out() . '" class="agd-btn agd-btn-secondary">';
        // Camera/video icon.
        $html .= '<svg class="agd-icon agd-icon-sm" xmlns="http://www.w3.org/2000/svg" ';
        $html .= 'width="14" height="14" viewBox="0 0 24 24" fill="none" ';
        $html .= 'stroke="currentColor" stroke-width="2"><path d="M23 7l-7 5 7 5V7z"/>';
        $html .= '<rect x="1" y="5" width="15" height="14" rx="2" ry="2"/></svg>';
        $html .= 'Proctoring Reports';
        if ($reviewcount > 0) {
            $badgeclass = $flaggedcount > 0 ? 'agd-badge-danger' : 'agd-badge-warning';
            $html .= ' <span class="agd-badge ' . $badgeclass . '" style="margin-left:4px;">';
            $html .= $reviewcount . ' to review</span>';
        }
        $html .= '</a>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Render the dashboard HTML.
     *
     * @param array $data Dashboard data.
     * @return string Dashboard HTML.
     */
    /**
     * Inline Lucide-style line icon.
     *
     * @param string $paths SVG path markup.
     * @param int $size Pixel size.
     * @param string $class Extra CSS class.
     * @return string
     */
    private function icon(string $paths, int $size = 18, string $class = ''): string {
        return '<svg class="agd-i ' . $class . '" xmlns="http://www.w3.org/2000/svg" width="' . $size .
            '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" ' .
            'stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' .
            $paths . '</svg>';
    }

    /**
     * Render one stat tile.
     *
     * @param string $label Muted label above the number.
     * @param int $value The number.
     * @param string $tone Tone key: primary, danger, indigo or teal.
     * @return string
     */
    private function stat_tile(string $label, int $value, string $tone): string {
        return '<div class="agd-tile agd-tile-' . $tone . '">' .
            '<span class="agd-tile-label">' . s($label) . '</span>' .
            '<span class="agd-tile-value">' . $value . '</span>' .
            '</div>';
    }

    /**
     * Work out the status tone and wording for one quiz row.
     *
     * Based on the data the plugin already holds: how long the oldest ungraded essay has
     * been waiting, measured against the site's overdue threshold. There is deliberately
     * no use of the quiz close date here - the plugin has never tracked it, and inventing
     * a due date would put a number on screen that nothing behind it supports.
     *
     * @param array $quiz One quiz row.
     * @param int $thresholdseconds Overdue threshold in seconds.
     * @return array{tone: string, label: string}
     */
    private function row_status(array $quiz, int $thresholdseconds): array {
        $oldest = (int) ($quiz['oldest_ungraded'] ?? 0);

        if ($oldest <= 0) {
            return ['tone' => 'neutral', 'label' => get_string('status_ontime', 'block_aigrader_dashboard')];
        }

        $waited = max(0, time() - $oldest);
        $age = format_time($waited);

        if (!empty($quiz['is_overdue'])) {
            return [
                'tone' => 'danger',
                'label' => get_string('status_overdue', 'block_aigrader_dashboard', $age),
            ];
        }

        // More than halfway to the threshold counts as approaching it.
        if ($thresholdseconds > 0 && $waited >= ($thresholdseconds / 2)) {
            return [
                'tone' => 'warning',
                'label' => get_string('status_duesoon', 'block_aigrader_dashboard', $age),
            ];
        }

        return ['tone' => 'success', 'label' => get_string('status_ontime', 'block_aigrader_dashboard')];
    }

    /**
     * Render the dashboard HTML.
     *
     * @param array $data Dashboard data.
     * @return string Dashboard HTML.
     */
    private function render_dashboard($data) {
        $thresholdhours = (int) (get_config('block_aigrader_dashboard', 'overdue_threshold') ?: 24);
        $thresholdseconds = $thresholdhours * 3600;

        $sparkles = '<path d="M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9z"/>' .
            '<path d="M19 15l.9 2.1L22 18l-2.1.9L19 21l-.9-2.1L16 18l2.1-.9z"/>';

        $html = '<div class="agd">';
        $html .= '<div class="agd-head">' . $this->icon($sparkles, 24, 'agd-i-primary') .
            '<h3 class="agd-title">' . get_string('pluginname', 'block_aigrader_dashboard') . '</h3></div>';

        // Flatten course/quiz pairs into table rows, limiting by course as before so the
        // existing overflow wording stays accurate.
        $blocklimit = 10;
        $allcourses = $data['courses'];
        $totalcourses = count($allcourses);
        $displayed = array_slice($allcourses, 0, $blocklimit);

        $rows = [];
        $quizcount = 0;
        foreach ($displayed as $course) {
            foreach ($course['quizzes'] as $quiz) {
                $quizcount++;
                $status = $this->row_status($quiz, $thresholdseconds);
                $rows[] = [
                    'course' => $course['name'],
                    'quiz' => $quiz['name'],
                    'ungraded' => (int) $quiz['ungraded'],
                    'overdue' => !empty($quiz['is_overdue']),
                    'oldest' => (int) ($quiz['oldest_ungraded'] ?? 0),
                    'link' => $quiz['link'],
                    'tone' => $status['tone'],
                    'status' => $status['label'],
                ];
            }
        }

        // Overdue first, then longest waiting first.
        usort($rows, function ($a, $b) {
            if ($a['overdue'] !== $b['overdue']) {
                return $a['overdue'] ? -1 : 1;
            }
            return $a['oldest'] <=> $b['oldest'];
        });

        // Stat tiles.
        $html .= '<div class="agd-tiles">';
        $html .= $this->stat_tile(
            get_string('stat_to_grade', 'block_aigrader_dashboard'), (int) $data['total'], 'primary');
        $html .= $this->stat_tile(
            get_string('overdue', 'block_aigrader_dashboard'), (int) $data['overdue'], 'danger');
        $html .= $this->stat_tile(
            get_string('stat_courses', 'block_aigrader_dashboard'), $totalcourses, 'indigo');
        $html .= $this->stat_tile(
            get_string('stat_quizzes', 'block_aigrader_dashboard'), $quizcount, 'teal');
        $html .= '</div>';

        if (empty($rows)) {
            $check = '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>';
            $html .= '<div class="agd-empty">' . $this->icon($check, 22, 'agd-i-success') .
                '<span>' . get_string(
                    $data['total'] > 0 ? 'no_quizzes' : 'all_graded', 'block_aigrader_dashboard') .
                '</span></div>';
            $html .= '</div>';
            return $html;
        }

        // Queue table.
        $html .= '<div class="agd-tablewrap"><table class="agd-table"><thead><tr>';
        foreach (['col_course', 'col_quiz', 'col_tograde', 'col_status'] as $col) {
            $align = $col === 'col_tograde' ? ' class="agd-num"' : '';
            $html .= '<th' . $align . '>' . get_string($col, 'block_aigrader_dashboard') . '</th>';
        }
        $html .= '<th></th></tr></thead><tbody>';

        foreach ($rows as $row) {
            $html .= '<tr class="agd-row agd-row-' . $row['tone'] . '">';
            $html .= '<td class="agd-course">' . format_string($row['course']) . '</td>';
            $html .= '<td class="agd-quiz">' . format_string($row['quiz']) . '</td>';
            $html .= '<td class="agd-num agd-count' . ($row['overdue'] ? ' agd-count-danger' : '') . '">' .
                $row['ungraded'] . '</td>';
            $html .= '<td><span class="agd-badge agd-badge-' . $row['tone'] . '">' .
                s($row['status']) . '</span></td>';
            $html .= '<td class="agd-actioncell"><a href="' . $row['link'] . '" class="agd-btn agd-btn-primary">' .
                $this->icon($sparkles, 16) . get_string('grade_now', 'block_aigrader_dashboard') . '</a></td>';
            $html .= '</tr>';
        }

        $html .= '</tbody><tfoot><tr class="agd-total">';
        $html .= '<td colspan="2">' . get_string('total', 'block_aigrader_dashboard') . '</td>';
        $html .= '<td class="agd-num agd-count agd-count-primary">' . (int) $data['total'] . '</td>';
        $html .= '<td colspan="2">';
        if ((int) $data['overdue'] > 0) {
            $html .= '<span class="agd-badge agd-badge-danger">' . get_string(
                'total_overdue', 'block_aigrader_dashboard', (int) $data['overdue']) . '</span>';
        }
        $html .= '</td></tr></tfoot></table></div>';

        // Overflow warning, unchanged in meaning.
        if ($totalcourses > $blocklimit) {
            $warn = '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/>' .
                '<line x1="12" y1="16" x2="12.01" y2="16"/>';
            $html .= '<div class="agd-warn">' . $this->icon($warn, 18) . get_string(
                'courses_overflow_warning',
                'block_aigrader_dashboard',
                [
                    'shown' => $blocklimit,
                    'total' => $totalcourses,
                    'hidden' => $totalcourses - $blocklimit,
                ]
            ) . '</div>';
        }

        // Footer actions.
        $list = '<line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/>' .
            '<line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/>' .
            '<line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>';
        $chart = '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/>' .
            '<line x1="6" y1="20" x2="6" y2="14"/>';

        $html .= '<div class="agd-foot">';
        $viewallurl = new moodle_url('/blocks/aigrader_dashboard/viewall.php');
        $html .= '<a href="' . $viewallurl->out() . '" class="agd-btn agd-btn-secondary">' .
            $this->icon($list, 16) .
            get_string('view_all_courses', 'block_aigrader_dashboard', ['total' => $totalcourses]) . '</a>';

        if (is_siteadmin()) {
            $reporturl = new moodle_url('/mod/quiz/report/aigrader/grader_report.php');
            $html .= '<a href="' . $reporturl->out() . '" class="agd-btn agd-btn-secondary">' .
                $this->icon($chart, 16) .
                get_string('view_activity_report', 'block_aigrader_dashboard') . '</a>';
            $html .= $this->render_proctoring_quicklink();
        }
        $html .= '</div>';

        $html .= '</div>';

        return $html;
    }
}
