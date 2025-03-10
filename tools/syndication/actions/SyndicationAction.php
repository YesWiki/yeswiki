<?php

use YesWiki\Core\YesWikiAction;
use YesWiki\Bazar\Service\EntryManager;

include_once 'tools/syndication/libs/syndication.lib.php';
require_once __DIR__ . '/../vendor/autoload.php';

class SyndicationAction extends YesWikiAction
{
    public function formatArguments($arg): array
    {
        $arg['showimage'] = $this->formatBoolean($arg, false, 'showimage');
        $arg['nouvellefenetre'] = $this->formatBoolean($arg, false, 'nouvellefenetre');
        if (empty($arg['class'])) {
            $arg['class'] = '';
        }
        if (empty($arg['nb'])) {
            $arg['nb'] = 0;
        }
        if (empty($arg['template'])) {
            $arg['template'] = 'liste_description.tpl.html';
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
                'description' => $mapping['description'] ?? 'bf_description',
                'image' => $mapping['image'] ?? 'imagebf_image',
                'categories' => $mapping['categories'] ?? 'bf_tags',
            ];
        }

        return $arg;
    }

    public function run(): ?string
    {
        $mappingToBazar = !empty($this->arguments['mapping']);
        if ($mappingToBazar) {
            if (empty($this->arguments['mapping']['id'])) {
                return '<div class="alert alert-danger">' . _t('ERROR') . ' ' . _t('SYNDICATION_MAPPING_ID_REQUIRED') . ', ex: id=1400,title=bf_titre,url=bf_url,description=bf_description,image=imagebf_image,categories=bf_tags.</div>';
            } else {
                // we load all entries to check if entry were already created from feed
                $entryManager = $this->getService(EntryManager::class);
                $entries = $entryManager->search(['formsIds' => [$this->arguments['mapping']['id']]]);
            }
        }
        if (!empty($this->arguments['url'])) {
            $nburl = 0;
            $syndication = ['pages' => []];
            foreach ($this->arguments['url'] as $cle => $url) {
                if ($url != '') {
                    $feed = new SimplePie\SimplePie();
                    $feed->set_feed_url($url);
                    $feed->enable_cache(true);
                    $feed->init();
                    $feed->handle_content_type();
                    if ($feed->error()) {
                        return '<div class="alert alert-danger">' . _t('ERROR') . ' ' . $feed->error() . '</div>' . "\n";
                    }

                    if ($feed) {
                        $feedItems = $feed->get_items(0, $this->arguments['nb']);
                        $nbItems = count($feedItems);
                        foreach ($feedItems as $item) {
                            $feedItem = [];
                            if (is_array($this->arguments['source'])) {
                                $feedItem['source'] = $this->arguments['source'][$nburl];
                            } else {
                                $feedItem['source'] = '';
                            }

                            $feedItem['url'] = $item->get_permalink();
                            $feedItem['title'] = $item->get_title();
                            $feedItem['description'] = $item->get_content();
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
                                }
                            }
                            if (!empty($this->arguments['nbchar'])) {
                                $feedItem['description'] = preg_replace("/\s+/u", ' ', strip_tags($feedItem['description'] ?? ''));
                                $descLen = strlen($feedItem['description']);
                                // check if text longer than max chars specified
                                if ($descLen > 0
                                    && $descLen > $this->arguments['nbchar']) {
                                    $feedItem['description'] = truncate(
                                        $feedItem['description'],
                                        $this->arguments['nbchar'],
                                        '... <a class="lien_lire_suite" href="' . $feedItem['url']
                                    . '" ' . ($this->arguments['nouvellefenetre'] ? 'target="_blank" ' : '')
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
                            if ($mappingToBazar) {
                                $feedItem['mappingId'] = $this->arguments['mapping']['id'];
                                $entry = [];
                                foreach ($this->arguments['mapping'] as $key => $val) {
                                    if ($key == 'id') {
                                        $entry['id_typeannonce'] = $val;
                                    } else {
                                        $entry[$val] = $feedItem[$key];
                                    }
                                }
                                $entry['date_creation_fiche'] = $item->get_date('Y-m-d H:i:s');
                                $feedItem['mappingInput'] = json_encode($entry);
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

            return '<div class="feed_syndication' . ($this->arguments['class'] ? ' ' . $this->arguments['class'] : '') . '">' . "\n" .
                $this->render('@syndication/' . $this->arguments['template'], [
                    'syndication' => $syndication,
                    'title' => $title,
                    'urlSite' => $feed->get_link(),
                    'urlHash' => md5(implode(',', $this->arguments['url'])),
                    'showImage' => $this->arguments['showimage'],
                    'ext' => $this->arguments['nouvellefenetre'],
                ]) . "\n" .
            '</div>' . "\n";
        } else {
            return '<div class="alert alert-danger"><strong>' . _t('SYNDICATION_ACTION_SYNDICATION') . '</strong> : '
        . _t('SYNDICATION_PARAM_URL_REQUIRED') . '.</div>' . "\n";
        }
    }
}
