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

defined('MOODLE_INTERNAL') || die();

/**
 * Seconds a cached ungraded-essay result is reused for when the site has not set its own
 * value. Comfortably longer than the five-minute refresh task, so the figures a page sees
 * are kept current by cron rather than by making somebody wait for the query.
 */
if (!defined('BLOCK_AIGRADER_DASHBOARD_DEFAULT_CACHE_TTL')) {
    define('BLOCK_AIGRADER_DASHBOARD_DEFAULT_CACHE_TTL', 900);
}

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
 * matched literally. The inner NOT EXISTS mirrors the report's "latest answer step"
 * semantics, so an answer typed and then deleted before submission is excluded by both
 * plugins.
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

    // "The latest answer-bearing step is not blank", expressed as a pair of EXISTS
    // clauses. This was a MAX(sequencenumber) subquery correlated on qa.id, which the
    // database had to evaluate once per candidate row over the same two large tables.
    // NOT EXISTS asks the same question - is there a later step carrying answer data -
    // and can stop at the first row it finds instead of aggregating over all of them.
    // The result set is identical.
    return "
              AND EXISTS (
                  SELECT 1
                    FROM {question_attempt_steps} qas_a
                    JOIN {question_attempt_step_data} qasd
                      ON qasd.attemptstepid = qas_a.id AND qasd.name = 'answer'
                   WHERE qas_a.questionattemptid = qa.id
                     AND qasd.value IS NOT NULL
                     AND {$valuecmp} NOT IN ({$blanklist})
                     AND NOT EXISTS (
                         SELECT 1
                           FROM {question_attempt_steps} qas_b
                           JOIN {question_attempt_step_data} qasd_b
                             ON qasd_b.attemptstepid = qas_b.id AND qasd_b.name = 'answer'
                          WHERE qas_b.questionattemptid = qa.id
                            AND qas_b.sequencenumber > qas_a.sequencenumber
                     )
              )";
}

/**
 * Collect the question attempts whose latest step is awaiting manual grading.
 *
 * v2.3.0: this is phase one of a two-phase lookup, and it exists because of how the old
 * single statement performed. That statement joined course, quiz, quiz attempts, question
 * usages, question attempts, questions and attempt steps, and only then narrowed to essay
 * questions and to steps needing grading. On a site with 1.2 million question attempts the
 * database examined over sixteen million rows to return six, because the two filters that
 * actually matter were applied last.
 *
 * Both of those filters are cheap and indexed. Core indexes question.qtype, and
 * question_attempts has a foreign key index on questionid, so essay questions and their
 * attempts are reached directly. Starting here, rather than hoping the optimiser chooses
 * this order on its own, keeps the plan stable as the site grows and behaves the same on
 * every database Moodle supports.
 *
 * The NOT EXISTS reproduces the original "latest step" rule exactly: an attempt qualifies
 * only when the step needing grading is the last step recorded against it.
 *
 * @return int[] Question attempt ids, de-duplicated.
 */
function aigrader_dashboard_candidate_attempt_ids(): array {
    global $DB;

    $sql = "SELECT qa.id
              FROM {question} qn
              JOIN {question_attempts} qa ON qa.questionid = qn.id
              JOIN {question_attempt_steps} qas
                ON qas.questionattemptid = qa.id
               AND qas.state = 'needsgrading'
             WHERE qn.qtype = 'essay'
               AND NOT EXISTS (
                       SELECT 1
                         FROM {question_attempt_steps} qas_later
                        WHERE qas_later.questionattemptid = qa.id
                          AND qas_later.sequencenumber > qas.sequencenumber
                   )";

    $ids = $DB->get_fieldset_sql($sql);

    return array_values(array_unique(array_map('intval', $ids)));
}

/**
 * Run the ungraded-essay aggregation and return the raw rows for the whole site.
 *
 * v2.1.0: this is the ONLY copy of this query. The block class and the notification task
 * previously carried their own near-identical versions, which had already drifted apart
 * (the task never received the RC3 rewrite and silently dropped rows through a non-unique
 * first column). Both now delegate here.
 *
 * v2.3.0: phase two of the lookup. It aggregates only the attempts phase one identified,
 * in batches, so the enrolment and blank-answer checks are applied to a small set instead
 * of to everything on the site. The course restriction has moved out of the statement and
 * into PHP: it only ever decided which grouped rows were returned, never what any count
 * contained, so filtering afterwards gives the same answer and lets one cached site-wide
 * result serve every user.
 *
 * @return stdClass[] One row per course/quiz pair, keyed by a course+quiz identifier.
 */
function aigrader_dashboard_query_ungraded_rows(): array {
    global $DB;

    $attemptids = aigrader_dashboard_candidate_attempt_ids();
    if (empty($attemptids)) {
        return [];
    }

    // Attempt states aligned with quiz_aigrader's render_essay_table() (v2.1.0), which
    // accepts the wider list. Restricting to 'finished' here was a second source of
    // disagreement between the dashboard total and the report page.
    $attemptstates = "'finished','complete','gradedright','gradedwrong','gradedpartial'";

    // IMPORTANT: First column must be unique for get_records_sql() — course+quiz IDs.
    // sql_concat() is used rather than a literal CONCAT() so the statement is valid on
    // every database Moodle supports, not only MySQL and MariaDB.
    $uniquekey = $DB->sql_concat('c.id', "'_'", 'q.id');

    $merged = [];

    // Batched so the IN list stays within what every supported database accepts. The
    // batches partition the attempt ids, so no attempt is counted twice and the per-batch
    // counts can simply be added together.
    foreach (array_chunk($attemptids, 5000) as $chunk) {
        $params = [];

        // Inactive students excluded, and blank essays excluded to match the grading
        // queue (v2.1.0). Both fragments append their own named parameters.
        $enrolwhere = aigrader_dashboard_active_enrolment_sql($params);
        $answerwhere = aigrader_dashboard_nonblank_answer_sql($DB);

        [$insql, $inparams] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED, 'agdqa');
        $params = array_merge($params, $inparams);

        $sql = "SELECT
                    {$uniquekey} as uniquekey,
                    c.id as courseid, c.fullname as coursename, c.shortname as courseshort,
                    q.id as quizid, q.name as quizname,
                    cm.id as cmid,
                    COUNT(DISTINCT qa.id) as ungraded_count,
                    MIN(qa.timemodified) as oldest_ungraded
                FROM {question_attempts} qa
                JOIN {question_usages} qu ON qu.id = qa.questionusageid
                JOIN {quiz_attempts} qza ON qza.uniqueid = qu.id
                    AND qza.state IN ({$attemptstates})
                JOIN {quiz} q ON q.id = qza.quiz
                JOIN {course} c ON c.id = q.course
                JOIN {course_modules} cm ON cm.instance = q.id
                    AND cm.module = (SELECT id FROM {modules} WHERE name = 'quiz')
                WHERE qa.id {$insql}
                  {$enrolwhere}
                  {$answerwhere}
                GROUP BY c.id, c.fullname, c.shortname, q.id, q.name, cm.id";

        foreach ($DB->get_records_sql($sql, $params) as $key => $row) {
            if (!isset($merged[$key])) {
                $merged[$key] = $row;
                continue;
            }
            $merged[$key]->ungraded_count += $row->ungraded_count;
            if ($row->oldest_ungraded !== null
                    && ($merged[$key]->oldest_ungraded === null
                        || $row->oldest_ungraded < $merged[$key]->oldest_ungraded)) {
                $merged[$key]->oldest_ungraded = $row->oldest_ungraded;
            }
        }
    }

    // The old statement ordered by course then quiz name; batching cannot preserve that,
    // so it is restored here.
    uasort($merged, function ($a, $b) {
        return [$a->coursename, $a->quizname] <=> [$b->coursename, $b->quizname];
    });

    return $merged;
}

/**
 * How long a cached ungraded-essay result stays usable, in seconds.
 *
 * Zero disables caching entirely. The scheduled task refreshes the cache well inside this
 * window, so under normal operation a page load never finds it expired.
 *
 * @return int
 */
function aigrader_dashboard_cache_ttl(): int {
    $ttl = get_config('block_aigrader_dashboard', 'cache_ttl');
    if ($ttl === false || $ttl === null || $ttl === '') {
        return BLOCK_AIGRADER_DASHBOARD_DEFAULT_CACHE_TTL;
    }
    return max(0, (int) $ttl);
}

/**
 * Cache key for the site-wide row set.
 *
 * Only the hide-inactive setting changes which rows the query produces. The course list no
 * longer appears here: one site-wide result is cached and filtered per user afterwards, so
 * every user shares the same entry and the scheduled task can warm it for all of them.
 *
 * @return string
 */
function aigrader_dashboard_cache_key(): string {
    return sha1(json_encode([1, aigrader_dashboard_hide_inactive_enabled()]));
}

/**
 * Fetch the site-wide rows, refreshing the cache when it is stale.
 *
 * v2.3.0: the point of this function is that a page load should never wait behind another
 * page load. Previously every request that arrived after the cache expired ran the full
 * query at the same time, each holding a database connection and a PHP process until it
 * finished — which is how one slow query became a slow site rather than a slow block.
 *
 * Now a single request refreshes at a time. Everybody else keeps using the previous
 * result, even once it is past its lifetime, because slightly old counts are far better
 * than a page that will not load. Only the very first request on a cold cache has nothing
 * to fall back on, and it is given an empty set rather than being made to wait.
 *
 * @param bool $blocking True to wait for and perform the refresh regardless of staleness,
 *                       used by the scheduled task.
 * @return stdClass[]
 */
function aigrader_dashboard_get_rows_cached(bool $blocking = false): array {
    $ttl = aigrader_dashboard_cache_ttl();
    if ($ttl <= 0 && !$blocking) {
        return aigrader_dashboard_query_ungraded_rows();
    }

    $cache = cache::make('block_aigrader_dashboard', 'ungradeddata');
    $key = aigrader_dashboard_cache_key();
    $cached = $cache->get($key);

    $hasrows = is_array($cached) && isset($cached['generated'], $cached['rows']);
    $fresh = $hasrows && (time() - (int) $cached['generated']) < $ttl;

    if ($fresh && !$blocking) {
        return $cached['rows'];
    }

    // One refresher at a time. A zero timeout means a request that cannot get the lock
    // carries on immediately with whatever it already has.
    $lockfactory = \core\lock\lock_config::get_lock_factory('block_aigrader_dashboard_ungraded');
    $lock = $lockfactory->get_lock('ungradeddata', $blocking ? 30 : 0);

    if (!$lock) {
        return $hasrows ? $cached['rows'] : [];
    }

    try {
        // Re-read inside the lock: another request may have refreshed while this one
        // waited, in which case there is nothing left to do.
        $cached = $cache->get($key);
        $hasrows = is_array($cached) && isset($cached['generated'], $cached['rows']);
        if (!$blocking && $hasrows && (time() - (int) $cached['generated']) < $ttl) {
            return $cached['rows'];
        }

        $rows = aigrader_dashboard_query_ungraded_rows();
        $cache->set($key, ['generated' => time(), 'rows' => $rows]);
        return $rows;
    } finally {
        $lock->release();
    }
}

/**
 * Recalculate the site-wide rows and store them, whatever the cache currently holds.
 *
 * Called by the scheduled task so the expensive query runs on cron rather than while
 * somebody is waiting for a page.
 *
 * @return int Number of course/quiz rows stored.
 */
function aigrader_dashboard_warm_cache(): int {
    return count(aigrader_dashboard_get_rows_cached(true));
}

/**
 * Keep only the rows belonging to the given courses.
 *
 * The course restriction used to live in the SQL. It only ever chose which grouped rows
 * came back — it never altered a count, because every count is already confined to its own
 * course — so applying it here produces the same answer from one shared result.
 *
 * @param stdClass[] $rows
 * @param int[]|null $gradablecourseids Null for every course.
 * @return stdClass[]
 */
function aigrader_dashboard_filter_rows_by_courses(array $rows, ?array $gradablecourseids): array {
    if ($gradablecourseids === null) {
        return $rows;
    }

    $allowed = array_flip(array_map('intval', $gradablecourseids));
    $filtered = [];
    foreach ($rows as $key => $row) {
        if (isset($allowed[(int) $row->courseid])) {
            $filtered[$key] = $row;
        }
    }
    return $filtered;
}

/**
 * Get ungraded essay data.
 *
 * v2.1.0: this is now the ONLY copy of this query. The block class and the notification
 * task previously carried their own near-identical versions, which had already drifted
 * apart (the task never received the RC3 rewrite and silently dropped rows through a
 * non-unique first column). Both now delegate here.
 *
 * v2.2.0: the query result is cached. v2.3.0: one site-wide result is cached for everyone,
 * refreshed on cron, and filtered to the caller's courses here. Overdue status is still
 * recalculated on every call, so an essay crosses the threshold on time regardless of how
 * old the cached counts are.
 *
 * @param int[]|null $gradablecourseids Course IDs to report on, or null for every course
 *                                        (used by the scheduled notification task).
 * @param bool $usecache False to bypass the cache and re-query.
 * @return array{courses: array, total: int, overdue: int}
 */
function aigrader_dashboard_get_ungraded_data(?array $gradablecourseids = null, bool $usecache = true): array {
    $courses = [];
    $total = 0;
    $overduethreshold = get_config('block_aigrader_dashboard', 'overdue_threshold') ?: 24;
    $overduetime = time() - ($overduethreshold * 3600);

    if ($gradablecourseids !== null && empty($gradablecourseids)) {
        return ['courses' => [], 'total' => 0, 'overdue' => 0];
    }

    $records = $usecache
        ? aigrader_dashboard_get_rows_cached()
        : aigrader_dashboard_query_ungraded_rows();

    $records = aigrader_dashboard_filter_rows_by_courses($records, $gradablecourseids);

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
