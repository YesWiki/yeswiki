<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use YesWiki\Content\Field\CalcField;
use YesWiki\Content\Field\TextField;
use YesWiki\Kernel\Service\LanguageService;
use YesWiki\Kernel\Service\NumberDisplay;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** End-users 24: a number is written in the page's language with the decimals its field asks for, the same with or without ext-intl. */
#[CoversClass(NumberDisplay::class)]
#[CoversClass(TextField::class)]
#[CoversClass(CalcField::class)]
class LocalNumbersTest extends YesWikiTestCase
{
    private string $languageWas = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->languageWas = self::getWiki()->services->get(LanguageService::class)->preferredLanguage();
    }

    protected function tearDown(): void
    {
        self::getWiki()->services->get(LanguageService::class)->serveIn($this->languageWas);
        parent::tearDown();
    }

    /** @return array<string, array{string, ?int, string, string}> */
    public static function numbers(): array
    {
        return [
            'two decimals in French' => ['1234.5', 2, 'fr', "1\u{202F}234,50"],
            'two decimals in English' => ['1234.5', 2, 'en', '1,234.50'],
            'an integer with no setting' => ['12', null, 'fr', '12'],
            'zeros a field did not ask for' => ['12.00', null, 'en', '12'],
            'at most six decimals' => ['3.3333333333333', null, 'fr', '3,333333'],
            'a negative number' => ['-1234.75', 1, 'en', '-1,234.8'],
            'stored with a comma' => ['2,5', null, 'en', '2.5'],
            'no decimals asked' => ['2.6', 0, 'fr', '3'],
            'not a number' => ['beaucoup', 2, 'fr', 'beaucoup'],
        ];
    }

    #[DataProvider('numbers')]
    public function testANumberIsWrittenInThePagesLanguage(string $raw, ?int $decimals, string $language, string $expected): void
    {
        $this->assertSame($expected, (new NumberDisplay())->format($raw, $decimals, $language));
    }

    public function testTheInputStepFollowsTheDecimals(): void
    {
        $this->assertSame('0.01', NumberDisplay::step(2));
        $this->assertSame('1', NumberDisplay::step(0));
        $this->assertSame('any', NumberDisplay::step(null));
        $this->assertNull(NumberDisplay::decimalsFrom(''));
        $this->assertSame(2, NumberDisplay::decimalsFrom('2'));
    }

    public function testANumberFieldShowsItsValueInTheReadersLanguage(): void
    {
        $services = self::getWiki()->services;
        $services->get(LanguageService::class)->serveIn('fr');
        $field = new TextField($this->definition(['texte', 'bf_montant', 'Montant', 7 => 'number', 13 => '2']), $services);

        $this->assertStringContainsString("1\u{202F}234,50", (string)$field->renderStaticIfPermitted(['bf_montant' => '1234.5']));
        $this->assertSame('0.01', $field->getStep());
        $this->assertStringContainsString('step="0.01"', (string)$field->renderInputIfPermitted([]));
    }

    public function testACalculatedFieldIsStoredShortAndShownWithItsDecimals(): void
    {
        $services = self::getWiki()->services;
        $services->get(LanguageService::class)->serveIn('fr');
        $field = new CalcField($this->definition(['calc', 'bf_tiers', 'Tiers', 4 => '{value}', 5 => '10/3', 13 => '2']), $services);

        $stored = $field->formatValuesBeforeSave([])['bf_tiers'];

        $this->assertSame('3.333333333', $stored, 'ten significant digits, not the float\'s fourteen');
        $this->assertStringContainsString('3,33', (string)$field->renderStaticIfPermitted(['bf_tiers' => $stored]));
    }

    /**
     * @param array<int, string> $values
     *
     * @return array<int, string>
     */
    private function definition(array $values): array
    {
        return array_replace(array_fill(0, 16, ''), $values);
    }
}
