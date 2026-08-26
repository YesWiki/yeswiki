<?php

namespace YesWiki\Content\Api;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use YesWiki\Content\Controller\EntryController;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\BazarListService;
use YesWiki\Core\YesWikiController;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Kernel\Service\StringUtilService;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Kernel\Service\WikiUrls;
use YesWiki\Render\Service\MarkdownFormatterService;
use YesWiki\Search\Service\SearchManager;
use YesWiki\Search\Service\TagsManager;

/** The wiki's RSS feeds (ticket 35, was the `rss` and `tagrss` page handlers). */
class FeedApiController extends YesWikiController
{
    private const NS_ATOM = 'http://www.w3.org/2005/Atom';
    private const NS_DC = 'http://purl.org/dc/elements/1.1/';
    private const XML_HEADERS = ['Content-Type' => 'text/xml; charset=UTF-8'];

    /** The RSS 2.0 feed of a bazar list. */
    #[Route('/api/entries/rss', methods: ['GET'], options: ['acl' => ['public']], priority: 2)]
    public function entriesFeed(Request $request): Response
    {
        try {
            $vSearchManager = $this->getService(SearchManager::class);
            $vBazarListService = $this->getService(BazarListService::class);

            $get = $request->query;
            $vIDs = $vBazarListService->getIDs($get->get('id') ?? $get->get('form_id') ?? []);

            $vItemCount = intval($get->get('nbitem') ?? $get->get('nb') ?? $this->getService(RuntimeConfig::class)['BAZ_NB_ENTREES_FLUX_RSS'] ?? 0);

            $user = $get->get('user', '');

            $vKeywords = $vSearchManager->aggregateKeywords(
                $get->has('q') ? urldecode((string)$get->get('q')) : null,
                $get->has('keywords') ? urldecode((string)$get->get('keywords')) : null
            );

            $vSearchFields = $get->has('searchfields') ? urldecode((string)$get->get('searchfields')) : null;

            $vQuery = $get->get('query', '');
            $vQuery = $vSearchManager->parseQuery(urldecode($vQuery));

            $vFieldMapping = $get->has('fieldmapping') ? urldecode((string)$get->get('fieldmapping')) : null;

            $vDateFilter = $get->has('datefilter') ? urldecode((string)$get->get('datefilter')) : null;

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
            $this->addTag($doc, $channel, 'copyright', 'Copyright (c) ' . date('Y') . ' ' . StringUtilService::withoutDiacritics($config['BAZ_RSS_NOMSITE']));
            $this->addTag($doc, $channel, 'docs', 'http://www.stervinou.com/projets/rss/');
            $this->addTag($doc, $channel, 'category', $config['BAZ_RSS_CATEGORIE']);
            $this->addTag($doc, $channel, 'managingEditor', $config['BAZ_RSS_MANAGINGEDITOR']);
            $this->addTag($doc, $channel, 'webMaster', $config['BAZ_RSS_WEBMASTER']);
            $this->addTag($doc, $channel, 'ttl', '60');

            $image = $channel->appendChild($doc->createElement('image'));
            $this->addTag($doc, $image, 'url', $config['BAZ_RSS_LOGOSITE']);

            $self = $channel->appendChild($doc->createElementNS(self::NS_ATOM, 'atom:link'));
            $self->setAttribute('href', $request->getSchemeAndHttpHost() . $request->getRequestUri());
            $self->setAttribute('rel', 'self');
            $self->setAttribute('type', 'application/rss+xml');

            if ($vCount > 0) {
                foreach ($vRSSEntries as $vRSSEntry) {
                    $url = (string)($vRSSEntry['url'] ?? '');
                    $published = strtotime((string)($vRSSEntry['created_at'] ?? $vRSSEntry['updated_at'] ?? ''));

                    $item = $channel->appendChild($doc->createElement('item'));
                    $this->addTag($doc, $item, 'title', $this->sanitize($vRSSEntry['title'] ?? $vRSSEntry['bf_titre'] ?? ''));
                    $this->addTag($doc, $item, 'link', $url, true);
                    $this->addTag($doc, $item, 'guid', $url, true);
                    $creator = $item->appendChild($doc->createElementNS(self::NS_DC, 'dc:creator'));
                    $creator->appendChild($doc->createTextNode((string)($vRSSEntry['owner'] ?? '')));
                    $this->addTag($doc, $item, 'description', preg_replace(
                        '/data-id=".*"/Ui',
                        '',
                        $this->sanitize($this->updateRelativeLinks(
                            $this->getService(EntryController::class)->view($vRSSEntry),
                            $this->getService(UrlFormatter::class)->href('', (string)($vRSSEntry['tag'] ?? ''))
                        ))
                    ), true);
                    $this->addTag($doc, $item, 'pubDate', $published === false ? null : date('r', $published));
                }
            } else {
                $item = $channel->appendChild($doc->createElement('item'));
                $this->addTag($doc, $item, 'title', $this->sanitize(_t('BAZ_PAS_DE_FICHES')));
                $this->addTag($doc, $item, 'link', $config['base_url'] . $config['root_page'], true);
                $this->addTag($doc, $item, 'guid', $config['base_url'] . $config['root_page'], true);
                $this->addTag($doc, $item, 'description', $this->sanitize(_t('BAZ_PAS_DE_FICHES')));
                $this->addTag($doc, $item, 'pubDate', date('r'));
            }

            return new Response((string)$doc->saveXML(), Response::HTTP_OK, self::XML_HEADERS);
        } catch (\Exception $e) {
            return new Response(
                'Caught exception: ' . $e->getMessage() . "\n",
                Response::HTTP_INTERNAL_SERVER_ERROR,
                ['Content-Type' => 'text/plain; charset=UTF-8']
            );
        }
    }

    /** The RSS 2.0 feed of the pages carrying one or more keywords. */
    #[Route('/api/tags/rss', methods: ['GET'], options: ['acl' => ['public']])]
    public function tagsFeed(Request $request): Response
    {
        $tags = trim((string)$request->query->get('tags', ''));
        if ($tags === '') {
            return new Response('', Response::HTTP_BAD_REQUEST, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $config = $this->getService(RuntimeConfig::class);
        $pages = $this->getService(TagsManager::class)->getPagesByTags(
            $tags,
            (string)$request->query->get('type', ''),
            20,
            'date'
        );

        $title = _t('LATEST_CHANGES_ON') . ' ' . $config['yeswiki_name'] . ', ' . _t('TAGS_CONTAINING_TAG') . ' ' . $tags;

        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->xmlStandalone = true;
        $doc->formatOutput = true;

        $rss = $doc->appendChild($doc->createElement('rss'));
        $rss->setAttribute('version', '2.0');
        $rss->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:atom', self::NS_ATOM);
        $rss->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:dc', self::NS_DC);

        $channel = $rss->appendChild($doc->createElement('channel'));
        $this->addTag($doc, $channel, 'title', $this->sanitize($title));
        $this->addTag($doc, $channel, 'link', $config['base_url'] . $config['root_page']);
        $this->addTag($doc, $channel, 'description', $this->sanitize($title));
        $this->addTag($doc, $channel, 'lastBuildDate', date('r'));

        $self = $channel->appendChild($doc->createElementNS(self::NS_ATOM, 'atom:link'));

        $self->setAttribute('href', $request->getSchemeAndHttpHost() . $request->getRequestUri());
        $self->setAttribute('rel', 'self');
        $self->setAttribute('type', 'application/rss+xml');

        $aclService = $this->getService(AclService::class);
        $urlFormatter = $this->getService(UrlFormatter::class);
        $formatter = $this->getService(MarkdownFormatterService::class);
        $pageContext = $this->getService(PageContext::class);

        foreach ($pages as $page) {
            $tag = (string)($page['tag'] ?? '');
            $body = PageBody::decode($page['body'] ?? null);

            if ($aclService->hasAccess('read', $tag)) {
                $previousTag = $pageContext->getTag();
                $previousPage = $pageContext->getPage();
                $pageContext->setTag($tag);
                $pageContext->setPage($page + ['body' => $body]);
                try {
                    $content = (string)preg_replace('/\{\{recentchangesrss(.*?)\}\}/s', '', PageBody::content($body));
                    $content = (string)preg_replace('/\{\{rss(.*?)\}\}/s', '', $content);
                    $description = $formatter->format($content);
                } finally {
                    $pageContext->setTag($previousTag);
                    $pageContext->setPage(is_array($previousPage) ? $previousPage : []);
                }
            } else {
                $description = '<i>' . _t('TAGS_HIDDEN_CONTENT') . '</i>';
            }

            $url = $urlFormatter->href('', $tag);
            $item = $channel->appendChild($doc->createElement('item'));
            $this->addTag($doc, $item, 'title', $this->sanitize($tag));
            $this->addTag($doc, $item, 'link', $url, true);
            $this->addTag($doc, $item, 'guid', $url, true);
            $this->addTag($doc, $item, 'description', $this->sanitize($description), true);
            $creator = $item->appendChild($doc->createElementNS(self::NS_DC, 'dc:creator'));
            $creator->appendChild($doc->createTextNode((string)($page['user'] ?? '')));
            $published = strtotime((string)($page['time'] ?? ''));
            $this->addTag($doc, $item, 'pubDate', $published === false ? null : date('r', $published));
        }

        return new Response((string)$doc->saveXML(), Response::HTTP_OK, self::XML_HEADERS);
    }

    /** One element of the feed. */
    private function addTag(\DOMDocument $doc, \DOMNode $parent, string $name, ?string $value, bool $cdata = false): void
    {
        $element = $parent->appendChild($doc->createElement($name));
        $value = $this->xmlSafe($value ?? '');
        if ($value === '') {
            return;
        }

        $content = $cdata
            ? $doc->createCDATASection(str_replace(']]>', ']]]]><![CDATA[>', $value))
            : $doc->createTextNode($value);
        if ($content !== false) {
            $element->appendChild($content);
        }
    }

    /** $value with everything XML 1.0 cannot carry taken out. */
    private function xmlSafe(string $value): string
    {
        $value = (string)mb_convert_encoding($value, 'UTF-8', 'UTF-8');

        return (string)preg_replace(
            '/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u',
            '',
            $value
        );
    }

    /** Entities back to characters, because the escaping is the XML writer's job. */
    private function sanitize(?string $string): string
    {
        return html_entity_decode((string)$string, ENT_QUOTES, 'UTF-8');
    }

    private function updateRelativeLinks(string $pBody, string $pPageURL): string
    {
        $vBody = $pBody;

        $pattern = '~(<a[[:blank:]]*[^>]*[[:blank:]]*href[[:blank:]]*=[[:blank:]]*)(["\'])(.*?)(\2)([^>]*>)~m';

        if (preg_match_all($pattern, $vBody, $matches)) {
            foreach ($matches[3] as $vKey => $vURL) {
                $vAbsoluteURL = WikiUrls::absoluteUrlForLinkInPage($pPageURL, $vURL);
                $vBody = str_replace($matches[0][$vKey], $matches[1][$vKey] . $matches[2][$vKey] . $vAbsoluteURL . $matches[2][$vKey] . $matches[5][$vKey], $vBody);
            }
        }

        return $vBody;
    }
}
