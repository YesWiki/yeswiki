<?php

namespace YesWiki\Test\Content;

use PHPUnit\Framework\Attributes\CoversClass;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\PageManager;
use YesWiki\Content\Service\PageTemplates;
use YesWiki\Files\Service\Storage;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\GroupManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** End-users 22: a page an admin marks as a template is offered to whoever creates a page, with its text, fields, look and files, never its rights or keywords. */
#[CoversClass(PageTemplates::class)]
class PageTemplatesTest extends YesWikiTestCase
{
    private const TEMPLATE = 'ModelePageTemplatesTest';
    private const HIDDEN = 'ModeleCachePageTemplatesTest';
    private const MADE = 'PageFaitePageTemplatesTest';
    private const FILE = 'files/' . self::TEMPLATE . '_plan_20260101000000_20260101000000.png';

    private string $admin = '';

    protected function setUp(): void
    {
        parent::setUp();
        $admins = self::getWiki()->services->get(GroupManager::class)->getMembers('admins');
        if ($admins === []) {
            $this->markTestSkipped('no admin account in the test wiki');
        }
        $this->admin = (string)$admins[0];
        self::actAs($this->admin);
        $pages = self::getWiki()->services->get(PageManager::class);
        $pages->save(self::TEMPLATE, [PageBody::CONTENT => "======Compte rendu======\n\n{{attach file=\"plan.png\" desc=\"plan\"}}\n\nPrésents :", PageBody::KEYWORDS => ['reunion']], '', true);
        $pages->setMetadata(self::TEMPLATE, ['theme' => 'margot', 'style' => 'margot.css', 'squelette' => '1col.twig', 'acls' => ['write' => '@admins']]);
        $pages->save(self::HIDDEN, [PageBody::CONTENT => 'Réservé'], '', true);
        self::getWiki()->services->get(AclService::class)->save(self::HIDDEN, 'read', '@admins');
        self::getWiki()->services->get(Storage::class)->write(self::FILE, 'png');
    }

    protected function tearDown(): void
    {
        $services = self::getWiki()->services;
        self::actAs($this->admin);
        foreach ([self::TEMPLATE, self::HIDDEN, self::MADE] as $tag) {
            $services->get(PageTemplates::class)->mark($tag, false);
            $services->get(PageManager::class)->deleteOrphaned($tag);
            $services->get(AclService::class)->delete($tag);
        }
        $storage = $services->get(Storage::class);
        foreach ([self::FILE, str_replace(self::TEMPLATE, self::MADE, self::FILE)] as $file) {
            if ($storage->exists($file)) {
                $storage->delete($file);
            }
        }
        self::actAs(null);
        parent::tearDown();
    }

    public function testOnlyAnAdminMarksAndOnlyAReadableTemplateIsOffered(): void
    {
        $templates = self::getWiki()->services->get(PageTemplates::class);

        self::actAs(null);
        $this->assertFalse($templates->mark(self::TEMPLATE, true), 'an anonymous visitor marks nothing');
        self::actAs($this->admin);
        $this->assertTrue($templates->mark(self::TEMPLATE, true));
        $this->assertTrue($templates->mark(self::HIDDEN, true));

        $offered = array_column($templates->available(), 'title', 'tag');
        $this->assertSame('Compte rendu', $offered[self::TEMPLATE] ?? null);
        $this->assertArrayHasKey(self::HIDDEN, $offered);

        self::actAs(null);
        $offered = array_column($templates->available(), 'tag');
        $this->assertContains(self::TEMPLATE, $offered);
        $this->assertNotContains(self::HIDDEN, $offered, 'a template the user cannot read is not offered');
        $this->assertNull($templates->bodyOf(self::HIDDEN));
        $this->assertSame([], $templates->lookOf(self::HIDDEN));
    }

    public function testANewPageGetsTheTextLookAndFilesButNotTheKeywordsOrRights(): void
    {
        $services = self::getWiki()->services;
        $templates = $services->get(PageTemplates::class);
        $templates->mark(self::TEMPLATE, true);

        $body = (array)$templates->bodyOf(self::TEMPLATE);
        $this->assertStringContainsString('Présents :', PageBody::content($body));
        $this->assertArrayNotHasKey(PageBody::KEYWORDS, $body);

        $look = $templates->lookOf(self::TEMPLATE);
        $this->assertSame(['theme' => 'margot', 'style' => 'margot.css', 'squelette' => '1col.twig'], $look, 'never the rights');

        $services->get(PageManager::class)->save(self::MADE, $body, '', true);
        $templates->copyFiles(self::TEMPLATE, self::MADE);
        $this->assertTrue($services->get(Storage::class)->exists(str_replace(self::TEMPLATE, self::MADE, self::FILE)));
        $this->assertFalse($templates->isTemplate(self::MADE));
    }

    public function testUnmarkingLeavesThePagesMadeFromIt(): void
    {
        $services = self::getWiki()->services;
        $templates = $services->get(PageTemplates::class);
        $templates->mark(self::TEMPLATE, true);
        $services->get(PageManager::class)->save(self::MADE, (array)$templates->bodyOf(self::TEMPLATE), '', true);

        $this->assertTrue($templates->mark(self::TEMPLATE, false));

        $this->assertNotContains(self::TEMPLATE, array_column($templates->available(), 'tag'));
        $this->assertNotNull($services->get(PageManager::class)->getOne(self::MADE, null, false, true));
    }
}
