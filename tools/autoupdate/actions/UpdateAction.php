<?php

use YesWiki\AutoUpdate\Service\AutoUpdateService;
use YesWiki\AutoUpdate\Service\MigrationService;
use YesWiki\AutoUpdate\Service\UpdateAdminPagesService;
use YesWiki\Core\Service\ArchiveService;
use YesWiki\Core\YesWikiAction;
use YesWiki\Security\Controller\SecurityController;

class UpdateAction extends YesWikiAction
{
    public function formatArguments($arg)
    {
        return [
            'version' => $arg['version'] ?? '',
        ];
    }

    public function run()
    {
        $securityController = $this->getService(SecurityController::class);
        $updateService = $this->getService(AutoUpdateService::class);

        if (!$updateService->initRepository($this->arguments['version'])) {
            return $this->render('@autoupdate/norepo.twig', []);
        }

        $action = $securityController->filterInput(INPUT_GET, 'action', FILTER_DEFAULT, true);
        if (empty($action) || !$this->wiki->UserIsAdmin() || $this->isWikiHibernated()) {
            // Base action, display current status of software, extension and themes
            return $this->render('@autoupdate/status.twig', [
                'isAdmin' => $this->wiki->UserIsAdmin(),
                'isHibernated' => $this->isWikiHibernated(),
                'core' => $updateService->repository->getCorePackage(),
                'themes' => $updateService->repository->getThemesPackages(),
                'tools' => $updateService->repository->getToolsPackages(),
                'phpVersion' => PHP_VERSION,
            ]);
        }

        // Give 5 minutes time for the script to execute
        @ini_set('max_execution_time', 300);
        @set_time_limit(300);

        // Handle upgrade and delete actions
        // package can be 'yeswiki' for core upgrade, or extension name, or theme name
        $packageName = $securityController->filterInput(INPUT_GET, 'package', FILTER_DEFAULT, true);

        switch ($action) {
            case 'upgrade':
                // Ensure a backup is made before the upgrade (or force upgrade)
                $forcedUpdateToken = $securityController->filterInput(INPUT_GET, 'forcedUpdateToken', FILTER_DEFAULT, true);
                if (!$this->getService(ArchiveService::class)->hasValidatedBackup($forcedUpdateToken)) {
                    return $this->render('@core/preupdate-backup.twig', [
                        'packageName' => $packageName,
                    ]);
                }

                // Perform the upgrade
                $messages = $updateService->upgrade($packageName);

                // Reload the page to perform postInstall operation with the new code
                $this->wiki->redirect($this->wiki->href('', '', [
                    'action' => 'post_install',
                    'messages' => json_encode($messages->toArray()),
                    'previous_version' => YESWIKI_VERSION,
                ], false));
                break;
            case 'post_install':
                $rawMessages = $securityController->filterInput(INPUT_GET, 'messages', FILTER_UNSAFE_RAW, false, 'string');
                $messages = empty($rawMessages) ? [] : json_decode($rawMessages, true);
                if (!is_array($messages)) {
                    $messages = [];
                }
                // Run migrations
                $migrationMessages = $this->getService(MigrationService::class)->run();
                $messages = array_merge($messages, $migrationMessages->toArray());
                break;
            case 'update_admin_pages':
                $messages = $this->getService(UpdateAdminPagesService::class)->updateAll();
                break;
            case 'delete':
                $messages = $updateService->delete($packageName);
                break;
            default:
                $messages = [];
                break;
        }

        // Display result of action, with a list of success/error messages
        return $this->wiki->render('@autoupdate/update-result.twig', [
            'messages' => $messages,
            'action' => $action,
        ]);
    }
}
