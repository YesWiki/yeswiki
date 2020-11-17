<?php

namespace YesWiki\Bazar\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Core\Service\DbService;
use YesWiki\Wiki;

class FormManager
{
    protected $wiki;
    protected $dbService;
    protected $ficheManager;
    protected $params;

    protected $cachedForms;

    private const FIELD_TYPE = 0;
    private const FIELD_ID = 1;
    private const FIELD_LABEL = 2;
    private const FIELD_SIZE = 3;
    private const FIELD_MAX_LENGTH = 4;
    private const FIELD_DEFAULT = 5;
    private const FIELD_PATTERN = 6;
    private const FIELD_SUB_TYPE = 7;
    private const FIELD_REQUIRED = 8;
    private const FIELD_SEARCHABLE = 9;
    private const FIELD_HELP = 10;
    private const FIELD_READ_ACCESS = 11;
    private const FIELD_WRITE_ACCESS = 12;
    private const FIELD_KEYWORDS = 13;
    private const FIELD_SEMANTIC = 14;
    private const FIELD_QUERIES = 15;

    public function __construct(Wiki $wiki, DbService $dbService, FicheManager $ficheManager, ParameterBagInterface $params)
    {
        $this->wiki = $wiki;
        $this->dbService = $dbService;
        $this->ficheManager = $ficheManager;
        $this->params = $params;

        $this->cachedForms = [];
    }

    public function getOne($formId)
    {
        if (isset($this->cachedForms[$formId])) {
            return $this->cachedForms[$formId];
        }

        $form = $this->dbService->loadSingle('SELECT * FROM '.$this->dbService->prefixTable('nature').'WHERE bn_id_nature='.$formId);

        if (!$form) {
            return false;
        }

        foreach ($form as $key => $value) {
            $form[$key] = _convert($value, 'ISO-8859-15');
        }

        $form['template'] = $this->parseTemplate($form['bn_template']);
        $form['prepared'] = $this->prepareData($form);

        $this->cachedForms[$formId] = $form;

        return $form;
    }

    public function getAll()
    {
        $forms = $this->dbService->loadAll('SELECT * FROM '.$this->dbService->prefixTable('nature') . 'ORDER BY bn_label_nature ASC');

        foreach ($forms as $form) {
            $formId = $form['bn_id_nature'];
            $this->cachedForms[$formId] = $this->getOne($formId);
        }

        return count($this->cachedForms) > 0 ? $this->cachedForms : null;
    }

    public function getMany($formsIds)
    {
        $results = [];

        foreach ($formsIds as $formId) {
            if (!$this->cachedForms[$formId]) {
                $this->cachedForms[$formId] = $this->getOne($formId);
            }
            $results[$formId] = $this->cachedForms[$formId];
        }

        return $results;
    }

    /**
     * Découpe le template et renvoie un tableau structuré
     *
     * @param  string  Template du formulaire
     * @return  mixed   Le tableau des elements du formulaire et options pour l'element liste
     */
    public function parseTemplate($raw)
    {
        //Parcours du template, pour mettre les champs du formulaire avec leurs valeurs specifiques
        $tableau_template = array();
        $nblignes = 0;

        //on traite le template ligne par ligne
        $chaine = explode("\n", $raw);
        foreach ($chaine as $ligne) {
            $ligne = trim($ligne);
            // on ignore les lignes vides ou commencant par # (commentaire)
            if (!empty($ligne) && !(strrpos($ligne, '#', -strlen($ligne)) !== false)) {
                //on decoupe chaque ligne par le separateur *** (c'est historique)
                $tablignechampsformulaire = array_map("trim", explode("***", $ligne));
                if (function_exists($tablignechampsformulaire[0])) {
                    if (count($tablignechampsformulaire) > 3) {
                        $tableau_template[$nblignes] = $tablignechampsformulaire;
                        for ($i=0; $i < 14; $i++) {
                            if (!isset($tableau_template[$nblignes][$i])) {
                                $tableau_template[$nblignes][$i] = '';
                            }
                        }
                        $nblignes++;
                    }
                }
            }
        }

        return $tableau_template;
    }

    public function prepareData($form)
    {
        $i = 0;
        $prepared = $result = [];

        $form['template'] = _convert($form['template'], 'ISO-8859-15');

        foreach ($form['template'] as $field) {

            /*
             * DEFAULT VALUES
             */

            // champs obligatoire
            if ($field[self::FIELD_REQUIRED]==1) {
                $prepared[$i]['required'] = true;
            } else {
                $prepared[$i]['required'] = false;
            }

            // texte d'invitation à la saisie
            $prepared[$i]['label'] = $field[self::FIELD_LABEL];

            // attributs html du champs
            $prepared[$i]['attributes'] = '';

            // valeurs associées
            $prepared[$i]['values'] = '';

            // texte d'aide
            $prepared[$i]['helper'] = $field[self::FIELD_HELP];

            /*
             * TYPES-SPECIFIC VALUES
             */

            switch($field[self::FIELD_TYPE]) {
                case 'radio':
                case 'liste':
                case 'checkbox':
                case 'listefiche':
                case 'checkboxfiche':
                    $prepared[$i]['id'] = $field[self::FIELD_TYPE] . $field[self::FIELD_ID] . $field[6];

                    // type de champ
                    if (in_array($field[self::FIELD_TYPE], array('listefiche', 'liste'))) {
                        $prepared[$i]['type'] = 'select';
                    } elseif (in_array($field[self::FIELD_TYPE], array('checkboxfiche', 'checkbox'))) {
                        $prepared[$i]['type'] = 'checkbox';
                    } else {
                        $prepared[$i]['type'] = 'radio';
                    }

                    // valeurs associées
                    if (in_array($field[self::FIELD_TYPE], array('radio', 'liste', 'checkbox'))) {
                        $prepared[$i]['values'] = baz_valeurs_liste($field[self::FIELD_ID]);
                        $prepared[$i]['values']['id'] = $field[self::FIELD_ID];
                    } else {
                        $tabquery = array();
                        if (!empty($field[self::FIELD_QUERIES])) {
                            $tableau = array();
                            $tab = explode('|', $field[self::FIELD_QUERIES]);
                            //découpe la requete autour des |
                            foreach ($tab as $req) {
                                $tabdecoup = explode('=', $req, 2);
                                $tableau[$tabdecoup[0]] = isset($tabdecoup[1]) ? trim($tabdecoup[1]) : '';
                            }
                            $tabquery = array_merge($tabquery, $tableau);
                        } else {
                            $tabquery = '';
                        }
                        $hash = md5($field[self::FIELD_ID] . serialize($tabquery));
                        if (!isset($result[$hash])) {
                            $result[$hash] = $this->ficheManager->search([
                                'queries' => $tabquery,
                                'formsIds' => $field[self::FIELD_ID],
                                'keywords' => (!empty($field[self::FIELD_KEYWORDS])) ? $field[self::FIELD_KEYWORDS] : ''
                            ]);
                        }
                        $prepared[$i]['values']['titre_liste'] = $field[self::FIELD_LABEL];
                        foreach ($result[$hash] as $values) {
                            $prepared[$i]['values']['label'][$values['id_fiche']] = $values['bf_titre'];
                        }
                    }
                    break;

                case 'texte':
                case 'textelong':
                case 'jour':
                case 'listedatedeb':
                case 'listedatefin':
                case 'mot_de_passe':
                case 'lien_internet':
                case 'champs_mail':
                    $prepared[$i]['id'] = $field[self::FIELD_ID];

                    // type de champ
                    if (!empty($field[self::FIELD_SUB_TYPE]) && in_array(
                            $field[self::FIELD_SUB_TYPE],
                            ['text', 'date', 'email', 'url', 'range', 'password', 'number']
                        )) {
                        $prepared[$i]['type'] = $field[self::FIELD_SUB_TYPE];
                    } elseif ($field[self::FIELD_TYPE] === 'texte') {
                        $prepared[$i]['type'] = 'text';
                    } elseif ($field[self::FIELD_TYPE] === 'textelong') {
                        $prepared[$i]['type'] = 'textarea';
                    } elseif (in_array($field[self::FIELD_TYPE], ['jour', 'listedatedeb', 'listedatefin'])) {
                        $prepared[$i]['type'] = 'date';
                    } elseif ($field[self::FIELD_TYPE] === 'champs_mail') {
                        $prepared[$i]['type'] = 'email';
                    } elseif ($field[self::FIELD_TYPE] === 'lien_internet') {
                        $prepared[$i]['type'] = 'url';
                    } elseif ($field[self::FIELD_TYPE] === 'mot_de_passe') {
                        $prepared[$i]['type'] = 'password';
                    }

                    // attributs html du champs
                    if ($field[self::FIELD_TYPE] === 'texte') {
                        if (in_array($field[self::FIELD_SUB_TYPE], array('range', 'number'))) {
                            $prepared[$i]['attributes'] .= ($field[self::FIELD_SIZE] != '') ? ' min="'.$field[self::FIELD_SIZE].'"' : '';
                            $prepared[$i]['attributes'] .= ' max="'.$field[self::FIELD_MAX_LENGTH].'"';
                        } else {
                            $prepared[$i]['attributes'] .= ' maxlength="'.$field[self::FIELD_MAX_LENGTH].'" size="'.$field[self::FIELD_MAX_LENGTH].'"';
                        };
                    } elseif ($field[self::FIELD_TYPE] === 'textelong') {
                        $prepared[$i]['attributes'] .= ' rows="' . $field[self::FIELD_MAX_LENGTH] . '"';
                    }
                    $prepared[$i]['attributes'] .= ($field[self::FIELD_PATTERN] != '') ? ' pattern="' . $field[self::FIELD_PATTERN] . '"' : '';

                    break;

                case 'fichier':
                case 'image':
                    $prepared[$i]['id'] = $field[self::FIELD_TYPE].$field[self::FIELD_ID];
                    $prepared[$i]['type'] = 'file';
                    if ($field[self::FIELD_TYPE] === 'image') {
                        $prepared[$i]['attributes'] .= ' accept="image/*"';
                    }
                    break;

                case 'champs_cache':
                case 'titre':
                    if ($field[self::FIELD_TYPE] == 'titre') {
                        $prepared[$i]['id'] = 'bf_titre';
                        $prepared[$i]['values'] = $field[self::FIELD_ID];
                    } else {
                        $prepared[$i]['id'] = $field[self::FIELD_ID];
                        $prepared[$i]['values'] = $field[self::FIELD_LABEL];
                    }
                    $prepared[$i]['type'] = 'hidden';
                    $prepared[$i]['label'] = '';
                    $prepared[$i]['required'] = '';
                    $prepared[$i]['helper'] = '';
                    break;

                case 'labelhtml':
                    $prepared[$i]['id'] = '';
                    $prepared[$i]['type'] = 'html';
                    $prepared[$i]['required'] = '';
                    $prepared[$i]['values'] = $field[self::FIELD_SIZE];
                    $prepared[$i]['helper'] = '';
                    break;

                case 'carte_google':
                    $prepared[$i]['id'] = '';
                    $prepared[$i]['type'] = 'map';
                    $prepared[$i]['label'] = '';
                    $prepared[$i]['helper'] = '';
                    break;

                case 'inscriptionliste':
                    $prepared[$i]['id'] = str_replace(['@', '.'], ['', ''], $field[self::FIELD_ID]);
                    $prepared[$i]['type'] = 'listsubscribe';
                    $prepared[$i]['required'] = '';
                    $prepared[$i]['values'] = $field[self::FIELD_ID];
                    $prepared[$i]['helper'] = '';
                    break;

                case 'utilisateur_wikini':
                    $prepared[$i]['id'] = $field[self::FIELD_ID];
                    $prepared[$i]['type'] = 'wikiuser';
                    $prepared[$i]['label'] = '';
                    $prepared[$i]['required'] = '';

                    break;
            }

            // traitement sémantique
            if (!empty($field[self::FIELD_SEMANTIC])) {
                $prepared[$i]['sem_type'] = strpos($field[self::FIELD_SEMANTIC], ',')
                    ? array_map(function ($str) {
                        return trim($str);
                    }, explode(',', $field[self::FIELD_SEMANTIC]))
                    : $field[self::FIELD_SEMANTIC];
            }

            $i++;
        }
        return $prepared;
    }
}
