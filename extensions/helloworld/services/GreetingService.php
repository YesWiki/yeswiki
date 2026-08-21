<?php

namespace YesWiki\HelloWorld\Service;

class GreetingService
{
    public function __construct()
    {
    }

    public function getUserName(): string
    {
        return 'Bibi';
    }
}
