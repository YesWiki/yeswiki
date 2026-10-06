<?php

namespace YesWiki\Admin\Action;

use Symfony\Component\Security\Csrf\Exception\TokenNotFoundException;
use Tamtamchik\SimpleFlash\Flash;
use YesWiki\Admin\Entity\PackageTool;
use YesWiki\Admin\Service\ArchiveService;
use YesWiki\Admin\Service\AutoUpdateService;
use YesWiki\Admin\Service\ExtensionActivation;
use YesWiki\Core\YesWikiAction;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\CsrfTokenChecker;
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

    /** what installs, replaces or removes code, and may only be asked for by this wiki's own pages */
    public const CONFIRMED_ACTIONS = ['upgrade', 'delete', 'activate', 'deactivate'];

    public function formatArguments($arg)
    {
        return [
            'version' => $arg['version'] ?? '',
        ];
    }

    /** Why a button is disabled, most specific reason first. */
    private function blockedReason(bool $isHibernated, bool $mayTrigger): string
    {
        if ($isHibernated) {
            return _t('WIKI_IN_HIBERNATION');
        }
        if (!$mayTrigger) {
            return _t('AU_NOT_DESIGNATED_UPDATE_INSTANCE');
        }

        return _t('ACLS_RESERVED_FOR_ADMINS');
    }

    /** @return string */
    public function run()
    {
        $vSecurityController = $this->getService(InputFilter::class);
        $vUpdateService = $this->getService(AutoUpdateService::class);
        $vMigrationService = $this->getService(MigrationService::class);
        $vArchiveService = $this->getService(ArchiveService::class);

        if (!$vUpdateService->initRepository($this->arguments['version'])) {
            return $this->render('@core/norepo.twig', []);
        }

        $vMigrationService = $this->getService(MigrationService::class);

        if (count($vMigrationService->getCompletedMigrations()) === 0) {
            $vMessages = $vMigrationService->run();
            foreach ($vMessages as $vMessage) {
                $text = $vMessage['text'] . ' : ' . $vMessage['status'];
                if ($vMessage['status'] == _t('AU_OK')) {
                    Flash::success($text);
                } elseif ($vMessage['status'] == _t('AU_NOTE')) {
                    Flash::info($text);
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

            $vCanTriggerCoreUpdate = $vIsAdmin && !$vIsReadOnly && $vIsDesignatedUpdateInstance;
            $vCanTriggerExtensionUpdate = $vIsAdmin && !$vIsReadOnly;

            return $this->render('@core/status.twig', [
                'isAdmin' => $vIsAdmin,
                'isHibernated' => $vIsReadOnly,
                'isDesignatedUpdateInstance' => $vIsDesignatedUpdateInstance,

                'canTriggerUpdate' => $vCanTriggerCoreUpdate,
                'canTriggerExtensionUpdate' => $vCanTriggerExtensionUpdate,
                'blockedReason' => $this->blockedReason($vIsReadOnly, $vIsDesignatedUpdateInstance),
                'extensionBlockedReason' => $this->blockedReason($vIsReadOnly, true),
                'core' => $vUpdateService->repository->getCorePackage(),
                'themes' => $vUpdateService->repository->getThemesPackages(),
                'tools' => $vUpdateService->repository->getToolsPackages(),
                'activeExtensions' => $this->getService(ExtensionActivation::class)->active(),
                'phpVersion' => PHP_VERSION,
            ]);
        }

        @ini_set('max_execution_time', 300);
        @set_time_limit(300);

        $vPackageName = $vSecurityController->filterInput(INPUT_GET, 'package', FILTER_DEFAULT, true);

        $vMessages = new Messages();

        if (in_array($vAction, ['upgrade', 'delete'], true) && !$vUpdateService->mayUpgrade((string)$vPackageName)) {
            $vMessages->add('AU_NOT_DESIGNATED_UPDATE_INSTANCE', 'AU_ERROR');

            return $this->getService(TemplateEngine::class)->renderSafely('@core/update-result.twig', [
                'messages' => $vMessages->toArray(),
                'action' => $vAction,
            ]);
        }

        try {
            if (in_array($vAction, self::CONFIRMED_ACTIONS, true)) {
                $this->getService(CsrfTokenChecker::class)->checkToken('main', 'POST', 'csrf-token', false);
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
                        $vPackage = $vUpdateService->repository->getPackage((string)$vPackageName);
                        $vNewExtension = $vPackage instanceof PackageTool && !$vPackage->installed;
                        $vUpgradeMessages = $vUpdateService->upgrade($vPackageName);

                        $vMessages->add($vUpgradeMessages);
                        if ($vNewExtension && !$vUpgradeMessages->hasError()) {
                            $this->switchExtension($vMessages, (string)$vPackageName, true);
                        }

                        $this->getService(Redirector::class)->redirect($this->getService(UrlFormatter::class)->href('', '', [
                            'action' => 'post_install',
                            'messages' => json_encode($vMessages->toArray()),
                            'previous_version' => $this->getService(\YesWiki\Kernel\Service\ProgramVersion::class)->releaseLine(),
                        ], false));
                    }
                    break;
                case 'post_install':
                    $vRawMessages = $vSecurityController->filterInput(INPUT_GET, 'messages', FILTER_UNSAFE_RAW, false, 'string');

                    $vMessages->add(empty($vRawMessages) ? [] : (json_decode($vRawMessages, true) ?? []));

                    $vMigrationMessages = $vMigrationService->run();

                    $vMessages->add($vMigrationMessages);
                    break;
                case 'delete':
                    $vDeleteMessages = $vUpdateService->delete($vPackageName);

                    $vMessages->add($vDeleteMessages);
                    if ($this->isExtension($vUpdateService, (string)$vPackageName)) {
                        $this->getService(ExtensionActivation::class)->deactivate((string)$vPackageName, true);
                    }
                    break;
                case 'activate':
                case 'deactivate':
                    $this->switchExtension($vMessages, (string)$vPackageName, $vAction === 'activate');
                    break;
            }
        } catch (TokenNotFoundException $pTokenNotFound) {
            $vMessages->add($pTokenNotFound->getMessage(), 'AU_ERROR');
        } catch (\Throwable $pThrowable) {
            $vMessages->add(_t('PERFORMABLE_ERROR') . $this->getService(ThrowableFormatter::class)->dump($pThrowable), 'AU_ERROR');
        }

        return $this->getService(TemplateEngine::class)->renderSafely('@core/update-result.twig', [
            'messages' => $vMessages->toArray(),
            'action' => $vAction,
        ]);
    }

    private function isExtension(AutoUpdateService $updateService, string $packageName): bool
    {
        return $updateService->repository->getPackage($packageName) instanceof PackageTool;
    }

    /** Switch an extension on or off for this Instance, saying what happened. */
    private function switchExtension(Messages $messages, string $name, bool $on): void
    {
        $activation = $this->getService(ExtensionActivation::class);
        $problems = $on ? $activation->activate($name) : $activation->deactivate($name);
        if ($problems === []) {
            $messages->add(_t($on ? 'AU_EXTENSION_ACTIVATED' : 'AU_EXTENSION_DEACTIVATED', ['name' => $name]), 'AU_OK');

            return;
        }
        foreach ($problems as $problem) {
            $messages->add($problem, 'AU_ERROR');
        }
    }
}
