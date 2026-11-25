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

namespace block_aigrader_dashboard\output;

use html_writer;
use moodle_url;

/**
 * The dashboard: headline figures, then one row per quiz with essays waiting.
 *
 * Built from Moodle Design System components (buttons, badges, alerts) and tokens, with the
 * LMS Labs premium finish. Shared by the block and the full-page view, so they look the same.
 *
 * @package    block_aigrader_dashboard
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dashboard {
    /** @var int Courses listed in the block before the rest go to the full page. */
    const BLOCK_COURSE_LIMIT = 10;

    /**
     * The whole dashboard.
     *
     * @param array $data From aigrader_dashboard_get_ungraded_data(): courses, total, overdue.
     * @param bool $fullpage True on the full page: every course, and no "view all" link.
     * @return string HTML.
     */
    public static function render(array $data, bool $fullpage = false): string {
        $thresholdhours = (int)(get_config('block_aigrader_dashboard', 'overdue_threshold') ?: 24);
        $courses = $data['courses'];
        $totalcourses = count($courses);
        $shown = $fullpage ? $courses : array_slice($courses, 0, self::BLOCK_COURSE_LIMIT);

        $rows = [];
        $quizcount = 0;
        foreach ($courses as $i => $course) {
            foreach ($course['quizzes'] as $quiz) {
                $quizcount++;
                if (!$fullpage && $i >= self::BLOCK_COURSE_LIMIT) {
                    continue;
                }
                $rows[] = [
                    'courseid' => (int)$course['id'],
                    'course' => $course['name'],
                    'quiz' => $quiz['name'],
                    'ungraded' => (int)$quiz['ungraded'],
                    'overdue' => !empty($quiz['is_overdue']),
                    'oldest' => (int)($quiz['oldest_ungraded'] ?? 0),
                    'link' => $quiz['link'],
                    'status' => self::status($quiz, $thresholdhours * 3600),
                ];
            }
        }
        // Overdue first, then whatever has waited longest.
        usort($rows, function ($a, $b) {
            if ($a['overdue'] !== $b['overdue']) {
                return $a['overdue'] ? -1 : 1;
            }
            return $a['oldest'] <=> $b['oldest'];
        });

        $html = html_writer::start_div('agd');
        $html .= self::paused_notice();
        $html .= self::tiles((int)$data['total'], (int)$data['overdue'], $totalcourses, $quizcount);

        if (!$rows) {
            $html .= html_writer::div(
                '<i class="fa-solid fa-circle-check" aria-hidden="true"></i> '
                    . html_writer::span(get_string($data['total'] > 0 ? 'no_quizzes' : 'all_graded', 'block_aigrader_dashboard')),
                'agd-empty'
            );
            return $html . html_writer::end_div();
        }

        $html .= self::table($rows, (int)$data['total'], (int)$data['overdue']);

        if (!$fullpage && $totalcourses > self::BLOCK_COURSE_LIMIT) {
            $html .= html_writer::div(
                '<i class="fa-solid fa-circle-info" aria-hidden="true"></i> '
                    . html_writer::span(get_string('courses_overflow_warning', 'block_aigrader_dashboard', [
                        'shown' => self::BLOCK_COURSE_LIMIT,
                        'total' => $totalcourses,
                        'hidden' => $totalcourses - self::BLOCK_COURSE_LIMIT,
                    ])),
                'agd-note'
            );
        }

        $html .= self::footer($fullpage, $totalcourses);
        return $html . html_writer::end_div();
    }

    /**
     * A notice when the site has paused AI suggestions in the Essay Grader, so the dashboard does
     * not send graders to a queue whose AI Grade buttons are hidden without saying why.
     *
     * @return string HTML, or '' when suggestions run normally.
     */
    protected static function paused_notice(): string {
        if (empty(get_config('quiz_aigrader', 'ai_paused'))) {
            return '';
        }
        return html_writer::div(
            '<i class="fa-solid fa-circle-pause mds-alert-icon" aria-hidden="true"></i>'
                . html_writer::span(get_string('ai_paused_notice', 'block_aigrader_dashboard')),
            'agd-alert mds-alert alert mds-alert--warning alert-warning',
            ['role' => 'status']
        );
    }

    /**
     * The four headline figures.
     *
     * @param int $total Essays to grade.
     * @param int $overdue Of which overdue.
     * @param int $courses Courses with essays waiting.
     * @param int $quizzes Quizzes with essays waiting.
     * @return string HTML.
     */
    protected static function tiles(int $total, int $overdue, int $courses, int $quizzes): string {
        $tiles = [
            ['stat_to_grade', $total, 'fa-pen-to-square', 'primary'],
            ['overdue', $overdue, 'fa-clock', $overdue > 0 ? 'danger' : 'neutral'],
            ['stat_courses', $courses, 'fa-graduation-cap', 'neutral'],
            ['stat_quizzes', $quizzes, 'fa-list-check', 'neutral'],
        ];
        $html = html_writer::start_tag('ul', ['class' => 'agd-tiles', 'role' => 'list']);
        foreach ($tiles as [$label, $value, $icon, $tone]) {
            $html .= html_writer::tag(
                'li',
                html_writer::span('<i class="fa-solid ' . $icon . '" aria-hidden="true"></i>', 'agd-tile-icon')
                    . html_writer::span(get_string($label, 'block_aigrader_dashboard'), 'agd-tile-label')
                    . html_writer::span((string)$value, 'agd-tile-value'),
                ['class' => 'agd-tile agd-tile-' . $tone]
            );
        }
        return $html . html_writer::end_tag('ul');
    }

    /**
     * The status of one quiz: how long its oldest essay has waited, against the overdue threshold.
     *
     * @param array $quiz One quiz row.
     * @param int $thresholdseconds The overdue threshold.
     * @return array{tone: string, icon: string, label: string}
     */
    protected static function status(array $quiz, int $thresholdseconds): array {
        $oldest = (int)($quiz['oldest_ungraded'] ?? 0);
        if ($oldest <= 0) {
            return ['tone' => 'success', 'icon' => 'fa-circle-check',
                'label' => get_string('status_ontime', 'block_aigrader_dashboard'), 'waited' => ''];
        }
        $waited = max(0, time() - $oldest);
        // Whole minutes are enough to judge urgency; seconds only add noise.
        $age = get_string('status_waiting', 'block_aigrader_dashboard', format_time(max(60, $waited - $waited % 60)));
        if (!empty($quiz['is_overdue'])) {
            return ['tone' => 'danger', 'icon' => 'fa-circle-exclamation',
                'label' => get_string('status_overdue', 'block_aigrader_dashboard'), 'waited' => $age];
        }
        if ($thresholdseconds > 0 && $waited >= $thresholdseconds / 2) {
            return ['tone' => 'warning', 'icon' => 'fa-hourglass-half',
                'label' => get_string('status_duesoon', 'block_aigrader_dashboard'), 'waited' => $age];
        }
        return ['tone' => 'success', 'icon' => 'fa-circle-check',
            'label' => get_string('status_ontime', 'block_aigrader_dashboard'), 'waited' => ''];
    }

    /**
     * One row per quiz: the quiz and its course, how many essays wait, the status, and Grade now.
     *
     * @param array $rows The rows, sorted.
     * @param int $total Essays to grade.
     * @param int $overdue Of which overdue.
     * @return string HTML.
     */
    protected static function table(array $rows, int $total, int $overdue): string {
        $html = html_writer::start_div('agd-tablewrap');
        $html .= html_writer::start_tag('table', ['class' => 'agd-table']);
        $html .= html_writer::tag('caption', get_string('table_caption', 'block_aigrader_dashboard'), ['class' => 'sr-only']);
        $html .= html_writer::start_tag('thead') . html_writer::start_tag('tr');
        $html .= html_writer::tag('th', get_string('col_quiz', 'block_aigrader_dashboard'), ['scope' => 'col']);
        $html .= html_writer::tag(
            'th',
            get_string('col_tograde', 'block_aigrader_dashboard'),
            ['scope' => 'col', 'class' => 'agd-num']
        );
        $html .= html_writer::tag(
            'th',
            get_string('col_status', 'block_aigrader_dashboard'),
            ['scope' => 'col', 'class' => 'agd-status']
        );
        $html .= html_writer::tag(
            'th',
            html_writer::span(get_string('col_action', 'block_aigrader_dashboard'), 'sr-only'),
            ['scope' => 'col']
        );
        $html .= html_writer::end_tag('tr') . html_writer::end_tag('thead');

        $html .= html_writer::start_tag('tbody');
        foreach ($rows as $row) {
            $s = $row['status'];
            $quizname = format_string($row['quiz']);
            $html .= html_writer::start_tag('tr', ['class' => 'agd-row agd-row-' . $s['tone']]);
            $html .= html_writer::tag(
                'th',
                html_writer::span($quizname, 'agd-quiz')
                    . html_writer::link(
                        new moodle_url('/course/view.php', ['id' => $row['courseid']]),
                        format_string($row['course']),
                        ['class' => 'agd-course']
                    ),
                ['scope' => 'row']
            );
            $html .= html_writer::tag('td', $row['ungraded'], ['class' => 'agd-num agd-count']);
            $html .= html_writer::tag(
                'td',
                self::badge($s['label'], $s['tone'], $s['icon'])
                    . ($s['waited'] !== '' ? html_writer::span(s($s['waited']), 'agd-waited') : ''),
                ['class' => 'agd-status']
            );
            $html .= html_writer::tag(
                'td',
                self::link(
                    $row['link'],
                    get_string('grade_now', 'block_aigrader_dashboard'),
                    'fa-arrow-right',
                    'agd-grade',
                    get_string('grade_now_quiz', 'block_aigrader_dashboard', $quizname),
                    true
                ),
                ['class' => 'agd-action']
            );
            $html .= html_writer::end_tag('tr');
        }
        $html .= html_writer::end_tag('tbody');

        $html .= html_writer::start_tag('tfoot') . html_writer::start_tag('tr');
        $html .= html_writer::tag('th', get_string('total', 'block_aigrader_dashboard'), ['scope' => 'row']);
        $html .= html_writer::tag('td', $total, ['class' => 'agd-num agd-count']);
        $html .= html_writer::tag(
            'td',
            $overdue > 0
                ? self::badge(get_string('total_overdue', 'block_aigrader_dashboard', $overdue), 'danger')
                : '',
            ['colspan' => 2, 'class' => 'agd-status']
        );
        $html .= html_writer::end_tag('tr') . html_writer::end_tag('tfoot');
        $html .= html_writer::end_tag('table');
        return $html . html_writer::end_div();
    }

    /**
     * Links below the table: all courses, the activity report, proctoring review.
     *
     * @param bool $fullpage On the full page already.
     * @param int $totalcourses Courses with essays waiting.
     * @return string HTML.
     */
    protected static function footer(bool $fullpage, int $totalcourses): string {
        $links = '';
        if (!$fullpage && $totalcourses > 0) {
            $links .= self::link(
                new moodle_url('/blocks/aigrader_dashboard/viewall.php'),
                get_string('view_all_courses', 'block_aigrader_dashboard', $totalcourses),
                'fa-list'
            );
        }
        if (is_siteadmin() && \core_component::get_component_directory('quiz_aigrader')) {
            $links .= self::link(
                new moodle_url('/mod/quiz/report/aigrader/grader_report.php'),
                get_string('view_activity_report', 'block_aigrader_dashboard'),
                'fa-chart-column'
            );
        }
        $links .= self::proctoring_link();
        $html = self::oversight_note();
        return $html . ($links === '' ? '' : html_writer::div($links, 'agd-foot'));
    }

    /**
     * For site administrators with the webcam proctoring rule installed: attempts to review.
     *
     * @return string HTML, or ''.
     */
    protected static function proctoring_link(): string {
        global $DB;
        if (!is_siteadmin() || !$DB->get_manager()->table_exists('quizaccess_webcamproctor_attempts')) {
            return '';
        }
        $flagged = (int)$DB->count_records_select('quizaccess_webcamproctor_attempts', "status = 'flagged'");
        $pending = (int)$DB->count_records_select('quizaccess_webcamproctor_attempts', "status IN ('pending', 'processing')");
        $badge = '';
        if ($flagged + $pending > 0) {
            $badge = self::badge(
                get_string('proctoring_to_review', 'block_aigrader_dashboard', $flagged + $pending),
                $flagged > 0 ? 'danger' : 'warning'
            );
        }
        return html_writer::span(
            self::link(
                new moodle_url('/mod/quiz/index.php'),
                get_string('proctoring_reports', 'block_aigrader_dashboard'),
                'fa-video'
            ) . $badge,
            'agd-foot-item'
        );
    }

    /**
     * A Moodle Design System Badge: a short label, optionally with a start icon.
     *
     * @param string $label The label (plain text).
     * @param string $tone primary, secondary, success, danger, warning or info.
     * @param string $icon Optional Font Awesome icon class.
     * @return string HTML
     */
    protected static function badge(string $label, string $tone, string $icon = ''): string {
        $classes = 'mds-badge badge mds-badge--subtle mds-badge--' . $tone . ($icon ? ' mds-badge--has-icon' : '');
        $iconhtml = $icon ? '<i class="fa-solid ' . $icon . '" aria-hidden="true"></i>' : '';
        return html_writer::span($iconhtml . s($label), $classes);
    }

    /**
     * A Moodle Design System Link, used for every navigation in the block.
     *
     * @param moodle_url|string $url Destination.
     * @param string $label Visible label (plain text).
     * @param string $icon Font Awesome icon class.
     * @param string $class Extra class.
     * @param string $arialabel Accessible name when the label alone is not specific.
     * @param bool $iconend Put the icon after the label.
     * @return string HTML
     */
    protected static function link(
        $url,
        string $label,
        string $icon,
        string $class = '',
        string $arialabel = '',
        bool $iconend = false
    ): string {
        $iconhtml = html_writer::span('<i class="fa-solid ' . $icon . '" aria-hidden="true"></i>', 'mds-link__icon');
        $labelhtml = html_writer::span(s($label), 'mds-link__label');
        $attrs = ['class' => trim('mds-link ' . $class)];
        if ($arialabel !== '') {
            $attrs['aria-label'] = $arialabel;
        }
        return html_writer::link($url, $iconend ? $labelhtml . $iconhtml : $iconhtml . $labelhtml, $attrs);
    }

    /**
     * Reminds graders that AI suggestions are drafts until a person approves them
     * (EU AI Act, Article 14: human oversight). Shown only when the AI Essay Grader is installed.
     *
     * @return string HTML
     */
    protected static function oversight_note(): string {
        if (!\core_component::get_component_directory('quiz_aigrader')) {
            return '';
        }
        return html_writer::div(
            '<i class="fa-solid fa-user-check" aria-hidden="true"></i>'
                . html_writer::span(get_string('oversight_note', 'block_aigrader_dashboard')),
            'agd-oversight'
        );
    }
}
