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
 * View All Courses — full-page view of every course with ungraded essays.
 *
 * Linked from the AI Grader Dashboard block when more than 10 courses have
 * ungraded work and cannot all fit in the sidebar block widget.
 *
 * @package    block_aigrader_dashboard
 * @copyright  2026 AI Grader
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
// Previous versions loaded block_aigrader_dashboard.php.
// (which extends block_base) here directly. block_base lives in blocklib.php which is
// normally only auto-loaded by the block rendering pipeline — NOT during a plain page
// request — causing "Class 'block_base' not found" on some Moodle installations.
// Fix: load locallib.php instead (pure functions, zero dependency on block_base) and
// call aigrader_dashboard_fetch_all_data() directly.
require_once($CFG->dirroot . '/blocks/aigrader_dashboard/locallib.php');

require_login();

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/blocks/aigrader_dashboard/viewall.php'));
$PAGE->set_title(get_string('view_all_courses_title', 'block_aigrader_dashboard'));
$PAGE->set_heading(get_string('view_all_courses_title', 'block_aigrader_dashboard'));

// Any user who can grade essays in at least one course may view this page.
// The data is already filtered by get_gradable_course_ids() inside fetch_all_data().
if (
    !is_siteadmin() &&
    !has_capability('block/aigrader_dashboard:viewall', $context) &&
    !has_capability('mod/quiz:grade', $context)
) {
    require_capability('block/aigrader_dashboard:viewall', $context);
}

$PAGE->set_pagelayout('standard');

$data = aigrader_dashboard_fetch_all_data();
$courses = $data['courses'];
$total   = $data['total'];
$overdue = $data['overdue'];

echo $OUTPUT->header();

// Back link.
$dashboardurl = new moodle_url('/my/');
echo html_writer::div(
    html_writer::link(
        $dashboardurl,
        '&#8592; ' . get_string('view_all_back', 'block_aigrader_dashboard'),
        ['class' => 'agd-back-link']
    ),
    'agd-viewall-back'
);

// Page heading row with summary stats.
echo html_writer::start_div('agd-viewall-header');
echo html_writer::tag('h2', get_string('view_all_courses_title', 'block_aigrader_dashboard'));

if ($total > 0) {
    echo html_writer::start_div('agd-viewall-stats');
    echo html_writer::tag(
        'span',
        $total . ' ' . get_string('total_ungraded', 'block_aigrader_dashboard'),
        ['class' => 'agd-stat-pill agd-stat-warning']
    );
    if ($overdue > 0) {
        echo html_writer::tag(
            'span',
            $overdue . ' ' . get_string('overdue', 'block_aigrader_dashboard'),
            ['class' => 'agd-stat-pill agd-stat-danger']
        );
    }
    echo html_writer::end_div();
}
echo html_writer::end_div();

if (empty($courses)) {
    echo html_writer::div(
        get_string('no_quizzes', 'block_aigrader_dashboard'),
        'agd-viewall-empty'
    );
} else {
    echo html_writer::tag(
        'p',
        get_string('view_all_course_count', 'block_aigrader_dashboard', count($courses)),
        ['class' => 'agd-viewall-count']
    );

    echo html_writer::start_div('agd-viewall-courses');

    foreach ($courses as $course) {
        echo html_writer::start_div('agd-viewall-course');

        // Course header.
        echo html_writer::start_div('agd-viewall-course-header');
        echo html_writer::tag(
            'span',
            format_string($course['name']),
            ['class' => 'agd-viewall-course-name']
        );
        echo html_writer::tag(
            'span',
            $course['total_ungraded'] . ' ' . get_string('ungraded', 'block_aigrader_dashboard'),
            ['class' => 'agd-badge agd-badge-warning']
        );
        echo html_writer::end_div();

        // Quiz rows inside this course.
        echo html_writer::start_tag('table', ['class' => 'agd-viewall-table generaltable']);
        echo html_writer::start_tag('thead');
        echo html_writer::start_tag('tr');
        echo html_writer::tag('th', get_string('quizname', 'block_aigrader_dashboard'), ['class' => 'agd-viewall-th-quiz']);
        echo html_writer::tag('th', get_string('ungraded', 'block_aigrader_dashboard'), ['class' => 'agd-viewall-th-count']);
        echo html_writer::tag('th', get_string('overdue', 'block_aigrader_dashboard'), ['class' => 'agd-viewall-th-overdue']);
        echo html_writer::tag('th', '', ['class' => 'agd-viewall-th-action']);
        echo html_writer::end_tag('tr');
        echo html_writer::end_tag('thead');
        echo html_writer::start_tag('tbody');

        foreach ($course['quizzes'] as $quiz) {
            $rowclass = $quiz['is_overdue'] ? 'agd-viewall-row-overdue' : '';
            echo html_writer::start_tag('tr', ['class' => $rowclass]);

            echo html_writer::tag('td', format_string($quiz['name']));
            echo html_writer::tag('td', $quiz['ungraded']);
            echo html_writer::tag(
                'td',
                $quiz['is_overdue']
                    ? html_writer::tag(
                        'span',
                        get_string('overdue', 'block_aigrader_dashboard'),
                        ['class' => 'agd-badge agd-badge-danger']
                    )
                    : '&mdash;'
            );
            echo html_writer::tag(
                'td',
                html_writer::link(
                    $quiz['link'],
                    get_string('grade_now', 'block_aigrader_dashboard'),
                    ['class' => 'agd-btn agd-btn-primary agd-btn-sm']
                )
            );

            echo html_writer::end_tag('tr');
        }

        echo html_writer::end_tag('tbody');
        echo html_writer::end_tag('table');

        echo html_writer::end_div(); // End agd-viewall-course.
    }

    echo html_writer::end_div(); // End agd-viewall-courses.
}

// Add inline styles for the viewall page.
echo html_writer::tag('style', '
.agd-viewall-back { margin-bottom: 1rem; }
.agd-back-link { color: inherit; text-decoration: none; opacity: .7; }
.agd-back-link:hover { opacity: 1; text-decoration: underline; }
.agd-viewall-header { display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; margin-bottom: 1rem; }
.agd-viewall-header h2 { margin: 0; }
.agd-viewall-stats { display: flex; gap: .5rem; flex-wrap: wrap; }
.agd-stat-pill { display: inline-block; padding: .2em .7em; border-radius: 1em; font-size: .85em; font-weight: 600; }
.agd-stat-pill.agd-stat-warning { background: #fff3cd; color: #856404; }
.agd-stat-pill.agd-stat-danger  { background: #f8d7da; color: #721c24; }
.agd-viewall-count { color: #6c757d; margin-bottom: 1rem; }
.agd-viewall-empty { padding: 2rem; text-align: center; color: #6c757d; }
.agd-viewall-courses { display: flex; flex-direction: column; gap: 1.5rem; }
.agd-viewall-course { border: 1px solid #dee2e6; border-radius: 6px; overflow: hidden; }
 .agd-viewall-course-header { display: flex; align-items: center; gap: .75rem; padding: .75rem 1rem; background: #f8f9fa; }
 .agd-viewall-course-header { border-bottom: 1px solid #dee2e6; flex-wrap: wrap; }
.agd-viewall-course-name { font-weight: 600; flex: 1; }
.agd-viewall-table { margin: 0 !important; border: none !important; width: 100% !important; table-layout: fixed !important; }
 .agd-viewall-table th, .agd-viewall-table td { padding: .5rem 1rem !important; overflow: hidden; }
 .agd-viewall-table th, .agd-viewall-table td { text-overflow: ellipsis; white-space: nowrap; }
.agd-viewall-th-quiz, .agd-viewall-table td:nth-child(1) { width: 50% !important; }
.agd-viewall-th-count, .agd-viewall-table td:nth-child(2) { width: 15% !important; text-align: center !important; }
.agd-viewall-th-overdue, .agd-viewall-table td:nth-child(3) { width: 15% !important; text-align: center !important; }
.agd-viewall-th-action, .agd-viewall-table td:nth-child(4) { width: 20% !important; text-align: right !important; }
.agd-viewall-row-overdue td { background: #fff8f0; }
.agd-btn-sm { padding: .25rem .65rem !important; font-size: .85em !important; }
/* Reuse block badge styles */
 .agd-badge { display: inline-block; padding: .2em .6em; border-radius: 1em; font-size: .8em; font-weight: 600; }
.agd-badge-warning { background: #fff3cd; color: #856404; }
.agd-badge-danger  { background: #f8d7da; color: #721c24; }
 .agd-btn { display: inline-flex; align-items: center; gap: .3rem; padding: .35rem .75rem; border-radius: 4px; }
 .agd-btn { text-decoration: none; font-size: .85rem; font-weight: 500; cursor: pointer; }
.agd-btn-primary { background: #0d6efd; color: #fff; }
.agd-btn-primary:hover { background: #0b5ed7; color: #fff; }
.agd-btn-secondary { background: #6c757d; color: #fff; }
.agd-btn-secondary:hover { background: #5c636a; color: #fff; }
');

echo $OUTPUT->footer();
