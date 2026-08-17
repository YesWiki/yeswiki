<?php

namespace YesWiki\Test\Kernel;

use YesWiki\Kernel\Service\LanguageService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Which language a visitor is answered in. */
class LanguageResolutionTest extends YesWikiTestCase
{
    /** What a wiki in French that also offers English and Spanish would hold. */
    private const OFFERED = ['en', 'es', 'fr'];

    private function service(): LanguageService
    {
        return LanguageService::getInstance();
    }

    /** A wiki, as detectPreferredLanguage reads one: an object with a `config`. */
    private function wiki(string $default = 'fr'): object
    {
        return (object)['config' => ['default_language' => $default]];
    }

    protected function setUp(): void
    {
        parent::setUp();
        unset($_GET['lang'], $_COOKIE[LanguageService::COOKIE], $_POST['config']);
    }

    protected function tearDown(): void
    {
        unset($_GET['lang'], $_COOKIE[LanguageService::COOKIE], $_POST['config']);
        parent::tearDown();
    }

    public function testAVisitorWhoseBrowserAsksForAnOfferedLanguageGetsIt(): void
    {
        $this->assertSame(
            'en',
            $this->service()->detectPreferredLanguage($this->wiki('fr'), self::OFFERED, 'en-GB,en;q=0.9,fr;q=0.5'),
            'a first visit is answered in the reader\'s own language when the wiki has it'
        );
    }

    public function testAVisitorWhoseBrowserAsksForSomethingElseGetsTheWikisOwnLanguage(): void
    {
        $this->assertSame(
            'fr',
            $this->service()->detectPreferredLanguage($this->wiki('fr'), self::OFFERED, 'de-DE,de;q=0.9'),
            'the default is the answer for the visitor whose language is not on offer'
        );
    }

    /** A language the wiki does not offer is not an answer, even when it is installed. */
    public function testANonOfferedLanguageIsNeverNegotiated(): void
    {
        $this->assertSame(
            'fr',
            $this->service()->detectPreferredLanguage($this->wiki('fr'), ['fr'], 'en-GB,en;q=0.9'),
            'a wiki in one language answers in it'
        );
    }

    public function testTheCookieBeatsTheBrowser(): void
    {
        $_COOKIE[LanguageService::COOKIE] = 'es';

        $this->assertSame(
            'es',
            $this->service()->detectPreferredLanguage($this->wiki('fr'), self::OFFERED, 'en-GB,en;q=0.9'),
            'a choice made once holds afterwards'
        );
    }

    public function testTheUrlBeatsTheCookie(): void
    {
        $_COOKIE[LanguageService::COOKIE] = 'es';
        $_GET['lang'] = 'en';

        $this->assertSame(
            'en',
            $this->service()->detectPreferredLanguage($this->wiki('fr'), self::OFFERED, 'fr-FR'),
            'the switcher is a link, and clicking it is the most recent thing the reader did'
        );
    }

    /** A cookie naming something the wiki does not offer is not a choice, it is stale. */
    public function testACookieForALanguageTheWikiDoesNotOfferIsIgnored(): void
    {
        $_COOKIE[LanguageService::COOKIE] = 'ro';

        $this->assertSame(
            'en',
            $this->service()->detectPreferredLanguage($this->wiki('fr'), self::OFFERED, 'en-GB,en;q=0.9'),
            'the browser decides again, rather than the wiki answering in a language it withdrew'
        );
    }

    /**
     * `?lang=` for a language the wiki does not offer is refused the same way -- which is what stops a link from putting a wiki into a language its webmaster took off the list.
     */
    public function testAnUnofferedLanguageInTheUrlIsRefused(): void
    {
        $_GET['lang'] = 'ro';

        $this->assertSame(
            'fr',
            $this->service()->detectPreferredLanguage($this->wiki('fr'), self::OFFERED, 'de-DE')
        );
    }

    /** A configuration naming no language at all still has to answer something. */
    public function testAConfigurationNamingNoLanguageFallsBackRatherThanFailing(): void
    {
        $service = $this->service();

        $this->assertSame('en', $service->detectPreferredLanguage($this->wiki('auto'), self::OFFERED, 'en-GB,en;q=0.9'));
        $this->assertSame('fr', $service->detectPreferredLanguage($this->wiki('auto'), self::OFFERED, 'de-DE'));
    }

    /** A page declaring what it is written in, which a reader's own choice still beats. */
    public function testAReadersChoiceBeatsThePagesOwnLanguage(): void
    {
        $_COOKIE[LanguageService::COOKIE] = 'es';

        $this->assertSame(
            'es',
            $this->service()->detectPreferredLanguage($this->wiki('fr'), self::OFFERED, 'fr-FR'),
            'the page metadata path is below the cookie'
        );
    }

    /** What a wiki offers: its own language plus the ones it turned on, and nothing else. */
    public function testOfferedLanguagesAreTheWikisOwnPlusTheOnesItTurnedOn(): void
    {
        $installed = ['ca', 'en', 'es', 'fr', 'nl', 'pt', 'ro'];
        $service = $this->service();

        $wiki = new \YesWiki\YesWikiRuntime();
        $wiki->config = ['default_language' => 'fr', 'other_languages' => ['en', 'es']];
        $this->assertSame(['fr', 'en', 'es'], $service->offeredLanguages($wiki, $installed));

        $wiki->config = ['default_language' => 'fr', 'other_languages' => []];
        $this->assertSame(['fr'], $service->offeredLanguages($wiki, $installed), 'one language, so no switcher');

        $wiki->config = ['default_language' => 'fr', 'other_languages' => ['en', 'zz']];
        $this->assertSame(['fr', 'en'], $service->offeredLanguages($wiki, $installed), 'a language nobody has is not on offer');
    }
}
