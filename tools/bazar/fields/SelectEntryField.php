<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;
use YesWiki\Bazar\Controller\EntryController;
use YesWiki\Wiki;

/**
 * @Field({"listefiche"})
 */
class SelectEntryField extends EnumField
{
    public $isDistantJson;
    protected $displayMethod;
    protected $baseUrl;

    protected const FIELD_DISPLAY_METHOD = 3;
    private const FIELD_CLASS_TYPE = 'SelectEntryField';

    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);

        $this->displayMethod = $values[self::FIELD_DISPLAY_METHOD];
        $this->isDistantJson = filter_var($this->name, FILTER_VALIDATE_URL);

        if ($this->isDistantJson) {
            $this->prepareJSONEntryField();
        } else {
            $this->options = null;
            $this->baseUrl = null;
        }
    }

    protected function renderInput($entry)
    {
        return $this->render('@bazar/inputs/select.twig', [
            'value' => $this->getValue($entry),
            'options' => $this->getOptions(),
        ]);
    }

    protected function renderStatic($entry)
    {
        $value = $this->getValue($entry);
        if (!$value) {
            return '';
        }

        if ($this->displayMethod === 'fiche') {
            if ($this->isDistantJson) {
                // TODO display the entry in an iframe ?
                return '';
            } else {
                // TODO add documentation
                return $this->getService(EntryController::class)->view($value);
            }
        }

        if ($this->isDistantJson) {
            if (!empty($this->optionsUrls[$value])) {
                $entryUrl = $this->optionsUrls[$value];
            } else {
                $entryUrl = $baseUrl . $value;
            }
        } else {
            $entryUrl = $this->services->get(Wiki::class)->Href('', $value);
        }

        $entryUrl = explode('&', $entryUrl);
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

    // LANG_FIXME not sure about this function
    public static function mapToFieldArray($fieldProps): array
    {
       $new = parent::mapToFieldArray($fieldProps);
       $new[self::FIELD_DISPLAY_METHOD] = $fieldProps['displayMethod'];
       ksort($new);
       return $new;
    }

    // change return of this method to keep compatible with php 7.3 (mixed is not managed)
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return array_merge(
            parent::jsonSerialize(),
            [
                'field_type' => self::FIELD_CLASS_TYPE,
                'displayMethod' => $this->displayMethod,
            ]
        );
    }
}
