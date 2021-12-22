<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;
use YesWiki\Bazar\Field\LabelField;
use YesWiki\Bazar\Service\TabsFieldService;

/**
 * @Field({"tabs"})
 */
class TabsField extends LabelField
{
    private $formTitles; // Tabs titles for from separated by coma
    private $viewTitles; // Tabs titles for view separated by coma
    private $moveSubmitButtonToLastTab ;
    private $tabsClass ;
    private $btnClass ;

    protected const FIELD_FORM_TITLES = 1;
    protected const FIELD_VIEW_TITLES = 3;
    protected const FIELD_MOVE_SUBMIT_BUTTON_TO_LAST_TAB = 5;
    protected const FIELD_TABS_CLASS = 6;
    protected const FIELD_BTN_COLOR = 7;
    protected const FIELD_BTN_SIZE = 9;

    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);
        $this->default = null;
        $this->searchable = null;
        $this->formTitles = $this->sanitizeTitles($values[self::FIELD_FORM_TITLES]);
        $this->viewTitles = $this->sanitizeTitles($values[self::FIELD_VIEW_TITLES]);
        $this->moveSubmitButtonToLastTab = ($values[self::FIELD_MOVE_SUBMIT_BUTTON_TO_LAST_TAB] === "moveSubmit") ;
        $this->tabsClass = in_array($values[self::FIELD_TABS_CLASS], ["nav-tabs","nav-pills"], true) ? $values[self::FIELD_TABS_CLASS] : "nav-tabs";
        $this->btnClass = (in_array($values[self::FIELD_BTN_COLOR], ["btn-primary","btn-secondary-1","btn-secondary-2"], true) ? $values[self::FIELD_BTN_COLOR] : "btn-primary") .
          ($values[self::FIELD_BTN_SIZE] === "btn-xs" ? " btn-xs" : "") ;
        $this->formText = $this->prepareFormText();
        $this->viewText = $this->prepareViewText();
    }

    protected function sanitizeTitles(?string $input):?array
    {
        $titles = explode(',', str_replace("|",",",$input));
        $titles = array_filter(array_map('trim', $titles), function ($title) {
            return !empty($title);
        });
        return $titles;
    }

    protected function prepareFormText(): ?string
    {
        return $this->render('@bazar/fields/tabs.twig', [
            'titles' => $this->getFormTitles(),
            'moveSubmitButtonToLastTab' => $this->getMoveSubmitButtonToLastTab()
        ]);
    }

    protected function prepareViewText(): ?string
    {
        return $this->render('@bazar/fields/tabs.twig', [
            'titles' => $this->getViewTitles()
        ]);
    }

    protected function renderInput($entry)
    {
        $tabsFieldService = $this->getService(TabsFieldService::class);
        $tabsFieldService->setFormTitles($this);
        return $this->formText;
    }

    protected function renderStatic($entry)
    {
        $tabsFieldService = $this->getService(TabsFieldService::class);
        $tabsFieldService->setViewTitles($this);
        return $this->viewText;
    }

    public function getFormTitles()
    {
        return $this->formTitles;
    }

    public function getViewTitles()
    {
        return $this->viewTitles;
    }

    public function getMoveSubmitButtonToLastTab()
    {
        return $this->moveSubmitButtonToLastTab;
    }

    public function getTabsClass()
    {
        return $this->tabsClass;
    }

    public function getBtnClass()
    {
        return $this->btnClass;
    }

    public function jsonSerialize()
    {
        return array_merge(
            parent::jsonSerialize(),
            [
                'formTitles' => $this->getFormTitles(),
                'viewTitles' => $this->getViewTitles(),
                'moveSubmitButtonToLastTab' => $this->getMoveSubmitButtonToLastTab(),
                'tabsClass' => $this->getTabsClass(),
                'btnClass' => $this->getBtnClass(),
            ]
        );
    }
}
