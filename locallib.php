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
 * AI Grader Dashboard Block — shared data-fetching functions.
 *
 * Extracted here so that viewall.php can fetch dashboard data WITHOUT needing
 * to load block_aigrader_dashboard.php (which extends block_base). Loading a
 * block class on a standalone page requires blocklib.php to be pre-loaded, but
 * blocklib.php is not always available at require_once() time — the Moodle
 * autoloader registers it lazily. Using plain functions in locallib.php avoids
 * that class-not-found error entirely.
 *
 * Called by:
 *  - block_aigrader_dashboard::fetch_all_data()  (proxies here)
 *  - viewall.php  (calls aigrader_dashboard_fetch_all_data() directly)
 *
 * @package    block_aigrader_dashboard
 * @copyright  2026 AI Grader
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Return every course-id where the current user can grade essays.
 * Site admins get all visible courses (capped at 500 most-recently-modified).
 *
 * @return int[]
 */
function aigrader_dashboard_get_gradable_course_ids(): array {
    global $DB, $USER;

    if (is_siteadmin()) {
        $records = $DB->get_records('course', ['visible' => 1], 'timemodified DESC', 'id', 0, 500);
        return array_keys($records);
    }

    $usercourses = enrol_get_users_courses($USER->id, true);
    $gradable = [];
    foreach ($usercourses as $course) {
        $context = context_course::instance($course->id);
        if (has_capability('mod/quiz:grade', $context)) {
            $gradable[] = $course->id;
        }
    }
    return $gradable;
}

/**
 * Whether submissions from inactive students should be excluded (v2.1.0).
 *
 * The switch lives in quiz_aigrader so a single setting governs both the grading
 * queue and this dashboard — the two must never disagree about what is countable.
 * get_config() returns false for a setting that has never been written (the case on
 * upgrade until an admin visits the settings page), so the default is applied here.
 * A local override is honoured first for sites running the block without the report
 * plugin installed.
 *
 * @return bool
 */
function aigrader_dashboard_hide_inactive_enabled(): bool {
    $local = get_config('block_aigrader_dashboard', 'hide_inactive_students');

    // The 'inherit' default defers to quiz_aigrader so one switch governs the whole.
    // suite; the explicit values exist for sites running the block on its own.
    if ($local === '1' || $local === 1) {
        return true;
    }
    if ($local === '0' || $local === 0) {
        return false;
    }

    $shared = get_config('quiz_aigrader', 'hide_inactive_students');
    if ($shared === false || $shared === null || $shared === '') {
        return true; // Default on — historical data hidden out of the box.
    }
    return (bool) (int) $shared;
}

/**
 * SQL fragment excluding attempts by students with no active enrolment (v2.1.0).
 *
 * quiz_aigrader uses get_enrolled_sql($coursecontext, '', 0, true) for this, but that
 * helper resolves a single course context and these queries aggregate across every
 * gradable course in one statement. The EXISTS below applies the same four conditions
 * correlated on c.id:
 *   - ue.status = ENROL_USER_ACTIVE (0)          — not a suspended enrolment
 *   - e.status  = ENROL_INSTANCE_ENABLED (0)     — enrolment method not disabled
 *   - timestart / timeend inside the current window, 0 meaning unbounded
 *   - the EXISTS itself                          — excludes fully unenrolled users
 * u.deleted = 0 is included because none of these queries joins {user}, so deleted
 * accounts were padding the totals regardless of enrolment state.
 *
 * @param array $params Query parameters, appended to by reference.
 * @return string SQL to append to the WHERE clause (empty when the filter is off).
 */
function aigrader_dashboard_active_enrolment_sql(array &$params): string {
    if (!aigrader_dashboard_hide_inactive_enabled()) {
        return '';
    }

    $now = time();
    $params['agd_now_start'] = $now;
    $params['agd_now_end'] = $now;

    return "
              AND EXISTS (
                  SELECT 1
                    FROM {user_enrolments} ue
                    JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0
                    JOIN {user} u ON u.id = ue.userid AND u.deleted = 0
                   WHERE ue.userid = qza.userid
                     AND e.courseid = c.id
                     AND ue.status = 0
                     AND (ue.timestart = 0 OR ue.timestart <= :agd_now_start)
                     AND (ue.timeend = 0 OR ue.timeend >= :agd_now_end)
              )";
}

/**
 * SQL fragment excluding essays with no gradable content (v2.1.0).
 *
 * quiz_aigrader skips these in PHP via answer_is_blank(): it takes the most recent step
 * carrying answer data and treats editor scaffolding as empty. That is why the dashboard
 * total used to exceed the number of cards actually rendered on the report page.
 *
 * strip_tags() has no SQL equivalent, so the empty-editor forms Moodle's editors emit are
 * matched literally. The inner MAX() mirrors the report's "latest answer step" semantics,
 * so an answer typed and then deleted before submission is excluded by both plugins.
 *
 * @param moodle_database $db
 * @return string SQL to append to the WHERE clause.
 */
function aigrader_dashboard_nonblank_answer_sql($db): string {
    $valuecmp = $db->sql_compare_text('qasd.value', 255);

    // Empty output produced by Atto / TinyMCE / plain text areas.
    $blank = [
        "''", "'<p></p>'", "'<p><br></p>'", "'<p><br /></p>'", "'<p><br/></p>'",
        "'<br>'", "'<br/>'", "'<br />'", "'&nbsp;'", "'<p>&nbsp;</p>'",
        "'<div></div>'", "'<div><br></div>'",
    ];
    $blanklist = implode(', ', $blank);

    return "
              AND EXISTS (
                  SELECT 1
                    FROM {question_attempt_steps} qas_a
                    JOIN {question_attempt_step_data} qasd
                      ON qasd.attemptstepid = qas_a.id AND qasd.name = 'answer'
                   WHERE qas_a.questionattemptid = qa.id
                     AND qasd.value IS NOT NULL
                     AND {$valuecmp} NOT IN ({$blanklist})
                     AND qas_a.sequencenumber = (
                         SELECT MAX(qas_b.sequencenumber)
                           FROM {question_attempt_steps} qas_b
                           JOIN {question_attempt_step_data} qasd_b
                             ON qasd_b.attemptstepid = qas_b.id AND qasd_b.name = 'answer'
                          WHERE qas_b.questionattemptid = qa.id
                     )
              )";
}

/**
 * Get ungraded essay data.
 *
 * v2.1.0: this is now the ONLY copy of this query. The block class and the notification
 * task previously carried their own near-identical versions, which had already drifted
 * apart (the task never received the RC3 rewrite and silently dropped rows through a
 * non-unique first column). Both now delegate here.
 *
 * @param int[]|null $gradablecourseids Course IDs to report on, or null for every course
 *                                        (used by the scheduled notification task).
 * @return array{courses: array, total: int, overdue: int}
 */
function aigrader_dashboard_get_ungraded_data(?array $gradablecourseids = null): array {
    global $DB;

    $courses = [];
    $total = 0;
    $overduethreshold = get_config('block_aigrader_dashboard', 'overdue_threshold') ?: 24;
    $overduetime = time() - ($overduethreshold * 3600);

    $params = [];
    $coursewhere = '';
    if ($gradablecourseids !== null) {
        if (empty($gradablecourseids)) {
            return ['courses' => [], 'total' => 0, 'overdue' => 0];
        }
        [$insql, $params] = $DB->get_in_or_equal($gradablecourseids, SQL_PARAMS_NAMED);
        $coursewhere = "AND c.id {$insql}";
    }

    // Inactive students excluded, and blank essays excluded to match the grading
    // queue (v2.1.0). Both fragments append their own named parameters.
    $enrolwhere = aigrader_dashboard_active_enrolment_sql($params);
    $answerwhere = aigrader_dashboard_nonblank_answer_sql($DB);

    // Attempt states aligned with quiz_aigrader's render_essay_table() (v2.1.0), which
    // accepts the wider list. Restricting to 'finished' here was a second source of
    // disagreement between the dashboard total and the report page.
    $attemptstates = "'finished','complete','gradedright','gradedwrong','gradedpartial'";

    // RC3: LEFT JOIN anti-pattern replaces the correlated subquery that ran
    // SELECT MAX(sequencenumber) once per question_attempt row.
    // IMPORTANT: First column must be unique for get_records_sql() — CONCAT of course+quiz IDs.
    $sql = "SELECT
                CONCAT(c.id, '_', q.id) as uniquekey,
                c.id as courseid, c.fullname as coursename, c.shortname as courseshort,
                q.id as quizid, q.name as quizname,
                cm.id as cmid,
                COUNT(DISTINCT qa.id) as ungraded_count,
                MIN(qa.timemodified) as oldest_ungraded
            FROM {course} c
            JOIN {quiz} q ON q.course = c.id
            JOIN {course_modules} cm ON cm.instance = q.id
                AND cm.module = (SELECT id FROM {modules} WHERE name = 'quiz')
            JOIN {quiz_attempts} qza ON qza.quiz = q.id AND qza.state IN ({$attemptstates})
            JOIN {question_usages} qu ON qu.id = qza.uniqueid
            JOIN {question_attempts} qa ON qa.questionusageid = qu.id
            JOIN {question} qn ON qn.id = qa.questionid
            JOIN {question_attempt_steps} qas ON qas.questionattemptid = qa.id
            LEFT JOIN {question_attempt_steps} qas_later
                ON qas_later.questionattemptid = qa.id
               AND qas_later.sequencenumber > qas.sequencenumber
            WHERE qn.qtype = 'essay'
              AND qas.state = 'needsgrading'
              AND qas_later.id IS NULL
              {$coursewhere}
              {$enrolwhere}
              {$answerwhere}
            GROUP BY c.id, c.fullname, c.shortname, q.id, q.name, cm.id
            ORDER BY c.fullname, q.name";

    $records = $DB->get_records_sql($sql, $params);

    $totaloverdue = 0;

    foreach ($records as $rec) {
        if (!isset($courses[$rec->courseid])) {
            $courses[$rec->courseid] = [
                'id'            => $rec->courseid,
                'name'          => $rec->coursename,
                'shortname'     => $rec->courseshort,
                'quizzes'       => [],
                'total_ungraded' => 0,
            ];
        }

        $isoverdue = $rec->oldest_ungraded && $rec->oldest_ungraded < $overduetime;

        $courses[$rec->courseid]['quizzes'][] = [
            'id'             => $rec->quizid,
            'name'           => $rec->quizname,
            'cmid'           => $rec->cmid,
            'ungraded'       => (int) $rec->ungraded_count,
            'oldest_ungraded' => $rec->oldest_ungraded,
            'is_overdue'     => $isoverdue,
            'link'           => (new moodle_url('/mod/quiz/report.php', [
                'id'   => $rec->cmid,
                'mode' => 'aigrader',
            ]))->out(false),
        ];

        $courses[$rec->courseid]['total_ungraded'] += (int) $rec->ungraded_count;
        $total += (int) $rec->ungraded_count;

        if ($isoverdue) {
            $totaloverdue += (int) $rec->ungraded_count;
        }
    }

    // Sort courses by ungraded count (highest first).
    usort($courses, function ($a, $b) {
        return $b['total_ungraded'] - $a['total_ungraded'];
    });

    return [
        'courses' => array_values($courses),
        'total'   => $total,
        'overdue' => $totaloverdue,
    ];
}

/**
 * Convenience wrapper — fetch all dashboard data for the current user.
 *
 * @return array{courses: array, total: int, overdue: int}
 */
function aigrader_dashboard_fetch_all_data(): array {
    $ids = aigrader_dashboard_get_gradable_course_ids();
    if (empty($ids)) {
        return ['courses' => [], 'total' => 0, 'overdue' => 0];
    }
    return aigrader_dashboard_get_ungraded_data($ids);
}
