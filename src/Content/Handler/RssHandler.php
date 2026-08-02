<?php

namespace YesWiki\Content\Handler;

use YesWiki\Content\Controller\EntryController;
use YesWiki\Content\Service\BazarListService;
use YesWiki\Core\YesWikiHandler;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Performable\RegisteredHandler;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Search\Service\SearchManager;

// TODO use Symfony XmlEncoder instead
// https://symfony.com/doc/current/components/serializer.html#the-xmlencoder
class RssHandler extends YesWikiHandler implements RegisteredHandler
{
    /** `/PageName/rss` -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'rss';
    }

    public function run()
    {
        try {
            if (!$this->getService(AclService::class)->hasAccess('read') || !$this->getService(PageContext::class)->getPage()) {
                return null;
            }

            $vSearchManager = $this->getService(SearchManager::class);
            $vBazarListService = $this->getService(BazarListService::class);

            $get = $this->getRequest()->query;
            $vIDs = $vBazarListService->getIDs($get->get('id') ?? $get->get('form_id') ?? []);

            $vItemCount = intval($get->get('nbitem') ?? $get->get('nb') ?? $this->getService(RuntimeConfig::class)['BAZ_NB_ENTREES_FLUX_RSS'] ?? 0);

            $user = $get->get('user', '');

            // chaine de recherche

            $vKeywords = $vSearchManager->aggregateKeywords(
                $get->has('q') ? urldecode($get->get('q')) : null,
                $get->has('keywords') ? urldecode($get->get('keywords')) : null
            );

            $vSearchFields = $get->has('searchfields') ? urldecode($get->get('searchfields')) : null;

            $vQuery = $get->get('query', '');
            $vQuery = $vSearchManager->parseQuery(urldecode($vQuery));

            // fieldMapping

            $vFieldMapping = $get->has('fieldmapping') ? urldecode($get->get('fieldmapping')) : null;

            // datefilter

            $vDateFilter = $get->has('datefilter') ? urldecode($get->get('datefilter')) : null;

            $vRSSEntries = $vBazarListService->getEntries(
                [
                    'id' => $vIDs,
                    'queries' => $vQuery,
                    'user' => $user,
                    'keywords' => $vKeywords,
                    'searchfields' => $vSearchFields,
                    'datefilter' => $vDateFilter,
                    'fieldmapping' => $vFieldMapping,
                    'order' => 'desc',
                    'field' => 'created_at',
                    'nb' => $vItemCount,
                    'minDate' => $get->get('dateMin') ?? $get->get('minDate') ?? $get->get('period') ?? '',
                ]
            );

            $vCount = count($vRSSEntries);

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
            $xml .= XML_Util::createTag('lastBuildDate', null, date('r'));
            $xml .= "\r\n      ";
            $xml .= XML_Util::createTag('count', null, $vCount);
            $xml .= "\r\n      ";
            $xml .= XML_Util::createTag('description', null, $this->sanitize($this->getService(RuntimeConfig::class)['BAZ_RSS_DESCRIPTIONSITE']));
            $xml .= "\r\n      ";
            $xml .= XML_Util::createTag('link', null, $this->sanitize($this->getService(RuntimeConfig::class)['BAZ_RSS_ADRESSESITE']));
            $xml .= "\r\n      ";
            $xml .= XML_Util::createTag('language', null, 'fr-FR');
            $xml .= "\r\n      ";
            $xml .= XML_Util::createTag('copyright', null, 'Copyright (c) ' . date('Y') . ' ' . htmlentities(removeAccents($this->getService(RuntimeConfig::class)['BAZ_RSS_NOMSITE'])));
            $xml .= "\r\n      ";
            $xml .= XML_Util::createTag('docs', null, 'http://www.stervinou.com/projets/rss/');
            $xml .= "\r\n      ";
            $xml .= XML_Util::createTag('category', null, $this->getService(RuntimeConfig::class)['BAZ_RSS_CATEGORIE']);
            $xml .= "\r\n      ";
            $xml .= XML_Util::createTag('managingEditor', null, $this->getService(RuntimeConfig::class)['BAZ_RSS_MANAGINGEDITOR']);
            $xml .= "\r\n      ";
            $xml .= XML_Util::createTag('webMaster', null, $this->getService(RuntimeConfig::class)['BAZ_RSS_WEBMASTER']);
            $xml .= "\r\n      ";
            $xml .= XML_Util::createTag('ttl', null, '60');
            $xml .= "\r\n      ";
            $xml .= XML_Util::createStartElement('image');
            $xml .= "\r\n        ";
            $xml .= XML_Util::createTag('url', null, $this->getService(RuntimeConfig::class)['BAZ_RSS_LOGOSITE']);
            $xml .= "\r\n      ";
            $xml .= XML_Util::createEndElement('image');

            if ($vCount > 0) {
                // Creation des items : titre + lien + description + date de publication
                foreach ($vRSSEntries as $vRSSEntry) {
                    $xml .= "\r\n      ";
                    $xml .= XML_Util::createStartElement('item');
                    $xml .= "\r\n        ";
                    $xml .= XML_Util::createTag('title', null, str_replace('&', '&amp;', $this->sanitize($vRSSEntry['title'] ?? $vRSSEntry['bf_titre'] ?? '')));
                    $xml .= "\r\n        ";
                    $xml .= XML_Util::createTag('link', null, '<![CDATA[' . $vRSSEntry['url'] . ']]>');
                    $xml .= "\r\n        ";
                    $xml .= XML_Util::createTag('guid', null, '<![CDATA[' . $vRSSEntry['url'] . ']]>');
                    $xml .= "\r\n        ";
                    $xml .= XML_Util::createTag('dc:creator', null, $vRSSEntry['owner']);
                    $xml .= "\r\n      ";
                    $xml .= XML_Util::createTag(
                        'description',
                        null,
                        '<![CDATA[' . preg_replace(
                            '/data-id=".*"/Ui',
                            '',
                            $this->sanitize($this->updateRelativeLinks($this->getService(EntryController::class)->view($vRSSEntry), $this->getService(UrlFormatter::class)->href('', $vRSSEntry['tag'])))
                        ) . ']]>'
                    );
                    $xml .= "\r\n        ";
                    $xml .= XML_Util::createTag('pubDate', null, date('r', strtotime($vRSSEntry['created_at'])));
                    $xml .= "\r\n      ";
                    $xml .= XML_Util::createEndElement('item');
                }
            } else {
                // pas d'annonces
                $xml .= "\r\n      ";
                $xml .= XML_Util::createStartElement('item');
                $xml .= "\r\n          ";
                $xml .= XML_Util::createTag('title', null, $this->sanitize(_t('BAZ_PAS_DE_FICHES')));
                $xml .= "\r\n          ";
                $xml .= XML_Util::createTag('link', null, '<![CDATA[' . $this->getService(RuntimeConfig::class)['base_url'] . $this->getService(RuntimeConfig::class)['root_page'] . ']]>');
                $xml .= "\r\n          ";
                $xml .= XML_Util::createTag('guid', null, '<![CDATA[' . $this->getService(RuntimeConfig::class)['base_url'] . $this->getService(RuntimeConfig::class)['root_page'] . ']]>');
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
            . '    <atom:link href="' . htmlentities($this->getRequest()->getSchemeAndHttpHost() . $this->getRequest()->getRequestUri())
            . '" rel="self" type="application/rss+xml" />',
                $this->sanitize($xml, ENT_QUOTES, 'UTF-8')
            );
        } catch (\Exception $e) {
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
