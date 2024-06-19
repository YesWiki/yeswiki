<?php

namespace YesWiki\HelloWorld\Service;

// How to register your service?
// =============================
//
// You need to make sure following code is present in the tools/XXX/config.yaml
// services:
//   _defaults:
//      autowire: true
//      public: true
//
//   YesWiki\HelloWorld\Service\:
//      resource: 'services/*'

class GreetingService
{
    public function __construct()
    {
    }

    public function getUserName()
    {
        return 'Bibi';
    }
}
