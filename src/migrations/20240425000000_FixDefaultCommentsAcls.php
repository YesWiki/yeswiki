<?php

use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Service\ConfigurationFileProvider;
use YesWiki\Kernel\Service\ConfigurationService;

class FixDefaultCommentsAcls extends YesWikiMigration
{
    public function run()
    {
        $config = $this->getService(ConfigurationService::class)->getConfiguration(ConfigurationFileProvider::getConfigFileFromEnv());
        $config->load();
        if (empty($config['default_comment_acl'])) {
            $config['default_comment_acl'] = 'comments-closed';
            $config->write();

            // Update all pages with new ACL
            $pageManager = $this->getService(PageManager::class);
            $aclService = $this->getService(AclService::class);

            $pages = $pageManager->getAll();
            foreach ($pages as $page) {
                $pageCommentAcl = $aclService->load($page['tag'], 'comment', false)['list'] ?? '';
                if (!empty($pageCommentAcl) && preg_match("/comment-closed\s*/", strval($pageCommentAcl))) {
                    $aclService->save($page['tag'], 'comment', 'comments-closed');
                }
            }
        }
    }
}
