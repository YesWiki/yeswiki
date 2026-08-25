<?php

namespace YesWiki\Content\Field;

use Psr\Container\ContainerInterface;
use YesWiki\Content\Attribute\Field;
use YesWiki\Render\Service\TabsRenderer;

#[Field(['tabchange'])]
class TabChangeField extends LabelField
{
    protected const FIELD_FORM_CHANGE = 1;
    protected const FIELD_VIEW_CHANGE = 3;

    /** @var bool */
    protected $formChange;
    /** @var bool */
    protected $viewChange;

    /**
     * @param array<int|string, mixed> $values
     */
    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);
        $this->formText = null;
        $this->viewText = null;
        $this->maxChars = null;
        $this->default = null;
        $this->formChange = ($values[self::FIELD_FORM_CHANGE] === 'formChange');
        $this->viewChange = ($values[self::FIELD_VIEW_CHANGE] === 'viewChange');
    }

    protected function renderInput($entry)
    {
        if (!$this->formChange) {
            return '';
        }

        return $this->getService(TabsRenderer::class)->changeTab('form');
    }

    protected function renderStatic($entry)
    {
        if (!$this->viewChange) {
            return '';
        }

        return $this->getService(TabsRenderer::class)->changeTab('view');
    }

    public function getFormChange(): bool
    {
        return $this->formChange;
    }

    public function getViewChange(): bool
    {
        return $this->viewChange;
    }

    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return [
            'formChange' => $this->getFormChange(),
            'viewChange' => $this->getViewChange(),
        ];
    }
}
