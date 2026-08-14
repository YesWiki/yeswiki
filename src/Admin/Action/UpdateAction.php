<?php

namespace YesWiki\Admin\Action;

// ticket 19: relocated from tools/autoupdate/actions/UpdateAction.php.

use Tamtamchik\SimpleFlash\Flash;
use YesWiki\Admin\Service\ArchiveService;
use YesWiki\Admin\Service\AutoUpdateService;
use YesWiki\Admin\Service\UpdateAdminPagesService;
use YesWiki\Core\YesWikiAction;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\InputFilter;
use YesWiki\Kernel\Entity\Messages;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\MigrationService;
use YesWiki\Kernel\Service\Redirector;
use YesWiki\Kernel\Service\ThrowableFormatter;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Service\TemplateEngine;

class UpdateAction extends YesWikiAction implements RegisteredAction
{
    /** `{{update}}` in page content -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'update';
    }

    public function formatArguments($arg)
    {
        return [
            'version' => $arg['version'] ?? '',
        ];
    }

    public function run()
    {
        $vSecurityController = $this->getService(InputFilter::class);
        $vUpdateService = $this->getService(AutoUpdateService::class);
        $vMigrationService = $this->getService(MigrationService::class);
        $vArchiveService = $this->getService(ArchiveService::class);
        $vUpdateAdminPagesService = $this->getService(UpdateAdminPagesService::class);

        if (!$vUpdateService->initRepository($this->arguments['version'])) {
            return $this->render('@core/norepo.twig', []);
        }

        // At the time we introduced the migration concept in YesWiki, the old code was not redirecting
        // to "post_install" action. So we add this code to detect the first time the code is run
        // after migrations are introduced, and we run them.
        $vMigrationService = $this->getService(MigrationService::class);

        if (count($vMigrationService->getCompletedMigrations()) === 0) {
            $vMessages = $vMigrationService->run();
            foreach ($vMessages as $vMessage) {
                // `flash()` -- the deleted procedural helper. This is the only call left, and
                // it is on the path a long-unmigrated wiki takes when it updates, so it fataled
                // exactly where a clear message mattered most (ticket 40).
                $text = $vMessage['text'] . ' : ' . $vMessage['status'];
                if ($vMessage['status'] == _t('AU_OK')) {
                    Flash::success($text);
                } else {
                    Flash::error($text);
                }
            }
        }

        $vIsReadOnly = $vArchiveService->isReadOnly();

        $vAction = $vSecurityController->filterInput(INPUT_GET, 'action', FILTER_DEFAULT, true);
        if (empty($vAction) || !$this->getService(AclService::class)->isAdmin() || $vIsReadOnly) {
            $vIsAdmin = $this->getService(AclService::class)->isAdmin();
            $vIsDesignatedUpdateInstance = $vUpdateService->isDesignatedUpdateInstance();

            // Base action, display current status of software, extension and themes
            return $this->render('@core/status.twig', [
                'isAdmin' => $vIsAdmin,
                'isHibernated' => $vIsReadOnly,
                'isDesignatedUpdateInstance' => $vIsDesignatedUpdateInstance,
                // computed once here rather than repeated in both core.twig and exts.twig
                'canTriggerUpdate' => $vIsAdmin && !$vIsReadOnly && $vIsDesignatedUpdateInstance,
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

        // ADR-0007: every action below mutates the shared YESWIKI_SOURCE_DIR (or, for
        // delete/upgrade, a tool/theme inside it) -- on a farm, only the instance sharing
        // it as its own YESWIKI_INSTANCE_DIR may trigger that (a satellite instance
        // triggering an "update" would silently mutate the shared source for every other
        // farm instance without their consent).
        if (!$vUpdateService->isDesignatedUpdateInstance()) {
            $vMessages->add('AU_NOT_DESIGNATED_UPDATE_INSTANCE', 'AU_ERROR');

            return $this->getService(TemplateEngine::class)->renderSafely('@core/update-result.twig', [
                'messages' => $vMessages->toArray(),
                'action' => $vAction,
            ]);
        }

        try {
            switch ($vAction) {
                case 'upgrade':
                    $vCanUpgrade = false;

                    $vArchiveParams = $vArchiveService->getArchiveParams();

                    // Check if the preupdate backup is activated
                    if (in_array($vArchiveParams['preupdate_backup_activated'] ?? true, [true, 'true', 1, '1'])) {
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
                            }
                            // else we can do the upgrade
                            $vCanUpgrade = true;
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
                        $this->getService(Redirector::class)->redirect($this->getService(UrlFormatter::class)->href('', '', [
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

                    $vUpdateAdminPagesMessages = $vUpdateAdminPagesService->updateAll();

                    $vMessages->add($vUpdateAdminPagesMessages);
                    break;
                case 'delete':
                    // Delete package

                    $vDeleteMessages = $vUpdateService->delete($vPackageName);

                    $vMessages->add($vDeleteMessages);
                    break;
            }
        } catch (\Throwable $pThrowable) {
            $vMessages->add(_t('PERFORMABLE_ERROR') . $this->getService(ThrowableFormatter::class)->dump($pThrowable), 'AU_ERROR');
        }

        // Display result of action, with a list of success/error messages
        return $this->getService(TemplateEngine::class)->renderSafely('@core/update-result.twig', [
            'messages' => $vMessages->toArray(),
            'action' => $vAction,
        ]);
    }
}
