<?php

namespace YesWiki\Content\Field;

use Psr\Container\ContainerInterface;
use YesWiki\Content\Controller\EntryController;

#[\Field(['listefiche'])]
class SelectEntryField extends EnumField
{
    /** @var mixed the form definition's rendering choice for this list */
    protected $displayMethod;

    protected const FIELD_DISPLAY_METHOD = 3;

    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);

        $this->displayMethod = $values[self::FIELD_DISPLAY_METHOD];

        $this->options = null;
    }

    protected function renderInput($entry)
    {
        $notice = $this->remoteLinkedFormNotice();
        if ($notice !== null) {
            return $this->render('@core/alert-message.twig', ['type' => 'warning', 'message' => $notice]);
        }

        return $this->render('@core/inputs/select.twig', [
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

        if ($this->remoteLinkedFormNotice() !== null) {
            return '';
        }

        if ($this->displayMethod === 'fiche') {
            return $this->getService(EntryController::class)->view($value);
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
