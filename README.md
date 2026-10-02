# block_aigrader_dashboard

## Summary

Centralized monitoring of ungraded essays with email notifications.

## Description

Centralized monitoring of ungraded essays with email notifications. Add to Dashboard or any page.

## Current Release

Version 2.3.0 moves the ungraded-essay calculation onto a scheduled task, so pages
never wait for it, and rewrites the query to start from the responses actually awaiting
marking. The dashboard now renders from a short-lived
cache and asks the database a cheaper question, so the marking queue loads quickly on
sites with long attempt histories. See the Performance section below and CHANGELOG.md.

## Licence

GNU GPL v3 or later.

## Performance on large sites

The block recalculates its figures from the question attempt tables, which grow with every
quiz attempt on the site. Because a block renders on every page it has been added to, that
work is repeated for each page load and each user who can see it.

Since v2.3.0 the calculation runs on a scheduled task — **Refresh AI Grader Dashboard
figures**, every five minutes — and page loads only ever read the stored result. The
**Dashboard cache lifetime** setting (default 900 seconds; 0 disables caching) controls how
long a stored result stays usable; keep it longer than the task interval. If a refresh is
due, one process performs it while everyone else continues on the previous figures, so no
page ever queues behind the query. Whether an essay is overdue is always recalculated, so
caching never delays overdue reporting. Notification emails ignore the cache and always
read live data.

On sites with very large attempt histories, two database indexes that Moodle core does not
define will speed these queries up further. They are applied by a site administrator or
DBA, not by the plugin - a plugin distributed through the Moodle plugins directory must not
alter core tables. See the AI Essay Grader (`quiz_aigrader`) README for the statements.
