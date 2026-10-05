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

    /** yeswiki.pro and co-lab-cnfpt.fr wrote the name beside the logo, on the same line. */
    public function testTheTextBesideTheLogoIsTheTitle(): void
    {
        [$title, $logo, $rest] = $this->readTitle(
            "\"\"<center>\"\"\n{{attach file=\"logoaietech\" size=\"original\" }}\"\"YesWiki\"\" Pro\n\"\"</center>\"\""
        );
        $this->assertSame('YesWiki Pro', $title);
        $this->assertSame('files/logoaietech', $logo);
        $this->assertSame([], $rest, 'the centring markup around them is not a leftover');

        $this->assertSame(
            'WIKI-PROG Nouvelle-Aquitaine',
            $this->readTitle('{{attach class="left" file="logoCNFPTmono" size="small" }}WIKI-PROG""<br />""Nouvelle-Aquitaine')[0]
        );
    }

    /** A title page holding only the logo has no title of its own: the image was the whole brand. */
    public function testALogoAloneLeavesTheTitleEmpty(): void
    {
        [$title, $logo] = $this->readTitle('{{attach desc="Logo Faire tilt" file="logo-faire-tilt-5" nofullimagelink="1" size="big" }}');

        $this->assertSame('', $title);
        $this->assertSame('files/logo-faire-tilt-5', $logo);
    }

    /** margot drew the logo at most 2.9rem high, and Ectoplasme draws it 12px shorter than the navbar. */
    public function testTheNavbarIsAsHighAsTheLogoWasDrawn(): void
    {
        $height = static fn (int $natural): int => (new \ReflectionMethod(\LayoutBecomesConfiguration::class, 'navbarHeightFor'))->invoke(null, $natural);

        $this->assertSame(58, $height(544), 'a big logo was capped at 46px');
        $this->assertSame(58, $height(46));
        $this->assertSame(52, $height(40), 'a smaller one was drawn at its own height');
        $this->assertSame(48, $height(20), 'never lower than the default navbar');
    }

    /** yeswiki.pro's logo stayed in files/ because another page names its file in full: the newest upload of that name is the logo. */
    public function testALogoLeftInFilesIsFoundUnderItsDoryphoreName(): void
    {
        $storage = $this->getWiki()->services->get(\YesWiki\Files\Service\Storage::class);
        $older = 'files/PageTitre_layouttestlogo_20220101000000_20220101000000.png';
        $newer = 'files/PageTitre_layouttestlogo_20230101000000_20230101000000.png';
        $other = 'files/PageTitre_layouttestlogoother_20240101000000_20240101000000.png';
        foreach ([$older, $newer, $other] as $path) {
            $storage->write($path, 'png');
        }

        try {
            $migration = new \LayoutBecomesConfiguration();
            $migration->setServices($this->getWiki()->services);
            $found = (new \ReflectionClass($migration))->getMethod('legacyUpload')->invoke($migration, 'files/layouttestlogo.png');

            $this->assertSame($newer, $found);
            $this->assertNull((new \ReflectionClass($migration))->getMethod('legacyUpload')->invoke($migration, 'files/absent.png'));
        } finally {
            foreach ([$older, $newer, $other] as $path) {
                $storage->delete($path);
            }
        }
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

    /** yeswiki.pro's cog has no title: it stays the parent, its links and buttons under it, its rules dropped. */
    public function testAnUntitledDropdownKeepsEverythingUnderIt(): void
    {
        [$entries, $account, $rest] = $this->readQuickMenu(
            "{{buttondropdown icon=\"cog\" caret=\"0\"}}\n"
            . " - {{login template=\"modal.twig\" nobtn=\"1\"}}\n"
            . " - ------\n"
            . " - {{button nobtn=\"1\" icon=\"fa fa-question\" text=\"Aide\" link=\"DocuMentation\"}}\n"
            . " - [recherche](RechercheTexte) \n"
            . '{{end elem="buttondropdown"}}'
        );

        $this->assertSame(
            [
                ['icon' => 'cog', 'label' => 'Menu', 'link' => '', 'child' => false],
                ['icon' => 'fa fa-question', 'label' => 'Aide', 'link' => 'DocuMentation', 'child' => true],
                ['icon' => '', 'label' => 'recherche', 'link' => 'RechercheTexte', 'child' => true],
            ],
            $entries
        );
        $this->assertTrue($account);
        $this->assertSame([], $rest);
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
