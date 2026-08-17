<?php

namespace YesWiki\Content\Field;

use Psr\Container\ContainerInterface;

#[\Field(['checkbox'])]
class CheckboxListField extends CheckboxField
{
    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);
        $this->type = 'checkbox';

        $this->loadOptionsFromList();

        $this->displayFilterLimit = $this->getService(\YesWiki\Kernel\Service\RuntimeConfig::class)['BAZ_MAX_CHECKBOXLIST_WITHOUT_FILTER'];
        $this->displaySelectAllLimit = empty($this->getService(\YesWiki\Kernel\Service\RuntimeConfig::class)['BAZ_MAX_CHECKBOXLIST_WITHOUT_SELECTALL']) ? $this->displayFilterLimit : $this->getService(\YesWiki\Kernel\Service\RuntimeConfig::class)['BAZ_MAX_CHECKBOXLIST_WITHOUT_SELECTALL'];
        $this->formName = _t('BAZ_DRAG_n_DROP_CHECKBOX_LIST') . ' ' . $this->name;
        $this->normalDisplayMode = (in_array($this->getService(\YesWiki\Kernel\Service\RuntimeConfig::class)['BAZ_MAX_CHECKBOXLIST_DISPLAY_MODE'], array_keys(self::CHECKBOX_TWIG_LIST))) ?
            $this->getService(\YesWiki\Kernel\Service\RuntimeConfig::class)['BAZ_MAX_CHECKBOXLIST_DISPLAY_MODE'] : self::CHECKBOX_DISPLAY_MODE_DIV;
        $this->dragAndDropDisplayMode = '@core/inputs/checkbox_drag_and_drop.twig';
    }

    protected function renderStatic($entry)
    {
        $keys = $this->getValues($entry);
        $values = [];

        if (empty($keys)) {
            return '';
        }

        if ($this->optionsTree) {
            return $this->render('@core/fields/checkbox-tree.twig', [
                'treeValues' => $this->filterTree($this->optionsTree, $keys),
            ]);
        }

        foreach ($this->getOptions() as $key => $label) {
            if (in_array($key, $keys)) {
                $values[$key] = $label;
            }
        }

        return $this->render('@core/fields/checkbox.twig', [
            'values' => $values,
        ]);
    }

    private function filterTree($tree, $checkedValues)
    {
        $filteredTree = [];

        foreach ($tree as $node) {
            if (in_array($node['id'], $checkedValues)) {
                $filteredNode = $node;
                $filteredNode['children'] = $this->filterTree($node['children'], $checkedValues);
                $filteredTree[] = $filteredNode;
            } else {
                $filteredChildren = $this->filterTree($node['children'], $checkedValues);
                if (!empty($filteredChildren)) {
                    $filteredNode = $node;
                    $filteredNode['children'] = $filteredChildren;
                    $filteredTree[] = $filteredNode;
                }
            }
        }

        return $filteredTree;
    }
}
