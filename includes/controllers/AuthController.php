<?php

namespace YesWiki\Core\Controller;

use YesWiki\Core\Entity\User;
use YesWiki\Core\Service\PasswordHasherFactory;
use YesWiki\Core\Service\UserManager;
use YesWiki\Core\YesWikiController;

class AuthController extends YesWikiController
{
    protected $passwordHasherFactory;
    protected $userManager;

    public function __construct(
        PasswordHasherFactory $passwordHasherFactory,
        UserManager $userManager
    ) {
        $this->passwordHasherFactory = $passwordHasherFactory;
        $this->userManager = $userManager;
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
        if (!$passwordHasher->verify($hashedPassword,$plainTextPassword)){
            return false;
        }
        if ($passwordHasher->needsRehash($hashedPassword)){
            $newHashedPassword = $passwordHasher->hash($plainTextPassword);
            $this->userManager->upgradePassword($user,$newHashedPassword);
        }
        return true;
    }

}
