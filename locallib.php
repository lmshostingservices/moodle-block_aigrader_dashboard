<?php
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

defined('MOODLE_INTERNAL') || die();

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

    $user_courses = enrol_get_users_courses($USER->id, true);
    $gradable = [];
    foreach ($user_courses as $course) {
        $context = context_course::instance($course->id);
        if (has_capability('mod/quiz:grade', $context)) {
            $gradable[] = $course->id;
        }
    }
    return $gradable;
}

/**
 * Get ungraded essay data for the supplied list of course IDs.
 *
 * @param int[] $gradable_course_ids
 * @return array{courses: array, total: int, overdue: int}
 */
function aigrader_dashboard_get_ungraded_data(array $gradable_course_ids): array {
    global $DB;

    $courses = [];
    $total = 0;
    $overdue_threshold = get_config('block_aigrader_dashboard', 'overdue_threshold') ?: 24;
    $overdue_time = time() - ($overdue_threshold * 3600);

    list($in_sql, $params) = $DB->get_in_or_equal($gradable_course_ids, SQL_PARAMS_NAMED);

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
            JOIN {quiz_attempts} qza ON qza.quiz = q.id AND qza.state = 'finished'
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
              AND c.id {$in_sql}
            GROUP BY c.id, c.fullname, c.shortname, q.id, q.name, cm.id
            ORDER BY c.fullname, q.name";

    $records = $DB->get_records_sql($sql, $params);

    $total_overdue = 0;

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

        $is_overdue = $rec->oldest_ungraded && $rec->oldest_ungraded < $overdue_time;

        $courses[$rec->courseid]['quizzes'][] = [
            'id'             => $rec->quizid,
            'name'           => $rec->quizname,
            'cmid'           => $rec->cmid,
            'ungraded'       => (int) $rec->ungraded_count,
            'oldest_ungraded' => $rec->oldest_ungraded,
            'is_overdue'     => $is_overdue,
            'link'           => (new moodle_url('/mod/quiz/report.php', [
                'id'   => $rec->cmid,
                'mode' => 'aigrader',
            ]))->out(false),
        ];

        $courses[$rec->courseid]['total_ungraded'] += (int) $rec->ungraded_count;
        $total += (int) $rec->ungraded_count;

        if ($is_overdue) {
            $total_overdue += (int) $rec->ungraded_count;
        }
    }

    // Sort courses by ungraded count (highest first).
    usort($courses, function($a, $b) {
        return $b['total_ungraded'] - $a['total_ungraded'];
    });

    return [
        'courses' => array_values($courses),
        'total'   => $total,
        'overdue' => $total_overdue,
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
