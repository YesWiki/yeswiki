<?php

namespace YesWiki\Content\Service;

use Symfony\Component\HttpFoundation\Request;
use YesWiki\Content\Entity\Translations;
use YesWiki\Kernel\Service\LanguageService;

/** The languages a feed writes a Content out in: the one `?lang=` asks for, or the source and every translation. */
class FeedLanguages
{
    public function __construct(private readonly LanguageService $languageService)
    {
    }

    /** The language `?lang=` asks for, or null when the feed is multilingual. */
    public function requested(Request $request): ?string
    {
        $asked = $request->query->get('lang');

        return is_string($asked) && in_array($asked, $this->languageService->availableLanguages(), true) ? $asked : null;
    }

    /**
     * Each language the feed shows $body in, with the body as a reader of that language sees it.
     *
     * @param array<string, mixed> $body the stored body, translations included
     *
     * @return array<string, array<string, mixed>> language => body
     */
    public function versions(array $body, string $sourceLanguage, ?string $requested): array
    {
        if ($requested !== null) {
            return [$requested => $requested === $sourceLanguage ? Translations::strip($body) : Translations::apply($body, $requested)];
        }

        $versions = [$sourceLanguage => Translations::strip($body)];
        foreach (Translations::languages($body) as $language) {
            if ($language !== $sourceLanguage) {
                $versions[$language] = Translations::apply($body, $language);
            }
        }

        return $versions;
    }

    /** $url opening the Content in $language, or $url itself for the source wording. */
    public function urlIn(string $url, string $language, string $sourceLanguage): string
    {
        if ($language === $sourceLanguage || $url === '') {
            return $url;
        }

        return $url . (str_contains($url, '?') ? '&' : '?') . 'lang=' . rawurlencode($language);
    }
}
