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

    /* function imported from tooles/templates/libs/templates.functions.php
     * to load templates and generate an error if needed
     *
     * @param $metadata metadata fr the current Page
     * @return array of templates
     */
    public function loadTemplates($metadata = []): ?array
    {
        // Premier cas le template par défaut est forcé : on ajoute ce qui est présent dans le fichier de configuration, ou le theme par defaut précisé ci dessus
        if (isset($this->config['hide_action_template']) && $this->config['hide_action_template'] == '1') {
            if (!isset($this->config['favorite_theme'])) {
                $this->config['favorite_theme'] = THEME_PAR_DEFAUT;
            }
            if (!isset($this->config['favorite_style'])) {
                $this->config['favorite_style'] = CSS_PAR_DEFAUT;
            }
            if (!isset($this->config['favorite_squelette'])) {
                $this->config['favorite_squelette'] = SQUELETTE_PAR_DEFAUT;
            }
            if (!isset($this->config['favorite_background_image'])) {
                $this->config['favorite_background_image'] = BACKGROUND_IMAGE_PAR_DEFAUT;
            }
        } else {
            // Sinon, on récupère premièrement les valeurs passées en REQUEST, ou deuxièmement les métasdonnées présentes pour la page, ou troisièmement les valeurs du fichier de configuration
            if (isset($_REQUEST['theme']) && (is_dir('custom/themes/'.$_REQUEST['theme']) || is_dir('themes/'.$_REQUEST['theme'])) &&
                isset($_REQUEST['style']) && (is_file('custom/themes/'.$_REQUEST['theme'].'/styles/'.$_REQUEST['style']) || is_file('themes/'.$_REQUEST['theme'].'/styles/'.$_REQUEST['style'])) &&
                isset($_REQUEST['squelette']) && (is_file('custom/themes/'.$_REQUEST['theme'].'/squelettes/'.$_REQUEST['squelette']) || is_file('themes/'.$_REQUEST['theme'].'/squelettes/'.$_REQUEST['squelette']))
            ) {
                $this->config['favorite_theme'] = $_REQUEST['theme'];
                $this->config['favorite_style'] = $_REQUEST['style'];
                $this->config['favorite_squelette'] = $_REQUEST['squelette'];

                if (isset($_REQUEST['bgimg']) && (is_file('files/backgrounds/'.$_REQUEST['bgimg']))) {
                    $this->config['favorite_background_image'] = $_REQUEST['bgimg'];
                } else {
                    $this->config['favorite_background_image'] = BACKGROUND_IMAGE_PAR_DEFAUT;
                }
            } else {
                // si les metas sont présentes on les utilise
                if (isset($metadata['theme']) && isset($metadata['style']) && isset($metadata['squelette'])) {
                    $this->config['favorite_theme'] = $metadata['theme'];
                    $this->config['favorite_style'] = $metadata['style'];
                    $this->config['favorite_squelette'] = $metadata['squelette'];
                    if (isset($metadata['bgimg'])) {
                        $this->config['favorite_background_image'] = $metadata['bgimg'];
                    } else {
                        $this->config['favorite_background_image'] = '';
                    }
                } else {
                    if (!isset($this->config['favorite_theme'])) {
                        $this->config['favorite_theme'] = THEME_PAR_DEFAUT;
                    }
                    if (!isset($this->config['favorite_style'])) {
                        $this->config['favorite_style'] = CSS_PAR_DEFAUT;
                    }
                    if (!isset($this->config['favorite_squelette'])) {
                        $this->config['favorite_squelette'] = SQUELETTE_PAR_DEFAUT;
                    }
                    if (!isset($this->config['favorite_background_image'])) {
                        $this->config['favorite_background_image'] = BACKGROUND_IMAGE_PAR_DEFAUT;
                    }
                }
            }
        }

        // Test existence du template, on utilise le template par defaut sinon==============================
        if (
            (!file_exists('custom/themes/'.$this->config['favorite_theme'].'/squelettes/'.$this->config['favorite_squelette'])
                and !file_exists('themes/'.$this->config['favorite_theme'].'/squelettes/'.$this->config['favorite_squelette']))
            || (!file_exists('custom/themes/'.$this->config['favorite_theme'].'/styles/'.$this->config['favorite_style'])
                && !file_exists('themes/'.$this->config['favorite_theme'].'/styles/'.$this->config['favorite_style']))
        ) {
            if (
                $this->config['favorite_theme'] != THEME_PAR_DEFAUT ||
                (
                    $this->config['favorite_theme'] == THEME_PAR_DEFAUT && (!file_exists('themes/'.THEME_PAR_DEFAUT.'/squelettes/'.$this->config['favorite_squelette'])  or
                        !file_exists('themes/'.THEME_PAR_DEFAUT.'/styles/'.$this->config['favorite_style']))
                )
            ) {
                if (
                    file_exists('themes/'.THEME_PAR_DEFAUT.'/squelettes/'.SQUELETTE_PAR_DEFAUT)
                    && file_exists('themes/'.THEME_PAR_DEFAUT.'/styles/'.CSS_PAR_DEFAUT)
                ) {
                    $GLOBALS['template-error']['type'] = 'theme-not-found';
                    $GLOBALS['template-error']['theme'] = $this->config['favorite_theme'];
                    $GLOBALS['template-error']['style'] = $this->config['favorite_style'];
                    $GLOBALS['template-error']['squelette'] = $this->config['favorite_squelette'];
                    $this->config['favorite_theme'] = THEME_PAR_DEFAUT;
                    $this->config['favorite_style'] = CSS_PAR_DEFAUT;
                    $this->config['favorite_squelette'] = SQUELETTE_PAR_DEFAUT;
                    $this->config['favorite_background_image'] = BACKGROUND_IMAGE_PAR_DEFAUT;
                } else {
                    return [];
                }
            }
            $this->config['use_fallback_theme'] = true;
        }

        $templates = [];

        // themes folder (used by {{update}})
        if (is_dir('themes')) {
            $templates = array_merge($templates, search_template_files('themes'));
        }
        // custom themes folder
        if (is_dir('custom/themes')) {
            $templates = array_merge($templates, search_template_files('custom/themes'));
        }
        ksort($templates);

        // update config
        $this->wiki->config = $this->config;

        return $templates;
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

        $useFallbackTheme = !empty($this->config['use_fallback_theme']) ;

        if (!((!$useFallbackTheme && file_exists('custom/'.$themePath)) || file_exists($themePath))) {
            $this->errorMessage = $this->TemplateEngine->render('@templates\alert-message.twig', [
                    'type' => 'danger',
                    'message' => _t('THEME_MANAGER_THEME_FOLDER') .$this->theme. _t('THEME_MANAGER_NOT_FOUND'),
                ]);
            return false;
        }

        if (!((!$useFallbackTheme &&file_exists('custom/'.$filePath)) || file_exists($filePath))) {
            $this->errorMessage = $this->TemplateEngine->render('@templates\alert-message.twig', [
                    'type' => 'danger',
                    'message' => _t('THEME_MANAGER_SQUELETTE_FILE') .$this->squelette. _t('THEME_MANAGER_NOT_FOUND'),
                ]);
            return false;
        }
        $filePath = (!$useFallbackTheme && file_exists('custom/'.$filePath)) ? 'custom/'. $filePath : $filePath;

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
                        $text = str_replace('{{'.$action.'}}', $this->Performer->run('action', 'formatter', ['text'=>'{{'.$action.'}}']), $text);
                    }
                    $j++;
                }
                $i++;
            }
        }

        return $text;
    }
}
