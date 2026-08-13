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
 * Settings for AI Grader Dashboard Block
 *
 * @package    block_aigrader_dashboard
 * @copyright  2024 AI Grader
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    // Header
    $settings->add(new admin_setting_heading(
        'block_aigrader_dashboard/header',
        get_string('settings_header', 'block_aigrader_dashboard'),
        get_string('settings_header_desc', 'block_aigrader_dashboard')
    ));

    // Primary notification email
    $settings->add(new admin_setting_configtext(
        'block_aigrader_dashboard/notification_email',
        get_string('notification_email', 'block_aigrader_dashboard'),
        get_string('notification_email_desc', 'block_aigrader_dashboard'),
        '',
        PARAM_EMAIL
    ));

    // CC email
    $settings->add(new admin_setting_configtext(
        'block_aigrader_dashboard/notification_cc',
        get_string('notification_cc', 'block_aigrader_dashboard'),
        get_string('notification_cc_desc', 'block_aigrader_dashboard'),
        '',
        PARAM_EMAIL
    ));

    // Notify on new essays
    $settings->add(new admin_setting_configcheckbox(
        'block_aigrader_dashboard/notify_new_essays',
        get_string('notify_new_essays', 'block_aigrader_dashboard'),
        get_string('notify_new_essays_desc', 'block_aigrader_dashboard'),
        1
    ));

    // Notify on overdue essays
    $settings->add(new admin_setting_configcheckbox(
        'block_aigrader_dashboard/notify_overdue',
        get_string('notify_overdue', 'block_aigrader_dashboard'),
        get_string('notify_overdue_desc', 'block_aigrader_dashboard'),
        1
    ));

    // Inactive student filter (v2.1.0). Defaults to inheriting the AI Essay Grader
    // setting so the dashboard total and the grading queue never disagree.
    $settings->add(new admin_setting_configselect(
        'block_aigrader_dashboard/hide_inactive_students',
        get_string('hide_inactive_students', 'block_aigrader_dashboard'),
        get_string('hide_inactive_students_desc', 'block_aigrader_dashboard'),
        'inherit',
        [
            'inherit' => get_string('hide_inactive_inherit', 'block_aigrader_dashboard'),
            '1'       => get_string('hide_inactive_yes', 'block_aigrader_dashboard'),
            '0'       => get_string('hide_inactive_no', 'block_aigrader_dashboard'),
        ]
    ));

    // Overdue threshold (hours)
    $settings->add(new admin_setting_configtext(
        'block_aigrader_dashboard/overdue_threshold',
        get_string('overdue_threshold', 'block_aigrader_dashboard'),
        get_string('overdue_threshold_desc', 'block_aigrader_dashboard'),
        '24',
        PARAM_INT
    ));

    // Notification frequency
    $frequencies = [
        'instant' => get_string('frequency_instant', 'block_aigrader_dashboard'),
        'hourly'  => get_string('frequency_hourly', 'block_aigrader_dashboard'),
        'daily'   => get_string('frequency_daily', 'block_aigrader_dashboard'),
        'weekly'  => get_string('frequency_weekly', 'block_aigrader_dashboard'),
    ];
    $settings->add(new admin_setting_configselect(
        'block_aigrader_dashboard/notification_frequency',
        get_string('notification_frequency', 'block_aigrader_dashboard'),
        get_string('notification_frequency_desc', 'block_aigrader_dashboard'),
        'daily',
        $frequencies
    ));
}
