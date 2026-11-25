<?php

namespace YesWiki\Test\Bazar\Action;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * {{bazar redirecturl}} sends to the url it was given, even when that url was written HTML-escaped.
 */
class BazarActionRedirectUrlTest extends YesWikiTestCase
{
    private function redirectUrl(string $given): string
    {
        $wiki = $this->getWiki();
        $GLOBALS['wiki'] = $wiki;
        require_once 'tools/bazar/actions/BazarAction.php';
        $action = new \BazarAction();
        $action->setWiki($wiki);
        $action->setParams($wiki->services->get(ParameterBagInterface::class));
        $format = new \ReflectionMethod($action, 'formatArguments');

        return $format->invoke($action, ['redirecturl' => $given])['redirecturl'];
    }

    #[DataProvider('urlProvider')]
    public function testTheRedirectKeepsItsParameters(string $given, string $expectedQuery)
    {
        $url = $this->redirectUrl($given);

        $this->assertStringNotContainsString('&amp;', $url);
        $this->assertStringEndsWith($expectedQuery, $url);
    }

    public static function urlProvider(): array
    {
        return [
            'full url' => ['https://example.org/?PagePrincipale&a=1&b=2', 'PagePrincipale&a=1&b=2'],
            'escaped full url' => ['https://example.org/?PagePrincipale&amp;a=1&amp;b=2', 'PagePrincipale&a=1&b=2'],
            'page tag' => ['PagePrincipale?a=1&b=2', 'PagePrincipale&a=1&b=2'],
            'escaped page tag' => ['PagePrincipale?a=1&amp;b=2', 'PagePrincipale&a=1&b=2'],
        ];
    }
}
