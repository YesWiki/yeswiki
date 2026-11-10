<?php

namespace YesWiki\Admin\Service;

use YesWiki\Admin\Entity\Package;
use YesWiki\Admin\Entity\PackageCore;
use YesWiki\Kernel\Entity\Messages;

/** Downloads a package, checks it, and copies it where it installs: the steps the update screen and an image build share, with no wiki needed. */
class PackageInstaller
{
    /** Installs or upgrades $package, saying what each step did; $packageName names it when the repository has no such package. */
    public function install(?Package $package, string $packageName): Messages
    {
        $messages = new Messages();
        $file = $package ? $package->getFile() : false;
        if ($package === null || false === $file) {
            $messages->add('AU_DOWNLOAD', 'AU_ERROR');

            return $messages;
        }
        $messages->add('AU_DOWNLOAD', 'AU_OK');

        if (!$package->checkIntegrity()) {
            $messages->add('AU_INTEGRITY', 'AU_ERROR');
            $package->cleanTempFiles();

            return $messages;
        }
        $messages->add('AU_INTEGRITY', 'AU_OK');

        $path = $package->extract();
        if (false === $path) {
            $messages->add('AU_EXTRACT', 'AU_ERROR');
            $package->cleanTempFiles();

            return $messages;
        }
        $messages->add('AU_EXTRACT', 'AU_OK');

        $vNotGoods = $package->checkACL();

        if (count($vNotGoods) > 0) {
            $vMaxLength = 100;
            $vFilesList = implode(', ', $vNotGoods);

            $messages[] = ['text' => (_t('AU_ACL') . '\n' . _t('AU_NOT_WRITABLE_FILES') . substr($vFilesList, 0, 100) . (strlen($vFilesList) > $vMaxLength ? '...' : '')), 'status' => _t('AU_ERROR')];

            $package->cleanTempFiles();

            return $messages;
        }
        $messages->add('AU_ACL', 'AU_OK');

        if (!$package->upgrade()) {
            $failure = $package->copyFailure();
            $why = $failure === null ? '' : ' : ' . _t('AU_COPY_FAILED_' . $failure['step'], ['path' => $failure['path']])
                . ($failure['reason'] === '' ? '' : ' (' . $failure['reason'] . ')');
            $messages->add(
                _t('AU_UPDATE_PACKAGE') . $packageName . $why,
                'AU_ERROR'
            );
            $package->cleanTempFiles();

            return $messages;
        }
        $messages->add(_t('AU_UPDATE_PACKAGE') . $packageName, 'AU_OK');

        if ($package instanceof PackageCore) {
            if (!$package->upgradeDefaultTheme()) {
                $messages->add('AU_UPDATE_THEME', 'AU_ERROR');
                $package->cleanTempFiles();

                return $messages;
            }
            $messages->add('AU_UPDATE_THEME', 'AU_OK');

            if (!$package->upgradeTools()) {
                $messages->add('AU_UPDATE_TOOL', 'AU_ERROR');
                $package->cleanTempFiles();

                return $messages;
            }
            $messages->add('AU_UPDATE_TOOL', 'AU_OK');
        }

        if (!$package->upgradeInfos()) {
            $messages->add('AU_UPDATE_INFOS', 'AU_ERROR');
            $package->cleanTempFiles();

            return $messages;
        }
        $messages->add('AU_UPDATE_INFOS', 'AU_OK');

        $package->cleanTempFiles();

        return $messages;
    }
}
