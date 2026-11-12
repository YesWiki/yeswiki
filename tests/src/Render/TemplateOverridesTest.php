<?php

namespace YesWiki\Test\Render;

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Entity\PageType;
use YesWiki\Content\Service\PageManager;
use YesWiki\Identity\Service\AclService;
use YesWiki\Render\Service\TemplateOverrides;
use YesWiki\Search\Service\SearchableTextExtractor;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Ticket 18: an override edited from the screens is a `template` Content, versioned, admins only, and named by its path. */
class TemplateOverridesTest extends YesWikiTestCase
{
    private const PATH = 'core/yw-test-overrides/probe.twig';

    private const TAG = 'core.yw-test-overrides.probe';

    private function overrides(): TemplateOverrides
    {
        return self::getWiki()->services->get(TemplateOverrides::class);
    }

    protected function tearDown(): void
    {
        $pages = self::getWiki()->services->get(PageManager::class);
        foreach ([self::TAG, 'core.yw-test-overrides.taken'] as $tag) {
            $pages->deleteOrphaned($tag);
        }
        $this->overrides()->startNewRequest();
        parent::tearDown();
    }

    public function testThePathNamesTheTag(): void
    {
        $this->assertSame(self::TAG, TemplateOverrides::tagFor(self::PATH));
        $this->assertSame('custom.footer', TemplateOverrides::tagFor('custom/footer.twig'));
    }

    /** @return iterable<string, array{string}> */
    public static function notOverrides(): iterable
    {
        yield 'outside the folder' => ['../yeswiki.config.php'];
        yield 'not twig' => ['core/style.css'];
        yield 'absolute' => ['/etc/passwd.twig'];
        yield 'a character no tag takes' => ['core/fiche 1.twig'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('notOverrides')]
    public function testOnlyATwigPathUnderTheFolderIsAnOverride(string $path): void
    {
        $this->expectException(\InvalidArgumentException::class);

        TemplateOverrides::tagFor($path);
    }

    public function testASavedOverrideIsAnAdminsOnlyTemplateContent(): void
    {
        $this->overrides()->save(self::PATH, '<p>{{ title }}</p>');

        $pages = self::getWiki()->services->get(PageManager::class);
        $this->assertSame(PageType::TEMPLATE, $pages->typeOf(self::TAG));
        $this->assertSame(['path' => self::PATH, 'twig' => '<p>{{ title }}</p>'], $pages->getOne(self::TAG, null, false, true)['body'] ?? null);
        $this->assertSame('<p>{{ title }}</p>', $this->overrides()->read(self::PATH));
        $acls = self::getWiki()->services->get(AclService::class);
        $this->assertSame('@admins', $acls->load(self::TAG, 'read', false)['list'] ?? null);
        $this->assertSame('@admins', $acls->load(self::TAG, 'write', false)['list'] ?? null);
    }

    public function testEachSaveIsARevisionTheRequestSeesOnce(): void
    {
        $this->overrides()->save(self::PATH, 'first');
        $first = $this->overrides()->revisionOf(self::PATH);
        $this->overrides()->save(self::PATH, 'second');

        $this->assertNotNull($first);
        $this->assertNotSame($first, $this->overrides()->revisionOf(self::PATH), 'a save moves the revision for the rest of the request too');
        $this->assertSame('second', $this->overrides()->read(self::PATH));
        $this->assertCount(2, self::getWiki()->services->get(PageManager::class)->getRevisions(self::TAG));
    }

    /** An import from the console runs as nobody, who cannot read an admins-only template: it must still add a revision, not start a new Content. */
    public function testSomeoneWhoCannotReadItStillAddsARevision(): void
    {
        $this->overrides()->save(self::PATH, 'by an admin');
        self::actAs(null);
        $this->overrides()->save(self::PATH, 'by the console');

        $this->assertSame('by the console', $this->overrides()->read(self::PATH));
        $this->assertCount(2, self::getWiki()->services->get(PageManager::class)->getRevisions(self::TAG));
        $this->assertSame('@admins', self::getWiki()->services->get(AclService::class)->load(self::TAG, 'read', false)['list'] ?? null);
    }

    public function testATemplateThatDoesNotCompileIsRefused(): void
    {
        $this->overrides()->save(self::PATH, 'good');

        try {
            $this->overrides()->save(self::PATH, "line one\n{% if %}");
            $this->fail('a template that does not compile was saved');
        } catch (\InvalidArgumentException $refused) {
            $this->assertStringContainsString('line 2', $refused->getMessage());
        }
        $this->assertSame('good', $this->overrides()->read(self::PATH), 'the current revision stays');
    }

    public function testATagAnotherContentHoldsIsRefused(): void
    {
        self::getWiki()->services->get(PageManager::class)->save('core.yw-test-overrides.taken', [PageBody::CONTENT => 'a page'], '', true);

        $this->expectException(\InvalidArgumentException::class);

        $this->overrides()->save('core/yw-test-overrides/taken.twig', 'mine');
    }

    public function testDeletingTakesEveryRevisionAway(): void
    {
        $this->overrides()->save(self::PATH, 'one');
        $this->overrides()->save(self::PATH, 'two');
        $this->overrides()->delete(self::PATH);

        $this->assertNull($this->overrides()->read(self::PATH));
        $this->assertNull($this->overrides()->revisionOf(self::PATH));
        $this->assertSame([], self::getWiki()->services->get(PageManager::class)->getRevisions(self::TAG));
    }

    public function testListingGivesEachOverrideItsPath(): void
    {
        $this->overrides()->save(self::PATH, 'listed');

        $listed = array_values(array_filter($this->overrides()->all(), fn (array $override): bool => $override['path'] === self::PATH));
        $this->assertCount(1, $listed);
        $this->assertSame(self::TAG, $listed[0]['tag']);
    }

    public function testATemplateIsNotSearchable(): void
    {
        $extracted = self::getWiki()->services->get(SearchableTextExtractor::class)->extractAll([
            'tag' => self::TAG, 'type' => PageType::TEMPLATE, 'body' => ['path' => self::PATH, 'twig' => 'secret words'], 'owner' => '', 'time' => '2026-11-11 00:00:00', 'metadata' => null, 'parent' => '',
        ]);

        $this->assertSame([], $extracted);
    }
}
