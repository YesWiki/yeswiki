<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;
use YesWiki\Templates\Controller\TabsController;

/**
 * @Field({"tabchange"})
 */
class TabChangeField extends LabelField
{
    protected const FIELD_FORM_CHANGE = 1;
    protected const FIELD_VIEW_CHANGE = 3;

    protected $formChange;
    protected $viewChange;

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

        return $this->getService(TabsController::class)->changeTab('form');
    }

    protected function renderStatic($entry)
    {
        if (!$this->viewChange) {
            return '';
        }

        return $this->getService(TabsController::class)->changeTab('view');
    }

    public function getFormChange()
    {
        return $this->formChange;
    }

    public function getViewChange()
    {
        return $this->viewChange;
    }

    // change return of this method to keep compatible with php 7.3 (mixed is not managed)
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return [
            'formChange' => $this->getFormChange(),
            'viewChange' => $this->getViewChange(),
        ];
    }
}
