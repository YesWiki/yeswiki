<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;
use YesWiki\Wiki;

/**
 * @Field({"radiofiche"})
 */
class RadioEntryField extends RadioField
{
    public $isDistantJson;
    protected $baseUrl;

    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);

        $this->isDistantJson = filter_var($this->name, FILTER_VALIDATE_URL);

        if ($this->isDistantJson) {
            $this->prepareJSONEntryField();
        } else {
            $this->options = null;
            $this->baseUrl = null;
        }
    }

    protected function renderStatic($entry)
    {
        $value = $this->getValue($entry);
        if (!$value) {
            return '';
        }

        if ($this->isDistantJson) {
            if (!empty($this->optionsUrls[$value])) {
                $entryUrl = $this->optionsUrls[$value];
            } else {
                $entryUrl = $this->baseUrl . $value;
            }
        } else {
            $entryUrl = $this->services->get(Wiki::class)->Href('', $value);
        }

        return $this->render('@bazar/fields/select_entry.twig', [
            'value' => $value,
            'label' => $this->getOptions()[$value],
            'entryUrl' => $entryUrl,
        ]);
    }

    public function getOptions()
    {
        return $this->getEntriesOptions();
    }

    /**
     * check if the current class is EnumEntry.
     */
    public function isEnumEntryField(): bool
    {
        return true;
    }
}
