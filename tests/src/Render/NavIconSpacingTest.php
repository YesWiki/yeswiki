<?php

namespace YesWiki\Test\Render;

use YesWiki\Kernel\Service\Performer;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** A `{{nav}}` icon is separated from its title. */
class NavIconSpacingTest extends YesWikiTestCase
{
    public function testAnIconIsSeparatedFromItsTitle(): void
    {
        $html = self::getWiki()->services->get(Performer::class)->run('nav', 'action', [
            'links' => 'PagePrincipale,BacASable',
            'titles' => 'Accueil,Bac à sable',
            'icons' => 'home,pencil',
        ]);

        $this->assertStringContainsString('Accueil', $html, 'the nav did not render at all');
        $this->assertMatchesRegularExpression(
            '/<\/svg>\s+Accueil/',
            $html,
            'the icon runs into its title: the separating space was never added'
        );
    }

    /** No icons asked for, no stray spacing invented. */
    public function testNoIconMeansNoSeparator(): void
    {
        $html = self::getWiki()->services->get(Performer::class)->run('nav', 'action', [
            'links' => 'PagePrincipale',
            'titles' => 'Accueil',
        ]);

        $this->assertStringContainsString('Accueil', $html);
        $this->assertStringNotContainsString('<svg', $html, 'no icon was asked for');
    }
}
