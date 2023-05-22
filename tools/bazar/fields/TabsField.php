<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;
use YesWiki\Bazar\Field\LabelField;
use YesWiki\Templates\Service\TabsService;

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
        $this->btnClass = (in_array($values[self::FIELD_BTN_COLOR], ["btn-primary","btn-secondary-1","btn-secondary-2"], true) ? $values[self::FIELD_BTN_COLOR] : "btn-primary") .
          ($values[self::FIELD_BTN_SIZE] === "btn-xs" ? " btn-xs" : "") ;
        $this->formText = $this->prepareFormText();
        $this->viewText = $this->prepareViewText();
    }

    protected function sanitizeTitles(?string $input):?array
    {
        $titles = explode(',', str_replace("|", ",", $input));
        $titles = array_filter(array_map('trim', $titles), function ($title) {
            return !empty($title);
        });
        return $titles;
    }

    protected function prepareFormText(?TabsService $tabsService = null): ?string
    {
        return $this->render('@bazar/fields/tabs.twig', $this->appendPrefix([
            'titles' => $this->getFormTitles(),
            'moveSubmitButtonToLastTab' => $this->getMoveSubmitButtonToLastTab()
        ],$tabsService,'form'));
    }

    protected function prepareViewText(?TabsService $tabsService = null): ?string
    {
        return $this->render('@bazar/fields/tabs.twig', $this->appendPrefix([
            'titles' => $this->getViewTitles()
        ],$tabsService,'view'));
    }

    protected function renderInput($entry)
    {
        $tabsService = $this->getService(TabsService::class);
        $tabsService->setFormTitles($this);
        $this->formText = $this->prepareFormText($tabsService);
        return $this->formText;
    }

    protected function renderStatic($entry)
    {
        $tabsService = $this->getService(TabsService::class);
        $tabsService->setViewTitles($this);
        $this->viewText = $this->prepareViewText($tabsService);
        return $this->viewText;
    }

    protected function appendPrefix(array $params,?TabsService $tabsService = null,$mode = 'view'): array
    {
        if ($tabsService){
            $params['prefix'] = $tabsService->getPrefix($mode);
        }
        return $params;
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

    public function getBtnClass()
    {
        return $this->btnClass;
    }

    // change return of this method to keep compatible with php 7.3 (mixed is not managed)
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return array_merge(
            parent::jsonSerialize(),
            [
                'formTitles' => $this->getFormTitles(),
                'viewTitles' => $this->getViewTitles(),
                'moveSubmitButtonToLastTab' => $this->getMoveSubmitButtonToLastTab(),
                'btnClass' => $this->getBtnClass(),
            ]
        );
    }
}
