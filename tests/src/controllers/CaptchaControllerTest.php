<?php

namespace YesWiki\Test\Core\Controller;

use PHPUnit\Framework\Attributes\CoversMethod;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Identity\Controller\CaptchaController;
use YesWiki\Identity\Service\InputFilter;
use YesWiki\Test\Core\ForcedParameterBag;
use YesWiki\Test\Core\YesWikiTestCase;
use YesWiki\Wiki;

require_once 'tests/YesWikiTestCase.php';
require_once 'tests/ForcedParameterBag.php';

/**
 * Regression tests for ticket 15 (security-core-split): checkCaptchaBeforeSave() was merged
 * into CaptchaController from the former InputFilter, alongside the pre-existing
 * captcha image/hash logic it already owned.
 */
#[CoversMethod(CaptchaController::class, 'checkCaptchaBeforeSave')]
class CaptchaControllerTest extends YesWikiTestCase
{
    private function buildController(Wiki $wiki, bool $useCaptcha): CaptchaController
    {
        $realParams = $wiki->services->get(ParameterBagInterface::class);
        $forcedParams = new ForcedParameterBag($realParams, ['use_captcha' => $useCaptcha]);

        $captchaController = new CaptchaController($forcedParams);
        $captchaController->setWiki($wiki);

        return $captchaController;
    }

    private function findWordForHash(CaptchaController $captchaController, string $hash): string
    {
        foreach (CaptchaController::DEFAULT_TEXTS as $word) {
            if ($captchaController->check($word, $hash)) {
                return $word;
            }
        }
        $this->fail('generateHash() produced a hash matching none of DEFAULT_TEXTS');
    }

    public function testAlwaysPassesWhenCaptchaDisabled()
    {
        $wiki = $this->getWiki();
        $this->assertFalse($wiki->services->get(\YesWiki\Identity\Service\AclService::class)->isAdmin(), 'test must run as a non-admin for checkCaptchaBeforeSave() to evaluate captcha');
        $wiki->request->request->set('submit', InputFilter::EDIT_PAGE_SUBMIT_VALUE);

        try {
            [$state, $error] = $this->buildController($wiki, false)->checkCaptchaBeforeSave();

            $this->assertTrue($state);
            $this->assertNull($error);
        } finally {
            $wiki->request->request->remove('submit');
        }
    }

    public function testFailsWhenCaptchaMissing()
    {
        $wiki = $this->getWiki();
        $wiki->request->request->set('submit', InputFilter::EDIT_PAGE_SUBMIT_VALUE);
        $wiki->request->request->remove('captcha');
        $wiki->request->request->remove('captcha_hash');

        try {
            [$state, $error] = $this->buildController($wiki, true)->checkCaptchaBeforeSave();

            $this->assertFalse($state);
            $this->assertSame(_t('CAPTCHA_ERROR_PAGE_UNSAVED'), $error);
        } finally {
            $wiki->request->request->remove('submit');
        }
    }

    public function testFailsWhenCaptchaWordIsWrongThenSucceedsWithCorrectWord()
    {
        $wiki = $this->getWiki();
        $captchaController = $this->buildController($wiki, true);
        $hash = $captchaController->generateHash();
        $word = $this->findWordForHash($captchaController, $hash);

        $wiki->request->request->set('submit', InputFilter::EDIT_PAGE_SUBMIT_VALUE);
        $wiki->request->request->set('captcha_hash', $hash);

        try {
            $wiki->request->request->set('captcha', $word . '-definitely-wrong');
            [$state, $error] = $captchaController->checkCaptchaBeforeSave();
            $this->assertFalse($state);
            $this->assertSame(_t('CAPTCHA_ERROR_WRONG_WORD'), $error);

            $wiki->request->request->set('captcha', $word);
            [$state, $error] = $captchaController->checkCaptchaBeforeSave();
            $this->assertTrue($state);
            $this->assertEmpty($error);
        } finally {
            $wiki->request->request->remove('submit');
            $wiki->request->request->remove('captcha');
            $wiki->request->request->remove('captcha_hash');
        }
    }
}
