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

/**
 * The RSS 2.0 feed of a bazar list.
 *
 * Built with DOM rather than by concatenating strings: the feed used to be assembled
 * with pear/xml_util (abandoned upstream), which escaped each value -- and then the whole
 * document was run through html_entity_decode() to turn the escaped `<![CDATA[` markers
 * back into real CDATA sections. That undid the escaping of everything ELSE too, so a
 * single `&` or `<` in an entry title produced a feed no reader could parse. Here the
 * escaping is the parser's job and CDATA is a node type, so neither trick is needed.
 */
class RssHandler extends YesWikiHandler implements RegisteredHandler
{
    private const NS_ATOM = 'http://www.w3.org/2005/Atom';
    private const NS_DC = 'http://purl.org/dc/elements/1.1/';

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

            $config = $this->getService(RuntimeConfig::class);

            $doc = new \DOMDocument('1.0', 'UTF-8');
            $doc->xmlStandalone = true;
            $doc->formatOutput = true;

            $rss = $doc->appendChild($doc->createElement('rss'));
            $rss->setAttribute('version', '2.0');
            $rss->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:atom', self::NS_ATOM);
            $rss->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:dc', self::NS_DC);

            $channel = $rss->appendChild($doc->createElement('channel'));
            $this->addTag($doc, $channel, 'title', $this->sanitize(_t('BAZ_DERNIERE_ACTU')));
            $this->addTag($doc, $channel, 'lastBuildDate', date('r'));
            $this->addTag($doc, $channel, 'count', (string)$vCount);
            $this->addTag($doc, $channel, 'description', $this->sanitize($config['BAZ_RSS_DESCRIPTIONSITE']));
            $this->addTag($doc, $channel, 'link', $this->sanitize($config['BAZ_RSS_ADRESSESITE']));
            $this->addTag($doc, $channel, 'language', 'fr-FR');
            $this->addTag($doc, $channel, 'copyright', 'Copyright (c) ' . date('Y') . ' ' . removeAccents($config['BAZ_RSS_NOMSITE']));
            $this->addTag($doc, $channel, 'docs', 'http://www.stervinou.com/projets/rss/');
            $this->addTag($doc, $channel, 'category', $config['BAZ_RSS_CATEGORIE']);
            $this->addTag($doc, $channel, 'managingEditor', $config['BAZ_RSS_MANAGINGEDITOR']);
            $this->addTag($doc, $channel, 'webMaster', $config['BAZ_RSS_WEBMASTER']);
            $this->addTag($doc, $channel, 'ttl', '60');

            $image = $channel->appendChild($doc->createElement('image'));
            $this->addTag($doc, $image, 'url', $config['BAZ_RSS_LOGOSITE']);

            // where a reader finds this very feed again
            $self = $channel->appendChild($doc->createElementNS(self::NS_ATOM, 'atom:link'));
            $self->setAttribute('href', $this->getRequest()->getSchemeAndHttpHost() . $this->getRequest()->getRequestUri());
            $self->setAttribute('rel', 'self');
            $self->setAttribute('type', 'application/rss+xml');

            if ($vCount > 0) {
                // Creation des items : titre + lien + description + date de publication
                foreach ($vRSSEntries as $vRSSEntry) {
                    $item = $channel->appendChild($doc->createElement('item'));
                    $this->addTag($doc, $item, 'title', $this->sanitize($vRSSEntry['title'] ?? $vRSSEntry['bf_titre'] ?? ''));
                    $this->addTag($doc, $item, 'link', $vRSSEntry['url'], true);
                    $this->addTag($doc, $item, 'guid', $vRSSEntry['url'], true);
                    $creator = $item->appendChild($doc->createElementNS(self::NS_DC, 'dc:creator'));
                    $creator->appendChild($doc->createTextNode((string)$vRSSEntry['owner']));
                    $this->addTag($doc, $item, 'description', preg_replace(
                        '/data-id=".*"/Ui',
                        '',
                        $this->sanitize($this->updateRelativeLinks($this->getService(EntryController::class)->view($vRSSEntry), $this->getService(UrlFormatter::class)->href('', $vRSSEntry['tag'])))
                    ), true);
                    $this->addTag($doc, $item, 'pubDate', date('r', strtotime($vRSSEntry['created_at'])));
                }
            } else {
                // pas d'annonces
                $item = $channel->appendChild($doc->createElement('item'));
                $this->addTag($doc, $item, 'title', $this->sanitize(_t('BAZ_PAS_DE_FICHES')));
                $this->addTag($doc, $item, 'link', $config['base_url'] . $config['root_page'], true);
                $this->addTag($doc, $item, 'guid', $config['base_url'] . $config['root_page'], true);
                $this->addTag($doc, $item, 'description', $this->sanitize(_t('BAZ_PAS_DE_FICHES')));
                $this->addTag($doc, $item, 'pubDate', date('r', strtotime('01/01/%Y')));
            }

            header('Content-type: text/xml; charset=UTF-8');

            return $doc->saveXML();
        } catch (\Exception $e) {
            return 'Caught exception: ' . $e->getMessage() . "\n";
        }
    }

    /**
     * One element of the feed. `$cdata` for the values that carry markup or a raw URL --
     * a CDATA section rather than escaping, so that a reader showing the description gets
     * the entry's HTML back rather than a page of visible tags.
     */
    private function addTag(\DOMDocument $doc, \DOMNode $parent, string $name, ?string $value, bool $cdata = false): void
    {
        $element = $parent->appendChild($doc->createElement($name));
        $value ??= '';
        if ($value === '') {
            return;
        }
        // `]]>` inside an entry would end the section early: split it across two of them
        $content = $cdata
            ? $doc->createCDATASection(str_replace(']]>', ']]]]><![CDATA[>', $value))
            : $doc->createTextNode($value);
        if ($content !== false) {
            $element->appendChild($content);
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
