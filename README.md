# AI Essay Grader dashboard (block_aigrader_dashboard)

A Moodle™ block that shows graders, at a glance, how many essays are waiting for marking in
the AI Essay Grader, which quizzes they are in, and which are overdue, with a **Grade now**
link that opens that quiz's marking queue.

- Four headline figures: essays to grade, overdue, courses and quizzes.
- One row per quiz, overdue first, then whatever has waited longest. Every status is in words
  as well as colour ("Overdue · waiting 2 days").
- **All courses** opens a full page listing every course the grader can mark.
- Email notifications about new and overdue essays, hourly, daily or weekly.
- Built with Moodle Design System components and tokens, follows the theme's dark mode, and
  meets WCAG 2.2 AA.

## Requirements

| | |
|---|---|
| Moodle™ | 4.4 to 5.3 |
| PHP | 8.1 or later |
| Recommended | AI Essay Grader quiz report (quiz_aigrader). Without it the block still counts essays waiting for manual grading. |

## Installation

1. Download the ZIP from Moodle Marketplace.
2. Site administration > Plugins > Install plugins, upload the ZIP, and follow the prompts.
3. Add the block to the Dashboard (Edit mode > Add a block > AI Essay Grader dashboard), or to a
   course page.

## Settings

Site administration > Plugins > Blocks > AI Essay Grader dashboard.

| Setting | Purpose |
|---|---|
| Notification email, CC email | Who receives the summary emails. |
| Notify about new essays, overdue essays | Which emails are sent. |
| Notification frequency | Hourly, daily or weekly digest, or as essays arrive. |
| Overdue after (hours) | When a waiting essay counts as overdue (default 24). |
| Hide submissions from inactive students | Follows the AI Essay Grader setting by default, so the figures match the queue. |
| Dashboard cache lifetime | How long the figures are reused (default 900 seconds). |

## Who sees what

Each grader sees only the courses where they can grade quizzes. Site administrators also get
links to the AI Essay Grader activity report and, when installed, the proctoring review.

## Responsible AI and the EU AI Act

The AI Essay Grader suggests marks for essays, which the EU AI Act treats as a high-risk use
(Annex III, point 3(b): evaluating learning outcomes). This block is part of how graders work
with that system, so it is built to support the duties that apply to the people who use it:

- **Human oversight (Article 14).** The block shows no AI output and never changes a mark. A
  short note under the table reminds graders that AI suggestions are drafts, and that no mark
  reaches the gradebook until a grader checks and approves it.
- **Stopping the system (Article 26(5)).** When the site has paused AI suggestions in the AI
  Essay Grader, the block says so, so graders are not sent to a queue whose AI grade buttons
  are hidden.
- **Timely review.** Overdue essays come first and show how long they have waited, so a grader
  has time to review each suggestion properly rather than approve in a rush.
- **No automated decisions about learners.** The block counts essays waiting for a person to
  mark them; it does not grade, rank or profile learners.

The AI Essay Grader's own documentation covers transparency to learners, accuracy, logging and
the provider's obligations.

## Privacy

The block stores no personal data. Notification emails go to the addresses an administrator
sets and name courses and quizzes only.

## Support

- Documentation: https://lms-labs.com/docs/dashboard-block
- Issues: https://github.com/lmshostingservices/moodle-block_aigrader_dashboard/issues
- Email: support@lmshostingservices.com

## Licence

GNU GPL v3 or later. Moodle™ is a registered trademark of Moodle Pty Ltd.

## Performance on large sites

The block recalculates its figures from the question attempt tables, which grow with every
quiz attempt on the site. Because a block renders on every page it has been added to, that
work is repeated for each page load and each user who can see it.

Since v2.3.0 the calculation runs on a scheduled task — **Refresh AI Essay Grader dashboard
figures**, every five minutes — and page loads only ever read the stored result. The
**Dashboard cache lifetime** setting (default 900 seconds; 0 disables caching) controls how
long a stored result stays usable; keep it longer than the task interval. If a refresh is
due, one process performs it while everyone else continues on the previous figures, so no
page ever queues behind the query. Whether an essay is overdue is always recalculated, so
caching never delays overdue reporting. Notification emails ignore the cache and always
read live data.

On sites with very large attempt histories, two database indexes that Moodle core does not
define will speed these queries up further. They are applied by a site administrator or
DBA, not by the plugin - a plugin distributed through the Moodle Marketplace must not
alter core tables. See the AI Essay Grader (`quiz_aigrader`) README for the statements.
