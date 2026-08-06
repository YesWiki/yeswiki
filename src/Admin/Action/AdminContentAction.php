<?php

namespace YesWiki\Admin\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\GroupOperationsService;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Service\ThemeManager;

class AdminContentAction extends YesWikiAction implements RegisteredAction
{
    /** `{{admincontent}}` in page content -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'admincontent';
    }

    public function run()
    {
        if (!$this->getService(AclService::class)->isAdmin()) {
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
            // the dropdown shows a name per form and nothing else
            $forms = $this->getService(\YesWiki\Content\Service\FormManager::class)->getAllLabels();
        } catch (\Throwable $e) {
            // bazar not available
        }

        // Distinct owners for the filter dropdown
        $ownersRows = $dbService->loadAll(
            "SELECT DISTINCT owner FROM {$dbService->prefixTable('pages')}"
            . " WHERE latest='Y' AND parent='' AND owner != '' ORDER BY owner ASC"
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
            'apiUrl' => $this->getService(UrlFormatter::class)->href('', 'api/admin/pages'),
            'bulkApiUrl' => $this->getService(UrlFormatter::class)->href('', 'api/admin/pages/bulk'),
        ]);
    }
}
