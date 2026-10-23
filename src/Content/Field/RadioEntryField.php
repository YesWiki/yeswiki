<?php

namespace YesWiki\Content\Field;

use Psr\Container\ContainerInterface;
use YesWiki\Content\Attribute\Field;

#[Field(['radiofiche'])]
class RadioEntryField extends RadioField
{
    use ShowsEntriesThroughAList;

    protected const FIELD_ENTRIES_TEMPLATE = 20;
    protected const FIELD_ENTRIES_PARAMS = 21;
    protected const FIELD_ENTRY_DISPLAY = 22;

    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);

        $this->options = null;
        $this->readEntriesDisplay($values);
    }

    protected function renderStatic($entry)
    {
        $value = $this->getValue($entry);
        if (!$value) {
            return '';
        }

        if ($this->remoteLinkedFormNotice() !== null) {
            return '';
        }

        if ($this->entriesTemplate !== '') {
            return $this->renderEntriesList([$value]);
        }

        $entryUrl = $this->getService(\YesWiki\Kernel\Service\UrlFormatter::class)->href('', $value);

        return $this->render('@core/fields/select_entry.twig', [
            'value' => $value,
            'label' => $this->getOptions()[$value] ?? $value,
            'entryUrl' => $entryUrl,
            'entryDisplay' => $this->entryDisplay,
        ]);
    }

    public function getOptions()
    {
        return $this->getEntriesOptions();
    }

    /** check if the current class is EnumEntry. */
    public function isEnumEntryField(): bool
    {
        return true;
    }
}
