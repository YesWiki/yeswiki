<?php

use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\ConfigurationService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\YesWikiMigration;

class FixDefaultCommentsAcls extends YesWikiMigration
{
    public function run()
    {
        $config = $this->wiki->services->get(ConfigurationService::class)->getConfiguration('wakka.config.php');
        $config->load();
        if (empty($config['default_comment_acl'])) {
            $config['default_comment_acl'] = 'comments-closed';
            $config->write();

            // Update all pages with new ACL
            $pageManager = $this->wiki->services->get(PageManager::class);
            $aclService = $this->wiki->services->get(AclService::class);

            $pages = $pageManager->getAll();
            foreach ($pages as $page) {
                $pageCommentAcl = $aclService->load($page['tag'], 'comment', false)['list'] ?? '';
                if (!empty($pageCommentAcl) && preg_match("/comment-closed\s*/", strval($pageCommentAcl))) {
                    dump($page['tag']);
                    $aclService->save($page['tag'], 'comment', 'comments-closed');
                }
            }
        }
    }
}
