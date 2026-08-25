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
        $this->assertSame([], $layout->navbar());
        $this->assertSame([], $layout->quickMenu());
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

    public function testTheNavbarKeepsOneLevelOfChildrenAndDropsWhatHasNoLabel(): void
    {
        $navbar = $this->service([LayoutService::NAVBAR => [
            ['label' => 'Accueil', 'link' => 'PagePrincipale'],
            ['label' => '  ', 'link' => 'Ignoree'],
            ['label' => 'Exemples', 'link' => '', 'children' => [
                ['label' => 'Agenda', 'link' => 'VueActivite'],
                ['label' => '', 'link' => 'Ignoree'],
            ]],
            'not an entry at all',
        ]])->navbar();

        $this->assertCount(2, $navbar);
        $this->assertSame(['label' => 'Accueil', 'link' => 'PagePrincipale', 'children' => []], $navbar[0]);
        $this->assertSame([['label' => 'Agenda', 'link' => 'VueActivite']], $navbar[1]['children']);
    }

    public function testAQuickEntryNeedsAnIconOrALabelAndNotBoth(): void
    {
        $entries = $this->service([LayoutService::QUICK_MENU => [
            ['icon' => 'search', 'label' => '', 'link' => 'search'],
            ['icon' => '', 'label' => 'Nous écrire', 'link' => 'Contact'],
            ['icon' => '', 'label' => '', 'link' => 'Nulle part'],
        ]])->quickMenu();

        $this->assertCount(2, $entries);
        $this->assertSame('search', $entries[0]['icon']);
        $this->assertSame('Nous écrire', $entries[1]['label']);
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
            [['label' => 'Accueil', 'link' => 'PagePrincipale']],
            [['icon' => 'search', 'label' => '', 'link' => 'search']]
        );

        $this->assertSame('Le wiki du collectif', $draft->title);

        $this->assertSame('text', $draft->brandMode);
        $this->assertSame(64, $draft->navbarHeight);
        $this->assertSame([['label' => 'Accueil', 'link' => 'PagePrincipale', 'children' => []]], $draft->navbar);
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
