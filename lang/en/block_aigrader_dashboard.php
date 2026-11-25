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
 * Language strings for AI Essay Grader dashboard block
 *
 * @package    block_aigrader_dashboard
 * @copyright  2024 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['ai_paused_notice'] = 'AI suggestions are paused on this site. Essays can still be marked by hand in the AI Essay Grader.';
$string['aigrader_dashboard'] = 'AI Essay Grader dashboard';
$string['aigrader_dashboard:addinstance'] = 'Add a new AI Essay Grader dashboard block';
$string['aigrader_dashboard:myaddinstance'] = 'Add the AI Essay Grader dashboard to the Dashboard';
$string['aigrader_dashboard:viewall'] = 'See every course in the AI Essay Grader dashboard';
$string['all_graded'] = 'All graded';
$string['cache_ttl'] = 'Dashboard cache lifetime';
$string['cache_ttl_desc'] = 'How long, in seconds, the dashboard figures stay usable before they are recalculated. A scheduled task refreshes them every five minutes, so in normal operation nobody ever waits for the calculation. Leave this comfortably longer than that gap. Set to 0 to recalculate on every page load, which is only sensible on small sites.';
$string['cachedef_ungradeddata'] = 'Ungraded essay counts per course and quiz';
$string['col_action'] = 'Action';
$string['col_course'] = 'Course';
$string['col_quiz'] = 'Quiz';
$string['col_status'] = 'Status';
$string['col_tograde'] = 'To grade';
$string['collapse_all'] = 'Collapse all';
$string['courses_overflow_warning'] = 'Showing {$a->shown} of {$a->total} courses. {$a->hidden} more have essays waiting: see all courses.';
$string['courses_with_ungraded'] = 'Courses with ungraded essays';
$string['email_footer'] = 'This is an automated message from the AI Essay Grader dashboard.';
$string['email_greeting'] = 'Hello,';
$string['email_item'] = '{$a->course} > {$a->quiz}: {$a->count} waiting';
$string['email_item_overdue'] = '{$a->course} > {$a->quiz}: {$a->count} waiting, overdue';
$string['email_new_intro'] = 'The following essays have been submitted and are awaiting AI grading:';
$string['email_overdue_intro'] = 'The following essays have been waiting for grading beyond the threshold:';
$string['email_subject_new'] = 'New essays waiting for grading';
$string['email_subject_overdue'] = 'Overdue essays need grading';
$string['essay'] = 'essay';
$string['essay_grader_report'] = 'Essay Grader report';
$string['essays'] = 'essays';
$string['expand_all'] = 'Expand all';
$string['frequency_daily'] = 'Daily digest';
$string['frequency_hourly'] = 'Hourly digest';
$string['frequency_instant'] = 'Instant (as they occur)';
$string['frequency_weekly'] = 'Weekly digest';
$string['grade_now'] = 'Grade now';
$string['grade_now_quiz'] = 'Grade now: {$a}';
$string['hide_inactive_inherit'] = 'Use the AI Essay Grader setting (recommended)';
$string['hide_inactive_no'] = 'No — count everyone who ever attempted';
$string['hide_inactive_students'] = 'Hide submissions from inactive students';
$string['hide_inactive_students_desc'] = 'Controls whether essays awaiting grading are counted for students who no longer have an active enrolment — suspended, expired, unenrolled, or on a disabled enrolment method. Leave on "Use the AI Essay Grader setting" so the dashboard totals always match the grading queue. The explicit Yes/No options are for sites running this block without the AI Essay Grader report plugin installed.';
$string['hide_inactive_yes'] = 'Yes — hide inactive students';
$string['last_updated'] = 'Last updated';
$string['loading'] = 'Loading...';
$string['new'] = 'New';
$string['no_quizzes'] = 'No quizzes with essay questions found.';
$string['notification_cc'] = 'CC email';
$string['notification_cc_desc'] = 'Additional email address to CC on notifications (optional).';
$string['notification_email'] = 'Notification email';
$string['notification_email_desc'] = 'Primary email address to receive notifications about ungraded essays.';
$string['notification_frequency'] = 'Notification frequency';
$string['notification_frequency_desc'] = 'How often to send notification digests.';
$string['notify_new_essays'] = 'Notify about new essays';
$string['notify_new_essays_desc'] = 'Send notification when new essays are submitted for grading.';
$string['notify_overdue'] = 'Notify about overdue essays';
$string['notify_overdue_desc'] = 'Send notification when essays have been waiting for grading beyond the threshold.';
$string['overdue'] = 'Overdue';
$string['overdue_threshold'] = 'Overdue after (hours)';
$string['overdue_threshold_desc'] = 'Number of hours after which an ungraded essay is considered overdue.';
$string['oversight_note'] = 'AI suggestions are drafts. No mark reaches the gradebook until a grader checks and approves it.';
$string['pluginname'] = 'AI Essay Grader dashboard';
$string['privacy:metadata'] = 'The AI Essay Grader dashboard block stores no personal data. It counts essays waiting for grading; notification emails go to the addresses an administrator sets, and name courses and quizzes only.';
$string['proctoring_reports'] = 'Proctoring reports';
$string['proctoring_to_review'] = '{$a} to review';
$string['quizname'] = 'Quiz name';
$string['refresh'] = 'Refresh';
$string['reports'] = 'Reports';
$string['settings_header'] = 'Notifications';
$string['settings_header_desc'] = 'Configure email notifications for ungraded essays.';
$string['stat_courses'] = 'Courses';
$string['stat_quizzes'] = 'Quizzes';
$string['stat_to_grade'] = 'Essays to grade';
$string['status_duesoon'] = 'Due soon';
$string['status_ontime'] = 'On time';
$string['status_overdue'] = 'Overdue';
$string['status_waiting'] = 'Waiting {$a}';
$string['table_caption'] = 'Quizzes with essays waiting for grading, overdue first';
$string['task_send_notifications'] = 'Send AI Essay Grader dashboard notification emails';
$string['task_warm_cache'] = 'Refresh AI Essay Grader dashboard figures';
$string['total'] = 'Total';
$string['total_overdue'] = '{$a} overdue';
$string['total_ungraded'] = 'Total ungraded';
$string['ungraded'] = 'ungraded';
$string['view_activity_report'] = 'Activity report';
$string['view_all_back'] = 'Back to Dashboard';
$string['view_all_course_count'] = 'Courses with essays waiting: {$a}';
$string['view_all_courses'] = 'All courses ({$a})';
$string['view_all_courses_title'] = 'Essays waiting for grading';
$string['webcam_proctoring_report'] = 'Webcam proctoring report';
