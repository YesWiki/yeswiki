<?php

use League\HTMLToMarkdown\HtmlConverter;
use Tamtamchik\SimpleFlash\Flash;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Core\YesWikiAction;

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
        $mappingToBazar = !empty($this->arguments['mapping']) && $this->wiki->userIsAdmin();
        if ($mappingToBazar) {
            $this->addToBazar();
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
                                $feedItem['linkToEntry'] = $feedItem['mappingInput'] = '';
                                $entryExists = multiArraySearch($entries, $this->arguments['mapping']['url'], $feedItem['url']);
                                if (!empty($entryExists)) {
                                    $feedItem['linkToEntry'] = $this->wiki->href('', $entryExists[0]['id_fiche']);
                                } else {
                                    $entry = [];
                                    $converter = new HtmlConverter(['strip_tags' => true]); // we will convert html to md, but safe
                                    foreach ($this->arguments['mapping'] as $key => $val) {
                                        switch ($key) {
                                        case 'id':
                                            $entry['id_typeannonce'] = $val;
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
                                    $entry['date_creation_fiche'] = $item->get_date('Y-m-d H:i:s');
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
        if (!empty($_GET['mapping'])) {
            $data = json_decode(urldecode($_GET['mapping']), true);
            if (!empty($data)) {
                $data['antispam'] = 1;
                $entryManager = $this->getService(EntryManager::class);
                $entryManager->create($data['id_typeannonce'], $data, false, $data['bf_url']);
                Flash::success(_t('SYNDICATION_ENTRY_SAVED', ['title' => $data['bf_titre']]));
                $this->wiki->redirect($this->wiki->href());
            }
        }
    }
}
