<?php

namespace YesWiki\Test\Bazar;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ActivityPubRewriteRulesTest extends TestCase
{
    public static function requests(): array
    {
        return [
            'actor' => ['actors/3', 'api/forms/3/actor'],
            'inbox' => ['actors/3/inbox', 'api/forms/3/actor/inbox'],
            'outbox' => ['actors/12/outbox', 'api/forms/12/actor/outbox'],
            'followers' => ['actors/3/followers', 'api/forms/3/actor/followers'],
            'following' => ['actors/3/following', 'api/forms/3/actor/following'],
            'webfinger' => ['.well-known/webfinger', 'api/webfinger'],
            'unknown collection' => ['actors/3/likes', null],
            'form id that is not a number' => ['actors/blog', null],
            'trailing slash' => ['actors/3/', null],
            'other well-known file' => ['.well-known/nodeinfo', null],
            'a page' => ['PagePrincipale', null],
        ];
    }

    #[DataProvider('requests')]
    public function testTheShippedHtaccessRoutesActivityPubToTheApi(string $path, ?string $route)
    {
        $this->assertSame($route, $this->route($this->apacheRules(file_get_contents('.htaccess')), $path));
    }

    #[DataProvider('requests')]
    public function testTheDocumentedNginxRulesRouteTheSameWay(string $path, ?string $route)
    {
        $this->assertSame($route, $this->route($this->nginxRules($this->documentedBlock('nginx')), '/' . $path));
    }

    public function testTheDocumentedApacheRulesAreTheShippedOnes()
    {
        $this->assertSame($this->apacheRules(file_get_contents('.htaccess')), $this->apacheRules($this->documentedBlock('apache')));
    }

    public function testTheRulesKeepTheQueryStringWebfingerNeeds()
    {
        foreach ($this->apacheRules(file_get_contents('.htaccess')) as [, , $flags]) {
            $this->assertContains('QSA', explode(',', $flags));
        }
    }

    public function testEveryRoutedPathIsAnApiRoute()
    {
        $declared = file_get_contents('tools/bazar/controllers/ApiController.php');
        foreach (self::requests() as [, $route]) {
            if ($route !== null) {
                $this->assertStringContainsString('@Route("/' . preg_replace('~/forms/\d+/~', '/forms/{formId}/', $route) . '"', $declared);
            }
        }
    }

    /**
     * @return list<array{string,string,string}>
     */
    private function apacheRules(string $config): array
    {
        preg_match_all('/^\s*RewriteRule\s+(\S+)\s+(\S+)\s+\[([^\]]*)\]/m', $config, $matches, PREG_SET_ORDER);

        return array_map(fn ($match) => [$match[1], $match[2], $match[3]], $matches);
    }

    /**
     * @return list<array{string,string,string}>
     */
    private function nginxRules(string $config): array
    {
        preg_match_all('/^\s*rewrite\s+(\S+)\s+(\S+)\s+last;/m', $config, $matches, PREG_SET_ORDER);

        return array_map(fn ($match) => [$match[1], $match[2], ''], $matches);
    }

    private function documentedBlock(string $language): string
    {
        $this->assertSame(1, preg_match('/```' . $language . '\n(.*?)```/s', file_get_contents('docs/fr/activitypub.md'), $match));

        return $match[1];
    }

    /**
     * The wiki route a request path reaches through the first matching rule, or null when no rule matches.
     */
    private function route(array $rules, string $path): ?string
    {
        foreach ($rules as [$pattern, $substitution]) {
            if (preg_match('~' . $pattern . '~', $path, $groups)) {
                $target = preg_replace_callback('/\$(\d)/', fn ($ref) => $groups[$ref[1]] ?? '', $substitution);
                $this->assertSame(1, preg_match('~^/?index\.php\?wiki=(.*)$~', $target, $wiki));

                return $wiki[1];
            }
        }

        return null;
    }
}
