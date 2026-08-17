<?php

namespace YesWiki\Render\Api;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use YesWiki\Core\ApiResponse;
use YesWiki\Core\YesWikiController;
use YesWiki\Identity\Service\CsrfTokenChecker;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Render\Service\PresetService;

/**
 * What another wiki needs to know about this one's Presets (ADR-0021).
 *
 * The point of these two routes is copying a look between instances. A Preset is a stylesheet
 * plus, usually, a webfont -- and the stylesheet alone is useless without the font files it
 * names. `/fonts` answers with everything needed to fetch them: family, style, weight, the
 * subset's `unicode-range`, and an absolute URL per file.
 *
 * **Read from the preset file itself, never from a store of its own.** The `@font-face` blocks
 * a preset carries were written when the font was fetched and already say all of that, so
 * describing a preset's fonts is reading it. Nothing to keep in step, and nothing that can
 * disagree with what the wiki actually renders with.
 *
 * `public`, like the presets themselves: a Preset is a stylesheet linked in the head of every
 * page and its fonts are served statically from `custom/fonts/`. Both are already readable by
 * anyone who loads the site, so an ACL here would guard nothing while making the wiki useless
 * as a source to copy from.
 */
class PresetApiController extends YesWikiController
{
    /**
     * The Presets this wiki has, as a wiki copying one would list them.
     *
     * Deliberately not the token values: a preset is a CSS file, and whoever wants its colours
     * can fetch that file from `href`. What is not fetchable is which files exist and what
     * they are called, which is what this answers.
     */
    #[Route('/api/presets', methods: ['GET'], options: ['acl' => ['public']])]
    public function listPresets(): ApiResponse
    {
        $presets = $this->getService(PresetService::class);

        return new ApiResponse(array_map(
            static fn (array $preset): array => [
                'id' => $preset['id'],
                'name' => $preset['name'],
                'custom' => $preset['custom'],
                'default' => $preset['default'],
                'complete' => $preset['complete'],
                'href' => $preset['href'],
            ],
            $presets->all()
        ), Response::HTTP_OK);
    }

    /**
     * The webfont files a Preset needs, absolute, so another wiki can fetch them.
     *
     * `?preset=` is optional. Without it, every Preset's fonts are answered at once,
     * deduplicated -- which is the useful question when you are setting up a new wiki and
     * want the one you already have to hand its fonts over.
     *
     * The preset is a query parameter rather than a path segment because its id contains a
     * slash (`custom/red.css`), and a route pattern that accepts one accepts the rest of the
     * path with it.
     */
    #[Route('/api/presets/fonts', methods: ['GET'], options: ['acl' => ['public']])]
    public function presetFonts(Request $request): ApiResponse
    {
        $presets = $this->getService(PresetService::class);
        $wanted = (string)$request->query->get('preset', '');
        // `base_url` is stored as `https://host/?` -- the trailing `/?` is how the wiki
        // addresses its own pages, and a font file is a plain path under the same host
        $baseUrl = rtrim((string)$this->getService(RuntimeConfig::class)['base_url'], '/?');

        $ids = $wanted !== ''
            ? [$wanted]
            : array_column($presets->all(), 'id');

        $fonts = [];
        foreach ($ids as $id) {
            foreach ($presets->fontsOf($id, $baseUrl) as $font) {
                // the URL identifies the file: two presets using the same family name the
                // same weights are the same files, and answering twice would have the wiki
                // asking download each of them twice
                $fonts[$font['url']] = $font;
            }
        }

        return new ApiResponse([
            'preset' => $wanted,
            'fonts' => array_values($fonts),
        ], Response::HTTP_OK);
    }

    /**
     * `@font-face` rules for every webfont installed here, so a browser can draw them.
     *
     * The admin preset screen links this. Without it, choosing a downloaded family in the
     * rail changed `font-family` on the document and nothing else happened: the rules that
     * declare a family live inside a *preset*, written when it is saved, so a font could be
     * fully installed and still be a name no browser had ever heard of. The preview was
     * therefore silent exactly where it mattered most -- on the choice you cannot judge from
     * a list of names.
     *
     * A route rather than an inline `<style>` because the URLs have to be absolute (a preset
     * stores them relative to itself) and because JS can re-fetch it after a download, which
     * is what lets a font install without the screen reloading.
     *
     * `public`, like the font files themselves: they are served statically from
     * `custom/fonts/` to every reader already.
     */
    #[Route('/api/presets/fonts.css', methods: ['GET'], options: ['acl' => ['public']])]
    public function fontFaces(): Response
    {
        $presets = $this->getService(PresetService::class);
        $baseUrl = rtrim((string)$this->getService(RuntimeConfig::class)['base_url'], '/?');

        return new Response(
            $presets->installedFontFaces($baseUrl),
            Response::HTTP_OK,
            ['Content-Type' => 'text/css; charset=utf-8']
        );
    }

    /**
     * Download a webfont so a preset can name it, without the screen going anywhere.
     *
     * **The point is what does *not* happen.** This used to be a form POST that redirected
     * back to `/admin/preset`, which threw away every unsaved edit in the rail and dropped
     * the drawer back to the list -- so adding a font in the middle of designing a preset
     * cost the preset. Downloading a file is not a reason to lose an hour's work, and it is
     * not a change to the preset being edited either: it adds a family to what the wiki has.
     *
     * Answers with the families that landed and the ones that did not, because a batch is
     * normal here (a body face and a heading face are chosen together) and one bad name must
     * not take the others down with it.
     */
    #[Route('/api/presets/fonts', methods: ['POST'], options: ['acl' => ['@admins']])]
    public function installFonts(Request $request): ApiResponse
    {
        $this->denyAccessUnlessAdmin();
        $this->getService(CsrfTokenChecker::class)->checkToken('main', 'POST', 'csrf-token', false);

        $presets = $this->getService(PresetService::class);
        $wiki = trim((string)$request->request->get('font_source', ''));
        $family = trim((string)$request->request->get('font_family', ''));

        try {
            $result = $wiki !== ''
                ? ['installed' => $presets->installFontsFromWiki($wiki, $family), 'failed' => []]
                : $presets->installFonts($family);
        } catch (\Throwable $failed) {
            return new ApiResponse(['error' => $failed->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return new ApiResponse([
            'installed' => $result['installed'],
            'failed' => $result['failed'],
            // the select's whole webfont group, rebuilt: the screen redraws it from this
            // rather than guessing what an installed family is called and what stack it
            // becomes -- the service already answers that question for the initial render
            'webfonts' => $presets->webfonts(),
        ], Response::HTTP_OK);
    }
}
