<?php

namespace YesWiki\Templates\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Bazar\Field\TabsField;

class TabsService
{
    protected $nextPrefix;
    protected $data;
    protected $stack;

    public function __construct()
    {
        $this->nextPrefix = 1;
        $this->stack = [];
        $this->data = [
            'form' => [
                'titles' => [],
                'counter' => false,
                'btnClass' => '',
                'prefixCounter' => 0
            ],
            'view' => [
                'titles' => [],
                'counter' => false,
                'btnClass' => '',
                'prefixCounter' => 0
            ],
            'action' => [
                'titles' => [],
                'counter' => false,
                'btnClass' => '',
                'prefixCounter' => 0
            ]
        ];
    }

    public function setFormTitles(TabsField $field)
    {
        $this->setTitles($field->getFormTitles(),'form',$field->getBtnClass());
    }

    public function setViewTitles(TabsField $field)
    {
        $this->setTitles($field->getViewTitles(),'view',$field->getBtnClass());
    }

    public function setActionTitles(array $params)
    {
        $this->setTitles($params['titles'] ?? [],'action',$params['btnClass'] ?? '');
    }

    private function setTitles(array $titles, string $mode, string $btnClass)
    {
        $this->data[$mode]['titles'] = $titles;
        $this->saveInStackIfNeeded($mode);
        $this->data[$mode]['counter'] = 1;
        $this->data[$mode]['btnClass'] = $btnClass;
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

        return compact(['counter','titles','isLast','btnClass','prefix']);
    }
}
