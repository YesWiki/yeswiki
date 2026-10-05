<?php

use YesWiki\Contact\Service\MailSubscriptions;
use YesWiki\Core\Controller\GroupController;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\UserManager;
use YesWiki\Core\YesWikiMigration;

/** Moves the {{mailperiod}} subscriptions out of the Mail<Page><Period> groups into triples, then deletes those groups. */
class ContactMailSubscriptionsFromGroups extends YesWikiMigration
{
    public function run()
    {
        $groupController = $this->wiki->services->get(GroupController::class);
        $subscriptions = $this->wiki->services->get(MailSubscriptions::class);
        $pageManager = $this->wiki->services->get(PageManager::class);
        $userManager = $this->wiki->services->get(UserManager::class);

        foreach (array_reverse(MailSubscriptions::PERIODS) as $period) {
            foreach ($groupController->getAll() as $group) {
                if (!preg_match('/^Mail(.+)' . ucfirst($period) . '$/', $group, $matches) || empty($pageManager->getOne($matches[1]))) {
                    continue;
                }
                foreach ($groupController->getMembers($group) as $member) {
                    if (!empty($userManager->getOneByName($member))) {
                        $subscriptions->subscribe($matches[1], $member, $period);
                    }
                }
                $groupController->delete($group);
            }
        }
    }
}
