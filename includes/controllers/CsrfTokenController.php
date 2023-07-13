<?php

namespace YesWiki\Core\Controller;

use Exception;
use Symfony\Component\Security\Csrf\Exception\TokenNotFoundException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use YesWiki\Core\YesWikiController;

class CsrfTokenController extends YesWikiController
{
    protected $csrfTokenManager;

    public function __construct(
        CsrfTokenManager $csrfTokenManager
    ) {
        $this->csrfTokenManager = $csrfTokenManager;
    }

    /**
     * check if token is present and valid in input
     *
     * @param string $name
     * @param string $inputType "GET" or "POST"
     * @param string $inputKey key in the input to use
     * @param bool $remove
     * @return bool
     *
     * @throws TokenNotFoundException
     * @throws Exception
     */
    public function checkToken(string $name, string $inputType, string $inputKey, bool $remove = true): bool
    {
        if (empty($name)) {
            throw new Exception("parameter `\$name` should not be empty !");
        }
        switch ($inputType) {
            case 'GET':
                $inputToken = filter_input(INPUT_GET, $inputKey, FILTER_UNSAFE_RAW);
                $inputToken = in_array($inputToken,[false,null],true) ? $inputToken : htmlspecialchars(strip_tags($inputToken));
                break;

            case 'POST':
                $inputToken = filter_input(INPUT_POST, $inputKey, FILTER_UNSAFE_RAW);
                $inputToken = in_array($inputToken,[false,null],true) ? $inputToken : htmlspecialchars(strip_tags($inputToken));
                break;
            
            default:
                throw new Exception("Unknown type for parameter `\$inputType` !");
                return false;
        }
        if (is_null($inputToken) || $inputToken === false) {
            throw new TokenNotFoundException(_t('NO_CSRF_TOKEN_ERROR'));
        }
        $token = new CsrfToken($name, $inputToken);
        $isValid = $this->csrfTokenManager->isTokenValid($token);
        if ($remove){
            $this->csrfTokenManager->removeToken($name);
        }
        if (!$isValid) {
            throw new TokenNotFoundException(_t('CSRF_TOKEN_FAIL_ERROR'));
        }
        return true;
    }
}
