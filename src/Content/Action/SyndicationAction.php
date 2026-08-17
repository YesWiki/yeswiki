<?php

namespace YesWiki\Content\Action;

use League\HTMLToMarkdown\HtmlConverter;
use Tamtamchik\SimpleFlash\Flash;
use YesWiki\Content\Entity\Item;
use YesWiki\Content\Entity\SuppliesItems;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FeedLoader;
use YesWiki\Core\YesWikiAction;
use YesWiki\Files\Service\RemoteImageCache;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Component\Category;
use YesWiki\Kernel\Component\Component;
use YesWiki\Kernel\Component\ProvidesComponents;
use YesWiki\Kernel\Component\Setting;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\Redirector;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Service\PresentationRenderer;
use YesWiki\Search\Service\SearchManager;

include_once YESWIKI_SOURCE_DIR . '/src/Content/syndication.functions.php';

class SyndicationAction extends YesWikiAction implements RegisteredAction, ProvidesComponents, SuppliesItems
{
    /** `{{syndication}}` in page content -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'syndication';
    }

    public function components(): array
    {
        return [
            Component::for('syndication')
                ->category(Category::Lists)
                ->label(_t('AB_syndication_action_label'))
                ->icon('rss')
                ->description(_t('AB_syndication_action_description'))
                ->previewHeight('300px')

                ->notOffered()
                ->settings(
                    Setting::url('url')
                        ->label(_t('AB_syndication_action_url_label'))
                        ->hint(_t('AB_syndication_action_url_hint'))
                        ->suggests('https://forum.yeswiki.net/c/annonces/8.rss, https://www.mediapart.fr/articles/feed')
                        ->required(),
                    Setting::text('source')
                        ->label(_t('AB_syndication_action_source_label'))
                        ->hint(_t('AB_syndication_action_source_hint'))
                        ->suggests('Annonces forum YesWiki, Articles mediapart'),
                    Setting::number('nb')
                        ->label(_t('AB_syndication_action_nb_label')),
                    Setting::choice('template', [
                        'accordeon.twig' => _t('AB_syndication_action_template_acordion'),
                        'liste.twig' => _t('AB_syndication_action_template_list'),
                        'liste_description.twig' => _t('AB_syndication_action_template_list_and_description'),
                    ])
                        ->label(_t('AB_syndication_action_template_label')),
                    Setting::checkbox('showimage')
                        ->label(_t('AB_syndication_action_show_image'))
                        ->default('0')
                        ->checkedValues('1', '0'),
                    Setting::text('title')
                        ->label(_t('AB_syndication_action_title_label')),
                    Setting::checkbox('newwindow')
                        ->label(_t('AB_syndication_action_nouvellefenetre_label'))
                        ->default('0')
                        ->checkedValues('1', '0'),
                    Setting::choice('formatdate', [
                        'jm' => _t('AB_syndication_action_formatdate_option_jm'),
                        'jma' => _t('AB_syndication_action_formatdate_option_jma'),
                        'jmh' => _t('AB_syndication_action_formatdate_option_jmh'),
                        'jmah' => _t('AB_syndication_action_formatdate_option_jmah'),
                    ])
                        ->label(_t('AB_syndication_action_formatdate_label')),
                    Setting::text('mapping')
                        ->label(_t('AB_syndication_action_mapping_bazar'))
                        ->hint(_t('AB_syndication_action_mapping_hint')),
                ),
        ];
    }

    public static function sourceLabel(): string
    {
        return _t('SOURCE_SYNDICATION');
    }

    public static function sourceSettings(): array
    {
        return [
            Setting::url('url')
                ->label(_t('AB_syndication_action_url_label'))
                ->withIcon('rss')
                ->required(),
        ];
    }

    /**
     * @return list<Setting>
     */
    public static function sourceSelectionSettings(): array
    {
        return [
            Setting::number('nb')
                ->label(_t('AB_syndication_action_nb_label'))
                ->withIcon('list-numbers')
                ->default(0)
                ->min(0),

            Setting::number('maxchars')
                ->label(_t('AB_syndication_action_maxchars_label'))
                ->hint(_t('AB_syndication_action_maxchars_hint'))
                ->withIcon('text-decrease')
                ->min(0),
        ];
    }

    /**
     * The feed, as Items -- which is what a Presentation renders (ticket 37).
     *
     * @return list<Item>
     */
    public function items(): array
    {
        $pages = [];
        foreach (($this->arguments['url'] ?? []) as $nburl => $url) {
            $fromFeed = $this->getService(FeedLoader::class)->read(
                (string)$url,
                function (\SimplePie\SimplePie $feed) use ($nburl): array {
                    if ($feed->error()) {
                        return [];
                    }
                    $items = [];
                    foreach ($feed->get_items(0, $this->arguments['nb']) as $item) {
                        $items[] = $this->feedItemFields($item, (int)$nburl);
                    }

                    return $items;
                }
            );
            $pages = array_merge($pages, $fromFeed ?? []);
        }

        return $this->itemsFrom($pages);
    }

    /**
     * @param array<array-key, array<string, mixed>> $pages the feed items already built --
     *                                                      keyed by date in run(), a plain
     *                                                      list in items()
     *
     * @return list<Item>
     */
    private function itemsFrom(array $pages): array
    {
        $items = [];
        foreach ($pages as $page) {
            $stamp = $page['datestamp'] ?? null;
            $image = (string)($page['image'] ?? '');
            $items[] = new Item(
                id: (string)($page['url'] ?? ($page['title'] ?? '')),
                title: (string)($page['title'] ?? ''),
                subtitle: ($page['source'] ?? '') !== '' ? (string)$page['source'] : null,
                description: ($page['description'] ?? '') !== '' ? (string)$page['description'] : null,
                image: $image === '' ? null : $image,
                url: ($page['url'] ?? '') !== '' ? (string)$page['url'] : null,
                date: is_numeric($stamp) ? date('c', (int)$stamp) : null,
                categories: array_values(array_map('strval', $page['categories'] ?? [])),
            );
        }

        return $items;
    }

    /**
     * One feed entry's fields, as this action has always read them off SimplePie.
     *
     * @return array<string, mixed>
     */
    private function feedItemFields(\SimplePie\Item $item, int $nburl): array
    {
        $feedItem = [];
        if (is_array($this->arguments['source'])) {
            $feedItem['source'] = $this->arguments['source'][$nburl];
        } else {
            $feedItem['source'] = '';
        }

        $feedItem['url'] = $item->get_permalink();

        $feedItem['title'] = (string)$item->get_title();
        $feedItem['description'] = $item->get_content();
        $desc = $item->get_description();
        if (empty($desc)) {
            $desc = $feedItem['description'];
        }
        $feedItem['summary'] = truncate(
            strip_tags($desc ?? ''),
            500
        );
        $feedItem['categories'] = array_column($item->get_categories() ?? [], 'term');
        $feedItem['image'] = null;
        if ($enclosure = $item->get_enclosure()) {
            $feedItem['image'] = $enclosure->get_thumbnail();
            if (
                empty($feedItem['image'])
                && $enclosure->get_medium() == 'image'
            ) {
                $feedItem['image'] = $enclosure->get_link();
            } elseif (preg_match(
                '/avif|gif|jpeg|png|jpg|svg|webp$/',
                strtolower($link = $enclosure->get_link() ?? ''),
                $matches
            )) {
                $feedItem['image'] = $link;
            } elseif (!empty($item->data['child']['http://www.itunes.com/dtds/podcast-1.0.dtd']['image'][0]['attribs']['']['href'])) {
                $feedItem['image'] = $item->data['child']['http://www.itunes.com/dtds/podcast-1.0.dtd']['image'][0]['attribs']['']['href'];
            }
        }
        if (!empty($this->arguments['maxchars'])) {
            $feedItem['description'] = (string)preg_replace("/\s+/u", ' ', strip_tags($feedItem['description'] ?? ''));
            $descLen = strlen($feedItem['description']);

            if ($descLen > 0
                && $descLen > $this->arguments['maxchars']) {
                $feedItem['description'] = truncate(
                    $feedItem['description'],
                    $this->arguments['maxchars'],
                    '... <a class="lien_lire_suite" href="' . $feedItem['url']
                . '" ' . ($this->arguments['newwindow'] ? 'target="_blank" ' : '')
                        . 'title="' . _t('SYNDICATION_READ_MORE') . '">' . _t('SYNDICATION_READ_MORE') . '</a>',
                );
            }
        }

        $rawDate = $item->get_date('j M Y, g:i a');
        $timestamp = is_string($rawDate) ? strtotime($rawDate) : false;
        $feedItem['datestamp'] = $timestamp;
        switch ($timestamp === false ? '' : $this->arguments['formatdate']) {
            case 'jm':
                $feedItem['date'] = date('d.m', (int)$timestamp);
                break;
            case 'jma':
                $feedItem['date'] = date('d.m.Y', (int)$timestamp);
                break;
            case 'jmh':
                $feedItem['date'] = date('d.m H:m', (int)$timestamp);
                break;
            case 'jmah':
                $feedItem['date'] = date('d.m.Y H:m', (int)$timestamp);
                break;
            default:
                $feedItem['date'] = '';
        }

        return $feedItem;
    }

    public function formatArguments($arg): array
    {
        $arg['showimage'] = $this->formatBoolean($arg, false, 'showimage');
        $arg['newwindow'] = $this->formatBoolean($arg, false, 'newwindow');
        if (empty($arg['class'])) {
            $arg['class'] = '';
        }
        if (empty($arg['nb'])) {
            $arg['nb'] = 0;
        }
        if (empty($arg['template'])) {
            $arg['template'] = 'liste_description.twig';
        }
        if (empty($arg['formatdate'])) {
            $arg['formatdate'] = '';
        }
        if (empty($arg['source'])) {
            $arg['source'] = '';
        } else {
            $arg['source'] = array_map(
                'trim',
                explode(',', $arg['source'])
            );
        }
        if (!empty($arg['url'])) {
            $arg['url'] = array_map('trim', explode(',', $arg['url']));
        }
        if (!empty($arg['mapping'])) {
            $params = array_map('trim', explode(',', $arg['mapping']));
            $mapping = [];
            foreach ($params as $param) {
                $values = array_map('trim', explode('=', $param));
                $mapping[$values[0]] = $values[1];
            }
            $arg['mapping'] = [
                'id' => $mapping['id'] ?? '',
                'title' => $mapping['title'] ?? 'bf_titre',
                'url' => $mapping['url'] ?? 'bf_url',
                'summary' => $mapping['summary'] ?? 'bf_chapeau',
                'description' => $mapping['description'] ?? 'bf_description',
                'image' => $mapping['image'] ?? 'imagebf_image',
                'categories' => $mapping['categories'] ?? 'bf_tags',
            ];
        }

        return $arg;
    }

    public function run(): ?string
    {
        $mappingToBazar = !empty($this->arguments['mapping']) && $this->getService(AclService::class)->isAdmin();
        $entries = [];
        if ($mappingToBazar) {
            $this->addToBazar();
            if (empty($this->arguments['mapping']['id'])) {
                return '<div class="yw-alert yw-alert--danger">' . _t('ERROR') . ' ' . _t('SYNDICATION_MAPPING_ID_REQUIRED') . ', ex: id=1400,title=bf_titre,url=bf_url,description=bf_description,image=imagebf_image,categories=bf_tags.</div>';
            }

            $vSearchManager = $this->getService(SearchManager::class);
            $entries = $vSearchManager->search(['formsIds' => [$this->arguments['mapping']['id']]]);
        }
        if (!empty($this->arguments['url'])) {
            $nburl = 0;
            $syndication = ['pages' => []];

            $feedTitle = '';
            $feedLink = '';
            foreach ($this->arguments['url'] as $cle => $url) {
                $failure = $this->getService(FeedLoader::class)->read(
                    (string)$url,
                    function (\SimplePie\SimplePie $feed) use (
                        $nburl,
                        $mappingToBazar,
                        $entries,
                        &$syndication,
                        &$feedTitle,
                        &$feedLink
                    ): ?string {
                        if ($feed->error()) {
                            return '<div class="yw-alert yw-alert--danger">' . _t('ERROR') . ' ' . $feed->error() . '</div>' . "\n";
                        }
                        $feedTitle = (string)$feed->get_title();
                        $feedLink = (string)$feed->get_link();
                        foreach ($feed->get_items(0, $this->arguments['nb']) as $item) {
                            $feedItem = $this->feedItemFields($item, $nburl);
                            if ($mappingToBazar) {
                                $feedItem['linkToEntry'] = $feedItem['mappingInput'] = '';
                                $entryExists = multiArraySearch($entries, $this->arguments['mapping']['url'], $feedItem['url']);
                                if (!empty($entryExists)) {
                                    $feedItem['linkToEntry'] = $this->getService(UrlFormatter::class)->href('', $entryExists[0]['tag']);
                                } else {
                                    $entry = [];
                                    $converter = new HtmlConverter(['strip_tags' => true]);
                                    foreach ($this->arguments['mapping'] as $key => $val) {
                                        switch ($key) {
                                            case 'id':
                                                $entry['form_id'] = $val;
                                                break;

                                            case 'categories':
                                                $entry[$val] = implode(',', $feedItem[$key]);
                                                break;

                                            case 'description':
                                                $entry[$val] = $converter->convert($feedItem[$key] ?? '');
                                                break;

                                            case 'image':
                                                $entry[$val] = $this->downloadFile($feedItem[$key] ?? '');
                                                break;

                                            default:
                                                $entry[$val] = $feedItem[$key];
                                                break;
                                        }
                                    }
                                    $entry['created_at'] = $item->get_date('Y-m-d H:i:s');
                                    $feedItem['mappingInput'] = json_encode($entry);
                                }
                            }

                            $syndication['pages'][$feedItem['datestamp'] . urlencode($feedItem['title'])] = $feedItem;
                        }

                        return null;
                    }
                );

                if ($failure !== null) {
                    return $failure;
                }
                $nburl = $nburl + 1;
            }

            krsort($syndication['pages']);
            if (empty($this->arguments['title'])) {
                $title = '';
            } elseif ($this->arguments['title'] == 'rss') {
                $title = $feedTitle;
            } else {
                $title = $this->arguments['title'];
            }

            $wrapper = '<div class="feed_syndication' . ($this->arguments['class'] ? ' ' . $this->arguments['class'] : '') . '">' . "\n";

            if (PresentationRenderer::knows((string)$this->arguments['template'])) {
                return $wrapper
                    . $this->getService(PresentationRenderer::class)->render(
                        (string)$this->arguments['template'],
                        $this->itemsFrom($syndication['pages']),
                        $this->arguments
                    )
                    . "\n</div>\n";
            }

            if (!empty($this->arguments['showimage'])) {
                $cache = $this->getService(RemoteImageCache::class);
                foreach ($syndication['pages'] as $key => $page) {
                    if (!empty($page['image'])) {
                        $syndication['pages'][$key]['image'] = $cache->localUrl((string)$page['image']);
                    }
                }
            }

            return $wrapper .
                $this->render('@core/' . $this->arguments['template'], [
                    'syndication' => $syndication,
                    'title' => $title,
                    'urlSite' => $feedLink,
                    'urlHash' => md5(implode(',', $this->arguments['url'])),
                    'showImage' => $this->arguments['showimage'],
                    'ext' => $this->arguments['newwindow'],
                ]) . "\n" .
            '</div>' . "\n";
        }

        return '<div class="yw-alert yw-alert--danger"><strong>' . _t('SYNDICATION_ACTION_SYNDICATION') . '</strong> : '
        . _t('SYNDICATION_PARAM_URL_REQUIRED') . '.</div>' . "\n";
    }

    protected function downloadFile($sourceUrl, $noSSLCheck = false, $timeoutInSec = 10, $replaceExisting = false)
    {
        if (empty($sourceUrl)) {
            return '';
        }
        $t = explode('/', $sourceUrl);
        $fileName = array_pop($t);
        $destFile = sha1($sourceUrl) . '_' . $fileName;
        $destPath = 'files/' . $destFile;
        if (!file_exists($destPath) || (file_exists($destPath) && $replaceExisting)) {
            $fp = fopen($destPath, 'wb');
            $ch = curl_init($sourceUrl);
            if ($fp === false || $ch === false) {
                return '';
            }
            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeoutInSec);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeoutInSec);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            if ($noSSLCheck) {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            }
            curl_exec($ch);
            $errors = curl_error($ch);
            if (!empty($errors)) {
                curl_close($ch);
                fclose($fp);

                return '';
            }
            curl_close($ch);
            fclose($fp);
        }

        return $destFile;
    }

    protected function addToBazar(): void
    {
        $mapping = $this->getRequest()->query->get('mapping');
        if (!empty($mapping)) {
            $data = json_decode(urldecode($mapping), true);
            if (!empty($data)) {
                $data['antispam'] = 1;
                $entryManager = $this->getService(EntryManager::class);
                $entryManager->create($data['form_id'], $data, false, $data['bf_url']);
                Flash::success(_t('SYNDICATION_ENTRY_SAVED', ['title' => $data['bf_titre']]));
                $this->getService(Redirector::class)->redirect($this->getService(UrlFormatter::class)->href());
            }
        }
    }
}
