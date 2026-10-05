<?php

namespace YesWiki\Helloworld\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\HelloWorld\Service\GreetingService;
use YesWiki\Kernel\Performable\RegisteredAction;

/** `{{greeting}}`: greets whoever reads the page, the way an extension's action is written (ADR-0029). */
class GreetingAction extends YesWikiAction implements RegisteredAction
{
    public static function performableName(): string
    {
        return 'greeting';
    }

    public function formatArguments($arg)
    {
        return [];
    }

    public function run(): string
    {
        $userName = $this->getService(GreetingService::class)->getUserName();

        return $this->render('@helloworld/greeting.twig', ['userName' => $userName]);
    }
}
