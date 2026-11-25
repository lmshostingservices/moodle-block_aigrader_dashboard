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
 * Linked from the AI Essay Grader dashboard block: every course with essays waiting.
 *
 * @package    block_aigrader_dashboard
 * @copyright  2026 LMS Hosting Services
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

// Anyone who can grade essays in at least one course may open this page: it lists only the
// courses they can grade. The check was made against the system context, where a course
// teacher holds neither capability, so the block's own "View all" link failed for teachers.
$data = aigrader_dashboard_fetch_all_data();
if (!$data['courses'] && !is_siteadmin() && !aigrader_dashboard_get_gradable_course_ids()) {
    require_capability('block/aigrader_dashboard:viewall', $context);
}

$PAGE->set_pagelayout('standard');
$PAGE->add_body_class('block_aigrader_dashboard-page');

echo $OUTPUT->header();
echo html_writer::start_div('block_aigrader_dashboard agd-page');
echo \block_aigrader_dashboard\output\dashboard::render($data, true);
echo html_writer::end_div();
echo $OUTPUT->footer();
