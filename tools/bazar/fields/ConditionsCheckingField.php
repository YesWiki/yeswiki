<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;

/**
 * @Field({"conditionschecking"})
 */
class ConditionsCheckingField extends LabelField
{
    private $condition;
    private $options;

    protected const FIELD_CONDITION = 1;
    protected const FIELD_OPTIONS = 2;

    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);
        $this->condition = $values[self::FIELD_CONDITION] ?? '';
        $this->options = !empty($values[self::FIELD_OPTIONS]) && in_array($values[self::FIELD_OPTIONS], ['noclean'], true) ? ['noclean' => true] : ['noclean' => false];
        $this->formText = $this->prepareFormText();
        $this->viewText = '';
    }

    protected function prepareFormText(): ?string
    {
        return $this->render('@bazar/inputs/conditions-checking.twig', [
        ]);
    }

    public function getCondition()
    {
        return $this->condition;
    }

    public function getOptions()
    {
        return $this->options;
    }

    // change return of this method to keep compatible with php 7.3 (mixed is not managed)
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return array_merge(
            parent::jsonSerialize(),
            [
                'condition' => $this->getCondition(),
                'option' => $this->getOptions(),
            ]
        );
    }
}
