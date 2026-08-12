<?php

namespace YesWiki\Content\Action;

// ticket 23: relocated from tools/syndication/actions/SyndicationAction.php.

use League\HTMLToMarkdown\HtmlConverter;
use Tamtamchik\SimpleFlash\Flash;
use YesWiki\Content\Entity\Item;
use YesWiki\Content\Entity\SuppliesItems;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Core\YesWikiAction;
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
                // a feed is a Source now, offered inside every Presentation rather than as
                // a palette card of its own (ticket 37). Still recognised, so a stored
                // `{{syndication}}` -- including one naming a retired template -- opens the
                // rail on everything it can be told.
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
                        ->label(_t('AB_syndication_action_title_label'))
                        ->advanced(),
                    Setting::checkbox('newwindow')
                        ->label(_t('AB_syndication_action_nouvellefenetre_label'))
                        ->default('0')
                        ->checkedValues('1', '0')
                        ->advanced(),
                    Setting::choice('formatdate', [
                        'jm' => _t('AB_syndication_action_formatdate_option_jm'),
                        'jma' => _t('AB_syndication_action_formatdate_option_jma'),
                        'jmh' => _t('AB_syndication_action_formatdate_option_jmh'),
                        'jmah' => _t('AB_syndication_action_formatdate_option_jmah'),
                    ])
                        ->label(_t('AB_syndication_action_formatdate_label'))
                        ->advanced(),
                    Setting::text('mapping')
                        ->label(_t('AB_syndication_action_mapping_bazar'))
                        ->hint(_t('AB_syndication_action_mapping_hint'))
                        ->advanced(),
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
            Setting::number('nb')
                ->label(_t('AB_syndication_action_nb_label'))
                ->default(0)
                ->advanced(),
        ];
    }

    /**
     * The feed, as Items -- which is what a Presentation renders (ticket 37).
     *
     * Nearly a straight rename: an RSS item already has a title, a link, a date, an image
     * and a summary, which is most of what an Item is. That is why a feed was the second
     * Source worth having -- it needs no mapping to be told.
     *
     * @return list<Item>
     */
    public function items(): array
    {
        $pages = [];
        foreach (($this->arguments['url'] ?? []) as $nburl => $url) {
            if ($url === '') {
                continue;
            }
            $feed = new \SimplePie\SimplePie();
            $feed->set_feed_url($url);
            $feed->enable_cache(true);
            $feed->init();
            $feed->handle_content_type();
            // a feed that will not load contributes nothing rather than failing the list:
            // several urls may be given, and one being down is not the others' problem
            if ($feed->error()) {
                continue;
            }
            foreach ($feed->get_items(0, $this->arguments['nb']) as $item) {
                $pages[] = $this->feedItemFields($item, (int)$nburl);
            }
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
            $items[] = new Item(
                id: (string)($page['url'] ?? ($page['title'] ?? '')),
                title: (string)($page['title'] ?? ''),
                // which feed it came from, when several were given: on a list of one feed
                // there is nothing to tell apart and the slot stays empty
                subtitle: ($page['source'] ?? '') !== '' ? (string)$page['source'] : null,
                description: ($page['description'] ?? '') !== '' ? (string)$page['description'] : null,
                image: ($page['image'] ?? null) !== null && $page['image'] !== '' ? (string)$page['image'] : null,
                url: ($page['url'] ?? '') !== '' ? (string)$page['url'] : null,
                // ISO, not the `formatdate` string: a Presentation sorts on this as well as
                // showing it, and `d.m` sorts alphabetically into nonsense
                date: is_numeric($stamp) ? date('c', (int)$stamp) : null,
                categories: array_values(array_map('strval', $page['categories'] ?? [])),
            );
        }

        return $items;
    }

    /**
     * One feed entry's fields, as this action has always read them off SimplePie.
     *
     * Extracted so `run()` and `items()` agree on what a feed entry IS. They want different
     * things around it -- `run()` also decorates admin-only "already imported?" links, and
     * `items()` wants none of that -- but neither should have its own idea of where the
     * title lives.
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
        // cast, not left nullable: run() keys the list on it and every renderer prints it
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
            $feedItem['description'] = preg_replace("/\s+/u", ' ', strip_tags($feedItem['description'] ?? ''));
            $descLen = strlen($feedItem['description']);
            // check if text longer than max chars specified
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

        $feedItem['datestamp'] = strtotime($item->get_date('j M Y, g:i a'));
        switch ($this->arguments['formatdate']) {
            case 'jm':
                $feedItem['date'] = date('d.m', $feedItem['datestamp']);
                break;
            case 'jma':
                $feedItem['date'] = date('d.m.Y', $feedItem['datestamp']);
                break;
            case 'jmh':
                $feedItem['date'] = date('d.m H:m', $feedItem['datestamp']);
                break;
            case 'jmah':
                $feedItem['date'] = date('d.m.Y H:m', $feedItem['datestamp']);
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
        if ($mappingToBazar) {
            $this->addToBazar();
            if (empty($this->arguments['mapping']['id'])) {
                return '<div class="yw-alert yw-alert--danger">' . _t('ERROR') . ' ' . _t('SYNDICATION_MAPPING_ID_REQUIRED') . ', ex: id=1400,title=bf_titre,url=bf_url,description=bf_description,image=imagebf_image,categories=bf_tags.</div>';
            }
            // we load all entries to check if entry were already created from feed
            $vSearchManager = $this->getService(SearchManager::class);
            $entries = $vSearchManager->search(['formsIds' => [$this->arguments['mapping']['id']]]);
        }
        if (!empty($this->arguments['url'])) {
            $nburl = 0;
            $syndication = ['pages' => []];
            foreach ($this->arguments['url'] as $cle => $url) {
                if ($url != '') {
                    $feed = new \SimplePie\SimplePie();
                    $feed->set_feed_url($url);
                    $feed->enable_cache(true);
                    $feed->init();
                    $feed->handle_content_type();
                    if ($feed->error()) {
                        return '<div class="yw-alert yw-alert--danger">' . _t('ERROR') . ' ' . $feed->error() . '</div>' . "\n";
                    }

                    if ($feed) {
                        $feedItems = $feed->get_items(0, $this->arguments['nb']);
                        $nbItems = count($feedItems);
                        foreach ($feedItems as $item) {
                            $feedItem = $this->feedItemFields($item, $nburl);
                            if ($mappingToBazar) {
                                $feedItem['linkToEntry'] = $feedItem['mappingInput'] = '';
                                $entryExists = multiArraySearch($entries, $this->arguments['mapping']['url'], $feedItem['url']);
                                if (!empty($entryExists)) {
                                    $feedItem['linkToEntry'] = $this->getService(UrlFormatter::class)->href('', $entryExists[0]['tag']);
                                } else {
                                    $entry = [];
                                    $converter = new HtmlConverter(['strip_tags' => true]); // we will convert html to md, but safe
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
                            // the key is beginning with the datestamp to order by date desc, and we concat the title for unicity
                            $syndication['pages'][$feedItem['datestamp'] . urlencode($feedItem['title'])] = $feedItem;
                        }
                    }
                }
                $nburl = $nburl + 1;
            }
            // sort all feeds per date
            krsort($syndication['pages']);
            if (empty($this->arguments['title'])) {
                $title = '';
            } elseif ($this->arguments['title'] == 'rss') {
                $title = $feed->get_title();
            } else {
                $title = $this->arguments['title'];
            }

            $wrapper = '<div class="feed_syndication' . ($this->arguments['class'] ? ' ' . $this->arguments['class'] : '') . '">' . "\n";

            // A shared Presentation, if that is what was asked for: `template="card"` means
            // the same thing here as it does on an entry list (ticket 37). Syndication's own
            // three templates stay for the bodies that name them.
            if (PresentationRenderer::knows((string)$this->arguments['template'])) {
                return $wrapper
                    . $this->getService(PresentationRenderer::class)->render(
                        (string)$this->arguments['template'],
                        $this->itemsFrom($syndication['pages']),
                        $this->arguments
                    )
                    . "\n</div>\n";
            }

            return $wrapper .
                $this->render('@core/' . $this->arguments['template'], [
                    'syndication' => $syndication,
                    'title' => $title,
                    'urlSite' => $feed->get_link(),
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
            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeoutInSec);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeoutInSec);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            if ($noSSLCheck) {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            }
            curl_exec($ch);
            $errors = curl_error($ch);
            if (!empty($errors)) {
                var_dump($errors);

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
