<?php

use YesWiki\Core\Service\PasswordHasherFactory;
use YesWiki\Core\YesWikiMigration;

class UpdatePasswordSize extends YesWikiMigration
{
    public function run()
    {
        // update user table to increase size of password
        $passwordHasherFactory = $this->wiki->services->get(PasswordHasherFactory::class);
        if (!$passwordHasherFactory->newModeIsActivated()) {
            $passwordHasherFactory->activateNewMode();
        }
    }
}
