<?php

use YesWiki\Contact\Service\MailSubscriptions;
use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Identity\Service\GroupOperationsService;
use YesWiki\Identity\Service\UserManager;

/** Moves mail subscriptions from Mail* groups to triples. */
class MailSubscriptionsLeaveTheGroups extends YesWikiMigration
{
    public function run()
    {
        $groups = $this->getService(GroupOperationsService::class);
        $subscriptions = $this->getService(MailSubscriptions::class);
        $pages = $this->getService(PageManager::class);
        $users = $this->getService(UserManager::class);

        $moved = 0;
        $deleted = 0;
        foreach (array_reverse(MailSubscriptions::PERIODS) as $period) {
            foreach ($groups->getAll() as $group) {
                if (!preg_match('/^Mail(.+)' . ucfirst($period) . '$/', $group, $matches)
                    || empty($pages->getOne($matches[1], null, false, true))) {
                    continue;
                }
                foreach ($groups->getMembers($group) as $member) {
                    if (!empty($users->getOneByName($member))) {
                        $subscriptions->subscribe($matches[1], $member, $period);
                        $moved++;
                    }
                }
                $groups->delete($group);
                $deleted++;
            }
        }

        if ($deleted > 0) {
            $this->say("{$moved} mail subscription(s) moved out of {$deleted} Mail<Page><Period> group(s), which are deleted.");
        }
    }
}
