<?php

namespace YesWiki\Render\Service;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Kernel\Service\RuntimeConfig;

/**
 * Whether this request is an htmx navigation, and what the server owes it (ticket 16).
 *
 * A boosted navigation is a *fragment*: the server skips the skeleton's `head` block and
 * returns its `body` block, which htmx swaps into `<body>`. No second rendering path -- it is
 * `ThemeManager::renderPage()` minus one block.
 *
 * Two rules live here because both are about the response as a whole rather than about any
 * one handler:
 *
 *  - **Only a page render may be swapped.** Anything else -- a `/raw` handler, a redirect, an
 *    error, a bare-document `terminate()` -- answers `HX-Redirect` and the browser does a real
 *    navigation. Defined positively by what the response *is*, so an extension adding a
 *    handler cannot silently produce something unswappable.
 *  - **A page built from a different skeleton may not be swapped either.** Per-page metadata
 *    can change theme, squelette, style, preset, background image and lang, so two pages can
 *    have different chrome and a different `<html lang>`. The client sends the fingerprint of
 *    what it is currently showing; a mismatch answers `HX-Redirect` *before* the page is
 *    rendered.
 */
class BoostedNavigation
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

    /**
     * Whether internal links load through htmx at all.
     *
     * Not a second code path: the non-boosted path is plain HTTP, which already answers every
     * request without `HX-Request` -- search engines, `curl`, a browser with JS disabled. The
     * flag only decides whether the skeleton emits `hx-boost`, so an admin whose theme does not
     * meet the contract has a way out that is not a patch.
     */
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

    /**
     * A hash of everything outside the `body` block that a page can change.
     *
     * Hashed rather than sent raw so a theme gaining a seventh per-page knob does not widen
     * the header contract -- only this method has to know what is in it.
     */
    public function fingerprint(): string
    {
        return substr(hash('xxh128', implode('|', $this->themeManager->layoutIdentity())), 0, 16);
    }

    /** Does the client's skeleton match the one this page would be rendered into? */
    public function fingerprintMatches(): bool
    {
        $request = $this->request();
        $sent = $request?->headers->get(self::FINGERPRINT_HEADER);

        // a boosted request that sends nothing is treated as a mismatch: a full load is always
        // a correct answer, whereas swapping into an unknown skeleton is not
        return $sent !== null && $sent === $this->fingerprint();
    }

    /**
     * Tell htmx to abandon the swap and navigate for real.
     *
     * 204 rather than 200: there is no body worth sending, and htmx acts on the header. The
     * URL is the one the user asked for, so the address bar ends up correct -- which is the
     * thing a transparently-followed XHR redirect gets wrong.
     */
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
            // no request in flight (CLI, tests booting the wiki directly)
            return null;
        }
    }
}
