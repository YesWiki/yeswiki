<?php

namespace YesWiki\Content\Field;

use Psr\Container\ContainerInterface;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Service\MarkdownFormatterService;
use YesWiki\Render\Service\TabsService;

#[\Field(['listefichesliees', 'listefiches'])]
class LinkedEntryField extends BazarField
{
    protected $query;
    protected $otherParams;
    protected $limit;
    protected $template;
    protected $linkedId;
    protected $addEntryBtnLabel;

    protected const FIELD_QUERY = 2;
    protected const FIELD_OTHER_PARAMS = 3;
    protected const FIELD_LIMIT = 4;
    protected const FIELD_TEMPLATE = 5;
    protected const FIELD_LINK_TYPE = 6;
    protected const FIELD_LABEL = 7;
    protected const FIELD_ADD_ENTRY_BTN_LABEL = 10;

    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);

        $this->label = $values[self::FIELD_LABEL] ?? '';
        $this->query = $values[self::FIELD_QUERY] ?? '';
        $this->otherParams = $values[self::FIELD_OTHER_PARAMS] ?? '';
        $this->limit = $values[self::FIELD_LIMIT] ?? '';
        $this->template = $values[self::FIELD_TEMPLATE] ?? '';
        $this->linkedId = $values[self::FIELD_LINK_TYPE] ?? '';
        $this->propertyName = null;
        $this->addEntryBtnLabel = $values[self::FIELD_ADD_ENTRY_BTN_LABEL] ?? '';
    }

    protected function renderInput($entry)
    {
        if (isset($entry['tag'])) {
            return $this->render(
                '@core/inputs/linked-entry.twig',
                $this->getTwigOptions($entry)
            );
        }
    }

    protected function renderStatic($entry)
    {
        if (!empty($entry['tag']) && !empty($entry['form_id'])) {
            return $this->render(
                '@core/fields/linked-entry.twig',
                $this->getTwigOptions($entry)
            );
        }

        return '';
    }

    protected function getTwigOptions($entry)
    {
        $output = $this->renderSecuredBazarList($entry);
        $addEntryBtnLabel = $this->addEntryBtnLabel;
        $addEntryLink = $this->getService(UrlFormatter::class)->href(
            'iframe',
            'BazaR',
            'context=addentry&showmenu=0&view=saisir&' . $this->linkedId . '=' . $entry['tag'] . '&id=' . $this->name,
            false
        );
        $emptyList = $this->isEmptyOutput($output);

        return compact(['output', 'addEntryLink', 'addEntryBtnLabel', 'emptyList']);
    }

    protected function renderSecuredBazarList($entry): string
    {
        $tabsService = $this->getService(TabsService::class);
        $index = $tabsService->saveState();
        $output = $this->getService(MarkdownFormatterService::class)->format($this->getBazarListAction($entry));
        $tabsService->resetState($index);

        return $output;
    }

    protected function isEmptyOutput(string $output): bool
    {
        return empty($output) || preg_match('/<div id="[^"]+" class="entry-list[^"]*"[^>]*>\\s*<div class="list">\\s*<\\/div>\\s*<\\/div>/', $output);
    }

    private function getBazarListAction($entry): string
    {
        $query = $this->getQueryForLinkedLabels($entry);
        if (!empty($query)) {
            $query = ((!empty($this->query)) ? $this->query . '|' : '') . $query;
            $action = '{{entrylist id="' . $this->name . '" query="' . $query . '" '
                . ((!empty($this->limit)) ? 'nb="' . $this->limit . '" ' : '')
                . 'template="' . (empty(trim($this->template)) ? 'liste_liens.twig' : $this->template) . '" '
                . $this->otherParams . '}}';

            return $action;
        }

        return '';
    }

    protected function getQueryForLinkedLabels($entry): ?string
    {
        if (str_contains((string)$this->name, '|')) {
            return '';
        }

        return isset($entry['tag']) ? $this->linkedId . '=' . $entry['tag'] : '';
    }

    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return array_merge(
            parent::jsonSerialize(),
            [
                'query' => $this->query,
                'limit' => $this->limit,
                'linkedId' => $this->linkedId,
                'template' => $this->template,
                'otherParams' => $this->otherParams,
            ]
        );
    }
}
