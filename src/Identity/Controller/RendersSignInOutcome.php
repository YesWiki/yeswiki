<?php

namespace YesWiki\Identity\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use YesWiki\Identity\SignIn\Contract\FailureReason;
use YesWiki\Identity\SignIn\SignInOutcome;
use YesWiki\Render\Service\TemplateEngine;

/** The answer to the browser, for a controller that runs a sign-in. */
trait RendersSignInOutcome
{
    protected function renderSignInOutcome(Response|SignInOutcome $outcome): Response
    {
        if ($outcome instanceof Response) {
            return $outcome;
        }
        if ($outcome->kind === SignInOutcome::SIGNED_IN) {
            return new RedirectResponse($outcome->url);
        }

        return $this->renderSignInRefused($outcome);
    }

    /** Only translated text, or the provider's message for the person: never what is meant for an administrator. Twig escapes all of it. */
    private function renderSignInRefused(SignInOutcome $outcome): Response
    {
        $templateEngine = $this->getService(TemplateEngine::class);
        $reason = $outcome->reason ?? FailureReason::InternalError;
        $message = $outcome->message !== '' ? $outcome->message : _t('SIGN_IN_REFUSED_' . strtoupper($reason->value), [
            'provider' => $outcome->config !== null ? $outcome->config->label : _t('SIGN_IN_PROVIDER_UNNAMED'),
            'who' => $outcome->who,
        ]);

        return new Response($templateEngine->renderPage($templateEngine->render('@core/account/sign-in-refused.twig', [
            'message' => $message,
            'who' => $outcome->who,
            'time' => date('Y-m-d H:i'),
        ])), Response::HTTP_FORBIDDEN);
    }
}
