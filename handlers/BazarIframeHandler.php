<?php

use YesWiki\Bazar\Service\EntryManager;

class BazarIframeHandler extends \YesWiki\Core\YesWikiHandler
{

    public function run()
    {
        // usefull for the widgets, display in a iframe the entry of a given form id (before it was included in the
        // iframe handler)

        // on recupere les entetes html mais pas ce qu'il y a dans le body
        $header = explode('<body', $this->wiki->Header());
        $output = $header[0];

        if ($this->wiki->HasAccess("read")) {
            if (empty($_GET['id'])) {
                $output .= '<div class="alert alert-danger">' . _t('BAZ_PAS_D_ID_DE_FICHE_INDIQUEE') . '</div>';
            } else {
                $entryManager = $this->wiki->services->get(EntryManager::class);

                // si le parametre id est passé, on souhaite afficher une liste bazar
                // TODO : factoriser avec bazarliste?

                // on compte le nombre de fois que l'action bazarliste est appelée afin de différencier les instances
                if (!isset($GLOBALS['_BAZAR_']['nbbazarliste'])) {
                    $GLOBALS['_BAZAR_']['nbbazarliste'] = 0;
                }
                ++$GLOBALS['_BAZAR_']['nbbazarliste'];
                // Recuperation de tous les parametres
                $params = getAllParameters($this->wiki);

                // chaine de recherche
                $q = '';
                if (isset($_GET['q']) and !empty($_GET['q'])) {
                    $q = $_GET['q'];
                }

                // tableau des fiches correspondantes aux critères
                if (is_array($params['idtypeannonce'])) {
                    // TODO see if we could use multiple form IDs, as is allowed by search function
                    $results = array();
                    foreach ($params['idtypeannonce'] as $formId) {
                        $results = array_merge(
                            $results,
                            $entryManager->search([
                                'queries' => $params['query'],
                                'formsIds' => [$formId],
                                'keywords' => $q
                            ])
                        );
                    }
                } else {
                    $results = $entryManager->search([
                        'queries' => $params['query'],
                        'formsIds' => [$params['idtypeannonce']],
                        'keywords' => $q
                    ]);
                }

                // affichage à l'écran de la liste bazar
                $bazaroutput = displayResultList($results, $params, false);
                if (isset($_GET['iframelinks']) && $_GET['iframelinks'] == '0') {
                    // pas de modification des urls
                    $output .= $bazaroutput;
                } else {
                    $output .= replaceLinksWithIframe($bazaroutput);
                }
            }
        } else {
            // if no read access to the page

            // on recupere les entetes html mais pas ce qu'il y a dans le body
            $output .= '<body class="yeswiki-iframe-body login-body">' . "\n"
                . '<div class="container">' . "\n"
                . '<div class="yeswiki-page-widget page-widget page" ' . $this->wiki->Format('{{doubleclic iframe="1"}}')
                . '>' . "\n";

            if ($contenu = $this->wiki->LoadPage("PageLogin")) {
                // si une page PageLogin existe, on l'affiche
                $output .= $this->wiki->Format($contenu["body"]);
            } else {
                // sinon on affiche le formulaire d'identification minimal
                $output .= '<div class="vertical-center white-bg">' . "\n"
                    . '<div class="alert alert-danger alert-error">' . "\n"
                    . _t('LOGIN_NOT_AUTORIZED') . '. ' . _t('LOGIN_PLEASE_REGISTER') . '.' . "\n"
                    . '</div>' . "\n"
                    . $this->wiki->Format('{{login signupurl="0"}}' . "\n\n")
                    . '</div><!-- end .vertical-center -->' . "\n";
            }
        }

        // common footer for all iframe page
        $output .= '</div><!-- end .page-widget -->' . "\n";

        // on affiche la barre de modification, si on ajoute &edit=1 à l'url de l'iframe
        if (isset($_GET['edit']) && $_GET['edit'] == '1') {
            $output .= $this->wiki->Format('{{barreredaction}}');
        }
        $output .= '</div><!-- end .container -->' . "\n";
        $this->wiki->AddJavascriptFile('tools/templates/libs/vendor/iframeResizer.contentWindow.min.js');
        // on recupere juste les javascripts et la fin des balises body et html
        $output .= preg_replace('/^.+<script/Us', '<script', $this->wiki->Footer());

        return $output;
    }
}