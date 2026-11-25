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

namespace block_aigrader_dashboard\output;

#[\PHPUnit\Framework\Attributes\CoversClass(dashboard::class)]
/**
 * Tests for the dashboard view.
 *
 * @package    block_aigrader_dashboard
 * @category   test
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_aigrader_dashboard\output\dashboard
 */
final class dashboard_test extends \advanced_testcase {
    /**
     * Data for one course with an overdue quiz and an on-time quiz.
     *
     * @return array
     */
    private function data(): array {
        return [
            'courses' => [[
                'id' => 7,
                'name' => 'Work health & safety',
                'quizzes' => [
                    ['name' => 'Task <2>', 'ungraded' => 5, 'is_overdue' => true, 'oldest_ungraded' => time() - 2 * DAYSECS,
                        'link' => 'https://example.com/mod/quiz/report.php?id=3&mode=aigrader'],
                    ['name' => 'Task 3', 'ungraded' => 1, 'is_overdue' => false, 'oldest_ungraded' => time() - 60,
                        'link' => 'https://example.com/mod/quiz/report.php?id=4&mode=aigrader'],
                ],
                'total_ungraded' => 6,
            ]],
            'total' => 6,
            'overdue' => 5,
        ];
    }

    /**
     * The figures, one row per quiz with the overdue one first, and the links, all escaped.
     */
    public function test_render(): void {
        $this->resetAfterTest();
        $html = dashboard::render($this->data());
        $this->assertStringContainsString('agd-tile-value">6<', $html);
        $this->assertStringContainsString('agd-tile-value">5<', $html);
        $this->assertSame(2, substr_count($html, 'agd-grade'));
        $this->assertLessThan(strpos($html, 'Task 3'), strpos($html, 'Task &lt;2&gt;'));
        $this->assertStringNotContainsString('Task <2>', $html);
        $this->assertStringContainsString('id=3&amp;mode=aigrader', $html);
        $this->assertStringContainsString('Work health &amp; safety', $html);
        $this->assertStringContainsString('/blocks/aigrader_dashboard/viewall.php', $html);
        $this->assertStringNotContainsString('<svg', $html);
        $this->assertStringNotContainsString('style=', $html);
    }

    /**
     * The full page lists everything and does not link to itself.
     */
    public function test_render_fullpage(): void {
        $this->resetAfterTest();
        $html = dashboard::render($this->data(), true);
        $this->assertStringNotContainsString('/blocks/aigrader_dashboard/viewall.php', $html);
    }

    /**
     * Graders are told when the site has paused AI suggestions.
     */
    public function test_paused_notice(): void {
        $this->resetAfterTest();
        $this->assertStringNotContainsString('agd-alert', dashboard::render($this->data()));
        set_config('ai_paused', 1, 'quiz_aigrader');
        $this->assertStringContainsString('AI suggestions are paused', dashboard::render($this->data()));
    }

    /**
     * Nothing waiting: a plain all-graded message.
     */
    public function test_render_empty(): void {
        $this->resetAfterTest();
        $html = dashboard::render(['courses' => [], 'total' => 0, 'overdue' => 0]);
        $this->assertStringContainsString(get_string('all_graded', 'block_aigrader_dashboard'), $html);
        $this->assertStringNotContainsString('<table', $html);
    }
}
