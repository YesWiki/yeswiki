<?php

namespace YesWiki\Identity\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use YesWiki\Core\DashboardShell;
use YesWiki\Identity\SignIn\Contract\ProviderConfig;
use YesWiki\Identity\SignIn\SignInOutcome;
use YesWiki\Identity\SignIn\SignInPlan;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Render\Service\TemplateEngine;

/** The answer to the browser, for a controller that runs a sign-in. */
trait RendersSignInOutcome
{
    use DashboardShell;

    /** The fields every provider has, named on the admin screen. */
    private const NAMED_FIELDS = ['id', 'email', 'first_name', 'last_name', 'groups'];

    protected function renderSignInOutcome(Response|SignInOutcome $outcome): Response
    {
        if ($outcome instanceof Response) {
            return $outcome;
        }
        if ($outcome->kind === SignInOutcome::SIGNED_IN) {
            return new RedirectResponse($outcome->url);
        }
        if ($outcome->kind === SignInOutcome::TESTED && $outcome->config !== null && $outcome->plan !== null) {
            return $this->renderSignInTested($outcome, $outcome->config, $outcome->plan);
        }

        return $this->renderSignInRefused($outcome);
    }

    /** Only translated text, or the provider's message for the person: never what is meant for an administrator. Twig escapes all of it. */
    private function renderSignInRefused(SignInOutcome $outcome): Response
    {
        $templateEngine = $this->getService(TemplateEngine::class);

        return new Response($templateEngine->renderPage($templateEngine->render('@core/account/sign-in-refused.twig', [
            'message' => $outcome->messageForPerson(),
            'who' => $outcome->who,
            'time' => date('Y-m-d H:i'),
        ])), Response::HTTP_FORBIDDEN);
    }

    /**
     * What a test returned, for the administrator who ran it: every value comes from the provider, and Twig escapes all of it.
     * The error was scrubbed of the provider's secrets by the flow.
     */
    private function renderSignInTested(SignInOutcome $outcome, ProviderConfig $config, SignInPlan $plan): Response
    {
        $current = 'admin/sign-in';
        $this->getService(PageContext::class)->setTag($current);
        $templateEngine = $this->getService(TemplateEngine::class);

        return new Response($templateEngine->renderPage($templateEngine->render('@core/admin/sign-in-test.twig', $this->dashboardShell($current, [
            'id' => $config->id,
            'label' => $config->label,
            'form' => null,
            'result' => [
                'wouldDo' => $plan->refusal === null
                    ? _t('SIGN_IN_TEST_WOULD_' . strtoupper($plan->action->value), ['account' => (string)$plan->accountTag, 'provider' => $config->label])
                    : _t('SIGN_IN_TEST_WOULD_REFUSE', ['reason' => _t('SIGN_IN_ADMIN_' . strtoupper($plan->refusal->value))]),
                'refused' => $plan->refusal !== null,
                'steps' => $outcome->steps,
                'attributes' => $this->testedAttributes($plan),
                'missing' => $this->missingAttributes($plan),
                'profile' => $plan->profile,
                'groupsToAdd' => $plan->groupsToAdd,
                'groupsToRemove' => $plan->groupsToRemove,
                'groupsIgnored' => $plan->groupsIgnored,
                'warnings' => array_column($plan->warnings, 'message'),
                'error' => $outcome->error,
            ],
        ]))));
    }

    /** @return list<array{name: string, values: list<string>, usedAs: list<string>}> */
    private function testedAttributes(SignInPlan $plan): array
    {
        $rows = [];
        foreach ($plan->attributes as $name => $value) {
            $usedAs = [];
            foreach ($plan->attributeUse as $field => $use) {
                if ($use['present'] && $use['attribute'] === (string)$name) {
                    $usedAs[] = $this->fieldLabel((string)$field);
                }
            }
            $rows[] = [
                'name' => (string)$name,
                'values' => array_map(fn (mixed $one): string => is_string($one) ? $one : (string)json_encode($one, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), is_array($value) ? array_values($value) : [$value]),
                'usedAs' => $usedAs,
            ];
        }

        return $rows;
    }

    /** @return list<array{field: string, attribute: string}> what the wiki looked for and the provider did not send */
    private function missingAttributes(SignInPlan $plan): array
    {
        $missing = [];
        foreach ($plan->attributeUse as $field => $use) {
            if (!$use['present']) {
                $missing[] = ['field' => $this->fieldLabel((string)$field), 'attribute' => $use['attribute']];
            }
        }

        return $missing;
    }

    private function fieldLabel(string $field): string
    {
        return in_array($field, self::NAMED_FIELDS, true) ? _t('SIGN_IN_ADMIN_ATTRIBUTE_' . strtoupper($field)) : $field;
    }
}
