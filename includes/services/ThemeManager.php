<?php

namespace YesWiki\Core\Service;

use YesWiki\Wiki;
use YesWiki\Core\Service\Performer;
use YesWiki\Core\Service\TemplateEngine;

class ThemeManager
{
    protected $wiki;
    protected $config;
    protected $fileLoaded;
    protected $theme;
    protected $squelette;
    protected $fileContent;
    protected $errorMessage;
    protected $TemplateEngine;
    protected $templateHeader;
    protected $templateFooter;
    protected $Performer;

    public function __construct(Wiki $wiki, TemplateEngine $TemplateEngine, Performer $Performer)
    {
        $this->wiki = $wiki;
        $this->TemplateEngine = $TemplateEngine;
        $this->Performer = $Performer;
        $this->config = $wiki->config;
        $this->fileLoaded = false;
        $this->theme = null;
        $this->squelette = null;
        $this->fileContent = null;
        $this->errorMessage = '';
        $this->templateHeader = '';
        $this->templateFooter = '';
    }

    public function loadTheme(): bool
    {
        // get theme
        $theme = $this->config['favorite_theme'] ?? THEME_PAR_DEFAUT ;

        // get squelette
        $squelette = $this->config['favorite_squelette'] ?? SQUELETTE_PAR_DEFAUT ;

        // do not load the file if already loaded
        $fileAlreadyLoaded = $this->fileLoaded &&
            ($this->theme == $theme) &&
            ($this->squelette == $squelette) ;
        if ($fileAlreadyLoaded) {
            return true;
        }
        $this->theme = $theme;
        $this->squelette = $squelette;

        // test folder
        $themePath = 'themes/'.$this->theme;
        $filePath = $themePath . '/squelettes/' . $this->squelette;

        if (!(file_exists('custom/'.$themePath) || file_exists($themePath))) {
            $this->errorMessage = $this->TemplateEngine->render('@templates\alert-message.twig', [
                    'type' => 'danger',
                    'message' => _t('THEME_MANAGER_THEME_FOLDER') .$this->theme. _t('THEME_MANAGER_NOT_FOUND'),
                ]);
            return false;
        }

        if (!(file_exists('custom/'.$filePath) || file_exists($filePath))) {
            $this->errorMessage = $this->TemplateEngine->render('@templates\alert-message.twig', [
                    'type' => 'danger',
                    'message' => _t('THEME_MANAGER_SQUELETTE_FILE') .$this->squelette. _t('THEME_MANAGER_NOT_FOUND'),
                ]);
            return false;
        }
        $filePath = file_exists('custom/'.$filePath) ? 'custom/'. $filePath : $filePath;

        $fileContent = file_get_contents($filePath);
        if ($fileContent === false) {
            $this->errorMessage = $this->TemplateEngine->render('@templates\alert-message.twig', [
                'type' => 'danger',
                'message' => _t('THEME_MANAGER_ERROR_GETTING_FILE') .$filePath,
            ]);
            return false;
        }
        $this->fileContent = $fileContent;
        $this->fileLoaded = true;

        // On recupere la partie haut du template et on execute les actions wikini
        $templateCut = explode("{WIKINI_PAGE}", $fileContent);
        $this->templateHeader = $templateCut[0] ?? '';
        $this->templateFooter = (count($templateCut) > 0) ? $templateCut[1] : '';

        return true;
    }

    public function getErrorMessage():string
    {
        return $this->errorMessage;
    }

    public function renderHeader():string
    {
        if ($this->fileLoaded || $this->loadTheme()) {
            return $this->renderActions($this->templateHeader);
        } else {
            return '';
        }
    }
    
    public function renderFooter():string
    {
        if ($this->fileLoaded || $this->loadTheme()) {
            return $this->renderActions($this->templateFooter);
        } else {
            return '';
        }
    }

    private function renderActions(string $text):?string
    {
        if ($act = preg_match_all("/".'(\\{\\{)'.'(.*?)'.'(\\}\\})'."/is", $text, $matches)) {
            $i = 0;
            $j = 0;
            foreach ($matches as $valeur) {
                foreach ($valeur as $val) {
                    if (isset($matches[2][$j]) && $matches[2][$j]!='') {
                        $action = $matches[2][$j];
                        $text = str_replace('{{'.$action.'}}', $this->Performer->run('action', 'formatter', compact('{{'.$action.'}}')), $text);
                    }
                    $j++;
                }
                $i++;
            }
        }

        return $text;
    }
}
