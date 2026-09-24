<?php

namespace YesWiki\Test\Core\Migrations;

use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Reading the three retired chrome pages into `layout_*` configuration (ticket 30). */
class LayoutBecomesConfigurationTest extends YesWikiTestCase
{
    public static function setUpBeforeClass(): void
    {
        self::getWiki();
        require_once 'src/migrations/20260806100000_LayoutBecomesConfiguration.php';
    }

    public function testTheSeededTitlePageMeansTheWikisOwnName(): void
    {
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

    /** A logo in any of the three ways it gets written into that page. */
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

    /**
     * The rows, not a tree: ticket 64 made the flat `child`-flagged row the shape a menu is written
     * from, and this migration hands its work to that writer rather than keeping a shape of its own.
     */
    public function testTheSeededMenuBecomesOneEntryAndOneDropdown(): void
    {
        [$navbar, $rest] = $this->readNavbar(
            " - [Bac à sable](BacASable)\n"
            . " - Menu exemple\n"
            . "   - [Exemple annuaire](TrombiAnnuaire)\n"
            . "   - [Exemple agenda](VueActivite)\n"
            . "{#INFO CACHÉE\nVous êtes dans la page qui se nomme PageMenuHaut\n#}"
        );

        $this->assertSame([
            ['label' => 'Bac à sable', 'link' => 'BacASable', 'child' => false],
            ['label' => 'Menu exemple', 'link' => '', 'child' => false],
            ['label' => 'Exemple annuaire', 'link' => 'TrombiAnnuaire', 'child' => true],
            ['label' => 'Exemple agenda', 'link' => 'VueActivite', 'child' => true],
        ], $navbar);
        $this->assertSame([], $rest);
    }

    /** Wikis indent with two spaces, three, or four; and some indent the whole list. */
    public function testTheShallowestBulletIsTheTopLevel(): void
    {
        [$navbar] = $this->readNavbar("    - [Un](PageUn)\n    - [Deux](PageDeux)");

        $this->assertCount(2, $navbar);
        $this->assertFalse($navbar[0]['child']);
        $this->assertFalse($navbar[1]['child']);
    }

    public function testANavActionBecomesEntries(): void
    {
        [$navbar, $rest] = $this->readNavbar('{{nav links="Accueil, BacASable" titles="Accueil, Bac à sable"}}');

        $this->assertSame(
            [
                ['label' => 'Accueil', 'link' => 'Accueil', 'child' => false],
                ['label' => 'Bac à sable', 'link' => 'BacASable', 'child' => false],
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

    public function testTheSeededQuickMenuBecomesTwoButtonsAndTheAccountCheckbox(): void
    {
        [$entries, $account, $rest] = $this->readQuickMenu(
            "{{button icon=\"loupe\" title=\"Rechercher\" link=\"search\"}}\n"
            . "{{button icon=\"gauge\" title=\"Tableau de bord\" link=\"dashboard\"}}\n"
            . '{{login}}'
        );

        $this->assertSame(
            [
                ['icon' => 'loupe', 'label' => 'Rechercher', 'link' => 'search', 'child' => false],
                ['icon' => 'gauge', 'label' => 'Tableau de bord', 'link' => 'dashboard', 'child' => false],
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

        $this->assertSame([['icon' => 'mail', 'label' => 'Nous écrire', 'link' => 'Contact', 'child' => false]], $entries);
    }

    public function testWhatIsNotAButtonIsReportedRatherThanDropped(): void
    {
        [$entries, , $rest] = $this->readQuickMenu("{{button link=\"search\"}}\n{{search}}");

        $this->assertCount(1, $entries);
        $this->assertSame(['{{search}}'], $rest, 'an action it cannot turn into a button is named');
    }

    /** fairetilt.co's PageRapideHaut: three buttons inside a cog dropdown, which must stay a dropdown. */
    public function testAButtonDropdownStaysAParentWithItsButtonsUnderIt(): void
    {
        [$entries, $account, $rest, $dropdown] = $this->readQuickMenu(
            "{{moteurrecherche template=\"moteurrecherche_button.tpl.html\"}}\n"
            . "{{buttondropdown icon=\"cog\" caret=\"0\" title=\"Gestion du site\"}}\n"
            . " - {{button nobtn=\"1\" icon=\"fas fa-user\" text=\"Me connecter\" link=\"Seconnecter\"}}\n"
            . " - {{button nobtn=\"1\" icon=\"fas fa-user\" text=\"M'inscrire\" link=\"Sinscrire\"}}\n"
            . "{{end elem=\"buttondropdown\"}}\n"
            . '{{login template="modal.twig" nobtn="1" signupurl="Sinscrire"}}'
        );

        $this->assertSame(
            [
                ['icon' => 'cog', 'label' => 'Gestion du site', 'link' => '', 'child' => false],
                ['icon' => 'fas fa-user', 'label' => 'Me connecter', 'link' => 'Seconnecter', 'child' => true],
                ['icon' => 'fas fa-user', 'label' => "M'inscrire", 'link' => 'Sinscrire', 'child' => true],
            ],
            $entries
        );
        $this->assertTrue($dropdown, 'the quick menu has to draw its dropdown');
        $this->assertTrue($account);
        $this->assertSame(['{{moteurrecherche template="moteurrecherche_button.tpl.html"}}'], $rest);
    }

    public function testAButtonAfterTheDropdownIsTopLevelAgain(): void
    {
        [$entries, , , $dropdown] = $this->readQuickMenu(
            "{{buttondropdown icon=\"cog\" title=\"Gestion\"}}\n"
            . "{{button text=\"Un\" link=\"Un\"}}\n"
            . "{{end elem=\"buttondropdown\"}}\n"
            . '{{button text="Deux" link="Deux"}}'
        );

        $this->assertFalse($entries[2]['child']);
        $this->assertTrue($dropdown);
    }

    /** fairetilt.co's PageMenuHaut ended on a section-wrapped button, which used to become an entry labelled "}}}". */
    public function testAnEntryShownOnlyToSomeVisitorsIsReportedRatherThanMangled(): void
    {
        $line = ' - {{section class="cover" visibility="!+" }}{{button class="btn-secondary-1 btn-xs" link="Seconnecter" text="Me connecter" }}{{end elem="section"}}';

        [$navbar, $rest] = $this->readNavbar(" - [Accueil](PagePrincipale)\n" . $line);

        $this->assertSame([['label' => 'Accueil', 'link' => 'PagePrincipale', 'child' => false]], $navbar);
        $this->assertSame([$line], $rest);
    }

    public function testAButtonInTheNavbarBecomesAnEntry(): void
    {
        [$navbar] = $this->readNavbar(' - {{button link="Contact" text="Nous écrire"}}');

        $this->assertSame([['label' => 'Nous écrire', 'link' => 'Contact', 'child' => false]], $navbar);
    }

    public function testALegacyWikiLinkBecomesAnEntry(): void
    {
        [$navbar] = $this->readNavbar(" - [[PagePrincipale Accueil]]\n - [[AutrePage]]");

        $this->assertSame(
            [
                ['label' => 'Accueil', 'link' => 'PagePrincipale', 'child' => false],
                ['label' => 'AutrePage', 'link' => 'AutrePage', 'child' => false],
            ],
            $navbar
        );
    }

    /**
     * @return array<int, mixed>
     */
    private function readTitle(string $body): array
    {
        return $this->call('readTitle', $body);
    }

    /**
     * @return array<int, mixed>
     */
    private function readNavbar(string $body): array
    {
        return $this->call('readNavbar', $body);
    }

    /**
     * @return array<int, mixed>
     */
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
