<?php

namespace YesWiki\Test\Render;

use YesWiki\Render\Service\MarkdownFormatterService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** A `- [ ]` item carries GitHub's classes, so the stylesheet can drop its bullet. */
class TaskListClassesTest extends YesWikiTestCase
{
    private function render(string $source): string
    {
        return $this->getWiki()->services->get(MarkdownFormatterService::class)->format($source);
    }

    public function testATaskItemAndItsListAreMarked(): void
    {
        $html = $this->render("- [ ] à faire\n- [X] fait\n");

        $this->assertSame(1, substr_count($html, '<ul class="contains-task-list">'));
        $this->assertSame(2, substr_count($html, '<li class="task-list-item">'));
    }

    public function testAPlainItemInTheSameListKeepsItsBullet(): void
    {
        $html = $this->render("- [ ] à faire\n- simple\n");

        $this->assertStringContainsString('<li>simple</li>', $html);
    }

    public function testALooseTaskListIsMarkedToo(): void
    {
        $html = $this->render("- [ ] un\n\n- [ ] deux\n");

        $this->assertSame(2, substr_count($html, '<li class="task-list-item">'));
    }

    public function testAListWithoutTasksIsLeftAlone(): void
    {
        $html = $this->render("- un\n- deux\n");

        $this->assertStringNotContainsString('task-list', $html);
    }
}
