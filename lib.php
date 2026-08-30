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
 * Library functions for AI Grader Dashboard Block
 *
 * @package    block_aigrader_dashboard
 * @copyright  2024 AI Grader
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Serves block files.
 *
 * @param stdClass $course Course object
 * @param stdClass $cm Course module object
 * @param context $context Context
 * @param string $filearea File area
 * @param array $args Arguments
 * @param bool $forcedownload Force download
 * @param array $options Additional options
 * @return bool False if file not found
 */
function block_aigrader_dashboard_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    return false;
}
