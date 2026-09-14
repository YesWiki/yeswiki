<?php

namespace YesWiki\Test\Admin;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use YesWiki\Admin\Service\DashboardData;

/** Which site an imported entry is filed under on the sources screen. */
class DashboardSourceOriginTest extends TestCase
{
    /** @return array<string, array{0: string, 1: string}> */
    public static function urls(): array
    {
        return [
            'a wiki page, named in the query' => ['https://lab.mrflos.pw/ecto/?PageTag', 'https://lab.mrflos.pw/ecto'],
            'a wiki entry read through the api' => ['https://lab.mrflos.pw/ecto/?api/entries/PageTag', 'https://lab.mrflos.pw/ecto'],
            'another wiki on the same host' => ['https://lab.mrflos.pw/autre/?PageTag', 'https://lab.mrflos.pw/autre'],
            'a wiki at the root' => ['https://wiki.example.org/?PageTag', 'https://wiki.example.org'],
            'a wiki reached through index.php' => ['https://wiki.example.org/index.php?PageTag', 'https://wiki.example.org'],
            'a wiki on its own port' => ['https://wiki.example.org:8443/ecto/?PageTag', 'https://wiki.example.org:8443/ecto'],
            'a blog article, named in the path' => ['https://framablog.org/2026/09/01/le-titre/', 'https://framablog.org'],
            'the same article with tracking parameters' => ['https://framablog.org/2026/09/02/autre/?utm_source=rss', 'https://framablog.org'],
            'a fediverse status' => ['https://mastodon.social/users/someone/statuses/123', 'https://mastodon.social'],
            'something that is not a url' => ['pas-une-url', 'pas-une-url'],
        ];
    }

    #[DataProvider('urls')]
    public function testEverySourceUrlIsFiledUnderItsSite(string $sourceUrl, string $expected): void
    {
        $this->assertSame($expected, DashboardData::originOf($sourceUrl));
    }

    public function testOneFeedIsOneSource(): void
    {
        $articles = [
            'https://framablog.org/2026/09/01/premier/',
            'https://framablog.org/2026/09/02/deuxieme/',
            'https://framablog.org/2025/12/31/troisieme/?utm_medium=rss',
        ];

        $origins = array_unique(array_map(DashboardData::originOf(...), $articles));

        $this->assertSame(['https://framablog.org'], array_values($origins));
    }
}
