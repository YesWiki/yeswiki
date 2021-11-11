<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;
use YesWiki\Bazar\Field\BazarField;
use YesWiki\Bazar\Field\CheckboxEntryField;
use YesWiki\Bazar\Field\SelectEntryField;
// use YesWiki\Bazar\Field\RadioEntryField;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Core\Service\Performer;

/**
 * @Field({"listefichesliees", "listefiches"})
 */
class LinkedEntryField extends BazarField
{
    protected $query;
    protected $otherParams;
    protected $limit;
    protected $template;
    protected $linkType;

    protected const FIELD_QUERY = 2;
    protected const FIELD_OTHER_PARAMS = 3;
    protected const FIELD_LIMIT = 4;
    protected const FIELD_TEMPLATE = 5;
    protected const FIELD_LINK_TYPE = 6;

    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);

        $this->query = $values[self::FIELD_QUERY] ?? '';
        $this->otherParams = $values[self::FIELD_OTHER_PARAMS] ?? '';
        $this->limit = $values[self::FIELD_LIMIT] ?? '';
        $this->template = $values[self::FIELD_TEMPLATE] ?? '';
        $this->linkType = (!empty($values[self::FIELD_LINK_TYPE]) && $values[self::FIELD_LINK_TYPE] === 'checkbox')
            ? 'checkboxfiche' : ($values[self::FIELD_LINK_TYPE] ?? '') ;
        $this->propertyName = null; // to prevent bad saved field when updating entry and !canEdit or at export/import
    }

    protected function renderInput($entry)
    {
        // Display the linked entries only on update
        if (isset($entry['id_fiche'])) {
            return $this->getService(Performer::class)->run('wakka', 'formatter', ['text' => $this->getBazarListAction($entry)]);
        }
    }

    protected function renderStatic($entry)
    {
        // Display the linked entries only if id_fiche and id_typeannonce
        if (!empty($entry['id_fiche']) && !empty($entry['id_typeannonce'])) {
            return $this->getService(Performer::class)->run('wakka', 'formatter', ['text' => $this->getBazarListAction($entry)]);
        } else {
            return null ;
        }
    }

    private function getBazarListAction($entry)
    {
        $query = $this->getQueryForLinkedLabels($entry) ;
        if (!empty($query)) {
            $query = ((!empty($this->query)) ? $this->query.  '|' : '')  . $query  ;

            return '{{bazarliste id="' . $this->name . '" query="' . $query . '"'
                . ((!empty($this->limit)) ? ' nb="' . $this->limit .'"': '')
                . ((!empty(trim($this->template))) ? ' template="' . trim($this->template) . '" ' : '')
                . $this->otherParams . '}}';
        } else {
            return '';
        }
    }

    protected function getQueryForLinkedLabels($entry): ?string
    {
        // get FormManager here and not in construct to prevent loop
        $form = $this->services->get(FormManager::class)->getOne($this->name);

        if (!is_array($form) || !is_array($form['prepared'])
                || empty($entry['id_typeannonce'])
                || empty($entry['id_fiche'])) {
            return '';
        }
        $query = '' ;
        // find CheckboxEntryField or SelectEntryField or RadioEntryField with right name
        foreach ($form['prepared'] as $field) {
            if (
            (
                $field instanceof SelectEntryField
                || $field instanceof CheckboxEntryField
                || $field instanceof RadioEntryField
            )
            && $field->getLinkedObjectName() == $entry['id_typeannonce']
            &&
            (
                empty($this->linkType)
                || strpos($field->getType(), $this->linkType) === 0 // checkboxfiche or listefiche
                || $field->getPropertyName()== $this->linkType // label
                || substr($field->getPropertyName(), strlen($field->getType().trim($entry['id_typeannonce']))) == $this->linkType // label
            )
                ) {
                $query .= (empty($query)) ? '' : '|' ;
                $query .= $field->getPropertyName() . '=' . $entry['id_fiche'];
            }
        }

        return $query ;
    }
    
    public function jsonSerialize()
    {
        return array_merge(
            parent::jsonSerialize(),
            [
                'query' => $this->query,
                'limit' => $this->limit,
                'linkType' => $this->linkType,
                'template' => $this->template,
                'otherParams' => $this->otherParams,
            ]
        );
    }
}
