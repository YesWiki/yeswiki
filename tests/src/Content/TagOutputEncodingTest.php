<?php

namespace YesWiki\Test\Content;

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Field\BazarField;
use YesWiki\Content\Service\FieldFactory;
use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiRuntime;
use YesWiki\Render\Service\ActionRunner;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Escaping of tags and tag action parameters. */
class TagOutputEncodingTest extends YesWikiTestCase
{
    private const PAGE_TAG = 'TagOutputEncodingPage';
    private const TAG_VALUE = '<img src=x onerror=alert(1)>';

    private YesWikiRuntime $wiki;

    protected function setUp(): void
    {
        $this->wiki = $this->getWiki();
        $GLOBALS['wiki'] = $this->wiki;
        $GLOBALS['yeswikiServices'] = $this->wiki->services;
        $this->wiki->services->get(PageManager::class)->save(
            self::PAGE_TAG,
            [PageBody::CONTENT => 'tagged content', PageBody::KEYWORDS => [self::TAG_VALUE]],
            '',
            true
        );
    }

    protected function tearDown(): void
    {
        $this->wiki->services->get(PageManager::class)->deleteOrphaned(self::PAGE_TAG);
    }

    /** @param array<string, string> $arguments */
    private function action(string $name, array $arguments): string
    {
        return (string)$this->wiki->services->get(ActionRunner::class)->action($name, $arguments);
    }

    public function testAFilterTagIsPrintedAsText(): void
    {
        $html = $this->action('filtertags', ['filter1' => self::TAG_VALUE]);

        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;</button>', $html);
    }

    public function testAFilterTitleIsPrintedAsText(): void
    {
        $html = $this->action('filtertags', ['filter1' => '<img src=x onerror=alert(1)> : other']);

        $this->assertStringNotContainsString('<img', $html);
    }

    public function testAFilterClassCannotLeaveItsAttribute(): void
    {
        $html = $this->action('filtertags', ['filter1' => 'other', 'class1' => '"><img src=x onerror=alert(1)>']);

        $this->assertStringNotContainsString('<img', $html);
    }

    public function testATagBadgeIsPrintedAsText(): void
    {
        $html = $this->action('filtertags', ['filter1' => self::TAG_VALUE, 'template' => 'pages_full.twig']);
        $html .= $this->action('includepages', ['pages' => self::PAGE_TAG, 'template' => 'pages_full.twig']);

        $this->assertStringContainsString('tagged content', $html, 'the tagged page is listed');
        $this->assertStringNotContainsString('<img', $html);
    }

    public function testAnIncludepagesClassCannotLeaveItsAttribute(): void
    {
        $html = $this->action('includepages', ['pages' => self::PAGE_TAG, 'class' => '"><img src=x onerror=alert(1)>']);

        $this->assertStringNotContainsString('<img', $html);
    }

    public function testATagsFieldPrintsItsTagsAsText(): void
    {
        $values = array_fill(0, 16, '');
        $values[0] = 'tags';
        $values[1] = 'bf_tags';
        $values[2] = 'Tags';
        $field = $this->wiki->services->get(FieldFactory::class)->create($values);
        $this->assertInstanceOf(BazarField::class, $field);

        $html = $field->renderStaticIfPermitted(['bf_tags' => 'plain,' . self::TAG_VALUE]);

        $this->assertStringContainsString('>plain</a>', $html);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;</a>', $html);
        $this->assertStringNotContainsString('<img', $html);
    }
}
