<?php

namespace YesWiki\Bazar\Exception;

/**
 * Raised when an entry is saved while some of the fields the form requires are still empty.
 */
class RequiredFieldsException extends \Exception
{
    private $fields;

    /**
     * @param array<string,string> $fields label of every missing field, indexed by property name
     */
    public function __construct(array $fields)
    {
        $this->fields = $fields;
        parent::__construct(_t('BAZ_CHAMPS_REQUIS') . ' : ' . implode(', ', $fields));
    }

    /**
     * @return array<string,string>
     */
    public function getFields(): array
    {
        return $this->fields;
    }
}
