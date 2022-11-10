<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;

/**
 * @Field({"acls"})
 */
class AclField extends BazarField
{
    protected $entryReadRight;
    protected $entryWriteRight;
    protected $entryCommentRight;

    protected const FIELD_ENTRY_READ_RIGHT = 1;
    protected const FIELD_ENTRY_WRITE_RIGHT = 2;
    protected const FIELD_ENTRY_COMMENT_RIGHT = 3;

    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);

        $this->name = null;
        $this->label = null ;
        $this->propertyName = null;
        $this->entryReadRight = $values[self::FIELD_ENTRY_READ_RIGHT];
        $this->entryWriteRight = $values[self::FIELD_ENTRY_WRITE_RIGHT];
        $this->entryCommentRight = $values[self::FIELD_ENTRY_COMMENT_RIGHT];
    }

    protected function renderInput($entry)
    {
        return "";
    }

    public function formatValuesBeforeSave($entry)
    {
        if (empty($GLOBALS['wiki']->LoadAcl($entry['id_fiche'], 'read', false)['list'])) {
            $GLOBALS['wiki']->SaveAcl($entry['id_fiche'], 'read', $this->replaceWithCreator($this->entryReadRight, $entry));
        }
        if (empty($GLOBALS['wiki']->LoadAcl($entry['id_fiche'], 'write', false)['list'])) {
            $GLOBALS['wiki']->SaveAcl($entry['id_fiche'], 'write', $this->replaceWithCreator($this->entryWriteRight, $entry));
        }
        if (empty($GLOBALS['wiki']->LoadAcl($entry['id_fiche'], 'comment', false)['list'])) {
            $GLOBALS['wiki']->SaveAcl($entry['id_fiche'], 'comment', $this->replaceWithCreator($this->entryCommentRight, $entry));
        }

        return [];
    }

    protected function renderStatic($entry)
    {
        return "";
    }

    private function replaceWithCreator($right, $entry)
    {
        // le signe # ou le mot user indiquent que le owner de la fiche sera utilisé pour les droits
        if ($right === 'user' or $right === '#') {
            return $entry['nomwiki'];
        }
        return $right;
    }
    
    // change return of this method to keep compatible with php 7.3 (mixed is not managed)
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return [
            'type' => $this->getType(),
            'entryReadRight' => $this->entryReadRight,
            'entryWriteRight' => $this->entryWriteRight,
            'entryCommentRight' => $this->entryCommentRight,
            ];
    }
}
