<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;

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
        $this->template = $values[self::FIELD_TEMPLATE] ?? $GLOBALS['wiki']->config['default_bazar_template'];
        $this->linkType = $values[self::FIELD_LINK_TYPE] === 'checkbox' ? 'checkboxfiche' : 'listefiche';
    }

    public function renderInput($entry)
    {
        // Display the linked entries only on update
        if( isset($entry['id_fiche']) ) {
            return $GLOBALS['wiki']->Format($this->getBazarListAction($entry));
        }
    }

    public function renderStatic($entry)
    {
        return $GLOBALS['wiki']->Format($this->getBazarListAction($entry));
    }

    private function getBazarListAction($entry)
    {
        if( $this->query && $this->query !== '' ) {
            $query = $this->query . '|' . $this->linkType . $entry['id_typeannonce'] . '=' . $entry['id_fiche'];
        } else if( isset($entry) && $entry !== '') {
            $query = $this->linkType . $entry['id_typeannonce'] . '=' . $entry['id_fiche'];
        } else {
            $query = '';
        }

        return '{{bazarliste id="' . $this->name . '" query="' . $query . '" nb="' . $this->limit . '" template="' . $this->template . '" ' . $this->otherParams . '}}';
    }
}
