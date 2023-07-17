<?php

namespace YesWiki\Core\Controller;

use YesWiki\Core\YesWikiController;

class InstallationController extends YesWikiController
{
    protected $step;
    protected $config;

    public function __construct() {
        $this->step = $this->getInstallationStep();

        // default lang
        loadpreferredI18n('');
        $this->config = $wakkaConfig = \YesWiki\Init::getConfig();
        //$wakkaConfigLocation = self::configFile;
        include_once 'setup/install.helpers.php';
        include_once 'setup/header.php';
        if (file_exists('setup/' . $this->step . '.php')) {
            include_once 'setup/' . $this->step . '.php';
        } else {
            echo '<em>', _t("INVALID_ACTION"), '</em>';
        }
        include_once 'setup/footer.php';
    }

    public function show()
    {
        return new Response($this->render('@core/installation.twig', [
          'config' => $this->wiki->config,
          'i18n' => $GLOBALS['translations_js'],
          'locale' => $GLOBALS['prefered_language'],
          'extensions' => $this->getInstallationStep()
        ]));
    }

    private function getInstallationStep($step = 'default'): string
    {
        if (! isset($_REQUEST['installAction']) or ! $installAction = trim($_REQUEST['installAction'])) {
            $installAction = 'default';
        }
        return $installAction;
    }
}
