<?php

namespace YesWiki\Test\Content;

use Symfony\Component\HttpFoundation\Request;
use YesWiki\Content\Controller\ListScreensController;
use YesWiki\Content\Controller\MenuController;
use YesWiki\Content\Entity\MenuNode;
use YesWiki\Content\Entity\Translations;
use YesWiki\Content\Service\MenuManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Kernel\Service\LanguageService;
use YesWiki\Kernel\Service\RequestScope;
use YesWiki\Render\Service\LanguageSwitch;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** The screens ticket 64 gives menus and value lists, which had none anybody could reach. */
class MenuScreensTest extends YesWikiTestCase
{
    private const MENU_TAG = 'MenuTraductionTest';

    protected function tearDown(): void
    {
        $this->getWiki()->services->get(MenuManager::class)->delete(self::MENU_TAG);
        $this->getWiki()->services->get(PageManager::class)->deleteOrphaned(self::MENU_TAG);
        $this->getWiki()->services->get(AuthenticationService::class)->logout();
        parent::tearDown();
    }

    private function skipUnlessMultilingual(): void
    {
        if (!in_array('en', $this->getWiki()->services->get(LanguageService::class)->availableLanguages(), true)) {
            $this->markTestSkipped('this wiki does not offer English, so nothing is translated into it');
        }
    }

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     */
    private function request(array $query, array $post = []): void
    {
        $this->getWiki()->services->get(RequestScope::class)->startNewRequest();
        $request = new Request($query, $post);
        if ($post !== []) {
            $request->setMethod('POST');
        }
        $this->getWiki()->services->get(CurrentRequest::class)->replace($request);
    }

    private function makeMenu(): void
    {
        $this->getWiki()->services->get(MenuManager::class)->create('Navigation', [
            $this->node([
                'id' => 'accueil',
                'label' => 'Accueil',
                'link' => 'PagePrincipale',
                'children' => [['id' => 'equipe', 'label' => 'Notre équipe', 'link' => 'LEquipe']],
            ]),
        ], self::MENU_TAG);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function node(array $data): MenuNode
    {
        $node = MenuNode::fromArray($data);
        $this->assertNotNull($node, 'the fixture does not describe a menu entry');

        return $node;
    }

    /**
     * @return list<MenuNode>
     */
    private function readMenu(): array
    {
        $menu = $this->getWiki()->services->get(MenuManager::class)->getOne(self::MENU_TAG);
        $this->assertNotNull($menu, 'the fixture menu is gone');

        return $menu['nodes'];
    }

    /**
     * @return array<string, mixed>
     */
    private function storedMenu(): array
    {
        return $this->getWiki()->services->get(MenuManager::class)->getUntranslated(self::MENU_TAG) ?? [];
    }

    private function asAdmin(): void
    {
        if (empty($this->getWiki()->services->get(AuthenticationService::class)->connectFirstAdmin())) {
            $this->markTestSkipped('no admin account in the test wiki');
        }
    }

    /** Appearance > Menus lists what the wiki has and offers the editor. */
    public function testTheMenusScreenRenders(): void
    {
        $this->asAdmin();

        $html = (string)$this->getWiki()->services->get(MenuController::class)->menus()->getContent();

        $this->assertStringContainsString('data-yw-menu-rows="entries"', $html, 'the shared editor is not on the screen');
        $this->assertStringContainsString('MenuNavigation', $html, 'the chrome menus are listed');
    }

    /** Administration > Value lists: the same lists, with the buttons. */
    public function testTheListsAdminScreenRenders(): void
    {
        $this->asAdmin();

        $html = (string)$this->getWiki()->services->get(ListScreensController::class)->adminLists()->getContent();

        $this->assertStringContainsString('existing-lists-table', $html);
    }

    /** And the public one, which is the same data with the controls gone. */
    public function testThePublicListsScreenRendersForAnybody(): void
    {
        $html = (string)$this->getWiki()->services->get(ListScreensController::class)->publicLists()->getContent();

        $this->assertStringContainsString('yw-dashboard', $html);
        $this->assertStringNotContainsString('data-yw-menu-rows', $html, 'a reader is offered no editor');
    }

    /** A menu open in the editor hands its languages to the one switch, the way every other editor does. */
    public function testTheMenuEditorOffersTheLanguages(): void
    {
        $this->skipUnlessMultilingual();
        $this->asAdmin();
        $this->makeMenu();

        $this->request(['menu' => self::MENU_TAG, 'editlang' => 'en']);
        $this->getWiki()->services->get(MenuController::class)->menus();

        $switch = $this->getWiki()->services->get(LanguageSwitch::class);
        $this->assertTrue($switch->isWriting(), 'the switch offers translations to write, not languages to read');
        $states = array_column($switch->options(), 'state', 'code');
        $this->assertSame('source', $states['fr'] ?? null);
        $this->assertSame('empty', $states['en'] ?? null, 'nothing translated yet');
    }

    /** The table lists every menu whether or not one is open, so there is something to click. */
    public function testTheScreenOffersNoLanguagesUntilAMenuIsOpen(): void
    {
        $this->skipUnlessMultilingual();
        $this->asAdmin();
        $this->makeMenu();

        $this->request([]);
        $this->getWiki()->services->get(MenuController::class)->menus();

        $this->assertFalse(
            $this->getWiki()->services->get(LanguageSwitch::class)->isWriting(),
            'the table is read, not written, so the switch stays the reader\'s own'
        );
    }

    /** Translating a menu shows blanks with the source beside, and no way to reshape it. */
    public function testTheMenuEditorBlanksTheLabelsAndLocksTheStructure(): void
    {
        $this->skipUnlessMultilingual();
        $this->asAdmin();
        $this->makeMenu();

        $this->request(['menu' => self::MENU_TAG, 'editlang' => 'en']);
        $html = (string)$this->getWiki()->services->get(MenuController::class)->menus()->getContent();

        $this->assertStringNotContainsString('value="Accueil"', $html, 'the input would be saved as its own translation');
        $this->assertStringContainsString('yw-translate-from__text">Accueil', $html, 'the translator is shown what they are retyping');
        $this->assertStringNotContainsString('data-yw-menu-add="entries"', $html, 'a translator may not add an entry');
        $this->assertStringNotContainsString('name="entries[0][link]"', $html, 'a link is not a translation');
    }

    /** And a reader of that language gets it, which is the whole point. */
    public function testAReaderOfThatLanguageGetsTheTranslatedLabels(): void
    {
        $this->skipUnlessMultilingual();
        $this->asAdmin();
        $this->makeMenu();
        $menus = $this->getWiki()->services->get(MenuManager::class);
        $menus->saveTranslations(self::MENU_TAG, 'en', ['nodes.accueil.label' => 'Home']);

        $language = $this->getWiki()->services->get(LanguageService::class);
        $served = $language->preferredLanguage();

        try {
            $language->serveIn('en');
            $menus->startNewRequest();
            $this->assertSame('Home', $this->readMenu()[0]->label);

            $language->serveIn('fr');
            $menus->startNewRequest();
            $this->assertSame('Accueil', $this->readMenu()[0]->label);
        } finally {
            $language->serveIn($served);
        }
    }

    /** Reshaping the menu in its own language keeps the translations somebody already typed. */
    public function testEditingTheStructureKeepsTheTranslations(): void
    {
        $this->skipUnlessMultilingual();
        $this->asAdmin();
        $this->makeMenu();
        $menus = $this->getWiki()->services->get(MenuManager::class);
        $menus->saveTranslations(self::MENU_TAG, 'en', ['nodes.accueil.label' => 'Home']);

        $menus->update(self::MENU_TAG, 'Navigation', [
            $this->node(['id' => 'accueil', 'label' => 'Accueil du site', 'link' => 'PagePrincipale']),
        ]);

        $this->assertSame(
            ['nodes.accueil.label' => 'Home'],
            Translations::of($this->storedMenu(), 'en'),
            'a save through MenuManager::update() rebuilds the body from title and nodes'
        );
    }
}
