<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use YesWiki\Kernel\Service\WikiUrls;

/** A wiki at the root of its domain is the only one ActivityPub actors and WebFinger can reach. */
class WikiUrlsDomainRootTest extends TestCase
{
    /** @return array<string, array{string, bool}> */
    public static function baseUrls(): array
    {
        return [
            'query mode' => ['https://monwiki.org/?', true],
            'rewrite mode' => ['https://monwiki.org/', true],
            'no trailing slash' => ['https://monwiki.org', true],
            'index.php' => ['https://monwiki.org/index.php?', true],
            'wakka.php' => ['https://monwiki.org/wakka.php?', true],
            'with a port' => ['http://localhost:8000/?', true],
            'subfolder' => ['https://exemple.org/monwiki/?', false],
            'subfolder in rewrite mode' => ['https://exemple.org/monwiki/', false],
            'index.php in a subfolder' => ['https://exemple.org/monwiki/index.php?', false],
        ];
    }

    #[DataProvider('baseUrls')]
    public function testIsDomainRoot(string $baseUrl, bool $expected): void
    {
        $this->assertSame($expected, WikiUrls::isDomainRoot($baseUrl));
    }
}
