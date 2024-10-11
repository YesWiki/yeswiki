<?php

namespace YesWiki\Core\Controller;

use Exception;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Symfony\Component\Security\Csrf\Exception\TokenNotFoundException;
use YesWiki\Core\YesWikiController;
use YesWiki\Security\Controller\SecurityController;

class CsrfTokenController extends YesWikiController
{
    protected $csrfTokenManager;

    public function __construct(
        CsrfTokenManager $csrfTokenManager
    ) {
        $this->csrfTokenManager = $csrfTokenManager;
    }

    /**
     * check if token is present and valid in input.
     *
     * @param string $inputType "GET" or "POST"
     * @param string $inputKey  key in the input to use
     *
     * @throws TokenNotFoundException
     * @throws Exception
     */
    public function checkToken(string $name, string $inputType, string $inputKey, bool $remove = true): bool
    {
        if (empty($name)) {
            throw new Exception('parameter `$name` should not be empty !');
        }
        switch ($inputType) {
            case 'GET':
                $inputToken = $this->getService(SecurityController::class)->filterInput(INPUT_GET, $inputKey, FILTER_DEFAULT, true);
                break;
            case 'POST':
                $inputToken = $this->getService(SecurityController::class)->filterInput(INPUT_POST, $inputKey, FILTER_DEFAULT, true);
                break;

            default:
                throw new Exception('Unknown type for parameter `$inputType` !');

                return false;
        }
        if (is_null($inputToken) || $inputToken === false) {
            throw new TokenNotFoundException(_t('NO_CSRF_TOKEN_ERROR'));
        }
        $token = new CsrfToken($name, $inputToken);
        $isValid = $this->csrfTokenManager->isTokenValid($token);
        if ($remove) {
            $this->csrfTokenManager->removeToken($name);
        }
        if (!$isValid) {
            throw new TokenNotFoundException(_t('CSRF_TOKEN_FAIL_ERROR'));
        }

        return true;
    }
}
