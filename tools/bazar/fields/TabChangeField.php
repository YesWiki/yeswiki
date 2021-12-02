<?php

namespace YesWiki\Bazar\Field;

use Exception;
use Psr\Container\ContainerInterface;
use Throwable;
use YesWiki\Bazar\Field\TabsField;
use YesWiki\Bazar\Service\TabsFieldService;
use YesWiki\Bazar\Field\LabelField;

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
        $this->formChange = ($values[self::FIELD_FORM_CHANGE] === "formChange") ;
        $this->viewChange = ($values[self::FIELD_VIEW_CHANGE] === "viewChange") ;
    }

    protected function renderInput($entry)
    {
        if (!$this->formChange) {
            return null;
        }
        $tabsFieldService = $this->getService(TabsFieldService::class);
        $params = $tabsFieldService->getFormData();
        if ($params['counter'] === false) {
            return null;
        }
        return $this->render('@bazar/fields/tab-change.twig', $params);
    }

    protected function renderStatic($entry)
    {
        if (!$this->viewChange) {
            return null;
        }
        $tabsFieldService = $this->getService(TabsFieldService::class);
        $params = $tabsFieldService->getViewData();
        if ($params['counter'] === false) {
            return null;
        }
        return $this->render('@bazar/fields/tab-change.twig', $params);
    }

    public function getFormChange()
    {
        return $this->formChange;
    }

    public function getViewChange()
    {
        return $this->viewChange;
    }

    public function jsonSerialize()
    {
        return [
                'formChange' => $this->getFormChange(),
                'viewChange' => $this->getViewChange(),
            ];
    }
}
