<?php

use YesWiki\Core\Service\PageManager;
use YesWiki\Core\YesWikiMigration;

class AddBotGuardStatsToDashboard extends YesWikiMigration
{
    public const TAG = 'TableauDeBord';
    public const SECTION = "{{section class=\"full-width text-left\" visibility=\"@admins\" }}\n{{adminbotguard}}\n{{end elem=\"section\"}}";

    public function run()
    {
        $pageManager = $this->getService(PageManager::class);
        $page = $pageManager->getOne(static::TAG, null, false, true);
        if (empty($page) || str_contains($page['body'], '{{adminbotguard')) {
            return;
        }
        $pageManager->save(static::TAG, rtrim($page['body']) . "\n\n" . self::SECTION, '', true);
    }
}
