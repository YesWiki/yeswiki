<?php

namespace YesWiki\AutoUpdate\Service;

use YesWiki\AutoUpdate\Entity\Messages;
use YesWiki\Core\Service\ConfigurationService;
use YesWiki\Wiki;

class PostInstallService
{
  private $wiki;
  private $confService;
  private $updateService;

  public function __construct(Wiki $wiki, ConfigurationService $confService, AutoUpdateService $updateService)
  {
    $this->wiki = $wiki;
    $this->confService = $confService;
    $this->updateService = $updateService;
  }

  public function run()
  {
    $messages = new Messages();
    $this->fixUnknownRelease($messages);
    $this->cercopitequePostInstall($messages);
    return $messages;
  }

  private function fixUnknownRelease($messages)
  {
    $params = $this->wiki->services->getParameterBag();
    $releaseInConfig = $params->get('yeswiki_release');
    if ($releaseInConfig == _t('AU_UNKNOW') || !preg_match("/^\d{1,4}[.-].*/", $releaseInConfig)) {
      $config = $this->confService->getConfiguration('wakka.config.php');
      $config->load();
      $config['yeswiki_release'] = YESWIKI_RELEASE;
      $config->write();
      $messages->add('yeswiki_release updated in wakka.config', 'AU_OK');
    }
  }

  private function cercopitequePostInstall($messages)
  {
    if (empty($_GET['previous_version']) || $_GET['previous_version'] != 'cercopitheque') {
      return;
    }

    $messages->add('AU_YESWIKI_DORYPHORE_POSTINSTALL', 'AU_OK');

    // check favorite_theme in wakka.config.php
    $config = $this->confService->getConfiguration('wakka.config.php');
    $config->load();
    $favoriteThemefromFile = $config['favorite_theme'] ?? '';

    // If default theme was used, install new yeswikicerco extension to keep same look and feel
    if (empty($favoriteThemefromFile) || $favoriteThemefromFile == 'yeswiki') {
      $upgradeThemeMessages = $this->updateService->upgrade('yeswikicerco');

      if (!empty($upgradeThemeMessages)) {
        if (empty($config['favorite_theme'])) {
          $config['favorite_theme'] = 'yeswikicerco';
          $config['favorite_style'] = 'gray.css';
          $config['favorite_squelette'] = 'responsive-1col.tpl.html';
        } else {
          $config['favorite_theme'] = 'yeswikicerco';
          $config['favorite_style'] = $config['favorite_style'] ?? 'gray.css';
          $config['favorite_squelette'] = $config['favorite_squelette'] ?? 'responsive-1col.tpl.html';
        }
        $config->write();

        $messages->add('=== yeswikicerco theme update ===', 'AU_OK');
      }
    }
  }
}