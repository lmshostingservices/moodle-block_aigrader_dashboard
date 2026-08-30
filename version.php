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
 * Version information for AI Grader Dashboard Block.
 *
 * v2.0.4 - FIX (BUG-AGD-BLOCKBASE): viewall.php still threw "Class 'block_base' not found"
 *          on some Moodle installations despite the v2.0.1 attempt. Root cause: loading
 *          blocklib.php via require_once is fragile — Moodle's autoloader caches this
 *          differently across 4.0–4.5+ versions. Definitive fix: extracted the shared
 *          data-fetching logic (get_gradable_course_ids, get_ungraded_data,
 *          fetch_all_data) into locallib.php as plain functions with ZERO dependency on
 *          block_base or blocklib.php. viewall.php now requires locallib.php directly and
 *          calls aigrader_dashboard_fetch_all_data() — no block class file is loaded.
 *          block_aigrader_dashboard::fetch_all_data() proxies to locallib so existing
 *          callers continue to work. PHP-only fix (locallib.php added, viewall.php +
 *          block_aigrader_dashboard.php updated). No DB schema changes.
 *
 * v2.0.1 - FIX: viewall.php threw "Class block_base not found" when accessed directly.
 *          Root cause: block_base is only auto-loaded by Moodle's block rendering pipeline;
 *          manually require_once-ing the block class file from a standalone page skips that
 *          pipeline. Fix: added require_once($CFG->libdir.'/blocklib.php') before the block
 *          class include in viewall.php so block_base is available at parse time. PHP-only fix.
 *
 * v1.9.8 - PERFORMANCE: enrol_get_users_courses() was called twice per page load (once in
 *          user_can_view() and again in get_ungraded_data()). Now called once and result shared.
 *          Correlated subquery on question_attempt_steps replaced with LEFT JOIN anti-pattern.
 *          Post-SQL per-row capability check loop removed: gradable course IDs are now
 *          pre-filtered before the SQL, so only relevant courses are queried.
 *          Site admin cap added: loads at most 500 most-recently-modified courses to prevent
 *          runaway IN() queries on large installations.
 *
 * @package    block_aigrader_dashboard
 * @copyright  2025 Essay Grader AI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'block_aigrader_dashboard';
$plugin->version   = 2026083001;
$plugin->requires  = 2022041900;
$plugin->supported  = [400, 500];  // Moodle 4.0 to 5.x.
$plugin->maturity  = MATURITY_STABLE;
// Release recovery after v2.1.2 was correctly blocked by mandatory CI.
$plugin->release   = '2.1.3';
