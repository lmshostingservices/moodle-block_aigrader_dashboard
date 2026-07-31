<?php
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
