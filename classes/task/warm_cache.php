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
 * Scheduled task that refreshes the cached ungraded-essay figures.
 *
 * @package    block_aigrader_dashboard
 * @copyright  2026 AI Grader
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_aigrader_dashboard\task;

/**
 * Recalculate the dashboard figures on cron so page loads never have to.
 *
 * The aggregation is expensive on a site with a large attempt history. Running it here
 * means the block always reads a ready-made result, and nobody waits for the query while
 * a page is loading.
 *
 * @package    block_aigrader_dashboard
 * @copyright  2026 AI Grader
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class warm_cache extends \core\task\scheduled_task {
    /**
     * Name shown in the scheduled tasks list.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_warm_cache', 'block_aigrader_dashboard');
    }

    /**
     * Recalculate and store the figures.
     */
    public function execute() {
        global $CFG;
        require_once($CFG->dirroot . '/blocks/aigrader_dashboard/locallib.php');

        if (aigrader_dashboard_cache_ttl() <= 0) {
            mtrace('AI Grader Dashboard: caching is disabled, nothing to refresh.');
            return;
        }

        $started = microtime(true);
        $rows = aigrader_dashboard_warm_cache();
        $elapsed = round(microtime(true) - $started, 2);

        mtrace("AI Grader Dashboard: refreshed {$rows} course/quiz rows in {$elapsed}s.");
    }
}
