<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use YesWiki\Content\Field\DateField;
use YesWiki\Kernel\Service\DateService;
use YesWiki\Kernel\Service\LanguageService;
use YesWiki\Render\Service\TemplateEngine;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** End-users 23: a date is written in the page's language, the same with or without ext-intl. */
#[CoversClass(DateService::class)]
class LocalDatesTest extends YesWikiTestCase
{
    private string $languageWas = '';
    private string $timezoneWas = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->languageWas = self::getWiki()->services->get(LanguageService::class)->preferredLanguage();
        $this->timezoneWas = date_default_timezone_get();
        date_default_timezone_set('Europe/Paris');
    }

    protected function tearDown(): void
    {
        self::getWiki()->services->get(LanguageService::class)->serveIn($this->languageWas);
        date_default_timezone_set($this->timezoneWas);
        parent::tearDown();
    }

    /** @return array<string, array{string, string, string, string}> */
    public static function dates(): array
    {
        return [
            'a day in French' => ['fr', '2026-02-01', DateService::DATE, '1 février 2026'],
            'a time in French' => ['fr', '2026-02-01 14:30', DateService::DATETIME, '1 février 2026 14:30'],
            'a time in English' => ['en', '2026-02-01 14:30', DateService::DATETIME, 'February 1, 2026 2:30 PM'],
            'what a date field stores' => ['fr', '2026-02-01T14:30:00+01:00', DateService::DATETIME, '1 février 2026 14:30'],
            'short in French' => ['fr', '2026-02-01 14:30:00', DateService::SHORT, '01/02/2026 14:30'],
            'day and month' => ['en', '2026-02-01 14:30', DateService::DAY_MONTH_TIME, '1 February 2:30 PM'],
        ];
    }

    #[DataProvider('dates')]
    public function testADateIsWrittenInThePagesLanguage(string $language, string $stored, string $style, string $expected): void
    {
        self::getWiki()->services->get(LanguageService::class)->serveIn($language);

        $this->assertSame($expected, self::getWiki()->services->get(DateService::class)->display($stored, $style));
    }

    public function testATimestampAndAnUnreadableValue(): void
    {
        $dates = self::getWiki()->services->get(DateService::class);
        self::getWiki()->services->get(LanguageService::class)->serveIn('fr');

        $this->assertSame('1 février 2026', $dates->display((new \DateTimeImmutable('2026-02-01 10:00'))->getTimestamp()));
        $this->assertSame('', $dates->display('pas une date'), 'no 1970');
        $this->assertSame('', $dates->display('0000-00-00 00:00:00'));
        $this->assertSame('', $dates->display(null));
    }

    public function testTheTwigFilterAndTheDateField(): void
    {
        $services = self::getWiki()->services;
        $services->get(LanguageService::class)->serveIn('en');

        $this->assertSame('February 1, 2026', $services->get(TemplateEngine::class)->renderFromString("{{ '2026-02-01'|localdate }}"));
        $field = new DateField(array_replace(array_fill(0, 16, ''), ['jour', 'bf_quand', 'Quand']), $services);
        $this->assertStringContainsString('February 1, 2026 2:30 PM', (string)$field->renderStaticIfPermitted(['id_typeannonce' => '1', 'bf_quand' => '2026-02-01T14:30:00+01:00']));
    }
}
