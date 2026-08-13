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
 * Scheduled task to send notification emails for ungraded essays
 *
 * @package    block_aigrader_dashboard
 * @copyright  2024 AI Grader
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_aigrader_dashboard\task;

defined('MOODLE_INTERNAL') || die();

class send_notifications extends \core\task\scheduled_task {
    /**
     * Return the task name
     */
    public function get_name() {
        return get_string('task_send_notifications', 'block_aigrader_dashboard');
    }

    /**
     * Execute the task
     */
    public function execute() {
        global $DB, $CFG;

        $email = get_config('block_aigrader_dashboard', 'notification_email');
        if (empty($email)) {
            mtrace('AI Grader Dashboard: No notification email configured, skipping.');
            return;
        }

        $notify_new = get_config('block_aigrader_dashboard', 'notify_new_essays');
        $notify_overdue = get_config('block_aigrader_dashboard', 'notify_overdue');
        $overdue_hours = (int) get_config('block_aigrader_dashboard', 'overdue_threshold') ?: 24;
        $frequency = get_config('block_aigrader_dashboard', 'notification_frequency') ?: 'daily';
        $cc_email = get_config('block_aigrader_dashboard', 'notification_cc');

        // Check frequency - only run at appropriate intervals
        $last_checked = get_config('block_aigrader_dashboard', 'last_notification_checked');
        $now = time();
        
        switch ($frequency) {
            case 'hourly':
                if ($last_checked && ($now - $last_checked) < 3600) {
                    return; // Silent skip - don't spam logs
                }
                break;
            case 'daily':
                if ($last_checked && ($now - $last_checked) < 86400) {
                    return;
                }
                break;
            case 'weekly':
                if ($last_checked && ($now - $last_checked) < 604800) {
                    return;
                }
                break;
            // 'instant' - always proceed
        }
        
        // Always update last checked time to prevent repeated cron runs
        set_config('last_notification_checked', $now, 'block_aigrader_dashboard');

        // Get ungraded essay data
        $ungraded_data = $this->get_ungraded_essays();
        
        if (empty($ungraded_data['courses'])) {
            mtrace('AI Grader Dashboard: No ungraded essays found.');
            return;
        }

        $overdue_threshold = $now - ($overdue_hours * 3600);
        $has_new = false;
        $has_overdue = false;
        $new_items = [];
        $overdue_items = [];

        foreach ($ungraded_data['courses'] as $course) {
            foreach ($course['quizzes'] as $quiz) {
                if ($quiz['ungraded'] > 0) {
                    $item = [
                        'course' => $course['name'],
                        'quiz' => $quiz['name'],
                        'count' => $quiz['ungraded'],
                        'link' => $quiz['link'],
                    ];

                    // Check if any are overdue
                    if (!empty($quiz['oldest_ungraded']) && $quiz['oldest_ungraded'] < $overdue_threshold) {
                        $has_overdue = true;
                        $overdue_items[] = $item;
                    } else {
                        $has_new = true;
                        $new_items[] = $item;
                    }
                }
            }
        }

        // Build and send email
        $message_parts = [];
        
        if ($notify_new && $has_new && !empty($new_items)) {
            $message_parts[] = get_string('email_new_intro', 'block_aigrader_dashboard');
            foreach ($new_items as $item) {
                $message_parts[] = "  • {$item['course']} > {$item['quiz']}: {$item['count']} " . 
                    ($item['count'] == 1 ? 'essay' : 'essays');
            }
            $message_parts[] = '';
        }

        if ($notify_overdue && $has_overdue && !empty($overdue_items)) {
            $message_parts[] = get_string('email_overdue_intro', 'block_aigrader_dashboard');
            foreach ($overdue_items as $item) {
                $message_parts[] = "  ⚠️ {$item['course']} > {$item['quiz']}: {$item['count']} " .
                    ($item['count'] == 1 ? 'essay' : 'essays') . " (OVERDUE)";
            }
            $message_parts[] = '';
        }

        if (empty($message_parts)) {
            mtrace('AI Grader Dashboard: No notifications to send based on settings.');
            return;
        }

        // Compose email
        $subject = $has_overdue 
            ? get_string('email_subject_overdue', 'block_aigrader_dashboard')
            : get_string('email_subject_new', 'block_aigrader_dashboard');

        $message = get_string('email_greeting', 'block_aigrader_dashboard') . "\n\n";
        $message .= implode("\n", $message_parts);
        $message .= "\n" . get_string('email_footer', 'block_aigrader_dashboard');

        // Send email using Moodle's email API
        $admin = get_admin();
        $user = new \stdClass();
        $user->id = -1;
        $user->email = $email;
        $user->firstname = 'AI Grader';
        $user->lastname = 'Notification';
        $user->maildisplay = 1;
        $user->mailformat = 1;

        $success = email_to_user($user, $admin, $subject, $message);
        
        if ($success) {
            mtrace("AI Grader Dashboard: Notification sent to {$email}");
            
            // Send CC if configured
            if (!empty($cc_email)) {
                $cc_user = clone $user;
                $cc_user->email = $cc_email;
                email_to_user($cc_user, $admin, $subject, $message);
                mtrace("AI Grader Dashboard: CC sent to {$cc_email}");
            }
        } else {
            mtrace("AI Grader Dashboard: Failed to send notification to {$email}");
        }
    }

    /**
     * Get ungraded essay data across all courses.
     *
     * v2.1.0: replaced with a delegation to locallib.php. The copy that lived here had
     * drifted from the block's version — it never received the RC3 rewrite (still running
     * a correlated MAX(sequencenumber) subquery per attempt row) and selected courseid as
     * its first column, which is not unique across quizzes, so get_records_sql() silently
     * discarded every quiz after the first in any course with more than one. Both faults
     * disappear with the shared implementation, which also applies the inactive-student
     * and blank-answer filters.
     *
     * Passing null reports on every course, preserving this task's site-wide scope.
     *
     * @return array{courses: array, total: int, overdue: int}
     */
    private function get_ungraded_essays() {
        global $CFG;
        require_once($CFG->dirroot . '/blocks/aigrader_dashboard/locallib.php');

        return aigrader_dashboard_get_ungraded_data(null);
    }
}
