<?php

namespace YesWiki\Test\Core\Migrations;

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Entity\PageType;
use YesWiki\Content\Service\PageManager;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** A Doryphore wiki's admin pages give way to /admin and /dashboard, and nothing links to them any more. */
class DoryphoreSystemPagesGiveWayToTheirScreensTest extends YesWikiTestCase
{
    private const PAGE = 'TestRetiredAdminLinksPage';
    private const QUICK = 'TestRetiredAdminQuickMenu';
    private const ADMIN_ONLY = 'TestRetiredAdminOnlyMenu';
    private const RETIRED = 'GererMiseAJour';

    public static function setUpBeforeClass(): void
    {
        self::getWiki();
        require_once 'src/migrations/20261005150000_DoryphoreSystemPagesGiveWayToTheirScreens.php';
    }

    public function testButtonsAndMarkdownLinksReachTheScreens(): void
    {
        $text = '{{button link="GererSite" text="x"}} [Formulaires](BazaR) [Tableau](tableaudebord "titre") '
            . '{{button link="MesContenus"}} [Aide](ReglesDeFormatage) '
            . '{{button link="GererSiteBis"}} [autre](BazaR&vue=consulter) GererSite';

        $this->assertSame(
            '{{button link="admin" text="x"}} [Formulaires](dashboard?view=formulaire) [Tableau](dashboard "titre") '
            . '{{button link="user/pages"}} [Aide](doc) '
            . '{{button link="GererSiteBis"}} [autre](BazaR&vue=consulter) GererSite',
            \DoryphoreSystemPagesGiveWayToTheirScreens::repointText($text)
        );
    }

    public function testMenusArePointedAtTheScreensAndAMenuOnlyForThemGoesWithThePages(): void
    {
        $wiki = $this->getWiki();
        $db = $wiki->services->get(DbService::class);
        $pageManager = $wiki->services->get(PageManager::class);
        $pages = trim($db->prefixTable('pages'));
        $this->assertNull($pageManager->getOne(self::RETIRED, null, true, true), 'the misspelt alias is no real page of the dev wiki');
        $tags = [self::PAGE, self::QUICK, self::ADMIN_ONLY, self::RETIRED];

        try {
            $pageManager->save(self::PAGE, ['content' => '{{button link="TableauDeBord" text="Tableau"}}'], '', true);
            $pageManager->save(self::QUICK, ['title' => 'q', 'nodes' => [
                ['id' => 'a', 'label' => 'Rechercher', 'link' => 'search'],
                ['id' => 'b', 'label' => 'Roue', 'link' => '', 'children' => [
                    ['id' => 'c', 'label' => 'Gestion', 'link' => 'GererSite'],
                    ['id' => 'd', 'label' => 'Formulaires', 'link' => 'BazaR'],
                ]],
            ]], '', true, null, PageType::MENU);
            $pageManager->save(self::ADMIN_ONLY, ['title' => 'o', 'nodes' => [
                ['id' => 'e', 'label' => 'Droits', 'link' => 'GererDroits'],
                ['id' => 'f', 'label' => 'Look', 'link' => 'GererThemes'],
            ]], '', true, null, PageType::MENU);
            $pageManager->save(self::RETIRED, ['content' => '{{update}}'], '', true);

            $migration = new \DoryphoreSystemPagesGiveWayToTheirScreens();
            $migration->setServices($wiki->services);
            [$changed, $retiredOnly] = $migration->repointLinks($db, $pages, $tags);
            sort($changed);
            $this->assertSame([self::PAGE, self::ADMIN_ONLY, self::QUICK], $changed);
            $this->assertSame([self::ADMIN_ONLY => true], $retiredOnly);

            $quick = PageBody::decode($this->latestBody($db, $pages, self::QUICK));
            $this->assertSame('search', $quick['nodes'][0]['link']);
            $this->assertSame('admin', $quick['nodes'][1]['children'][0]['link']);
            $this->assertSame('dashboard?view=formulaire', $quick['nodes'][1]['children'][1]['link']);
            $this->assertStringContainsString('link=\"dashboard\"', $this->latestBody($db, $pages, self::PAGE));

            $this->assertSame([self::RETIRED], $migration->deleteRetiredPages($db, $pages, [self::RETIRED]));
            $this->assertNull($pageManager->getOne(self::RETIRED, null, true, true));

            $this->assertSame([[], []], $migration->repointLinks($db, $pages, $tags), 'a second run finds nothing left to repoint');
        } finally {
            foreach ($tags as $tag) {
                $db->query("DELETE FROM {$pages} WHERE tag = ?", [$tag]);
            }
        }
    }

    private function latestBody(DbService $db, string $pages, string $tag): string
    {
        $row = $db->loadSingle("SELECT body FROM {$pages} WHERE tag = ? AND latest = 'Y'", [$tag]);
        $this->assertNotNull($row, "{$tag} still has a latest revision");

        return (string)$row['body'];
    }
}
