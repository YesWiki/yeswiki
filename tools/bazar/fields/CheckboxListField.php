<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;
use YesWiki\Wiki;

/**
 * @Field({"checkbox"})
 */
class CheckboxListField extends CheckboxField
{
    private const FIELD_CLASS_TYPE = 'CheckboxListField';

    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);
        $this->type = 'checkbox';

        $this->loadOptionsFromList();

        $this->displayFilterLimit = $this->services->get(Wiki::class)->config['BAZ_MAX_CHECKBOXLIST_WITHOUT_FILTER'];
        $this->displaySelectAllLimit = empty($this->services->get(Wiki::class)->config['BAZ_MAX_CHECKBOXLIST_WITHOUT_SELECTALL']) ? $this->displayFilterLimit : $this->services->get(Wiki::class)->config['BAZ_MAX_CHECKBOXLIST_WITHOUT_SELECTALL'];
        $this->formName = _t('BAZ_DRAG_n_DROP_CHECKBOX_LIST') . ' ' . $this->name;
        $this->normalDisplayMode = (in_array($this->services->get(Wiki::class)->config['BAZ_MAX_CHECKBOXLIST_DISPLAY_MODE'], array_keys(self::CHECKBOX_TWIG_LIST))) ?
            $this->services->get(Wiki::class)->config['BAZ_MAX_CHECKBOXLIST_DISPLAY_MODE'] : self::CHECKBOX_DISPLAY_MODE_DIV;
        $this->dragAndDropDisplayMode = '@bazar/inputs/checkbox_drag_and_drop.twig';
    }

    // change return of this method to keep compatible with php 7.3 (mixed is not managed)
        #[\ReturnTypeWillChange]
        public function jsonSerialize()
        {
            return array_merge(
                parent::jsonSerialize(),
                [
                    'field_type' => self::FIELD_CLASS_TYPE,
                ]
            );
        }

    protected function renderStatic($entry)
    {
        $keys = $this->getValues($entry);
        $values = [];

        if (empty($keys)) {
            return '';
        }

        // List with multi levels
        if ($this->optionsTree) {
            return $this->render('@bazar/fields/checkbox-tree.twig', [
                'treeValues' => $this->filterTree($this->optionsTree, $keys),
            ]);
        }

        // List with one level
        foreach ($this->getOptions() as $key => $label) {
            if (in_array($key, $keys)) {
                $values[$key] = $label;
            }
        }

        return $this->render('@bazar/fields/checkbox.twig', [
            'values' => $values,
        ]);
    }

    // Filter the tree to keep only branches where a nodeID is checked
    private function filterTree($tree, $checkedValues)
    {
        $filteredTree = [];

        foreach ($tree as $node) {
            if (in_array($node['id'], $checkedValues)) {
                $filteredNode = $node;
                if( isset($node['children'])) {
                    $filteredNode['children'] = $this->filterTree($node['children'], $checkedValues);
                }
                $filteredTree[] = $filteredNode;
            } else {
                if ( isset($node['children'])) {
                    $filteredChildren = $this->filterTree($node['children'], $checkedValues);
                    if (!empty($filteredChildren)) {
                        $filteredNode = $node;
                        $filteredNode['children'] = $filteredChildren;
                        $filteredTree[] = $filteredNode;
                    }
                }
            }
        }

        return $filteredTree;
    }
}
