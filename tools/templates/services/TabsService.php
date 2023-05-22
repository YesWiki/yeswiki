<?php

namespace YesWiki\Templates\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Bazar\Field\TabsField;

class TabsService
{
    protected $nextPrefix;
    protected $data;
    protected $stack;
    public $dataDefaults;
    public function __construct()
    {
        $this->nextPrefix = 1;
        $this->stack = [];
        $this->dataDefaults = [
            'titles' => [],
            'counter' => false,
            'btnClass' => '',
            'prefixCounter' => 0,
            'bottom_nav' => true,
            'counter_on_bottom_nav' => false
        ];
        $this->data = [
            'form' => $this->dataDefaults,
            'view' => $this->dataDefaults,
            'action' => $this->dataDefaults
        ];
    }

    public function setFormTitles(TabsField $field)
    {
        $this->setTitles(
            $field->getFormTitles(),
            'form',
            $field->getBtnClass(),
            # TODO : make a new option for the Tabsfield to change those values
            $this->dataDefaults['bottom_nav'],
            $this->dataDefaults['counter_on_bottom_nav']
        );
    }

    public function setViewTitles(TabsField $field)
    {
        $this->setTitles(
            $field->getViewTitles(),
            'view',
            $field->getBtnClass(),
            # TODO : make a new option for the Tabsfield to change those values
            $this->dataDefaults['bottom_nav'],
            $this->dataDefaults['counter_on_bottom_nav']
        );
    }

    public function setActionTitles(array $params)
    {
        $this->setTitles(
            $params['titles'] ?? [],
            'action',
            $params['btnClass'] ?? '',
            $params['bottom_nav'] ?? $this->dataDefaults['bottom_nav'],
            $params['counter_on_bottom_nav'] ?? $this->dataDefaults['counter_on_bottom_nav']
        );
    }

    private function setTitles(array $titles, string $mode, string $btnClass, bool $bottom_nav, bool $counter_on_bottom_nav)
    {
        $this->data[$mode]['titles'] = $titles;
        $this->saveInStackIfNeeded($mode);
        $this->data[$mode]['counter'] = 1;
        $this->data[$mode]['btnClass'] = $btnClass;
        $this->data[$mode]['bottom_nav'] = $bottom_nav;
        $this->data[$mode]['counter_on_bottom_nav'] = $counter_on_bottom_nav;
        $this->data[$mode]['prefixCounter'] = $this->getNewPrefix();
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

    public function getPrefix(string $mode): string
    {
        return "{$this->data[$mode]['prefixCounter']}_";
    }

    private function saveInStackIfNeeded(string $mode)
    {
        if ($this->data[$mode]['counter'] !== false){
            // init stack for this mode
            if (!isset($this->stack[$mode])){
                $this->stack[$mode] = [];
            }
            $this->stack[$mode][] = $this->data[$mode];
            $this->data[$mode]['counter'] = false;
        }
    }

    private function retrieveFromStackIfNeeded(string $mode)
    {
        if (!empty($this->stack[$mode])){
            $this->data[$mode] = array_pop($this->stack[$mode]);
        }
    }

    private function getNewPrefix(): int
    {
        $newPrefix = $this->nextPrefix;
        $this->nextPrefix = $this->nextPrefix +1;
        return $newPrefix;
    }

    private function getData(string $mode)
    {
        $counter = $this->data[$mode]['counter'];
        $titles = $this->data[$mode]['titles'];
        $btnClass = $this->data[$mode]['btnClass'];
        $bottom_nav = $this->data[$mode]['bottom_nav'];
        $counter_on_bottom_nav = $this->data[$mode]['counter_on_bottom_nav'];
        $prefix = $this->getPrefix($mode);
        $isLast = false;
        // update internal counter
        if ($counter !== false) {
            // end not already reached
            if ($counter < count($titles) && !$isLast) {
                // do not increase counter if TabChange specfied is last
                $this->data[$mode]['counter'] = $counter + 1 ;
            } else {
                $this->data[$mode]['counter'] = false ; // to indicate end is reached
                $isLast = true;
                $this->retrieveFromStackIfNeeded($mode);
            }
        } else {
            $titles = [] ; // to be sure titles are not used
        }

        return compact(['counter','titles','isLast','btnClass','prefix','bottom_nav','counter_on_bottom_nav']);
    }
}
