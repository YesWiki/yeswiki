<?php

namespace YesWiki\Render\Service;

use YesWiki\Kernel\Service\LanguageService;
use YesWiki\Kernel\Service\RequestScopedState;
use YesWiki\Kernel\Service\UrlFormatter;

/** The languages a reader may switch to, asked once and drawn the same way wherever the switch appears. */
class LanguageSwitch implements RequestScopedState
{
    /** @var list<array{code: string, source: bool, current: bool, state: string}>|null */
    private ?array $writing = null;

    public function __construct(
        private readonly LanguageService $languageService,
        private readonly UrlFormatter $urlFormatter,
    ) {
    }

    public function startNewRequest(): void
    {
        $this->writing = null;
    }

    /**
     * This screen writes a Content rather than reading one, so the switch changes which
     * translation is being written instead of which language the reader is served in. The
     * two are the same act here: `editlang` decides both (LanguageService).
     *
     * @param list<array{code: string, source: bool, current: bool, state: string}> $languages
     */
    public function writing(array $languages): void
    {
        $this->writing = $languages;
    }

    /** Whether the switch is currently offering translations to write. */
    public function isWriting(): bool
    {
        return $this->writing !== null;
    }

    /** Whether this wiki offers a reader anything to switch between. */
    public function isOffered(): bool
    {
        return count($this->readingOptions()) > 1;
    }

    /**
     * One row per language: what to call it, where it leads, and whether it is the one being served.
     *
     * @return list<array{code: string, label: string, href: string, current: bool, state?: string, source?: bool}>
     */
    public function options(): array
    {
        return $this->writing === null ? $this->readingOptions() : $this->writingOptions();
    }

    /**
     * The languages this wiki is read in, which is what an author's own `{{languages}}` offers whatever screen it sits on.
     *
     * @return list<array{code: string, label: string, href: string, current: bool}>
     */
    public function readingOptions(): array
    {
        $current = $this->languageService->preferredLanguage();

        $options = [];
        foreach ($this->languageService->availableLanguages() as $code) {
            $code = (string)$code;
            $options[] = [
                'code' => $code,
                'label' => $this->nameOf($code),
                'href' => $this->urlFormatter->currentWith(['lang' => $code]),
                'current' => $code === $current,
            ];
        }

        return $options;
    }

    /**
     * The source language first, then every language it may be translated into, each saying how far its translation has got.
     *
     * @return list<array{code: string, label: string, href: string, current: bool, state: string, source: bool}>
     */
    private function writingOptions(): array
    {
        $options = [];
        foreach ($this->writing ?? [] as $language) {
            $code = (string)$language['code'];
            $options[] = [
                'code' => $code,
                'label' => $this->nameOf($code),
                'href' => $this->urlFormatter->currentWith(['editlang' => $code]),
                'current' => (bool)$language['current'],
                'state' => (string)$language['state'],
                'source' => (bool)$language['source'],
            ];
        }

        return $options;
    }

    private function nameOf(string $code): string
    {
        $names = $this->languageService->languagesList();

        return (string)($names[$code]['nativeName'] ?? $code);
    }
}
