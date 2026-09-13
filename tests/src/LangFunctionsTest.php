<?php

namespace YesWiki\Test\Core;

use PHPUnit\Framework\TestCase;
use YesWiki\Kernel\Service\LanguageService;

require_once 'src/Kernel/Service/LanguageService.php';

/** What the translation catalogue does with a key. */
class LangFunctionsTest extends TestCase
{
    /** The service, serving this request in $language. */
    private static function inLanguage(string $language): LanguageService
    {
        $service = LanguageService::getInstance();
        $service->serveIn($language);

        return $service;
    }

    public function testPlaceholdersAreFilledInEitherSpelling(): void
    {
        $service = self::inLanguage('fr');
        $service->loadTranslations(['LANG_FUNCTIONS_TEST_KEY' => 'role {role} of %{field}, {role} again']);

        $this->assertSame('role start of bf_date, start again', $service->translate('LANG_FUNCTIONS_TEST_KEY', ['role' => 'start', 'field' => 'bf_date']));
    }

    public function testAnUnknownKeyIsReturnedAsItself(): void
    {
        $this->assertSame('NO_SUCH_KEY_HERE', self::inLanguage('fr')->translate('NO_SUCH_KEY_HERE'));
    }
}
