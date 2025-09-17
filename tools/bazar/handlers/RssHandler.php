<?php

use YesWiki\Bazar\Controller\EntryController;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\SearchManager;
use YesWiki\Core\YesWikiHandler;
use YesWiki\Security\Controller\SecurityController;

// TODO use Symfony XmlEncoder instead
// https://symfony.com/doc/current/components/serializer.html#the-xmlencoder
class RssHandler extends YesWikiHandler
{
    public function run()
    {
        try {
            if (!$this->wiki->HasAccess('read') || !$this->wiki->page) {
                return null;
            }

            $securityController = $this->getService(SecurityController::class);

            if (isset($_GET['id'])) {
                $id = $securityController->filterInput(INPUT_GET, 'id', FILTER_DEFAULT, true, 'string', ['flags' => FILTER_FORCE_ARRAY]);
            } elseif (isset($_GET['id_typeannonce'])) {
                $id = $securityController->filterInput(INPUT_GET, 'id_typeannonce', FILTER_DEFAULT, true, 'string', ['flags' => FILTER_FORCE_ARRAY]);
            }

            if ($id == null) {
                $vIDsArray = [];
            } else {
                // $id a été transformé en tableau ci dessus - cf PR précédente - on garde l'algorithme et on rajoute, pour plus de résilience, la possibilité
                // d'une spécification des ids séparés par des virgules (ex : id=1,2) ou des ids en tableau id[0]=1&id[0]=2
                // On extrait donc les élements du tableau séparés par des virgules afin d'avoir un tableau standardisé d'ids unique.

                $vIDsArray = [];

                foreach ($id as $vID) {
                    if (strpos($vID, ',') !== false) {
                        // S'il y a des virgules, on explode et merge
                        $vIDsArray = array_merge($vIDsArray, array_map('trim', explode(',', $vID)));
                    } else {
                        // Sinon on ajoute simplement l'élément
                        $vIDsArray[] = $vID;
                    }
                }

                foreach ($vIDsArray as $vID) {
                    if (strval($vID) != strval(intval($vID))) {
                        $vIDsArray = '';
                        break;
                    }
                }
            }

            if (isset($_GET['nbitem'])) {
                $nbitem = $_GET['nbitem'];
            } else {
                $nbitem = $this->wiki->config['BAZ_NB_ENTREES_FLUX_RSS'];
            }

            if (isset($_GET['utilisateur'])) {
                $utilisateur = $_GET['utilisateur'];
            } else {
                $utilisateur = '';
            }

            // chaine de recherche

            $vKeywords = $this->getService(SearchManager::class)->aggregateKeywords(
                isset($_GET['q']) ? urldecode($_GET['q']) : null,
                isset($_GET['keywords']) ? urldecode($_GET['keywords']) : null
            );

            $vSearchFields = isset($_GET['searchfields']) ? urldecode($_GET['searchfields']) : null;

            $vQuery = $this->getService(SearchManager::class)->parseQuery(isset($_GET['query']) ? urldecode($_GET['query']) : '');

            // ordre
            $ordre = 'desc';

            // champ
            $champ = 'date_creation_fiche';

            $tableau_flux_rss = $this->getService(EntryManager::class)->search(
                [
                    'queries' => $vQuery,
                    'formsIds' => $vIDsArray,
                    'user' => $utilisateur,
                    'keywords' => $vKeywords,
                    'searchfields' => $vSearchFields,
                ],
                true, // filter on read ACL
            true  // use Guard
            );

            $GLOBALS['ordre'] = $ordre;
            $GLOBALS['champ'] = $champ;
            usort($tableau_flux_rss, 'champCompare');

            // Limite le nombre de résultat au nombre de fiches demandées
            $tableau_flux_rss = array_slice($tableau_flux_rss, 0, $nbitem);

            // setlocale() pour avoir les formats de date valides (w3c) --julien
            setlocale(LC_TIME, 'C');

            $xml = XML_Util::getXMLDeclaration('1.0', 'UTF-8', 'yes');
            $xml .= "\r\n  ";
            $xml .= XML_Util::createStartElement('rss', ['version' => '2.0',
                'xmlns:atom' => 'http://www.w3.org/2005/Atom', 'xmlns:dc' => 'http://purl.org/dc/elements/1.1/', ]);
            $xml .= "\r\n    ";
            $xml .= XML_Util::createStartElement('channel');
            $xml .= "\r\n      ";
            $xml .= XML_Util::createTag('title', null, $this->sanitize(_t('BAZ_DERNIERE_ACTU')));
            $xml .= "\r\n      ";
            $xml .= XML_Util::createTag('link', null, $this->sanitize($this->wiki->config['BAZ_RSS_ADRESSESITE']));
            $xml .= "\r\n      ";
            $xml .= XML_Util::createTag('description', null, $this->sanitize($this->wiki->config['BAZ_RSS_DESCRIPTIONSITE']));
            $xml .= "\r\n      ";
            $xml .= XML_Util::createTag('language', null, 'fr-FR');
            $xml .= "\r\n      ";
            $xml .= XML_Util::createTag('copyright', null, 'Copyright (c) ' . date('Y') . ' ' . htmlentities(removeAccents($this->wiki->config['BAZ_RSS_NOMSITE'])));
            $xml .= "\r\n      ";
            $xml .= XML_Util::createTag('lastBuildDate', null, date('r'));
            $xml .= "\r\n      ";
            $xml .= XML_Util::createTag('docs', null, 'http://www.stervinou.com/projets/rss/');
            $xml .= "\r\n      ";
            $xml .= XML_Util::createTag('category', null, $this->wiki->config['BAZ_RSS_CATEGORIE']);
            $xml .= "\r\n      ";
            $xml .= XML_Util::createTag('managingEditor', null, $this->wiki->config['BAZ_RSS_MANAGINGEDITOR']);
            $xml .= "\r\n      ";
            $xml .= XML_Util::createTag('webMaster', null, $this->wiki->config['BAZ_RSS_WEBMASTER']);
            $xml .= "\r\n      ";
            $xml .= XML_Util::createTag('ttl', null, '60');
            $xml .= "\r\n      ";
            $xml .= XML_Util::createStartElement('image');
            $xml .= "\r\n        ";
            $xml .= XML_Util::createTag('title', null, $this->sanitize(_t('BAZ_DERNIERE_ACTU')));
            $xml .= "\r\n        ";
            $xml .= XML_Util::createTag('url', null, $this->wiki->config['BAZ_RSS_LOGOSITE']);
            $xml .= "\r\n        ";
            $xml .= XML_Util::createTag('link', null, $this->wiki->config['BAZ_RSS_ADRESSESITE']);
            $xml .= "\r\n      ";
            $xml .= XML_Util::createEndElement('image');

            if (count($tableau_flux_rss) > 0) {
                // Creation des items : titre + lien + description + date de publication
                foreach ($tableau_flux_rss as $ligne) {
                    $xml .= "\r\n      ";
                    $xml .= XML_Util::createStartElement('item');
                    $xml .= "\r\n        ";
                    $xml .= XML_Util::createTag('title', null, str_replace('&', '&amp;', $this->sanitize($ligne['bf_titre'])));
                    $xml .= "\r\n        ";
                    $xml .= XML_Util::createTag('link', null, '<![CDATA[' . $this->wiki->href('', $ligne['id_fiche']) . ']]>');
                    $xml .= "\r\n        ";
                    $xml .= XML_Util::createTag('guid', null, '<![CDATA[' . $this->wiki->href('', $ligne['id_fiche']) . ']]>');
                    $xml .= "\r\n        ";
                    $xml .= XML_Util::createTag('dc:creator', null, $ligne['owner']);
                    $xml .= "\r\n      ";
                    $xml .= XML_Util::createTag(
                        'description',
                        null,
                        '<![CDATA[' . preg_replace(
                        '/data-id=".*"/Ui',
                        '',
                        $this->sanitize($this->updateRelativeLinks($this->getService(EntryController::class)->view($ligne), $this->wiki->href('', $ligne['id_fiche'])))
                    ) . ']]>'
                    );
                    $xml .= "\r\n        ";
                    $xml .= XML_Util::createTag('pubDate', null, date('r', strtotime($ligne['date_creation_fiche'])));
                    $xml .= "\r\n      ";
                    $xml .= XML_Util::createEndElement('item');
                }
            } else {
                //pas d'annonces
                $xml .= "\r\n      ";
                $xml .= XML_Util::createStartElement('item');
                $xml .= "\r\n          ";
                $xml .= XML_Util::createTag('title', null, $this->sanitize(_t('BAZ_PAS_DE_FICHES')));
                $xml .= "\r\n          ";
                $xml .= XML_Util::createTag('link', null, '<![CDATA[' . $this->wiki->config['base_url'] . $this->wiki->config['root_page'] . ']]>');
                $xml .= "\r\n          ";
                $xml .= XML_Util::createTag('guid', null, '<![CDATA[' . $this->wiki->config['base_url'] . $this->wiki->config['root_page'] . ']]>');
                $xml .= "\r\n          ";
                $xml .= XML_Util::createTag('description', null, $this->sanitize(_t('BAZ_PAS_DE_FICHES')));
                $xml .= "\r\n          ";
                $xml .= XML_Util::createTag('pubDate', null, date('r', strtotime('01/01/%Y')));
                $xml .= "\r\n      ";
                $xml .= XML_Util::createEndElement('item');
            }
            $xml .= "\r\n    ";
            $xml .= XML_Util::createEndElement('channel');
            $xml .= "\r\n  ";
            $xml .= XML_Util::createEndElement('rss');

            header('Content-type: text/xml; charset=UTF-8');

            return str_replace(
                '</image>',
                '</image>' . "\n"
            . '    <atom:link href="' . htmlentities((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'])
            . '" rel="self" type="application/rss+xml" />',
                $this->sanitize($xml, ENT_QUOTES, 'UTF-8')
            );
        } catch (Exception $e) {
            return 'Caught exception: ' . $e->getMessage() . "\n";
        }
    }

    private function sanitize($string)
    {
        $string = html_entity_decode($string, ENT_QUOTES, 'UTF-8');

        return $string;
    }

    private function updateRelativeLinks($pBody, $pPageURL)
    {
        $vBody = $pBody;

        $pattern = '~(<a[[:blank:]]*[^>]*[[:blank:]]*href[[:blank:]]*=[[:blank:]]*)(["\'])(.*?)(\2)([^>]*>)~m';

        if (preg_match_all($pattern, $vBody, $matches)) {
            foreach ($matches[3] as $vKey => $vURL) {
                $vAbsoluteURL = getAbsoluteURLForLinkInAPage($pPageURL, $vURL);
                $vBody = str_replace($matches[0][$vKey], $matches[1][$vKey] . $matches[2][$vKey] . $vAbsoluteURL . $matches[2][$vKey] . $matches[5][$vKey], $vBody);
            }
        }

        return $vBody;
    }
}
