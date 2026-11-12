<?php

namespace YesWiki\Test\Identity\SignIn;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use YesWiki\Core\YesWikiController;
use YesWiki\Identity\Controller\RendersSignInOutcome;
use YesWiki\Identity\Controller\SignInController;
use YesWiki\Identity\SignIn\Contract\FailureReason;
use YesWiki\Identity\SignIn\Contract\ProviderConfig;
use YesWiki\Identity\SignIn\SignInOutcome;
use YesWiki\Kernel\Service\RouteProvider;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** A controller showing an outcome the way the sign-in routes do. */
final class RefusedPageRenderer extends YesWikiController
{
    use RendersSignInOutcome;

    public function show(Response|SignInOutcome $outcome): Response
    {
        return $this->renderSignInOutcome($outcome);
    }
}

class SignInRefusedPageTest extends YesWikiTestCase
{
    private function renderer(): RefusedPageRenderer
    {
        $renderer = new RefusedPageRenderer();
        $renderer->setServices(self::getWiki()->services);

        return $renderer;
    }

    private function config(string $label = 'Acme SSO'): ProviderConfig
    {
        return ProviderConfig::fromArray('acme', ['type' => 'oidc', 'label' => $label]);
    }

    public function testASignInGoesWhereItWasGoing(): void
    {
        $response = $this->renderer()->show(SignInOutcome::signedIn('https://w/?There'));

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('https://w/?There', $response->headers->get('Location'));
    }

    public function testAResponseFromTheProviderIsPassedOn(): void
    {
        $response = new RedirectResponse('https://idp.example/');

        $this->assertSame($response, $this->renderer()->show($response));
    }

    public function testARefusalExplainsItselfWith403(): void
    {
        $response = $this->renderer()->show(SignInOutcome::refused($this->config(), FailureReason::EmailConflict, 'marie@acme.example'));
        $html = (string)$response->getContent();

        $this->assertSame(403, $response->getStatusCode());
        $this->assertStringContainsString('yw-account-guest__card', $html, 'the page of someone who has no session');
        $this->assertStringContainsString(htmlspecialchars(_t('SIGN_IN_REFUSED_TITLE'), ENT_QUOTES), $html);
        $this->assertStringContainsString(htmlspecialchars(_t('SIGN_IN_REFUSED_TODO'), ENT_QUOTES), $html);
        $this->assertStringContainsString(htmlspecialchars(_t('SIGN_IN_BACK'), ENT_QUOTES), $html);
        $this->assertStringContainsString('Acme SSO', $html, 'it names the provider');
        $this->assertStringContainsString('marie@acme.example', $html, 'and the email to give the administrator');
        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}/', $html, 'and the time');
    }

    public function testNothingForTheAdministratorReachesThePage(): void
    {
        $html = (string)$this->renderer()->show(SignInOutcome::refused($this->config(), FailureReason::ProviderRejected, 'who@x', 'invalid_client: the raw provider error'))->getContent();

        $this->assertStringNotContainsString('invalid_client', $html);
    }

    public function testThePersonsMessageReplacesTheTranslation(): void
    {
        $outcome = SignInOutcome::refused($this->config(), FailureReason::ProviderRejected, 'who@x', 'admin only', 'Ask your manager');
        $html = (string)$this->renderer()->show($outcome)->getContent();

        $this->assertStringContainsString('Ask your manager', $html);
        $this->assertStringNotContainsString(htmlspecialchars(_t('SIGN_IN_REFUSED_PROVIDER_REJECTED', ['provider' => 'Acme SSO']), ENT_QUOTES), $html);
    }

    public function testWhatTheProviderSentIsEscaped(): void
    {
        $evil = '<script>alert(1)</script>';
        $config = $this->config($evil);

        foreach ([
            SignInOutcome::refused($config, FailureReason::NoAccount, 'someone'),
            SignInOutcome::refused($this->config(), FailureReason::NoAccount, $evil),
            SignInOutcome::refused($this->config(), FailureReason::ProviderRejected, 'someone', '', $evil),
        ] as $outcome) {
            $html = (string)$this->renderer()->show($outcome)->getContent();
            $this->assertStringNotContainsString($evil, $html);
            $this->assertStringContainsString('&lt;script&gt;', $html);
        }
    }

    public function testARefusalNamingNoProviderStillReads(): void
    {
        $html = (string)$this->renderer()->show(SignInOutcome::refused(null, FailureReason::InvalidResponse, ''))->getContent();

        $this->assertStringNotContainsString('%{provider}', $html);
        $this->assertStringNotContainsString('%{who}', $html);
        $this->assertStringContainsString(htmlspecialchars(_t('SIGN_IN_PROVIDER_UNNAMED'), ENT_QUOTES), $html);
    }

    public function testAnUnknownProviderAnswersTheRefusedPage(): void
    {
        $controller = self::getWiki()->services->get(SignInController::class);

        $this->assertSame(403, $controller->begin('nobody-configured-this')->getStatusCode());
        $this->assertSame(403, $controller->callback('nobody-configured-this')->getStatusCode());
    }

    public function testAProviderPublishingNoMetadataAnswers404(): void
    {
        $controller = self::getWiki()->services->get(SignInController::class);

        $this->assertSame(404, $controller->metadata('local')->getStatusCode());
        $this->assertSame(404, $controller->metadata('nobody-configured-this')->getStatusCode());
    }

    /** @return array<string, array{string, list<string>}> */
    public static function routes(): array
    {
        return [
            'start' => ['/user/sign-in/{id}', ['GET']],
            'callback' => ['/user/sign-in/{id}/callback', ['GET', 'POST']],
            'metadata' => ['/user/sign-in/{id}/metadata', ['GET']],
        ];
    }

    /** @param list<string> $methods */
    #[DataProvider('routes')]
    public function testTheRoutesAreThereForAnyone(string $path, array $methods): void
    {
        $found = null;
        foreach (self::getWiki()->services->get(RouteProvider::class)->get() as $route) {
            if ($route->getPath() === $path) {
                $found = $route;
            }
        }

        $this->assertNotNull($found, $path);
        $this->assertSame($methods, $found->getMethods());
        $this->assertContains('public', (array)$found->getOption('acl'));
    }

    /** @return array<string, array{string}> */
    public static function languages(): array
    {
        return ['en' => ['en'], 'fr' => ['fr']];
    }

    #[DataProvider('languages')]
    public function testEveryReasonIsExplainedThreeWays(string $language): void
    {
        $strings = require "src/lang/yeswiki_{$language}.php";

        foreach (['SIGN_IN_REFUSED_TITLE', 'SIGN_IN_REFUSED_TODO', 'SIGN_IN_BACK', 'SIGN_IN_PROVIDER_UNNAMED'] as $key) {
            $this->assertNotEmpty($strings[$key] ?? null, $key);
        }
        foreach (FailureReason::cases() as $reason) {
            foreach (['REFUSED', 'ADMIN', 'FIX'] as $audience) {
                $key = 'SIGN_IN_' . $audience . '_' . strtoupper($reason->value);
                $this->assertNotEmpty($strings[$key] ?? null, $key);
            }
        }
    }
}
