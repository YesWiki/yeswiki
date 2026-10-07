<?php

namespace YesWiki\Test\Admin;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use YesWiki\Admin\Service\InstallationService;

require_once 'tests/YesWikiTestCase.php';

/** Base url and rewrite mode at install. */
class BaseUrlFollowsRewriteModeTest extends TestCase
{
    /** @return array<string, array{string, bool, string}> */
    public static function baseUrls(): array
    {
        return [
            'rewrite drops the question mark' => ['https://example.org/wiki/?', true, 'https://example.org/wiki/'],
            'rewrite keeps an url without it' => ['https://example.org/wiki/', true, 'https://example.org/wiki/'],
            'no rewrite adds the question mark' => ['https://example.org/wiki/', false, 'https://example.org/wiki/?'],
            'no rewrite keeps an url with it' => ['https://example.org/wiki/?', false, 'https://example.org/wiki/?'],
            'a script url is left as typed' => ['https://example.org/wiki/index.php?', true, 'https://example.org/wiki/index.php?'],
        ];
    }

    #[DataProvider('baseUrls')]
    public function testTheBaseUrlFollowsTheRewriteMode(string $baseUrl, bool $rewriteMode, string $expected): void
    {
        $this->assertSame($expected, InstallationService::baseUrlForRewriteMode($baseUrl, $rewriteMode));
    }
}
