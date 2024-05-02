<?php

use YesWiki\AutoUpdate\Service\AutoUpdateService;
use YesWiki\AutoUpdate\Service\MigrationService;
use YesWiki\AutoUpdate\Service\UpdateAdminPagesService;
use YesWiki\Core\Service\ArchiveService;
use YesWiki\Core\YesWikiAction;

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
        $updateService = $this->getService(AutoUpdateService::class);

        if (!$updateService->initRepository($this->arguments['version'])) {
            return $this->render("@autoupdate/norepo.twig", []);
        }

        if (empty($_GET['action']) || !$this->wiki->UserIsAdmin() || $this->isWikiHibernated()) {
            // Base action, display current status of software, extension and themes 
            return $this->render("@autoupdate/status.twig", [
                'isAdmin' => $this->wiki->UserIsAdmin(),
                'isHibernated' => $this->isWikiHibernated(),
                'core' => $updateService->repository->getCorePackage(),
                'themes' => $updateService->repository->getThemesPackages(),
                'tools' => $updateService->repository->getToolsPackages(),
                'phpVersion' => PHP_VERSION
            ]);
        }

        // Give 5 minutes time for the script to execute
        @ini_set('max_execution_time', 300);
        @set_time_limit(300);

        // Handle upgrade and delete actions
        $action = $_GET['action'];
        // package can be 'yeswiki' for core upgrade, or extension name, or theme name
        $packageName = $_GET['package'] ?? '';

        switch ($action) {
            case 'upgrade':
                // Ensure a backup is made before the upgrade (or force upgrade)
                if (!$this->getService(ArchiveService::class)->hasValidatedBackup($_GET['forcedUpdateToken'] ?? "")) {
                    return $this->render("@core/preupdate-backup.twig", [
                        'packageName' => $packageName
                    ]);
                }

                // Perform the upgrade
                // $messages = $updateService->upgrade($packageName);
                $messages = [['status' => 'ok', 'text' => "fake message"]];

                // When upgrading the core (i.e. yeswiki) we reload the page
                // to perform postInstall operation with the new code
                if ($packageName == 'yeswiki') {
                    $this->wiki->redirect($this->wiki->href('', '', [
                        'action' => 'post_install',
                        'messages' => json_encode($messages),
                        'previous_version' => YESWIKI_VERSION
                    ], false));
                }
                break;
            case 'post_install':
                $messages = json_decode($_GET['messages']);

                // Run migrations
                $migrations = new MigrationService($this->wiki);
                $migrationMessages = $migrations->run();

                $messages = array_merge($messages, $migrationMessages);
                break;
            case 'update_admin_pages':
                $messages = $this->getService(UpdateAdminPagesService::class)->updateAll();
                break;
            case 'delete':
                $messages = $updateService->delete($packageName);
                break;
        }

        // Display result of action, with a list of success/error messages
        return $this->wiki->render("@autoupdate/update-result.twig", [
            'messages' => $messages,
            'action' => $action
        ]);
    }
}