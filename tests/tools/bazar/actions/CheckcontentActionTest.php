<?php

namespace YesWiki\Test\Bazar\Actions;

use Symfony\Component\HttpFoundation\Request;
use YesWiki\Core\Controller\AuthController;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * {{checkcontent}} repairs nothing on a POST that PHP truncated past max_input_vars.
 */
class CheckcontentActionTest extends YesWikiTestCase
{
    private $wiki;
    private $previousRequest;

    protected function setUp(): void
    {
        $this->wiki = $this->getWiki();

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        if (empty($this->wiki->services->get(AuthController::class)->connectFirstAdmin())) {
            $this->markTestSkipped('no admin account in the test wiki');
        }

        $this->previousRequest = $this->wiki->request;
    }

    protected function tearDown(): void
    {
        $this->wiki->request = $this->previousRequest;
        $this->wiki->services->get(AuthController::class)->logout();
    }

    private function tooManyMessage(): string
    {
        return htmlspecialchars(
            html_entity_decode(_t('BAZ_CHECKCONTENT_TOO_MANY_SELECTED')),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }

    private function runWithPost(array $post): string
    {
        $this->wiki->request = Request::create($this->wiki->config['base_url'], 'POST', $post);

        return $this->wiki->Action('checkcontent', 1, []);
    }

    public function testATruncatedRepairPostSaysSoInsteadOfFailingSilently()
    {
        $html = $this->runWithPost(['checkcontent-repair' => ['required_empty::FicheUne::bf_titre']]);

        $this->assertStringContainsString($this->tooManyMessage(), $html);
    }

    public function testACompletePostIsNotTakenForATruncatedOne()
    {
        $html = $this->runWithPost([
            'checkcontent-repair' => ['required_empty::FicheUne::bf_titre'],
            'checkcontent-complete' => '1',
        ]);

        $this->assertStringNotContainsString($this->tooManyMessage(), $html);
    }

    public function testAPostFromAnotherFormOnThePageIsNotTakenForATruncatedOne()
    {
        $html = $this->runWithPost(['some-other-action-field' => 'value']);

        $this->assertStringNotContainsString($this->tooManyMessage(), $html);
    }

    public function testTheFormPickerCarriesTheMarkerThatProvesThePostArrivedWhole()
    {
        $html = $this->wiki->Action('checkcontent', 1, []);

        $this->assertStringContainsString('name="checkcontent-complete"', $html);
    }
}
