<?php

namespace YesWiki\Core\Controller;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Core\Entity\User;
use YesWiki\Core\Exception\BadFormatPasswordException;
use YesWiki\Core\Service\PasswordHasherFactory;
use YesWiki\Core\Service\UserManager;
use YesWiki\Core\YesWikiController;

class AuthController extends YesWikiController
{
    public const DEFAULT_NAME_MAX_LENGTH = 80;
    public const DEFAULT_EMAIL_MAX_LENGTH = 254;
    public const DEFAULT_PASSWORD_MINIMUM_LENGTH = 5;

    private $limitations;
    protected $params;
    protected $passwordHasherFactory;
    protected $userManager;

    public function __construct(
        ParameterBagInterface $params,
        PasswordHasherFactory $passwordHasherFactory,
        UserManager $userManager
    ) {
        $this->params = $params;
        $this->passwordHasherFactory = $passwordHasherFactory;
        $this->userManager = $userManager;
        $this->initLimitations();
    }

    /** Initializes object limitation properties using values from the config file
     *
     * @return void
     */
    private function initLimitations()
    {
        $this->limitations = [];
        $this->initLimitationHelper(
            'user_name_max_length',
            'nameMaxLength',
            FILTER_VALIDATE_INT,
            self::DEFAULT_NAME_MAX_LENGTH,
            'USER_NAME_MAX_LENGTH_NOT_INT'
        );
        $this->initLimitationHelper(
            'user_email_max_length',
            'emailMaxLength',
            FILTER_VALIDATE_INT,
            self::DEFAULT_EMAIL_MAX_LENGTH,
            'USER_EMAIL_MAX_LENGTH_NOT_INT'
        );
        $this->initLimitationHelper(
            'user_password_min_length',
            'passwordMinimumLength',
            FILTER_VALIDATE_INT,
            self::DEFAULT_PASSWORD_MINIMUM_LENGTH,
            'USER_PASSWORD_MIN_LENGTH_NOT_INT'
        );
    }

    /** checks if the given string is the user's password
     *
     * @param string $plainTextPassword
     * @param User $user
     * @return boolean True if OK or false if any problems
     */
    public function checkPassword(string $plainTextPassword, User $user)
    {
        $passwordHasher = $this->passwordHasherFactory->getPasswordHasher($user);
        $hashedPassword = $user->getPassword();
        if (!$passwordHasher->verify($hashedPassword, $plainTextPassword)) {
            return false;
        }
        if ($passwordHasher->needsRehash($hashedPassword)) {
            $newHashedPassword = $passwordHasher->hash($plainTextPassword);
            $this->userManager->upgradePassword($user, $newHashedPassword);
        }
        return true;
    }

    /**
     * force a new password when renewing password
     * @param User $user
     * @param string $plainTextPassword
     * @throws BadFormatPasswordException
     */
    public function setPassword(User $user, string $plainTextPassword)
    {
        $this->checkPasswordValidateRequirements($plainTextPassword);
        $passwordHasher = $this->passwordHasherFactory->getPasswordHasher($user);
        $newHashedPassword = $passwordHasher->hash($plainTextPassword);
        $this->userManager->upgradePassword($user, $newHashedPassword);
    }

    /**
     * check if password respets the requirements
     * @param string $password
     * @return bool
     * @throws BadFormatPasswordException
     */
    public function checkPasswordValidateRequirements(string $password):bool
    {
        if (strlen($password) < $this->limitations['passwordMinimumLength']) {
            throw new BadFormatPasswordException(_t('USER_PASSWORD_TOO_SHORT').'. '._t('USER_PASSWORD_MINIMUM_NUMBER_OF_CHARACTERS_IS').' ' .$this->limitations['passwordMinimumLength'].'.');
        }
        return true;
    }

    /**
     * init and store limitations in limitations array
     * @param string $parameterName
     * @param string $limitationKey
     * @param mixed $type
     * @param mixed $default
     * @param string $errorMessageKey
     */
    private function initLimitationHelper(string $parameterName, string $limitationKey, $type, $default, string $errorMessageKey)
    {
        $this->limitations[$limitationKey] = $default;
        if ($this->params->has($parameterName)) {
            $parameter = $this->params->get($parameterName);
            if (!filter_var($parameter, FILTER_VALIDATE_INT)) {
                trigger_error(_t($errorMessageKey));
            } else {
                $this->limitations[$limitationKey] = $parameter;
            }
        }
    }
}
