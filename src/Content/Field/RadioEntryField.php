<?php

namespace YesWiki\Content\Field;

use Psr\Container\ContainerInterface;

#[\Field(['radiofiche'])]
class RadioEntryField extends RadioField
{
    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);

        $this->options = null;
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

        $entryUrl = $this->getService(\YesWiki\Kernel\Service\UrlFormatter::class)->href('', $value);

        return $this->render('@core/fields/select_entry.twig', [
            'value' => $value,
            'label' => $this->getOptions()[$value],
            'entryUrl' => $entryUrl,
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
