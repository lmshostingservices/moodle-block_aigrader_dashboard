## [v2.2.0] - 2026-09-10

A performance release. The dashboard now renders from a short-lived cache and asks the
database a much cheaper question, so large sites get their marking queue back quickly.

### Performance

- **The dashboard is now cached.** The block appears on every page it has been added to
  and recalculated its figures for each of those page loads, for every user who could see
  it. Results are now held briefly and reused, which removes almost all of that repeated
  work on sites with long attempt histories.
- **New "Dashboard cache lifetime" setting.** Defaults to 120 seconds, and can be set to 0
  to recalculate on every page load. Short by design - the dashboard is a work queue, so
  markers see their own approvals reflected quickly.
- **The overdue calculation stays live.** Only the query result is cached; whether an essay
  has passed the overdue threshold is worked out fresh every time, so nothing is ever
  reported as on time when it is not.
- **Cheaper "latest answer" lookups.** Two subqueries that aggregated across the question
  attempt tables now stop at the first row that answers the question. Same results, less
  work for the database.
- **Notification emails keep reading live data.** The scheduled task deliberately bypasses
  the cache, so an email never reports a queue that has already been cleared.

### Changed

- Course and quiz identifiers are now combined with Moodle's own SQL helper, so the
  dashboard query is valid on every database Moodle supports rather than MySQL and
  MariaDB alone.
- Added `db/caches.php` with a described cache definition, so the new cache appears in
  *Site administration > Plugins > Caching* and can be purged like any other.

## [v2.1.4] - 2026-08-31

### Fixed
- Missing language string `grade_now`, which produced an "Invalid get_string() identifier" debugging notice on every page rendering the dashboard block and on the View-all page. The key was referenced in `block_aigrader_dashboard.php` and `viewall.php` but never defined in the English language pack. Language-pack-only change; no logic or schema changes.

## [v2.1.3] - 2026-08-30

### Changed
- RELEASE RECOVERY: Resolved Moodle Plugin CI code-style, PHPDoc, and CSS lint failures found in the immutable v2.1.2 release candidate. These are standards-only changes with no intended functional effect; the historical v2.1.0, v2.1.1, and v2.1.2 tags remain unchanged.

## [v2.1.2] - 2026-08-30

### Changed
- RELEASE RECOVERY: Republished the reviewed authoritative source under a new immutable tag with mandatory CI evidence. The v2.1.1 tag remains immutable but could not satisfy the release gate because it was created without the managed CI workflow. The immutable v2.1.2 candidate was correctly blocked after CI identified code-style failures.

## [v2.1.1] - 2026-08-29

### Changed
- RELEASE RECOVERY: Republished the reviewed authoritative source under a new immutable tag because the historical tag contained a different source tree. No functional changes.

# Changelog - AI Grader Dashboard Block

All notable changes to this plugin will be documented in this file.

## [2.1.0] - 2026-08-03

### Added
- **Inactive student filter**: ungraded counts now only include students with an active
  enrolment in the course. Attempt rows survive suspension and unenrolment, so historical
  students previously inflated every figure the block reported. Implemented as an `EXISTS`
  against `{user_enrolments}` / `{enrol}` correlated on `c.id`, applying the same four
  conditions `get_enrolled_sql(..., $onlyactive = true)` uses in quiz_aigrader: active
  enrolment status, enabled enrolment method, current `timestart`/`timeend` window, and
  the existence of an enrolment row at all. `u.deleted = 0` is now also enforced — these
  queries never joined `{user}`, so deleted accounts were being counted regardless.
- **New setting** `block_aigrader_dashboard/hide_inactive_students`, defaulting to
  `inherit`, which defers to `quiz_aigrader/hide_inactive_students`. Explicit Yes/No values
  exist for sites running the block without the report plugin.

### Fixed
- **Dashboard total no longer disagrees with the grading queue.** Two causes, both resolved:
  the block counted essays with no content while the report page skipped them, and the block
  accepted only `quiz_attempts.state = 'finished'` while the report accepted a wider list.
  Blank answers are now excluded in SQL using the same "latest answer step" semantics as
  `quiz_aigrader::answer_is_blank()`, and the state list matches.
- **Notification email was silently under-reporting.** The task's query selected `courseid`
  as its first column and passed it to `get_records_sql()`, which keys results by that
  column — so in any course with more than one quiz, every quiz after the first was
  discarded before the email was built. Fixed by the consolidation below.

### Changed
- **Query consolidated to one copy.** The ungraded-essay query existed three times: in
  `block_aigrader_dashboard.php`, in `locallib.php`, and in `classes/task/send_notifications.php`.
  The copies had already drifted — the task never received the 1.9.8 RC3 rewrite and was
  still running a correlated `MAX(sequencenumber)` subquery per attempt row. `locallib.php`
  is now the single implementation; the block class and the task delegate to it.
  `aigrader_dashboard_get_ungraded_data()` accepts `null` to mean "every course", preserving
  the task's site-wide scope.
- `get_gradable_course_ids()` in the block class likewise delegates to the locallib function
  rather than duplicating it.

### Notes
- No database schema changes.
- Requires quiz_aigrader 3.9.7 for the shared setting; works standalone otherwise, filter on.

## [1.9.8] - 2026-03-11

### Performance
- `enrol_get_users_courses()` was called twice per page load (once in the view check and once in the data loader). The result is now computed once and passed through, eliminating a redundant enrollment DB query on every block render.
- Correlated subquery `SELECT MAX(sequencenumber) WHERE questionattemptid = qa.id` on `question_attempt_steps` replaced with a LEFT JOIN anti-pattern.
- Post-SQL per-row capability check loop removed entirely. Gradable course IDs are now pre-filtered before the main SQL runs, so only relevant courses enter the query.
- Site admin cap added: at most 500 most recently modified courses are queried for large installations, preventing runaway IN() queries.

## [1.9.7] - 2026-01-17

### Changed
- Version sync and stability improvements.

## [1.9.5] - 2025-12-22

### Changed
- Added official Moodle 5.x compatibility declaration (`$plugin->supported = [400, 500]`)



## [1.9.4] - 2025-12-20

### Changed
- Migrated to centralized download architecture
- Updated versioned ZIP filename

## [1.9.0] - 2025-12-18

### Added
- Quick links to all AI Grader plugins
- Credit balance display
- Plugin status indicators

### Changed
- Aligned design with lms-labs.com (Inter font, HSL colors)

## [1.0.0] - 2025-01-01

### Added
- Initial release
- Dashboard block for AI Grader plugin navigation
- Moodle 4.0+ compatibility
