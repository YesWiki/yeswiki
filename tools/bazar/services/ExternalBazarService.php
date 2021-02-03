<?php

namespace YesWiki\Bazar\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Wiki;

class ExternalBazarService
{
    public function __construct(Wiki $wiki, ParameterBagInterface $params)
    {
        $this->wiki = $wiki;
        $this->params = $params;
    }

    public function getForm($url, $formId) : ?array
    {
        $url= $this->formatUrl($url);

        $json = getCachedUrlContent($url.'?BazaR/json&demand=forms&id='.$formId);
        $forms = json_decode($json, true);

        if ($forms) {
            return $forms[0];
        } else {
            echo '<div class="alert alert-danger">Erreur ExternalWikiService::getForm: contenu du formulaire mal formaté.</div>';
        }
    }

    public function getForms($url) : ?array
    {
        $url= $this->formatUrl($url);

        $json = getCachedUrlContent($url.'?BazaR/json&demand=forms');
        $forms = json_decode($json, true);

        if ($forms) {
            return $forms;
        } else {
            echo '<div class="alert alert-danger">Erreur ExternalWikiService::getForms: contenu des formulaires mal formaté.</div>';
        }
    }

    public function getEntries($params)
    {
        // Merge les paramètres passé avec des paramètres par défaut
        $params = array_merge(
            [
                'url' => '', // URL du wiki où récupérer les fiches
                'queries' => '', // Sélection par clé-valeur
                'formsIds' => [], // Types de fiches (par ID de formulaire)
            ],
            $params
        );

        if (empty($params['url'])) {
            exit('<div class="alert alert-danger">Action bazarlisteexterne : parametre url obligatoire.</div>');
        }

        $params['url'] = $this->formatUrl($params['url']);

        // Formattage des queries
        $querystring = '';
        if (is_array($params['queries'])) {
            foreach ($params['queries'] as $key => $value) {
                if (is_array($value)) {
                    $value = implode(',', $value);
                }
                $querystring .= $key.'='.$value.'|';
            }
            $querystring = '&query='.htmlspecialchars(substr($querystring, 0, -1));
        }

        $json = getCachedUrlContent($params['url'].'?BazaR/json&demand=entries&form='.$params['formsIds'][0].$querystring);
        $entries = json_decode($json, true);

        if ($entries) {
            return $entries;
        } else {
            echo '<div class="alert alert-danger">Erreur ExternalWikiService::getEntries: contenu des fiches mal formaté.</div>';
        }
    }

    private function formatUrl($url)
    {
        $arr = explode("/wakka.php", $url, 2);
        $arr = explode("/?", $arr[0], 2);
        return $arr[0];
    }
}
