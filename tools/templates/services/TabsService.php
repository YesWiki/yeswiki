<?php

namespace YesWiki\Templates\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Bazar\Field\TabsField;

class TabsService
{
    protected $formTitles ;
    protected $formCounter ;
    protected $formBtnClass ;
    protected $viewTitles ;
    protected $viewCounter ;
    protected $viewBtnClass ;
    protected $actionTitles ;
    protected $actionCounter ;
    protected $actionBtnClass ;
    protected $prefixCounter;

    public function __construct()
    {
        $this->prefixCounter = 0;
        $this->formTitles = [];
        $this->formCounter = false;
        $this->formBtnClass = '';
        $this->viewTitles = [];
        $this->viewCounter = false;
        $this->viewBtnClass = '';
        $this->actionTitles = [];
        $this->actionCounter = false;
        $this->actionBtnClass = '';
    }

    public function setFormTitles(TabsField $field)
    {
        $this->formTitles = $field->getFormTitles();
        $this->formCounter = 1;
        $this->formBtnClass = $field->getBtnClass();
        $this->prefixCounter = $this->prefixCounter +1;
    }

    public function setViewTitles(TabsField $field)
    {
        $this->viewTitles = $field->getViewTitles();
        $this->viewCounter = 1;
        $this->viewBtnClass = $field->getBtnClass();
        $this->prefixCounter = $this->prefixCounter +1;
    }

    public function setActionTitles(array $params)
    {
        $this->actionTitles = $params['titles'] ?? [];
        $this->actionCounter = 1;
        $this->actionBtnClass = $params['btnClass'] ?? '';
        $this->prefixCounter = $this->prefixCounter +1;
    }

    public function getFormData()
    {
        return $this->getData('form');
    }

    public function getViewData()
    {
        return $this->getData('view');
    }

    public function getActionData()
    {
        return $this->getData('action');
    }

    public function getPrefix()
    {
        return "{$this->prefixCounter}_";
    }

    private function getData(string $mode)
    {
        $counter = $this->{$mode . 'Counter'};
        $titles = $this->{$mode . 'Titles'};
        $isLast = false;
        // update internal counter
        if ($counter !== false) {
            // end not already reached
            if ($counter < count($titles) && !$isLast) {
                // do not increase counter if TabChange specfied is last
                $this->{$mode . 'Counter'} = $counter + 1 ;
            } else {
                $this->{$mode . 'Counter'} = false ; // to indicate end is reached
                $isLast = true;
            }
        } else {
            $titles = [] ; // to be sure titles are not used
        }
        $btnClass = $this->{$mode . 'BtnClass'};
        $prefix = $this->getPrefix();

        return compact(['counter','titles','isLast','btnClass','prefix']);
    }
}
