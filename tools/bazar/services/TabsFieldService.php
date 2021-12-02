<?php

namespace YesWiki\Bazar\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Bazar\Field\TabsField;

class TabsFieldService
{
    protected $formTitles ;
    protected $formCounter ;
    protected $viewTitles ;
    protected $viewCounter ;
    protected $btnClass ;

    public function __construct()
    {
        $this->formTitles = [];
        $this->formCounter = false;
        $this->viewTitles = [];
        $this->viewCounter = false;
        $this->btnClass = '';
    }

    public function setFormTitles(TabsField $field)
    {
        $this->formTitles = $field->getFormTitles();
        $this->formCounter = 1;
        $this->btnClass = $field->getBtnClass();
    }

    public function setViewTitles(TabsField $field)
    {
        $this->viewTitles = $field->getViewTitles();
        $this->viewCounter = 1;
        $this->btnClass = $field->getBtnClass();
    }

    public function getFormData()
    {
        return $this->getData('form');
    }

    public function getViewData()
    {
        return $this->getData('view');
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
        $btnClass = $this->btnClass;

        return compact(['counter','titles','isLast','btnClass']);
    }
}
