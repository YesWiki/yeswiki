<?php

namespace YesWiki\Bazar\Field;

use Field;
use Psr\Container\ContainerInterface;
use YesWiki\Core\Service\Performer;
use YesWiki\Templates\Service\TabsService;

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
        $this->propertyName = null; // to prevent bad saved field when updating entry and !canEdit or at export/import
        $this->addEntryBtnLabel = $values[self::FIELD_ADD_ENTRY_BTN_LABEL] ?? '';
    }

    protected function renderInput($entry)
    {
        // Display the linked entries only on update
        if (isset($entry['id_fiche'])) {
            return $this->render(
                '@bazar/inputs/linked-entry.twig',
                $this->getTwigOptions($entry)
            );
        }
    }

    protected function renderStatic($entry)
    {
        // Display the linked entries only if id_fiche and id_typeannonce
        if (!empty($entry['id_fiche']) && !empty($entry['id_typeannonce'])) {
            return $this->render(
                '@bazar/fields/linked-entry.twig',
                $this->getTwigOptions($entry)
            );
        }

        return '';
    }

    protected function getTwigOptions($entry)
    {
        $output = $this->renderSecuredBazarList($entry);
        $addEntryBtnLabel = $this->addEntryBtnLabel;
        $addEntryLink = $this->getWiki()->href(
            'iframe',
            'BazaR',
            'context=addentry&voirmenu=0&vue=saisir&' . $this->linkedId . '=' . $entry['id_fiche'] . '&id=' . $this->name,
            false
        );
        $emptyList = $this->isEmptyOutput($output);

        return compact(['output', 'addEntryLink', 'addEntryBtnLabel', 'emptyList']);
    }

    protected function renderSecuredBazarList($entry): string
    {
        $tabsService = $this->getService(TabsService::class);
        $index = $tabsService->saveState();
        $output = $this->getService(Performer::class)->run(
            'wakka',
            'formatter',
            ['text' => $this->getBazarListAction($entry)]
        );
        $tabsService->resetState($index);

        return $output;
    }

    protected function isEmptyOutput(string $output): bool
    {
        return empty($output) || preg_match('/<div id="[^"]+" class="bazar-list[^"]*"[^>]*>\\s*<div class="list">\\s*<\\/div>\\s*<\\/div>/', $output);
    }

    private function getBazarListAction($entry): string
    {
        $query = $this->getQueryForLinkedLabels($entry);
        if (!empty($query)) {
            $query = ((!empty($this->query)) ? $this->query . '|' : '') . $query;
            $action = '{{bazarliste id="' . $this->name . '" query="' . $query . '" '
                . ((!empty($this->limit)) ? 'nb="' . $this->limit . '" ' : '')
                . 'template="' . (empty(trim($this->template)) ? 'liste_liens.tpl.html' : $this->template) . '" '
                . $this->otherParams . '}}';

            return $action;
        }

        return '';
    }

    protected function getQueryForLinkedLabels($entry): ?string
    {
        $formId = explode('|', $this->name);
        $externalForm = false;
        if (count($formId) == 2) {
            $apiUrl = $formId[0] . '/?api/forms/' . $formId[1];
            $externalForm = true;
            $form = json_decode(file_get_contents($apiUrl), true);
        }
        if (!$externalForm) {
            // we just query on the field
            return isset($entry['id_fiche']) ? $this->linkedId . '=' . $entry['id_fiche'] : '';
        }
        if (!is_array($form) || !is_array($form['prepared'])
                || empty($entry['id_typeannonce'])
                || empty($entry['id_fiche'])) {
            return '';
        }
        $query = '';
        // find EnumEntryField with right name
        foreach ($form['prepared'] as $field) {
            if (strstr($field['propertyname'], '-api-forms-' . $entry['id_typeannonce'] ?? 'none')) {
                $query .= (empty($query)) ? '' : '|';
                $query .= ($externalForm ? $field['propertyname'] : $field->getPropertyName()) . '=' . $entry['id_fiche'];
            }
        }

        return $query;
    }

    // change return of this method to keep compatible with php 7.3 (mixed is not managed)
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
