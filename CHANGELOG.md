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
