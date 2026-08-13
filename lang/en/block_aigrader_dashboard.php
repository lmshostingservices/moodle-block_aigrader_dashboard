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
 * Language strings for AI Grader Dashboard Block
 *
 * @package    block_aigrader_dashboard
 * @copyright  2024 AI Grader
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'AI Grader Dashboard';
$string['aigrader_dashboard'] = 'AI Grader Dashboard';
$string['aigrader_dashboard:addinstance'] = 'Add a new AI Grader Dashboard block';
$string['aigrader_dashboard:myaddinstance'] = 'Add AI Grader Dashboard to Dashboard';
$string['aigrader_dashboard:viewall'] = 'View all courses in AI Grader Dashboard';

// Block content
$string['no_quizzes'] = 'No quizzes with essay questions found.';
$string['all_graded'] = 'All graded';
$string['ungraded'] = 'ungraded';
$string['grade_now'] = 'Grade Now';
$string['essays'] = 'essays';
$string['essay'] = 'essay';
$string['overdue'] = 'Overdue';
$string['new'] = 'New';
$string['loading'] = 'Loading...';
$string['refresh'] = 'Refresh';
$string['collapse_all'] = 'Collapse All';
$string['expand_all'] = 'Expand All';
$string['last_updated'] = 'Last updated';
$string['total_ungraded'] = 'Total ungraded';
$string['courses_with_ungraded'] = 'Courses with ungraded essays';

// Settings
$string['settings_header'] = 'Notification Settings';
$string['settings_header_desc'] = 'Configure email notifications for ungraded essays.';
$string['notification_email'] = 'Notification Email';
$string['notification_email_desc'] = 'Primary email address to receive notifications about ungraded essays.';
$string['notification_cc'] = 'CC Email';
$string['notification_cc_desc'] = 'Additional email address to CC on notifications (optional).';
$string['notify_new_essays'] = 'Notify on New Essays';
$string['notify_new_essays_desc'] = 'Send notification when new essays are submitted for grading.';
$string['notify_overdue'] = 'Notify on Overdue Essays';
$string['notify_overdue_desc'] = 'Send notification when essays have been waiting for grading beyond the threshold.';
$string['overdue_threshold'] = 'Overdue Threshold (hours)';
$string['overdue_threshold_desc'] = 'Number of hours after which an ungraded essay is considered overdue.';
$string['notification_frequency'] = 'Notification Frequency';
$string['notification_frequency_desc'] = 'How often to send notification digests.';
$string['frequency_instant'] = 'Instant (as they occur)';
$string['frequency_hourly'] = 'Hourly digest';
$string['frequency_daily'] = 'Daily digest';
$string['frequency_weekly'] = 'Weekly digest';

// Email templates
$string['email_subject_new'] = 'New Essays Awaiting Grading';
$string['email_subject_overdue'] = 'Overdue Essays Require Attention';
$string['email_greeting'] = 'Hello,';
$string['email_new_intro'] = 'The following essays have been submitted and are awaiting AI grading:';
$string['email_overdue_intro'] = 'The following essays have been waiting for grading beyond the threshold:';
$string['email_footer'] = 'This is an automated notification from AI Grader Dashboard.';

// Task
$string['task_send_notifications'] = 'Send AI Grader notification emails';

// Privacy
$string['privacy:metadata'] = 'The AI Grader Dashboard block does not store any personal data.';

// Activity Report
$string['view_activity_report'] = 'View Activity Report';

// View All page
$string['quizname'] = 'Quiz name';
$string['view_all_courses'] = 'View all {$a->total} courses';
$string['view_all_courses_title'] = 'All Courses with Ungraded Essays';
$string['courses_overflow_warning'] = 'Showing {$a->shown} of {$a->total} courses — {$a->hidden} course(s) with ungraded essays are not shown above.';
$string['view_all_back'] = 'Back to Dashboard';
$string['view_all_course_count'] = '{$a} course(s) with ungraded essays';

// Reports dropdown
$string['reports'] = 'Reports';
$string['essay_grader_report'] = 'Essay Grader Report';
$string['webcam_proctoring_report'] = 'Webcam Proctoring Report';
