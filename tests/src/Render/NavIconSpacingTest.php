<?php

namespace YesWiki\Test\Render;

use YesWiki\Kernel\Service\Performer;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * A `{{nav}}` icon is separated from its title.
 *
 * The line that adds the space read `if (!empty($icon) && !empty($text))`, copied from
 * `ButtondropdownAction` where `$text` is a local variable. There is no `$text` in `NavAction`,
 * so the condition was never true and every icon ran straight into its title.
 *
 * `empty()` on an undefined variable is legal PHP, which is why this surfaced as an
 * *always-false branch* rather than an undefined-variable notice — and both readings of it sat
 * in the PHPStan baseline, one as `booleanAnd.alwaysFalse` and one as `empty.variable`, saying
 * the same thing twice to nobody (ticket 40).
 */
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
