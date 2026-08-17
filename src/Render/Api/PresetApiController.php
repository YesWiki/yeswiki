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

/** What another wiki needs to know about this one's Presets (ADR-0021). */
class PresetApiController extends YesWikiController
{
    /** The Presets this wiki has, as a wiki copying one would list them. */
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

    /** The webfont files a Preset needs, absolute, so another wiki can fetch them. */
    #[Route('/api/presets/fonts', methods: ['GET'], options: ['acl' => ['public']])]
    public function presetFonts(Request $request): ApiResponse
    {
        $presets = $this->getService(PresetService::class);
        $wanted = (string)$request->query->get('preset', '');

        $baseUrl = rtrim((string)$this->getService(RuntimeConfig::class)['base_url'], '/?');

        $ids = $wanted !== ''
            ? [$wanted]
            : array_column($presets->all(), 'id');

        $fonts = [];
        foreach ($ids as $id) {
            foreach ($presets->fontsOf($id, $baseUrl) as $font) {
                $fonts[$font['url']] = $font;
            }
        }

        return new ApiResponse([
            'preset' => $wanted,
            'fonts' => array_values($fonts),
        ], Response::HTTP_OK);
    }

    /** `@font-face` rules for every webfont installed here, so a browser can draw them. */
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

    /** Download a webfont so a preset can name it, without the screen going anywhere. */
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

            'webfonts' => $presets->webfonts(),
        ], Response::HTTP_OK);
    }
}
