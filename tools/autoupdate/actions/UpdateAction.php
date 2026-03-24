<?php

use YesWiki\AutoUpdate\Entity\Messages;
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
        $vSecurityController = $this->getService(SecurityController::class);
        $vUpdateService = $this->getService(AutoUpdateService::class);
        $vMigrationService = $this->getService(MigrationService::class);
        $vArchiveService = $this->getService(ArchiveService::class);
        $vUpdateAdminPagesService = $this->getService(UpdateAdminPagesService::class);

        if (!$vUpdateService->initRepository($this->arguments['version'])) {
            return $this->render('@autoupdate/norepo.twig', []);
        }

        // At the time we introduced the migration concept in YesWiki, the old code was not redirecting
        // to "post_install" action. So we add this code to detect the first time the code is run
        // after migrations are introduced, and we run them.
        $vMigrationService = $this->getService(MigrationService::class);

        if (count($vMigrationService->getCompletedMigrations()) === 0) {
            $vMessages = $vMigrationService->run();
            foreach ($vMessages as $vMessage) {
                flash(
                    $vMessage['text'] . ' : ' . $vMessage['status'],
                    $vMessage['status'] == _t('AU_OK') ? 'success' : 'error'
                );
            }
        }

        $vIsReadOnly = $vArchiveService->isReadOnly();

        $vAction = $vSecurityController->filterInput(INPUT_GET, 'action', FILTER_DEFAULT, true);
        if (empty($vAction) || !$this->wiki->UserIsAdmin() || $vIsReadOnly) {
            // Base action, display current status of software, extension and themes
            return $this->render('@autoupdate/status.twig', [
                'isAdmin' => $this->wiki->UserIsAdmin(),
                'isHibernated' => $vIsReadOnly,
                'core' => $vUpdateService->repository->getCorePackage(),
                'themes' => $vUpdateService->repository->getThemesPackages(),
                'tools' => $vUpdateService->repository->getToolsPackages(),
                'phpVersion' => PHP_VERSION,
            ]);
        }

        // Give 5 minutes time for the script to execute
        @ini_set('max_execution_time', 300);
        @set_time_limit(300);

        // Handle upgrade and delete actions
        // package can be 'yeswiki' for core upgrade, or extension name, or theme name
        $vPackageName = $vSecurityController->filterInput(INPUT_GET, 'package', FILTER_DEFAULT, true);

        $vMessages = new Messages();

        try {
            switch ($vAction) {
                case 'upgrade':
                    $vCanUpgrade = false;

                    $vArchiveParams = $vArchiveService->getArchiveParams();

                    // Check if the preupdate backup is activated
                    if (in_array(($vArchiveParams['preupdate_backup_activated'] ?? true), [true, 'true', 1, '1'])) {
                        // It is so let's go further...

                        $vStatus = $vArchiveService->getArchivingStatus();

                        if ($vStatus['privatePathWritable']) {
                            // Get the forced update token if it exist
                            $vForcedUpdateToken = $vSecurityController->filterInput(INPUT_GET, 'forcedUpdateToken', FILTER_DEFAULT, true);

                            // Check if we need to do a backup...

                            if (!$vArchiveService->hasValidatedBackup($vForcedUpdateToken)) {
                                // ...and, if so, let's the client handle it
                                return $this->render('@core/preupdate-backup.twig', [
                                    'packageName' => $vPackageName,
                                ]);
                            } else {
                                // else we can do the upgrade
                                $vCanUpgrade = true;
                            }
                        } else {
                            $vMessages->add('AU_PRIVATE_PATH_NOT_WRITABLE', 'AU_ERROR');
                        }
                    } else {
                        $vCanUpgrade = true;
                    }

                   if ($vCanUpgrade) {
                       // Perform the upgrade

                       $vUpgradeMessages = $vUpdateService->upgrade($vPackageName);

                       $vMessages->add($vUpgradeMessages);

                       // Reload the page to perform postInstall operation with the new code
                       $this->wiki->redirect($this->wiki->href('', '', [
                           'action' => 'post_install',
                           'messages' => json_encode($vMessages->toArray()),
                           'previous_version' => YESWIKI_VERSION,
                       ], false));
                   }
                    break;
                case 'post_install':
                    $vRawMessages = $vSecurityController->filterInput(INPUT_GET, 'messages', FILTER_UNSAFE_RAW, false, 'string');

                    $vMessages->add(empty($vRawMessages) ? [] : (json_decode($vRawMessages, true) ?? []));

                    // Run migrations

                    $vMigrationMessages = $vMigrationService->run();

                    $vMessages->add($vMigrationMessages);
                    break;
                case 'update_admin_pages':
                    // Update admin pages

                    $vUpdateAdminPagesMessages = vUpdateAdminPagesService->updateAll();

                    $vMessages->add($vUpdateAdminPagesMessages);
                    break;
                case 'delete':
                    // Delete package

                    $vDeleteMessages = $vUpdateService->delete($vPackageName);

                    $vMessages->add($vDeleteMessages);
                    break;
            }
        } catch (Throwable $pThrowable) {
            $vMessages->add(_t('PERFORMABLE_ERROR') . $this->wiki->dumpThrowable ($pThrowable), 'AU_ERROR');
        }

        // Display result of action, with a list of success/error messages
        return $this->wiki->render('@autoupdate/update-result.twig', [
            'messages' => $vMessages->toArray(),
            'action' => $vAction,
        ]);
    }
}
