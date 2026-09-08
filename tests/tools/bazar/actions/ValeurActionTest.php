<?php

namespace YesWiki\Test\Bazar\Actions;

use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * {{valeur}} reads a remote entry: it must refuse an address inside the network
 * and must treat the field name as a name, not as part of its regex.
 */
class ValeurActionTest extends YesWikiTestCase
{
    private const REMOTE_URL = 'http://valeur-action-test.example.com';
    private const REMOTE_PAGE = '<div data-id="bf_ville"><span class="BAZ_label">Ville</span>'
        . '<span class="BAZ_texte">Bordeaux</span></div> <!-- /.BAZ_rubrique -->';

    private $wiki;
    private $previousExternalPage;

    protected function setUp(): void
    {
        $this->wiki = $this->getWiki();
        $GLOBALS['wiki'] = $this->wiki;
        $this->previousExternalPage = $GLOBALS['externalpage'] ?? null;
        // seeding the cache keeps the action away from the network
        $GLOBALS['externalpage'] = [self::REMOTE_URL => self::REMOTE_PAGE];
    }

    protected function tearDown(): void
    {
        if ($this->previousExternalPage === null) {
            unset($GLOBALS['externalpage']);
        } else {
            $GLOBALS['externalpage'] = $this->previousExternalPage;
        }
    }

    private function valeur(array $arguments): string
    {
        return $this->wiki->Action('valeur', 1, array_merge(['url' => self::REMOTE_URL], $arguments));
    }

    public function testItReadsTheNamedField()
    {
        $this->assertSame('Bordeaux', trim($this->valeur(['champ' => 'bf_ville'])));
    }

    public function testAFieldNameIsNotAPattern()
    {
        $output = $this->valeur(['champ' => 'bf_.*', 'defaut' => 'nothing']);

        $this->assertSame('nothing', trim($output));
    }

    public function testAFieldNameCannotBreakTheRegex()
    {
        $output = $this->valeur(['champ' => 'bf_ville/', 'defaut' => 'nothing']);

        $this->assertSame('nothing', trim($output));
        $this->assertSame(PREG_NO_ERROR, preg_last_error(), preg_last_error_msg());
    }

    public function testAnAddressInsideTheNetworkIsRefused()
    {
        unset($GLOBALS['externalpage']);

        foreach (['http://127.0.0.1:9999', 'http://localhost:9999', 'http://192.168.1.110:9999', 'http://[::1]:9999'] as $url) {
            $output = $this->wiki->Action('valeur', 1, ['url' => $url, 'champ' => 'bf_titre']);

            $this->assertStringContainsString('alert-danger', $output, "$url was not refused");
        }
    }

    public function testANonHttpSchemeIsRefused()
    {
        unset($GLOBALS['externalpage']);

        $output = $this->wiki->Action('valeur', 1, ['url' => 'file:///etc/passwd', 'champ' => 'bf_titre']);

        $this->assertStringContainsString('alert-danger', $output);
    }
}
