# block_aigrader_dashboard

## Summary

Centralized monitoring of ungraded essays with email notifications.

## Description

Centralized monitoring of ungraded essays with email notifications. Add to Dashboard or any page.

## Current Release

Version 2.2.1 is a maintenance update to the 2.2.0 performance release. The dashboard now renders from a short-lived
cache and asks the database a cheaper question, so the marking queue loads quickly on
sites with long attempt histories. See the Performance section below and CHANGELOG.md.

## Licence

GNU GPL v3 or later.

## Performance on large sites

The block recalculates its figures from the question attempt tables, which grow with every
quiz attempt on the site. Because a block renders on every page it has been added to, that
work is repeated for each page load and each user who can see it.

Since v2.2.0 the result is cached for a short period, controlled by **Dashboard cache
lifetime** in the block's settings (default 120 seconds; 0 disables caching). Whether an
essay is overdue is always recalculated, so a short cache does not delay overdue reporting.
Notification emails ignore the cache and always read live data.

On sites with very large attempt histories, two database indexes that Moodle core does not
define will speed these queries up further. They are applied by a site administrator or
DBA, not by the plugin - a plugin distributed through the Moodle plugins directory must not
alter core tables. See the AI Essay Grader (`quiz_aigrader`) README for the statements.
