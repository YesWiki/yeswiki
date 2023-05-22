<?php

namespace YesWiki\Templates\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Bazar\Field\TabsField;

class TabsService
{
    public const DEFAULT_DATA = [
        'titles' => [],
        'counter' => false,
        'btnClass' => '',
        'prefixCounter' => 0,
        'bottom_nav' => true,
        'counter_on_bottom_nav' => true, // because default behaviour for tabs in forms
        'selectedtab' => 1,
        'isClosed' => false,
        'tabOpened' => false,
    ];

    protected $nextPrefix;
    protected $data;
    protected $stack;

    public function __construct()
    {
        $this->nextPrefix = 1;
        $this->stack = [];
        $this->data = [
            'form' => self::DEFAULT_DATA,
            'view' => self::DEFAULT_DATA,
            'action' => self::DEFAULT_DATA
        ];
    }

    public function setFormTitles(TabsField $field)
    {
        $this->setTitles(
            $field->getFormTitles(),
            'form',
            $field->getBtnClass(),
            # TODO : make a new option for the Tabsfield to change those values
            self::DEFAULT_DATA['bottom_nav'],
            self::DEFAULT_DATA['counter_on_bottom_nav'],
            self::DEFAULT_DATA['selectedtab']
        );
    }

    public function setViewTitles(TabsField $field)
    {
        $this->setTitles(
            $field->getViewTitles(),
            'view',
            $field->getBtnClass(),
            # TODO : make a new option for the Tabsfield to change those values
            self::DEFAULT_DATA['bottom_nav'],
            self::DEFAULT_DATA['counter_on_bottom_nav'],
            self::DEFAULT_DATA['selectedtab']
        );
    }

    public function setActionTitles(array $params)
    {
        $this->setTitles(
            $params['titles'] ?? [],
            'action',
            $params['btnClass'] ?? '',
            $params['bottom_nav'] ?? $this->dataDefaults['bottom_nav'],
            $params['counter_on_bottom_nav'] ?? $this->dataDefaults['counter_on_bottom_nav'],
            $params['selectedtab'] ?? 1
        );
    }

    private function setTitles(array $titles, string $mode, string $btnClass, bool $bottom_nav, bool $counter_on_bottom_nav, int $selectedtab)
    {
        $this->saveInStackIfNeeded($mode);
        $this->data[$mode]['titles'] = $titles;
        $this->data[$mode]['counter'] = 1;
        $this->data[$mode]['btnClass'] = $btnClass;
        $this->data[$mode]['bottom_nav'] = $bottom_nav;
        $this->data[$mode]['counter_on_bottom_nav'] = $counter_on_bottom_nav;
        $this->data[$mode]['prefixCounter'] = $this->getNewPrefix();
        $this->data[$mode]['selectedtab'] = ($selectedtab > 0 && $selectedtab <= count($titles))? $selectedtab : 1;
        $this->data[$mode]['isClosed'] = false;
        $this->data[$mode]['tabOpened'] = false;
    }

    public function getFormData(bool $increment = true)
    {
        return $this->getData('form',$increment);
    }

    public function getViewData(bool $increment = true)
    {
        return $this->getData('view',$increment);
    }

    public function getActionData(bool $increment = true)
    {
        return $this->getData('action',$increment);
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

    private function getData(string $mode, bool $increment = true)
    {
        $data = $this->data[$mode];
        $data['prefix'] = $this->getPrefix($mode);
        $data['isLast'] = false;
        // update internal counter
        if ($data['counter'] !== false) {
            if ($increment){
                $this->data[$mode]['tabOpened'] = false;
                // end not already reached
                if ($data['counter'] < count($data['titles'])) {
                    // do not increase counter if TabChange specified is last
                    $this->data[$mode]['counter'] = $data['counter'] + 1 ;
                } else {
                    $this->data[$mode]['counter'] = false ; // to indicate end is reached
                    $data['isLast'] = true;
                    if ($mode != 'action'){
                        $this->data[$mode]['isClosed'] = true;
                        $this->retrieveFromStackIfNeeded($mode);
                    }
                }
            }
        } else {
            $data['titles'] = [] ; // to be sure titles are not used
        }

        return $data;
    }

    public function openTab(string $mode)
    {
        $this->data[$mode]['tabOpened'] = true;
    }

    public function registerClose(string $mode)
    {
        $this->data[$mode]['isClosed'] = true;
        if ($mode == 'action'){
            $this->retrieveFromStackIfNeeded($mode);
        }
    }
}
