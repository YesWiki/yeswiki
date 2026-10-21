<?php

namespace YesWiki\Test\Core;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once 'includes/urlutils.inc.php';

class UrlUtilsTest extends TestCase
{
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
    public function testIsDomainRootUrl(string $baseUrl, bool $expected)
    {
        $this->assertSame($expected, isDomainRootUrl($baseUrl));
    }
}
