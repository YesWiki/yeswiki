<?php

namespace YesWiki\Test\Content;

use YesWiki\Content\Entity\MenuNode;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\MenuManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Render\Service\MenuRenderer;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';
require_once 'tests/ForcedParameterBag.php';

/** A menu is Content, and one renderer draws it (ticket 64 / ADR-0028). */
class MenuTest extends YesWikiTestCase
{
    private const MENU = 'MenuRendererTestMenu';
    private const READABLE = 'MenuRendererTestReadable';
    private const SECRET = 'MenuRendererTestSecret';
    private const MISSING = 'MenuRendererTestNeverWritten';

    protected function setUp(): void
    {
        parent::setUp();
        $wiki = $this->getWiki();
        $pageManager = $wiki->services->get(PageManager::class);

        $pageManager->save(self::READABLE, [PageBody::CONTENT => 'anybody may read this'], '', true);
        $pageManager->save(self::SECRET, [PageBody::CONTENT => 'not for everyone'], '', true);
        $wiki->services->get(AclService::class)->save(self::SECRET, 'read', '@admins');

        $wiki->services->get(MenuManager::class)->create('Rendering', [
            new MenuNode(id: 'n1', label: 'Readable', link: self::READABLE),
            new MenuNode(id: 'n2', label: 'Secret', link: self::SECRET),
            new MenuNode(id: 'n3', label: 'Missing', link: self::MISSING),
            new MenuNode(id: 'n4', label: 'Parent', link: '', children: [
                new MenuNode(id: 'n5', label: 'Child', link: self::READABLE),
            ]),
        ], self::MENU);

        unset($_SESSION['user']);
    }

    protected function tearDown(): void
    {
        $wiki = $this->getWiki();
        $wiki->services->get(MenuManager::class)->delete(self::MENU);
        foreach ([self::READABLE, self::SECRET] as $tag) {
            $wiki->services->get(PageManager::class)->deleteOrphaned($tag);
        }
        $wiki->services->get(AclService::class)->delete(self::SECRET);
        parent::tearDown();
    }

    /**
     * @param array<string, mixed> $options
     */
    private function render(string $placement = MenuRenderer::NAV, array $options = []): string
    {
        return $this->getWiki()->services->get(MenuRenderer::class)->render(self::MENU, $placement, $options);
    }

    /** A menu row is a row: versioned, typed, and read back as the tree it holds. */
    public function testAMenuIsContentOfItsOwnType(): void
    {
        $wiki = $this->getWiki();

        $this->assertTrue($wiki->services->get(MenuManager::class)->isMenu(self::MENU));
        $this->assertSame(
            \YesWiki\Content\Entity\PageType::MENU,
            $wiki->services->get(PageManager::class)->typeOf(self::MENU)
        );
        $this->assertArrayHasKey(self::MENU, $wiki->services->get(MenuManager::class)->readable());
    }

    /** Exactly two levels: a third one is not stored, so no renderer has to truncate anything. */
    public function testAThirdLevelIsNotAMenu(): void
    {
        $node = MenuNode::fromArray([
            'id' => 'p', 'label' => 'Parent', 'link' => '',
            'children' => [
                ['id' => 'c', 'label' => 'Child', 'link' => 'Quelque part', 'children' => [
                    ['id' => 'g', 'label' => 'Grandchild', 'link' => 'Plus loin'],
                ]],
            ],
        ]);

        $this->assertNotNull($node);
        $this->assertCount(1, $node->children);
        $this->assertSame([], $node->children[0]->children, 'the third level was stored');
    }

    /** What the visitor may not read, the visitor is not shown -- with no setting to get wrong. */
    public function testAnUnreadableEntryIsNeverDrawn(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('Readable', $html);
        $this->assertStringNotContainsString('Secret', $html);
    }

    /**
     * A page that does not exist is an invitation for whoever may write it, and nothing to anyone else.
     *
     * The reader half is asked on a wiki that does not let everyone write, because on one that does
     * -- the default, and what a wiki is -- every visitor may create it and every visitor is
     * rightly invited. `default_write_acl` lives in the parameter bag, so this builds a renderer
     * over a bag that says something else rather than pretending the running wiki changed.
     */
    public function testAMissingPageIsAWantedLinkForWritersOnly(): void
    {
        $wiki = $this->getWiki();
        $authentication = $wiki->services->get(\YesWiki\Identity\Service\AuthenticationService::class);

        $restricted = $this->rendererWhereOnlyAdminsWrite();

        $this->assertStringNotContainsString(
            'Missing',
            $restricted->render(self::MENU),
            'a reader is not shown work they may not do'
        );

        $authentication->connectFirstAdmin();
        try {
            $html = $this->rendererWhereOnlyAdminsWrite()->render(self::MENU);

            $this->assertStringContainsString('Missing', $html);
            $this->assertStringContainsString('data-missing-tag="true"', $html);
        } finally {
            $authentication->logout();
        }
    }

    /** The same renderer, over a wiki whose default write ACL is `@admins`. */
    private function rendererWhereOnlyAdminsWrite(): MenuRenderer
    {
        $services = $this->getWiki()->services;
        $acls = new AclService(
            $services->get(\YesWiki\Identity\Service\AuthenticationService::class),
            $services->get(\YesWiki\Kernel\Service\DbService::class),
            $services->get(\YesWiki\Identity\Service\UserManager::class),
            new \YesWiki\Test\Core\ForcedParameterBag(
                $services->get(\Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface::class),
                ['default_write_acl' => '@admins']
            ),
            $services->get(\YesWiki\Kernel\Service\HibernationService::class),
            $services
        );

        return new MenuRenderer(
            $services->get(MenuManager::class),
            $services->get(PageManager::class),
            $acls,
            $services->get(\YesWiki\Kernel\Service\UrlFormatter::class),
            $services->get(PageContext::class),
            $services->get(\YesWiki\Render\Service\TemplateEngine::class),
        );
    }

    /** The same menu, three placements, one preparation: the wrapper is all that differs. */
    public function testEveryPlacementDrawsTheSameEntries(): void
    {
        $navbar = $this->render(MenuRenderer::NAVBAR);
        $quick = $this->render(MenuRenderer::QUICK);
        $nav = $this->render(MenuRenderer::NAV);

        foreach ([$navbar, $quick, $nav] as $html) {
            $this->assertStringContainsString('Readable', $html);
            $this->assertStringNotContainsString('Secret', $html);
        }
        $this->assertStringContainsString('yw-topnav topnavpage', $navbar);
        $this->assertStringContainsString('yw-topnav-fast-access', $quick);
        $this->assertStringContainsString('yw-menu', $nav);
    }

    /** With dropdowns off, only the top level draws -- and a parent that led nowhere goes with them. */
    public function testDropdownsOffLeavesTheTopLevelOnly(): void
    {
        $withChildren = $this->render(MenuRenderer::NAV, ['showdropdown' => true]);
        $flat = $this->render(MenuRenderer::NAV, ['showdropdown' => false]);

        $this->assertStringContainsString('Child', $withChildren);
        $this->assertStringNotContainsString('Child', $flat);
        $this->assertStringNotContainsString('Parent', $flat, 'a parent leading nowhere says nothing on its own');
    }

    /** The page being read lights up, whether the link is a bare tag or carries a method. */
    public function testTheEntryForThePageBeingReadIsActive(): void
    {
        $pageContext = $this->getWiki()->services->get(PageContext::class);
        $was = $pageContext->getTag();
        $pageContext->setTag(self::READABLE);

        try {
            $this->assertStringContainsString('active', $this->render());
        } finally {
            $pageContext->setTag($was);
        }
    }
}
