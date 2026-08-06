<?php

namespace YesWiki\Test\Core\Migrations;

use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Reading the three retired chrome pages into `layout_*` configuration (ticket 30).
 *
 * The parsing is what is tested, not the run: the run writes the wiki's own configuration
 * file, which `make test` has no business doing, and every decision in this migration is in
 * the reading -- what a bullet means, what indentation means, and above all what happens to
 * the lines it does not recognise.
 *
 * That last one is the point. A migration allowed to drop what it cannot parse is a
 * migration that empties somebody's menu quietly, so every case here checks the leftovers
 * as well as the entries.
 */
class LayoutBecomesConfigurationTest extends YesWikiTestCase
{
    public static function setUpBeforeClass(): void
    {
        // YesWikiMigration is only autoloadable once getWiki() has registered
        // src/autoload.inc.php's fallback autoloader
        self::getWiki();
        require_once 'src/migrations/20260806100000_LayoutBecomesConfiguration.php';
    }

    // ---------------------------------------------------------------- PageTitre

    public function testTheSeededTitlePageMeansTheWikisOwnName(): void
    {
        // `{{configuration param="yeswiki_name"}}` is the fallback written by hand, and an
        // untouched wiki must come out with an empty field rather than that tag as its title
        [$title, $logo, $rest] = $this->readTitle(
            "{{configuration param=\"yeswiki_name\" }}\n\n{#Astuce, vous pouvez remplacer le code#}"
        );

        $this->assertSame('', $title);
        $this->assertSame('', $logo);
        $this->assertSame([], $rest, 'the explanatory comment is not a leftover');
    }

    public function testATitleSomeoneTypedIsCarriedAcross(): void
    {
        [$title, $logo, $rest] = $this->readTitle("Le wiki du collectif\n");

        $this->assertSame('Le wiki du collectif', $title);
        $this->assertSame('', $logo);
        $this->assertSame([], $rest);
    }

    /**
     * A logo in any of the three ways it gets written into that page.
     */
    public function testALogoIsFoundHoweverItWasWritten(): void
    {
        $this->assertSame('files/logo.png', $this->readTitle('![](files/logo.png)')[1]);
        $this->assertSame('files/logo.png', $this->readTitle('""<img src="files/logo.png">""')[1]);
        $this->assertSame('files/logo.png', $this->readTitle('{{attach file="logo.png" size="small"}}')[1]);
    }

    public function testAnythingElseInTheTitlePageIsReportedRatherThanDropped(): void
    {
        [$title, , $rest] = $this->readTitle("Mon wiki\n{{include page=\"UnBandeau\"}}");

        $this->assertSame('Mon wiki', $title);
        $this->assertSame(['{{include page="UnBandeau"}}'], $rest);
    }

    // ------------------------------------------------------------- PageMenuHaut

    public function testTheSeededMenuBecomesOneEntryAndOneDropdown(): void
    {
        [$navbar, $rest] = $this->readNavbar(
            " - [Bac à sable](BacASable)\n"
            . " - Menu exemple\n"
            . "   - [Exemple annuaire](TrombiAnnuaire)\n"
            . "   - [Exemple agenda](VueActivite)\n"
            . "{#INFO CACHÉE\nVous êtes dans la page qui se nomme PageMenuHaut\n#}"
        );

        $this->assertCount(2, $navbar);
        $this->assertSame(['label' => 'Bac à sable', 'link' => 'BacASable', 'children' => []], $navbar[0]);
        $this->assertSame('Menu exemple', $navbar[1]['label']);
        // a bullet with no link is the dropdown parent: its label opens the menu
        $this->assertSame('', $navbar[1]['link']);
        $this->assertSame(
            [
                ['label' => 'Exemple annuaire', 'link' => 'TrombiAnnuaire'],
                ['label' => 'Exemple agenda', 'link' => 'VueActivite'],
            ],
            $navbar[1]['children']
        );
        $this->assertSame([], $rest);
    }

    /**
     * Wikis indent with two spaces, three, or four; and some indent the whole list.
     *
     * The shallowest bullet in the page is the top level whatever it is, or a list written
     * entirely indented would come out as one entry with everything hanging off it.
     */
    public function testTheShallowestBulletIsTheTopLevel(): void
    {
        [$navbar] = $this->readNavbar("    - [Un](PageUn)\n    - [Deux](PageDeux)");

        $this->assertCount(2, $navbar);
        $this->assertSame([], $navbar[0]['children']);
    }

    public function testANavActionBecomesEntries(): void
    {
        [$navbar, $rest] = $this->readNavbar('{{nav links="Accueil, BacASable" titles="Accueil, Bac à sable"}}');

        $this->assertSame(
            [
                ['label' => 'Accueil', 'link' => 'Accueil', 'children' => []],
                ['label' => 'Bac à sable', 'link' => 'BacASable', 'children' => []],
            ],
            $navbar
        );
        $this->assertSame([], $rest);
    }

    public function testMarkdownExtrasAreNotPartOfTheAddress(): void
    {
        [$navbar] = $this->readNavbar(' - [Le forum](https://forum.example "Voir le forum"){.newtab}');

        $this->assertSame('https://forum.example', $navbar[0]['link']);
        $this->assertSame('Le forum', $navbar[0]['label']);
    }

    public function testWhatIsNotAListIsReportedRatherThanDropped(): void
    {
        [$navbar, $rest] = $this->readNavbar(
            " - [Accueil](Accueil)\n"
            . "\"\"<table><tr><td><a href=\"?Autre\">Autre</a></td></tr></table>\"\"\n"
            . '{{include page="UnMenuMaison"}}'
        );

        $this->assertCount(1, $navbar, 'the one line it understood');
        $this->assertCount(2, $rest, 'and both of the ones it did not');
        $this->assertStringContainsString('<table>', $rest[0]);
        $this->assertStringContainsString('UnMenuMaison', $rest[1]);
    }

    // ---------------------------------------------------------- PageRapideHaut

    public function testTheSeededQuickMenuBecomesTwoButtonsAndTheAccountCheckbox(): void
    {
        [$entries, $account, $rest] = $this->readQuickMenu(
            "{{button icon=\"loupe\" title=\"Rechercher\" link=\"search\"}}\n"
            . "{{button icon=\"gauge\" title=\"Tableau de bord\" link=\"dashboard\"}}\n"
            . '{{login}}'
        );

        $this->assertSame(
            [
                ['icon' => 'loupe', 'label' => 'Rechercher', 'link' => 'search'],
                ['icon' => 'gauge', 'label' => 'Tableau de bord', 'link' => 'dashboard'],
            ],
            $entries
        );
        $this->assertTrue($account, '{{login}} is the account button');
        $this->assertSame([], $rest);
    }

    public function testAQuickMenuWithoutLoginGetsNoAccountButton(): void
    {
        [, $account] = $this->readQuickMenu('{{button icon="loupe" link="search"}}');

        $this->assertFalse($account, 'a wiki that removed it from the page had removed it');
    }

    public function testAButtonLabelledWithTextRatherThanTitle(): void
    {
        [$entries] = $this->readQuickMenu('{{button text="Nous écrire" link="Contact" icon="mail"}}');

        $this->assertSame([['icon' => 'mail', 'label' => 'Nous écrire', 'link' => 'Contact']], $entries);
    }

    public function testWhatIsNotAButtonIsReportedRatherThanDropped(): void
    {
        [$entries, , $rest] = $this->readQuickMenu("{{button link=\"search\"}}\n{{search}}");

        $this->assertCount(1, $entries);
        $this->assertSame(['{{search}}'], $rest, 'an action it cannot turn into a button is named');
    }

    // ------------------------------------------------------------------ helpers

    /** @return array<int, mixed> */
    private function readTitle(string $body): array
    {
        return $this->call('readTitle', $body);
    }

    /** @return array<int, mixed> */
    private function readNavbar(string $body): array
    {
        return $this->call('readNavbar', $body);
    }

    /** @return array<int, mixed> */
    private function readQuickMenu(string $body): array
    {
        return $this->call('readQuickMenu', $body);
    }

    /**
     * @return array<int, mixed>
     */
    private function call(string $method, string $body): array
    {
        $migration = new \LayoutBecomesConfiguration();

        return (new \ReflectionClass($migration))->getMethod($method)->invoke($migration, $body);
    }
}
