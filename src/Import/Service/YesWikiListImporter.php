<?php

namespace YesWiki\Import\Service;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\ListManager;

class YesWikiListImporter extends Importer
{
    protected string $source;

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
        if (empty($config['url'])) {
            throw new \Exception('Le paramètre "url" est requis pour un importer YesWikiList.');
        }
        if (empty($config['listId'])) {
            throw new \Exception('Le paramètre "listId" est requis pour un importer YesWikiList.');
        }

        return $config;
    }

    public static function getAdminFields(): array
    {
        return [
            'url' => ['type' => 'url', 'required' => true],
            'listId' => ['type' => 'text', 'required' => true],
            'title' => ['type' => 'text', 'required' => false],
            'noSSLCheck' => ['type' => 'checkbox', 'required' => false],
        ];
    }

    public static function needsBazarForm(): bool
    {
        return false;
    }

    /**
     * @return array<mixed>
     */
    public function getData(): array
    {
        $response = $this->importerManager->curl(
            $this->config['url'],
            [],
            false,
            [],
            empty($this->config['noSSLCheck']) ? false : $this->config['noSSLCheck']
        );
        $data = json_decode((string)$response, true);

        return is_array($data) ? $data : [];
    }

    /**
     * @return list<array{id: string, label: string}>
     */
    public function mapData(mixed $data): array
    {
        $nodes = [];
        foreach ($data as $item) {
            if (empty($item['id_fiche']) || !isset($item['bf_titre']) || $item['bf_titre'] === '') {
                continue;
            }

            $nodes[] = [
                'id' => $item['tag'] ?? $item['id_fiche'] ?? '',
                'label' => $item['title'] ?? $item['bf_titre'] ?? '',
            ];
        }
        usort($nodes, function (array $a, array $b): int {
            return strcmp($this->sortableLabel($a['label']), $this->sortableLabel($b['label']));
        });

        return $nodes;
    }

    /**
     * Sorting on the label as typed puts "Éole" after "Zéphyr": compare on a transliterated, lowercased copy instead.
     */
    private function sortableLabel(string $label): string
    {
        return strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $label) ?: $label);
    }

    /**
     * @param array<mixed> $data
     */
    public function syncData(array $data): void
    {
        $listId = $this->config['listId'];
        $title = $this->config['title'] ?? $listId;
        // Where these values came from, recorded on the list itself, so a webmaster looking at two
        // lists can tell which one tonight's sync will replace (ticket 64).
        $origin = (string)($this->config['url'] ?? '');
        if ($this->listManager->isList($listId)) {
            $this->listManager->update($listId, $title, $data, $origin);
            echo 'La liste "' . $listId . '" a été mise à jour avec ' . count($data) . ' valeur(s).' . "\n";
        } else {
            $this->listManager->create($title, $data, $listId, $origin);
            echo 'La liste "' . $listId . '" a été créée avec ' . count($data) . ' valeur(s).' . "\n";
        }
    }
}
