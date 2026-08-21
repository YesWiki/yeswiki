<?php

namespace YesWiki\Render\Service;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Kernel\Service\RequestScopedState;
use YesWiki\Kernel\Service\RuntimeConfig;

/** Whether this request is an htmx navigation, and what the server owes it (ticket 16). */
class BoostedNavigation implements RequestScopedState
{
    /** The header the client echoes back so the server can compare skeletons. */
    public const FINGERPRINT_HEADER = 'HX-YesWiki-Layout';

    /** Set by ThemeManager::renderPage(); read when deciding whether the response is swappable. */
    private bool $renderedAPage = false;

    public function __construct(
        private readonly CurrentRequest $currentRequest,
        private readonly RuntimeConfig $config,
        private readonly ThemeManager $themeManager,
    ) {
    }

    /** Whether internal links load through htmx at all. */
    public function isEnabled(): bool
    {
        $value = $this->config->getValue('htmx_navigation', true);

        return $value === null ? true : (bool)$value;
    }

    /** Is the request in flight an htmx navigation we should answer with a fragment? */
    public function isBoosted(): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }
        $request = $this->request();

        return $request !== null
            && $request->headers->get('HX-Request') === 'true'
            && $request->headers->get('HX-Boosted') === 'true';
    }

    /** Called by ThemeManager::renderPage() -- the only thing that produces a swappable body. */
    public function markPageRendered(): void
    {
        $this->renderedAPage = true;
    }

    public function hasRenderedAPage(): bool
    {
        return $this->renderedAPage;
    }

    /** A hash of everything outside the `body` block that a page can change. */
    public function fingerprint(): string
    {
        return substr(hash('xxh128', implode('|', $this->themeManager->layoutIdentity())), 0, 16);
    }

    /** Does the client's skeleton match the one this page would be rendered into? */
    public function fingerprintMatches(): bool
    {
        $request = $this->request();
        $sent = $request?->headers->get(self::FINGERPRINT_HEADER);

        return $sent !== null && $sent === $this->fingerprint();
    }

    /** Tell htmx to abandon the swap and navigate for real. */
    public function fullLoadResponse(?string $url = null): Response
    {
        $url ??= $this->request()?->getRequestUri() ?? '/';

        return new Response('', Response::HTTP_NO_CONTENT, ['HX-Redirect' => $url]);
    }

    private function request(): ?Request
    {
        try {
            return $this->currentRequest->get();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * "This response went through renderPage()" is true of one request, not of a process.
     *
     * Left set, every later response in a worker would claim to be swappable, including the ones
     * that must force a full load (ADR-0024).
     */
    public function startNewRequest(): void
    {
        $this->renderedAPage = false;
    }
}
