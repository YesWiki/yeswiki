<?php

use YesWiki\Core\YesWikiMigration;
use YesWiki\Identity\Service\PasswordHasherFactory;

class UpdatePasswordSize extends YesWikiMigration
{
    public function run()
    {
        // update user table to increase size of password
        $passwordHasherFactory = $this->getService(PasswordHasherFactory::class);
        if (!$passwordHasherFactory->newModeIsActivated()) {
            $passwordHasherFactory->activateNewMode();
        }
    }
}
