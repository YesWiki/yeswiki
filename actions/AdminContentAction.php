<?php

use YesWiki\Core\Service\GroupOperationsService;
use YesWiki\Core\Service\DbService;
use YesWiki\Core\Service\HibernationService;
use YesWiki\Core\Service\ThemeManager;
use YesWiki\Core\YesWikiAction;

class AdminContentAction extends YesWikiAction
{
    public function run()
    {
        if (!$this->wiki->UserIsAdmin()) {
            return $this->render('@core/alert-message.twig', [
                'type' => 'danger',
                'message' => _t('ACLS_RESERVED_FOR_ADMINS'),
            ]);
        }

        $dbService = $this->getService(DbService::class);
        $groupOperationsService = $this->getService(GroupOperationsService::class);
        $themeManager = $this->getService(ThemeManager::class);
        $hibernationService = $this->getService(HibernationService::class);

        // Forms for the type filter dropdown
        $forms = [];
        try {
            $forms = $this->getService(YesWiki\Core\Service\FormManager::class)->getAll();
        } catch (Throwable $e) {
            // bazar not available
        }

        // Distinct owners for the filter dropdown
        $ownersRows = $dbService->loadAll(
            "SELECT DISTINCT owner FROM {$dbService->prefixTable('pages')}"
            . " WHERE latest='Y' AND comment_on='' AND owner != '' ORDER BY owner ASC"
        );
        $owners = array_column($ownersRows ?? [], 'owner');

        // Groups for ACL selectors
        $groups = $groupOperationsService->getAll();

        // Templates for the theme bulk-action modal
        $templates = $themeManager->getTemplates();

        return $this->render('@core/admin-content-action.twig', [
            'owners' => $owners,
            'groups' => $groups,
            'forms' => $forms,
            'templates' => $templates,
            'isHibernated' => $hibernationService->isWikiHibernated(),
            'apiUrl' => $this->wiki->Href('', 'api/admin/pages'),
            'bulkApiUrl' => $this->wiki->Href('', 'api/admin/pages/bulk'),
        ]);
    }
}
