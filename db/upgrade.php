<?php
/**
 * Upgrade script for AI Grader Dashboard Block
 *
 * @package    block_aigrader_dashboard
 * @copyright  2024 AI Grader
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade the block_aigrader_dashboard plugin.
 *
 * @param int $oldversion The old version of the plugin.
 * @return bool
 */
function xmldb_block_aigrader_dashboard_upgrade($oldversion) {
    global $DB;

    if ($oldversion < 2025120103) {
        // Assign myaddinstance capability to authenticated user role at system context
        // This is required because Dashboard operates at CONTEXT_SYSTEM where course roles don't exist
        $systemcontext = context_system::instance();
        
        // Get the authenticated user role (this is what users have at system level)
        $authuser = $DB->get_record('role', ['shortname' => 'user']);
        if ($authuser) {
            // Assign the capability if not already assigned
            $existing = $DB->get_record('role_capabilities', [
                'contextid' => $systemcontext->id,
                'roleid' => $authuser->id,
                'capability' => 'block/aigrader_dashboard:myaddinstance'
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
        
        // Also assign to manager role for good measure
        $manager = $DB->get_record('role', ['shortname' => 'manager']);
        if ($manager) {
            $existing = $DB->get_record('role_capabilities', [
                'contextid' => $systemcontext->id,
                'roleid' => $manager->id,
                'capability' => 'block/aigrader_dashboard:myaddinstance'
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

    // v2.0.1: FIX — viewall.php now requires blocklib.php before including the block class
    //         so block_base is defined. PHP-only fix; no DB schema changes.
    if ($oldversion < 202603240201) {
        upgrade_block_savepoint(true, 202603240201, 'aigrader_dashboard');
    }

    // v2.0.2: VERSION BUMP — Clean release. No DB schema changes.
    if ($oldversion < 202603240202) {
        upgrade_block_savepoint(true, 202603240202, 'aigrader_dashboard');
    }

    // v2.0.3: "View All" button is now always visible at the bottom of the block
    //   (previously only appeared when there were more than 10 courses). Adds an amber
    //   warning banner when the block limit (10 courses) is exceeded — clearly informing
    //   teachers that additional courses with ungraded marking requirements are not
    //   visible in the block and they must click "View All" to see them. Button text
    //   now shows the total course count. No DB schema changes.
    //   Files changed: block_aigrader_dashboard.php, styles.css, lang/en/...php.
    if ($oldversion < 202604170203) {
        upgrade_block_savepoint(true, 202604170203, 'aigrader_dashboard');
    }

    // v2.0.4: BUG-AGD-BLOCKBASE — viewall.php still threw "Class 'block_base' not found"
    //   on some Moodle installations despite the v2.0.1 attempt (loading blocklib.php).
    //   Definitive fix: shared data-fetching logic extracted into locallib.php as plain
    //   functions with zero dependency on block_base or blocklib.php. viewall.php now
    //   requires locallib.php directly and calls aigrader_dashboard_fetch_all_data().
    //   block_aigrader_dashboard::fetch_all_data() proxies to locallib. PHP-only fix.
    //   No DB schema changes. version.php → 202604170204.
    if ($oldversion < 202604170204) {
        upgrade_block_savepoint(true, 202604170204, 'aigrader_dashboard');
    }

    // v2.0.5 - BUG FIX: viewall.php had two display issues.
    //   1. [[quizname]] placeholder: get_string('quizname', 'quiz') fails on Moodle
    //      installations where the quiz plugin lang string 'quizname' is absent or not
    //      yet loaded — Moodle outputs the [[quizname]] fallback. Fix: added 'quizname'
    //      string ("Quiz name") to block's own lang/en/block_aigrader_dashboard.php and
    //      changed the call to get_string('quizname', 'block_aigrader_dashboard').
    //   2. Column misalignment: each course section renders its own independent <table>,
    //      so browsers sized each table's columns independently, producing unequal column
    //      widths across courses. Fix: table-layout:fixed + explicit column width
    //      percentages (50/15/15/20%) via CSS so all tables have identical column
    //      proportions regardless of content. PHP-only + lang + CSS fix. No DB changes.
    //      version.php → 2026041700205. Also corrects previous 12-digit numeric
    //      (202604170204 → 2026041700205, now properly 13 digits).
    if ($oldversion < 2026041700205) {
        upgrade_block_savepoint(true, 2026041700205, 'aigrader_dashboard');
    }

    // v2.0.7: SAVEPOINT-BUMP — no-op marker for clean upgrade path. No DB schema changes.
    if ($oldversion < 2026060400207) {
        upgrade_block_savepoint(true, 2026060400207, 'aigrader_dashboard');
    }

    if ($oldversion < 2026060400208) {
        // Domain update: lms-labs.com → lms-labs.com
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['version.php', 'lib.php', 'db/upgrade.php'] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) { opcache_invalidate($_full, true); }
            }
        } elseif (function_exists('opcache_reset')) { opcache_reset(); }
        upgrade_block_savepoint(true, 2026060400208, 'aigrader_dashboard');
    }

    return true;
}