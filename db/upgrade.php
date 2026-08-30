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
 * Upgrade script for AI Grader Dashboard Block
 *
 * @package    block_aigrader_dashboard
 * @copyright  2024 AI Grader
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the block_aigrader_dashboard plugin.
 *
 * @param int $oldversion The old version of the plugin.
 * @return bool
 */
function xmldb_block_aigrader_dashboard_upgrade($oldversion) {
    global $DB;

    if ($oldversion < 2025120103) {
        // Assign myaddinstance capability to the authenticated user role at system context.
        $systemcontext = context_system::instance();
        // Get the authenticated user role (this is what users have at system level).
        $authuser = $DB->get_record('role', ['shortname' => 'user']);
        if ($authuser) {
            // Assign the capability if not already assigned.
            $existing = $DB->get_record('role_capabilities', [
                'contextid' => $systemcontext->id,
                'roleid' => $authuser->id,
                'capability' => 'block/aigrader_dashboard:myaddinstance',
            ]);
            if (!$existing) {
                assign_capability(
                    'block/aigrader_dashboard:myaddinstance',
                    CAP_ALLOW,
                    $authuser->id,
                    $systemcontext->id,
                    true
                );
            }
        }

        // Also assign to manager role for good measure.
        $manager = $DB->get_record('role', ['shortname' => 'manager']);
        if ($manager) {
            $existing = $DB->get_record('role_capabilities', [
                'contextid' => $systemcontext->id,
                'roleid' => $manager->id,
                'capability' => 'block/aigrader_dashboard:myaddinstance',
            ]);
            if (!$existing) {
                assign_capability(
                    'block/aigrader_dashboard:myaddinstance',
                    CAP_ALLOW,
                    $manager->id,
                    $systemcontext->id,
                    true
                );
            }
        }

        // Block savepoint reached.
        upgrade_block_savepoint(true, 2025120103, 'aigrader_dashboard');
    }

    if ($oldversion < 2026032401) {
        upgrade_block_savepoint(true, 2026032401, 'aigrader_dashboard');
    }

    if ($oldversion < 2026032402) {
        upgrade_block_savepoint(true, 2026032402, 'aigrader_dashboard');
    }

    if ($oldversion < 2026041703) {
        upgrade_block_savepoint(true, 2026041703, 'aigrader_dashboard');
    }

    if ($oldversion < 2026041704) {
        upgrade_block_savepoint(true, 2026041704, 'aigrader_dashboard');
    }

    if ($oldversion < 2026041705) {
        upgrade_block_savepoint(true, 2026041705, 'aigrader_dashboard');
    }

    if ($oldversion < 2026060407) {
        upgrade_block_savepoint(true, 2026060407, 'aigrader_dashboard');
    }

    if ($oldversion < 2026060408) {
        // Invalidate cached plugin files after the domain update.
        if (function_exists('opcache_invalidate')) {
            $plugindir = realpath(__DIR__ . '/..');
            foreach (['version.php', 'lib.php', 'db/upgrade.php'] as $file) {
                $fullpath = $plugindir . '/' . $file;
                if (file_exists($fullpath)) {
                    opcache_invalidate($fullpath, true);
                }
            }
        } else if (function_exists('opcache_reset')) {
            opcache_reset();
        }
        upgrade_block_savepoint(true, 2026060408, 'aigrader_dashboard');
    }

    return true;
}
