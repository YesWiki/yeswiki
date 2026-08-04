<?php

namespace YesWiki\Test\Core\Migrations;

use PHPUnit\Framework\Attributes\DataProvider;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * The migration behind the changed default: `{{login}}` renders the account button now,
 * and every `{{login}}` written before this release meant the form.
 *
 * Tested on the rewriting itself rather than through the database, because the rewriting
 * is the part with a decision in it -- three spellings of the same tag, each meaning
 * something different afterwards -- and because running the real thing here would edit
 * the developer's own pages as a side effect of `make test`.
 */
class LoginDefaultsToTheAccountButtonTest extends YesWikiTestCase
{
    public static function setUpBeforeClass(): void
    {
        // YesWikiMigration is only autoloadable once getWiki() has registered
        // src/autoload.inc.php's fallback autoloader
        self::getWiki();
        require_once 'src/migrations/20260804090000_LoginDefaultsToTheAccountButton.php';
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function rewriteProvider(): array
    {
        return [
            // what a page written before this release meant: the fields
            'a bare tag' => ['{{login}}', '{{login template="login-form.twig"}}'],
            'a tag with other parameters' => [
                '{{login context="login-page" signupurl="0"}}',
                '{{login template="login-form.twig" context="login-page" signupurl="0"}}',
            ],
            // `default.twig` used to BE the form, and is a name nothing answers to now
            'the old default named explicitly' => [
                '{{login template="default.twig"}}',
                '{{login template="login-form.twig"}}',
            ],
            // the navbar's button, renamed -- and kept named, so that running this a
            // second time does not read it as a page that never chose
            'the account link' => [
                '{{login template="account-link.twig"}}',
                '{{login template="account-button.twig"}}',
            ],
            'the account link among others' => [
                '{{login template="account-link.twig" class="pull-right"}}',
                '{{login template="account-button.twig" class="pull-right"}}',
            ],
            // a template of the webmaster's own is none of this migration's business
            'a template of ones own' => [
                '{{login template="mytheme-login.twig"}}',
                '{{login template="mytheme-login.twig"}}',
            ],
            // and nothing else in the page is touched
            'other actions' => ['{{lostpassword}} {{listusers}}', '{{lostpassword}} {{listusers}}'],
        ];
    }

    #[DataProvider('rewriteProvider')]
    public function testEveryLoginTagKeepsRenderingWhatItRendered(string $before, string $expected): void
    {
        $this->assertSame($expected, $this->rewrite($before));
    }

    public function testRunningItTwiceChangesNothingTheSecondTime(): void
    {
        $once = $this->rewrite('{{login}} and {{login template="account-link.twig"}}');
        $this->assertSame($once, $this->rewrite($once), 'a migration is run again by every wiki that lags');
    }

    /** The migration's rewriting of one page body, as it would rewrite a stored one. */
    private function rewrite(string $content): string
    {
        $migration = new \LoginDefaultsToTheAccountButton();
        $rewriteBody = (new \ReflectionClass($migration))->getMethod('rewriteBody');

        $rewritten = $rewriteBody->invoke($migration, ['content' => $content]);

        // null is "nothing in it changed", which leaves the body as it was
        return $rewritten === null ? $content : (string)$rewritten['content'];
    }
}
