<?php

use YesWiki\Core\YesWikiMigration;

class AddContextToPageLogin extends YesWikiMigration
{
    public function run()
    {
        // TODO : test if PageLogin exists, and add context="login-page" to {{login}} if exists
    }
}

