<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;

/**
 * @Field({"listefiche"})
 */
class SelectEntryField extends EnumField
{
    protected $isDistantJson;
    protected $displayMethod;

    protected const FIELD_DISPLAY_METHOD = 3;

    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);

        $this->displayMethod = $values[self::FIELD_DISPLAY_METHOD];
        $this->isDistantJson = filter_var($this->name, FILTER_VALIDATE_URL);

        if($this->isDistantJson) {
            $this->propertyName = $this->type . removeAccents(preg_replace('/--+/u', '-', preg_replace('/[[:punct:]]/', '-', $this->name))) . $this->listLabel;
            $this->loadOptionsFromJson();
        } else {
            $this->loadOptionsFromEntries();
        }
    }

    public function renderInput($entry)
    {
        return $this->render('@bazar/inputs/select.twig', [
            'value' => $this->getValue($entry),
            'options' => $this->options
        ]);
    }

    public function renderStatic($entry)
    {
        $value = $this->getValue($entry);
        if( !$value ) return null;

        if( $this->displayMethod === 'fiche' ) {
            if( $this->isDistantJson ) {
                // TODO display the entry in a iframe ?
                return null;
            } else {
                // TODO add documentation
                return baz_voir_fiche(0, $value);
            }
        }

        if( $this->isDistantJson ) {
            $entryUrl = explode('BazaR/json', $this->name);
            $entryUrl = $entryUrl[0] . $value;
        } else {
            $entryUrl = $GLOBALS['wiki']->href('', $value);
        }

        return $this->render('@bazar/fields/select_entry.twig', [
            'value' => $value,
            'label' => $this->options[$value],
            'entryUrl' => $entryUrl
        ]);
    }
}
