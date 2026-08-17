<?php

namespace YesWiki\Test\Core;

use PHPUnit\Framework\TestCase;

require_once 'src/Kernel/lang.functions.php';

/**
 * Ticket 25 revision (tools/lang integrated into core, not deleted): the {{lang="xx"}} body-section filter shared by the show/iframe handlers and the {{include}} action.
 */
class LangFunctionsTest extends TestCase
{
    private const BODY = "intro\n{{lang=\"fr\"}}Bonjour{{lang=\"en\"}}Hello{{lang=\"eu\"}}Kaixo";

    public function testPreferredLanguageSectionWins()
    {
        $this->assertSame('Hello', filterBodyByLanguage(self::BODY, 'en', 'fr'));
        $this->assertSame('Bonjour', filterBodyByLanguage(self::BODY, 'fr', 'en'));
    }

    public function testFallsBackToDefaultLanguage()
    {
        $this->assertSame('Bonjour', filterBodyByLanguage(self::BODY, 'de', 'fr'));
    }

    public function testNoMarkersReturnsBodyUnchanged()
    {
        $this->assertSame('plain body', filterBodyByLanguage('plain body', 'fr', 'en'));
    }

    public function testNoMatchAtAllReturnsFullBody()
    {
        $this->assertSame(self::BODY, filterBodyByLanguage(self::BODY, 'de', 'it'));
    }

    public function testLastSectionWinsForDuplicateLanguage()
    {
        $body = '{{lang="fr"}}premier{{lang="fr"}}second';
        $this->assertSame('second', filterBodyByLanguage($body, 'fr', 'en'));
    }
}
