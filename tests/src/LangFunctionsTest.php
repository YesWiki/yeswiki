<?php

namespace YesWiki\Test\Core;

use PHPUnit\Framework\TestCase;
use YesWiki\Kernel\Service\LanguageService;

require_once 'src/Kernel/Service/LanguageService.php';

/**
 * Ticket 25 revision (tools/lang integrated into core, not deleted): the {{lang="xx"}} body-section filter shared by the show/iframe handlers and the {{include}} action.
 */
class LangFunctionsTest extends TestCase
{
    private const BODY = "intro\n{{lang=\"fr\"}}Bonjour{{lang=\"en\"}}Hello{{lang=\"eu\"}}Kaixo";

    /** The service, serving this request in $language, which is what the old second argument said. */
    private static function inLanguage(string $language): LanguageService
    {
        $service = LanguageService::getInstance();
        $service->serveIn($language);

        return $service;
    }

    public function testPreferredLanguageSectionWins(): void
    {
        $this->assertSame('Hello', self::inLanguage('en')->sectionFor(self::BODY, 'fr'));
        $this->assertSame('Bonjour', self::inLanguage('fr')->sectionFor(self::BODY, 'en'));
    }

    public function testFallsBackToDefaultLanguage(): void
    {
        $this->assertSame('Bonjour', self::inLanguage('de')->sectionFor(self::BODY, 'fr'));
    }

    public function testNoMarkersReturnsBodyUnchanged(): void
    {
        $this->assertSame('plain body', self::inLanguage('fr')->sectionFor('plain body', 'en'));
    }

    public function testNoMatchAtAllReturnsFullBody(): void
    {
        $this->assertSame(self::BODY, self::inLanguage('de')->sectionFor(self::BODY, 'it'));
    }

    public function testLastSectionWinsForDuplicateLanguage(): void
    {
        $body = '{{lang="fr"}}premier{{lang="fr"}}second';
        $this->assertSame('second', self::inLanguage('fr')->sectionFor($body, 'en'));
    }

    public function testPlaceholdersAreFilledInEitherSpelling(): void
    {
        $service = self::inLanguage('fr');
        $service->loadTranslations(['LANG_FUNCTIONS_TEST_KEY' => 'role {role} of %{field}, {role} again']);

        $this->assertSame('role start of bf_date, start again', $service->translate('LANG_FUNCTIONS_TEST_KEY', ['role' => 'start', 'field' => 'bf_date']));
    }
}
