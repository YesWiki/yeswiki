<?php

use Symfony\Component\Security\Csrf\Exception\TokenNotFoundException;
use YesWiki\AutoUpdate\Entity\Messages;
use YesWiki\AutoUpdate\Service\AutoUpdateService;
use YesWiki\AutoUpdate\Service\MigrationService;
use YesWiki\AutoUpdate\Service\UpdateAdminPagesService;
use YesWiki\Core\Controller\CsrfTokenController;
use YesWiki\Core\Service\ArchiveService;
use YesWiki\Core\Service\RequestGuard;
use YesWiki\Core\YesWikiAction;
use YesWiki\Security\Controller\SecurityController;

class UpdateAction extends YesWikiAction
{
    /** what installs, replaces or removes code, and may only be asked for by this wiki's own pages */
    public const CONFIRMED_ACTIONS = ['upgrade', 'delete', 'update_admin_pages'];

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
        $vRequestGuard = $this->getService(RequestGuard::class);

        if (!$vUpdateService->initRepository($this->arguments['version'])) {
            return $this->render('@autoupdate/norepo.twig', []);
        }

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
            return $this->render('@autoupdate/status.twig', [
                'isAdmin' => $this->wiki->UserIsAdmin(),
                'isHibernated' => $vIsReadOnly,
                'core' => $vUpdateService->repository->getCorePackage(),
                'themes' => $vUpdateService->repository->getThemesPackages(),
                'tools' => $vUpdateService->repository->getToolsPackages()->withoutForbidden($vUpdateService->forbiddenExtensions()),
                'phpVersion' => PHP_VERSION,
            ]);
        }

        @ini_set('max_execution_time', 300);
        @set_time_limit(300);

        $vPackageName = $vSecurityController->filterInput(INPUT_GET, 'package', FILTER_DEFAULT, true);

        $vMessages = new Messages();

        try {
            if (in_array($vAction, self::CONFIRMED_ACTIONS, true)) {
                $this->getService(CsrfTokenController::class)->checkToken('main', 'POST', 'csrf-token', false);
            }
            switch ($vAction) {
                case 'upgrade':
                    $vCanUpgrade = false;

                    $vArchiveParams = $vArchiveService->getArchiveParams();

                    if (in_array($vArchiveParams['preupdate_backup_activated'] ?? true, [true, 'true', 1, '1'])) {
                        $vStatus = $vArchiveService->getArchivingStatus();

                        if ($vStatus['privatePathWritable']) {
                            $vForcedUpdateToken = $vSecurityController->filterInput(INPUT_GET, 'forcedUpdateToken', FILTER_DEFAULT, true);

                            if (!$vArchiveService->hasValidatedBackup($vForcedUpdateToken)) {
                                return $this->render('@core/preupdate-backup.twig', [
                                    'packageName' => $vPackageName,
                                ]);
                            }
                            $vCanUpgrade = true;
                        } else {
                            $vMessages->add('AU_PRIVATE_PATH_NOT_WRITABLE', 'AU_ERROR');
                        }
                    } else {
                        $vCanUpgrade = true;
                    }

                    if ($vCanUpgrade) {
                        $vUpgradeMessages = $vUpdateService->upgrade($vPackageName);

                        $vMessages->add($vUpgradeMessages);

                        $this->wiki->redirect($vRequestGuard->sign($this->wiki->href('', '', [
                            'action' => 'post_install',
                            'messages' => json_encode($vMessages->toArray()),
                            'previous_version' => YESWIKI_VERSION,
                        ], false)));
                    }
                    break;
                case 'post_install':
                    if (!$vRequestGuard->linkIsSigned($this->getRequest())) {
                        $vMessages->add('CSRF_TOKEN_FAIL_ERROR', 'AU_ERROR');

                        return $this->wiki->render('@autoupdate/update-result.twig', [
                            'messages' => $vMessages->toArray(),
                            'action' => $vAction,
                            'confirmUrl' => $vRequestGuard->sign($this->wiki->href('', '', [
                                'action' => 'post_install',
                                'messages' => $this->getRequest()->query->get('messages', ''),
                                'previous_version' => $this->getRequest()->query->get('previous_version', ''),
                            ], false)),
                        ]);
                    }
                    $vRawMessages = $vSecurityController->filterInput(INPUT_GET, 'messages', FILTER_UNSAFE_RAW, false, 'string');

                    $vMessages->add(empty($vRawMessages) ? [] : (json_decode($vRawMessages, true) ?? []));

                    $vMigrationMessages = $vMigrationService->run();

                    $vMessages->add($vMigrationMessages);
                    break;
                case 'update_admin_pages':
                    $vUpdateAdminPagesMessages = $vUpdateAdminPagesService->updateAll();

                    $vMessages->add($vUpdateAdminPagesMessages);
                    break;
                case 'delete':
                    $vDeleteMessages = $vUpdateService->delete($vPackageName);

                    $vMessages->add($vDeleteMessages);
                    break;
            }
        } catch (TokenNotFoundException $pTokenNotFound) {
            $vMessages->add($pTokenNotFound->getMessage(), 'AU_ERROR');
        } catch (Throwable $pThrowable) {
            $vMessages->add(_t('PERFORMABLE_ERROR') . $this->wiki->dumpThrowable($pThrowable), 'AU_ERROR');
        }

        return $this->wiki->render('@autoupdate/update-result.twig', [
            'messages' => $vMessages->toArray(),
            'action' => $vAction,
        ]);
    }
}
