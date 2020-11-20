<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;

/**
 * Base class for ListListField and EntryListField
 */
abstract class ListField extends BazarField
{
    protected $listLabel;
    protected $options;

    protected const FIELD_LIST_LABEL = 6;

    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);

        $this->listLabel = $values[self::FIELD_LIST_LABEL];
        $this->options = [];

        $this->entryId = $values[self::FIELD_TYPE] . $values[self::FIELD_NAME] . $values[self::FIELD_LIST_LABEL];
    }

    public function getOptions()
    {
        return  $this->options;
    }
}
