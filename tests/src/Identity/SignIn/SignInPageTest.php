<?php

namespace YesWiki\Test\Identity\SignIn;

use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Render\Service\MarkdownFormatterService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';
require_once __DIR__ . '/FakeProviderTypes.php';

/** {{login}} lists every enabled provider (spec 8.1). */
class SignInPageTest extends YesWikiTestCase
{
    use FakeProviderTypes;

    protected function setUp(): void
    {
        parent::setUp();
        self::getWiki()->services->get(AuthenticationService::class)->logout();
    }

    protected function tearDown(): void
    {
        $this->restoreProviders();
        parent::tearDown();
    }

    /** @param array<string, array<string, mixed>> $section */
    private function render(array $section): string
    {
        $this->installProviders($section);

        return (string)self::getWiki()->services->get(MarkdownFormatterService::class)->format('{{login template="account-login-form.twig"}}');
    }

    public function testRedirectProvidersComeFirstAsButtons(): void
    {
        $html = $this->render(['acme' => ['type' => 'fakeredirect', 'label' => 'Acme SSO'], 'local' => ['type' => 'local']]);
        $this->assertNotFalse(strpos($html, 'Acme SSO'));
        $this->assertNotFalse(strpos($html, 'name="password"'));
        $this->assertLessThan(strpos($html, 'name="password"'), strpos($html, 'Acme SSO'));
        $this->assertStringContainsString('user/sign-in/acme', $html);
    }

    /** An entry with no label of its own is offered under its type's label: "Sign in with acme-2" means nothing to anybody. */
    public function testAnUnlabelledProviderIsNamedByItsType(): void
    {
        $html = $this->render(['acme-2' => ['type' => 'fakeredirect'], 'dir' => ['type' => 'fakepw'], 'local' => ['type' => 'local']]);

        $this->assertMatchesRegularExpression('#<a[^>]*user/sign-in/acme-2[^>]*>\s*(<[^>]+>\s*)*[^<]*fakeredirect#', $html);
        $this->assertDoesNotMatchRegularExpression('#>\s*[^<]*\bacme-2\s*<#', $html, 'the identifier is in the link only');
        $this->assertStringContainsString('fakepw', $html);
        $this->assertStringContainsString(_t('SIGN_IN_LOCAL_LABEL'), $html);
    }
    public function testTheButtonCarriesTheReturnUrl(): void
    {
        $html = $this->render(['acme' => ['type' => 'fakeredirect']]);
        $this->assertMatchesRegularExpression('#user/sign-in/acme[^"]*return=#', $html);
    }

    /** htmx would fetch the provider's page into the wiki's, under the wiki's address: the browser must leave for real. */
    public function testTheButtonLeavesWithoutBoostedNavigation(): void
    {
        $html = $this->render(['acme' => ['type' => 'fakeredirect']]);
        $this->assertStringContainsString('<div class="yw-sign-in__providers" hx-boost="false">', $html);
    }

    public function testOnePasswordProviderHasNoTabs(): void
    {
        $html = $this->render(['local' => ['type' => 'local']]);
        $this->assertStringNotContainsString('yw-sign-in__tabs', $html);
        $this->assertStringContainsString('name="password"', $html);
        $this->assertMatchesRegularExpression('#name="provider"[^>]*value="local"|value="local"[^>]*name="provider"#', $html);
    }

    public function testTwoPasswordProvidersHaveTabs(): void
    {
        $html = $this->render(['local' => ['type' => 'local'], 'dir' => ['type' => 'fakepw', 'label' => 'Company directory']]);
        $this->assertStringContainsString('yw-sign-in__tabs', $html);
        $this->assertStringContainsString('Company directory', $html);
    }

    public function testNoPasswordProviderHasNoForm(): void
    {
        $html = $this->render(['acme' => ['type' => 'fakeredirect']]);
        $this->assertStringNotContainsString('name="password"', $html);
        $this->assertStringNotContainsString(htmlspecialchars(_t('SIGN_IN_NO_PROVIDER'), ENT_QUOTES), $html);
    }

    public function testLostPasswordAndSignupOnlyWithLocal(): void
    {
        $this->assertStringNotContainsString('user/signup', $this->render(['dir' => ['type' => 'fakepw']]));
        $this->assertStringNotContainsString('user/lost-password', $this->render(['dir' => ['type' => 'fakepw']]));
        $withLocal = $this->render(['local' => ['type' => 'local']]);
        $this->assertStringContainsString('user/signup', $withLocal);
        $this->assertStringContainsString('user/lost-password', $withLocal);
        $this->assertStringContainsString('name="remember"', $withLocal);
        $this->assertStringNotContainsString('name="remember"', $this->render(['dir' => ['type' => 'fakepw']]), 'a directory sign-in is always remembered: no box to tick');
    }

    public function testTheHorizontalFormPostsTheProviderToo(): void
    {
        $this->installProviders(['dir' => ['type' => 'fakepw']]);
        $only = (string)self::getWiki()->services->get(MarkdownFormatterService::class)->format('{{login template="horizontal.twig"}}');
        $this->assertMatchesRegularExpression('#name="provider"[^>]*value="dir"|value="dir"[^>]*name="provider"#', $only);

        $this->installProviders(['local' => ['type' => 'local'], 'dir' => ['type' => 'fakepw']]);
        $both = (string)self::getWiki()->services->get(MarkdownFormatterService::class)->format('{{login template="horizontal.twig"}}');
        $this->assertStringContainsString('yw-sign-in__tabs', $both);
    }

    public function testNothingEnabledSaysSo(): void
    {
        $html = $this->render(['local' => ['type' => 'local', 'enabled' => false]]);
        $this->assertStringContainsString(htmlspecialchars(_t('SIGN_IN_NO_PROVIDER'), ENT_QUOTES), $html);
        $this->assertStringNotContainsString('name="password"', $html);
    }
}
