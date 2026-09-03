<?php

namespace YesWiki\Test\Render;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Content\Service\PageManager;
use YesWiki\Kernel\Service\ConfigurationService;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Render\Service\LayoutService;
use YesWiki\Test\Core\ForcedParameterBag;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';
require_once 'tests/ForcedParameterBag.php';

/** The wiki's chrome, read out of configuration (ticket 30). */
class LayoutServiceTest extends YesWikiTestCase
{
    /**
     * @param array<string, mixed> $config
     */
    private function service(array $config = []): LayoutService
    {
        $services = $this->getWiki()->services;

        return new LayoutService(
            new ForcedParameterBag($services->get(ParameterBagInterface::class), $config),
            $services->get(ConfigurationService::class),
            $services->get(PageManager::class),
            $services->get(\YesWiki\Content\Service\MenuManager::class),
            $services->get(PageContext::class),
            $services->get(\YesWiki\Files\Service\Storage::class),
        );
    }

    public function testAWikiThatNeverSavedTheScreenStillHasAChrome(): void
    {
        $layout = $this->service([
            LayoutService::TITLE => null,
            LayoutService::LOGO => null,
            LayoutService::NAVBAR => null,
            LayoutService::QUICK_MENU => null,
            LayoutService::ACCOUNT_BUTTON => null,
        ]);

        $this->assertSame('', $layout->ownTitle());
        $this->assertSame('', $layout->navbar(), 'no menu is named');
        $this->assertSame('', $layout->quickMenu());
        $this->assertSame('text', $layout->brandMode());
        $this->assertTrue($layout->hasAccountButton(), 'a wiki still has to let people in');
    }

    public function testTheTitleFallsBackToTheWikisName(): void
    {
        $named = $this->service([LayoutService::TITLE => '', 'yeswiki_name' => 'Le wiki du collectif']);

        $this->assertSame('Le wiki du collectif', $named->title());
        $this->assertFalse($named->hasOwnTitle());
        $this->assertSame('', $named->ownTitle(), 'the screen shows an empty box, not the fallback');

        $own = $this->service([LayoutService::TITLE => 'Autre chose', 'yeswiki_name' => 'Le wiki du collectif']);
        $this->assertSame('Autre chose', $own->title());
        $this->assertTrue($own->hasOwnTitle());
    }

    /** A brand mode is never one the templates cannot draw, and never a logo that is not there. */
    public function testTheBrandModeIsAlwaysOneTheTemplatesDraw(): void
    {
        $this->assertSame('text', $this->service([LayoutService::BRAND => 'sideways'])->brandMode());
        $this->assertSame(
            'logo-text',
            $this->service([LayoutService::BRAND => 'logo-text', LayoutService::LOGO => 'files/logo.png'])->brandMode()
        );

        $this->assertSame(
            'text',
            $this->service([LayoutService::BRAND => 'logo', LayoutService::LOGO => ''])->brandMode()
        );
    }

    /**
     * The rows an editor posts become the two-level tree a menu holds (ticket 64).
     *
     * Configuration names a menu now rather than holding one, so what used to be read out of an
     * array here is read out of the form instead -- and this is where the nesting is decided.
     */
    public function testPostedRowsBecomeTheTwoLevelTreeAMenuHolds(): void
    {
        $draft = $this->service()->fromForm([], [
            ['label' => 'Accueil', 'link' => 'PagePrincipale', 'child' => 0],
            ['label' => 'Exemples', 'link' => '', 'child' => 0],
            ['label' => 'Agenda', 'link' => 'VueActivite', 'child' => 1],
            ['label' => '', 'link' => '', 'child' => 1],
        ], []);

        $this->assertCount(2, $draft->navbar);
        $this->assertSame('Accueil', $draft->navbar[0]->label);
        $this->assertSame([], $draft->navbar[0]->children);
        $this->assertCount(1, $draft->navbar[1]->children, 'a labelless row is not an entry');
        $this->assertSame('VueActivite', $draft->navbar[1]->children[0]->link);
    }

    /** A child with nothing above it is a top-level entry, which is what un-indenting the first row means. */
    public function testTheFirstRowIsAlwaysTopLevel(): void
    {
        $draft = $this->service()->fromForm([], [
            ['label' => 'Seule', 'link' => 'Quelque part', 'child' => 1],
        ], []);

        $this->assertCount(1, $draft->navbar);
        $this->assertSame('Seule', $draft->navbar[0]->label);
    }

    /** An entry needs to say something or lead somewhere; a blank row is not an entry. */
    public function testARowThatSaysNothingAndLeadsNowhereIsNotAnEntry(): void
    {
        $draft = $this->service()->fromForm([], [], [
            ['icon_source' => 'sprite', 'icon_value' => 'search', 'label' => '', 'link' => 'search', 'child' => 0],
            ['icon_source' => 'sprite', 'icon_value' => '', 'label' => 'Nous écrire', 'link' => 'Contact', 'child' => 0],
            ['icon_source' => 'sprite', 'icon_value' => '', 'label' => '', 'link' => '', 'child' => 0],
        ]);

        $this->assertCount(2, $draft->quickMenu);
        $this->assertSame('search', $draft->quickMenu[0]->iconValue);
        $this->assertSame('Nous écrire', $draft->quickMenu[1]->label);
    }

    /** The flags belong to the placement, and each placement starts as it has always drawn. */
    public function testEachPlacementDrawsAsItAlwaysHasUntilItIsTold(): void
    {
        $layout = $this->service([
            LayoutService::NAVBAR_ICONS => null,
            LayoutService::NAVBAR_LABELS => null,
            LayoutService::QUICK_MENU_ICONS => null,
            LayoutService::QUICK_MENU_LABELS => null,
        ]);

        $this->assertSame(
            ['showicons' => false, 'showlabels' => true, 'showdropdown' => true],
            $layout->navbarFlags(),
            'the navbar reads as words'
        );
        $this->assertSame(
            ['showicons' => true, 'showlabels' => false, 'showdropdown' => false],
            $layout->quickMenuFlags(),
            'the quick access bar reads as glyphs'
        );
    }

    public function testTheAccountButtonIsOffOnlyWhenItWasTurnedOff(): void
    {
        $this->assertTrue($this->service([LayoutService::ACCOUNT_BUTTON => true])->hasAccountButton());
        $this->assertTrue($this->service([LayoutService::ACCOUNT_BUTTON => '1'])->hasAccountButton());
        $this->assertFalse($this->service([LayoutService::ACCOUNT_BUTTON => false])->hasAccountButton());
    }

    /** The bar height is clamped, because it goes somewhere nothing can override it. */
    public function testTheNavbarHeightIsAlwaysUsable(): void
    {
        $this->assertSame(
            LayoutService::NAVBAR_HEIGHT_DEFAULT,
            $this->service([LayoutService::NAVBAR_HEIGHT => null])->navbarHeight(),
            'never set means the default'
        );
        $this->assertSame(
            LayoutService::NAVBAR_HEIGHT_DEFAULT,
            $this->service([LayoutService::NAVBAR_HEIGHT => 'tall'])->navbarHeight(),
            'and so does a value that is not a number'
        );
        $this->assertSame(72, $this->service([LayoutService::NAVBAR_HEIGHT => '72'])->navbarHeight());
        $this->assertSame(
            LayoutService::NAVBAR_HEIGHT_MIN,
            $this->service([LayoutService::NAVBAR_HEIGHT => 0])->navbarHeight()
        );
        $this->assertSame(
            LayoutService::NAVBAR_HEIGHT_MAX,
            $this->service([LayoutService::NAVBAR_HEIGHT => 5000])->navbarHeight()
        );
    }

    /** A posted form and the configuration produce the same kind of thing. */
    public function testADraftReadsLikeTheConfigurationDoes(): void
    {
        $service = $this->service([
            LayoutService::TITLE => '',
            'yeswiki_name' => 'Le wiki du collectif',
        ]);

        $draft = $service->fromForm(
            ['title' => '', 'logo' => '', 'brand' => 'logo', 'account' => true, 'height' => '64'],
            [['label' => 'Accueil', 'link' => 'PagePrincipale', 'child' => 0]],
            [['icon_value' => 'search', 'label' => '', 'link' => 'search', 'child' => 0]]
        );

        $this->assertSame('Le wiki du collectif', $draft->title);

        $this->assertSame('text', $draft->brandMode);
        $this->assertSame(64, $draft->navbarHeight);
        $this->assertCount(1, $draft->navbar);
        $this->assertSame('Accueil', $draft->navbar[0]->label);
        $this->assertCount(1, $draft->quickMenu);
        $this->assertTrue($draft->accountButton);
    }

    /** The per-page chrome override, which is the one thing here that reads the page. */
    public function testOnlyThePageBackedRolesCanBeOverridden(): void
    {
        $layout = $this->service();

        foreach (LayoutService::PAGES as $role) {
            $this->assertSame($role, $layout->pageFor($role), 'no metadata, no override');
        }
        $this->assertSame('PageTitre', $layout->pageFor('PageTitre'), 'not a role any more');
        $this->assertSame('PageMenuHaut', $layout->pageFor('PageMenuHaut'));
    }
}
