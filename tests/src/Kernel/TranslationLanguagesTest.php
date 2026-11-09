<?php

namespace YesWiki\Test\Kernel;

use YesWiki\Content\Service\TranslatableContent;
use YesWiki\Kernel\Service\LanguageService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Content is translated only into the languages `other_languages` turns on, and into none while it turns none on, whatever language it is written in. */
class TranslationLanguagesTest extends YesWikiTestCase
{
    /** @return array<string, array{mixed, list<string>}> */
    public static function configurations(): array
    {
        return [
            'nothing turned on' => [null, []],
            'an empty list' => [[], []],
            'one language' => [['en'], ['fr', 'en']],
            'a language with no catalogue is left out' => [['en', 'xx', 'fr'], ['fr', 'en']],
            'a single string' => ['en', ['fr', 'en']],
        ];
    }

    /** @param list<string> $expected */
    #[\PHPUnit\Framework\Attributes\DataProvider('configurations')]
    public function testOnlyTheLanguagesTurnedOnAreTranslationLanguages(mixed $others, array $expected): void
    {
        $wiki = $this->getWiki();
        $saved = $wiki->config['other_languages'] ?? null;
        $savedDefault = $wiki->config['default_language'] ?? null;
        $wiki->config['default_language'] = 'fr';
        if ($others === null) {
            unset($wiki->config['other_languages']);
        } else {
            $wiki->config['other_languages'] = $others;
        }

        try {
            $languages = $wiki->services->get(LanguageService::class);
            $languages->loadPreferredLanguage($wiki, '');
            $this->assertSame(array_values(array_diff($expected, ['fr'])), $wiki->services->get(TranslatableContent::class)->targetLanguages('fr'));
            $this->assertSame(array_values(array_diff($expected, ['en'])), $wiki->services->get(TranslatableContent::class)->targetLanguages('en'), 'Content written in another language is not translated into the default one either');
            $this->assertNotEmpty($languages->availableLanguages(), 'the languages a reader may switch the interface to are another matter');
        } finally {
            $wiki->config['default_language'] = $savedDefault;
            if ($saved === null) {
                unset($wiki->config['other_languages']);
            } else {
                $wiki->config['other_languages'] = $saved;
            }
            $wiki->services->get(LanguageService::class)->loadPreferredLanguage($wiki, '');
        }
    }
}
