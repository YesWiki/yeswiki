<?php

namespace YesWiki\Identity\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use YesWiki\Core\DashboardShell;
use YesWiki\Identity\SignIn\AttributeRows;
use YesWiki\Identity\SignIn\Contract\FailureReason;
use YesWiki\Identity\SignIn\Contract\ProviderConfig;
use YesWiki\Identity\SignIn\SignInOutcome;
use YesWiki\Identity\SignIn\SignInPlan;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Render\Service\TemplateEngine;

/** The answer to the browser, for a controller that runs a sign-in. */
trait RendersSignInOutcome
{
    use DashboardShell;

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
                    : _t('SIGN_IN_TEST_WOULD_REFUSE', ['reason' => $this->refusalReason($plan, $plan->refusal)]),
                'refused' => $plan->refusal !== null,
                'steps' => $outcome->steps,
                'attributes' => AttributeRows::of($plan->attributes, $plan->attributeUse),
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

    /** Why a real sign-in would be refused: a user ID or email that came with a value it cannot use is said to have come. */
    private function refusalReason(SignInPlan $plan, FailureReason $refusal): string
    {
        if ($refusal === FailureReason::MissingAttribute) {
            foreach (['id', 'email'] as $field) {
                $use = $plan->attributeUse[$field] ?? null;
                if ($use !== null && !$use['present']) {
                    return isset($use['unusable']) ? $this->unusableLine($field, $use['attribute'], $use['unusable']) : _t('SIGN_IN_ADMIN_MISSING_ATTRIBUTE');
                }
            }
        }

        return _t('SIGN_IN_ADMIN_' . strtoupper($refusal->value));
    }

    /** @return list<string> each attribute the wiki looked for and could not use: not sent, or sent with a value it cannot use */
    private function missingAttributes(SignInPlan $plan): array
    {
        $missing = [];
        foreach ($plan->attributeUse as $field => $use) {
            if (!$use['present']) {
                $missing[] = isset($use['unusable'])
                    ? $this->unusableLine((string)$field, $use['attribute'], $use['unusable'])
                    : _t('SIGN_IN_TEST_NOT_SENT', ['field' => AttributeRows::fieldLabel((string)$field), 'attribute' => $use['attribute']]);
            }
        }

        return $missing;
    }

    /** @param 'binary'|'not_text'|'not_one_address' $why */
    private function unusableLine(string $field, string $attribute, string $why): string
    {
        return _t('SIGN_IN_TEST_UNUSABLE_' . strtoupper($why), ['field' => AttributeRows::fieldLabel($field), 'attribute' => $attribute]);
    }
}
