<?php

namespace YesWiki\Import\Service;

use League\HTMLToMarkdown\HtmlConverter;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Content\Entity\ContentTypeSchema;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FeedLoader;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\ListManager;
use YesWiki\Kernel\Service\StringUtilService;

class RssImporter extends Importer
{
    protected string $source;
    /**
     * @var list<array<string, mixed>> the form this importer installs when it has none
     */
    protected array $databaseForms;

    public function __construct(
        string $source,
        ParameterBagInterface $params,
        ContainerInterface $services,
        EntryManager $entryManager,
        ImporterManager $importerManager,
        FormManager $formManager,
        ListManager $listManager
    ) {
        $this->source = $source;
        $this->params = $params;
        $this->services = $services;
        $this->entryManager = $entryManager;
        $this->importerManager = $importerManager;
        $this->formManager = $formManager;
        $this->listManager = $listManager;
        $dataSources = $params->has('dataSources') ? $params->get('dataSources') : [];
        $sourceOptions = is_array($dataSources) ? ($dataSources[$this->source] ?? []) : [];
        $this->config = $this->checkConfig(is_array($sourceOptions) ? $sourceOptions : []);
        $this->databaseForms = [
            [
                'id' => null,
                'label' => 'Imports de flux RSS',
                'description' => 'Imports de flux RSS',
                ContentTypeSchema::CONTENT_TYPE => ContentTypeSchema::TYPE_ENTRY,

                'template' => <<<EOT
texte***bf_titre***Titre***255***255*** *** ***text***1*** *** *** * *** * *** *** *** ***
image***bf_image***Image***400***400***1000***1000***right***0*** *** *** * *** * *** *** *** ***
textelong***bf_chapeau***Résumé***80***8*** *** ***wiki***0*** *** *** * *** * *** *** *** ***
textelong***bf_description***Contenu***80***12*** *** ***wiki***0*** *** *** * *** * *** *** *** ***
texte***bf_auteurice***Auteurices***255***255*** *** ***wiki***0*** *** *** * *** * *** *** *** ***
tags***bf_categories***Catégories*** *** *** *** *** ***0*** *** *** * *** * *** *** *** ***
lien_internet***bf_url***Url de l'article*** *** *** *** *** ***0*** *** *** * *** * *** *** *** ***
EOT,
                'lang' => 'fr-FR',
                'only_one_entry' => 'N',
                'only_one_entry_message' => null,
            ],
        ];
    }

    /**
     * Check if config input is good enough to be used by Importer.
     *
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed> checked config
     */
    public function checkConfig(array $config)
    {
        $config = parent::checkConfig($config);

        return $config;
    }

    public static function getAdminFields(): array
    {
        return [
            'url' => ['type' => 'url', 'required' => true],
        ];
    }

    public static function getOwnFields(): array
    {
        return [
            ['key' => 'bf_titre', 'label' => 'Titre'],
            ['key' => 'bf_auteurice', 'label' => 'Auteurices'],
            ['key' => 'bf_categories', 'label' => 'Catégories'],
            ['key' => 'bf_chapeau', 'label' => 'Résumé'],
            ['key' => 'bf_description', 'label' => 'Contenu'],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getData(): array
    {
        $data = $this->getService(FeedLoader::class)->read(
            (string)$this->config['url'],
            function (\SimplePie\SimplePie $feed): array {
                $data = [];
                if ($feed->error()) {
                    $errors = $feed->error();
                    echo 'Erreur lors de la récupération du flux RSS "' . $this->config['url'] . '" : '
                        . (is_array($errors) ? implode(', ', $errors) : $errors) . "\n";

                    return $data;
                }
                $rssItems = $feed->get_items();
                echo count($rssItems) . ' article(s) trouvé(s) dans le flux.' . "\n";
                foreach ($rssItems as $item) {
                    $content = (string)$item->get_content();
                    preg_match_all(
                        '~<img\s[^>]*?src\s*=\s*[\'\"]([^\'\"]*?)[\'\"][^>]*?>~',
                        $content,
                        $matches
                    );
                    $img = $matches[1][0] ?? '';
                    if (empty($img)) {
                        if ($enclosure = $item->get_enclosure()) {
                            $img = $enclosure->get_thumbnail() ?: $enclosure->get_link();
                        }
                    }
                    if (empty($img) && ($thumbnail = $item->get_thumbnail())) {
                        $img = $thumbnail['url'];
                    }
                    $cats = [];
                    $categories = $item->get_categories();
                    if (!empty($categories)) {
                        foreach ($categories as $category) {
                            $cats[] = $category->get_label();
                        }
                    }
                    if ($author = $item->get_author()) {
                        $author = $author->get_name();
                    }
                    $data[] = [
                        'title' => $item->get_title(),
                        'author' => $author,
                        'categories' => $cats,
                        'summary' => $item->get_description(),
                        'link' => $item->get_link(),
                        'date' => $item->get_date('Y-m-d H:i:s'),
                        'content' => $content,
                        'image' => $img,
                    ];
                }

                return $data;
            }
        );

        return $data ?? [];
    }

    /**
     * @return array<array-key, array<string, mixed>>
     */
    public function mapData(mixed $data): array
    {
        $preparedData = [];
        $converter = new HtmlConverter(['strip_tags' => true]);
        foreach ($data as $i => $item) {
            $entry = [];
            $entry['bf_titre'] = $item['title'];
            $entry['bf_auteurice'] = $item['author'];
            $entry['bf_categories'] = implode(', ', $item['categories']);
            $entry['bf_description'] = $converter->convert($item['content']);
            $entry['bf_chapeau'] = $converter->convert($item['summary']);
            $entry['bf_url'] = $item['link'];
            $entry['date_creation_fiche'] = $item['date'];
            $entry['imagebf_image'] = $this->importerManager->downloadFile($item['image']);
            $preparedData[$i] = $this->applyFieldsMapping($entry);
        }

        return $preparedData;
    }

    /**
     * @param array<mixed> $data
     */
    public function syncData(array $data): void
    {
        $existingEntries = $this->entryManager->search(['formsIds' => [$this->config['formId']]]);
        foreach ($data as $entry) {
            $res = StringUtilService::searchNested($existingEntries, 'bf_url', $entry['bf_url']);
            if (!$res) {
                $entry['antispam'] = 1;
                $this->entryManager->create($this->config['formId'], $entry, false, $entry['bf_url']);
                echo 'L\'article "' . ($entry['bf_titre'] ?? $entry['bf_url']) . '" créé.' . "\n";
            } else {
                echo 'L\'article "' . ($entry['bf_titre'] ?? $entry['bf_url']) . '" existe déja.' . "\n";
            }
        }
    }

    public function syncFormModel(): void
    {
        $form = $this->formManager->getOne($this->config['formId']);
        if (empty($form)) {
            $this->databaseForms[0]['id'] = $this->config['formId'];
            $this->formManager->create($this->databaseForms[0]);
        } else {
            echo 'La base bazar existe deja.' . "\n";
        }
    }
}
