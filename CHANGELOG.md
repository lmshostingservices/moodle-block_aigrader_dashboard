# Changelog - AI Grader Dashboard Block

All notable changes to this plugin will be documented in this file.

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
