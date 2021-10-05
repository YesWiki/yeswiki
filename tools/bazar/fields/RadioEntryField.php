<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;
use YesWiki\Wiki;

/**
 * @Field({"radiofiche"})
 */
class RadioEntryField extends EnumField
{
    public $isDistantJson;
    protected $baseUrl ;

    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);

        $this->type = 'radiofiche';
        
        $this->isDistantJson = filter_var($this->name, FILTER_VALIDATE_URL);

        if ($this->isDistantJson) {
            $this->prepareJSONEntryField();
        } else {
            $this->options = null ;
            $this->baseUrl = null;
        }
    }

    protected function renderInput($entry)
    {
        return $this->render('@bazar/inputs/radio.twig', [
            'options' => $this->getOptions(),
            'value' => $this->getValue($entry)
        ]);
    }

    protected function renderStatic($entry)
    {
        $value = $this->getValue($entry) ;
        if (!$value) {
            return null;
        }

        if ($this->isDistantJson) {
            if (!empty($this->optionsUrls[$value])) {
                $entryUrl = $this->optionsUrls[$value];
            } else {
                $entryUrl = $this->baseUrl . $value;
            }
        } else {
            $entryUrl = $this->services->get(Wiki::class)->href('', $value);
        }

        return $this->render('@bazar/fields/select_entry.twig', [
            'value' => $value,
            'label' => $this->getOptions()[$value],
            'entryUrl' => $entryUrl
        ]);
    }
    
    public function getOptions()
    {
        // load options only when needed but not at construct to prevent infinite loops
        if (is_null($this->options)) {
            if ($this->isDistantJson) {
                $this->loadOptionsFromJson();
            } else {
                $this->loadOptionsFromEntries();
            }
        }
        return  $this->options;
    }
}
