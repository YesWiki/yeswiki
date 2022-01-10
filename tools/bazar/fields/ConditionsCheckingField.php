<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;
use YesWiki\Bazar\Field\LabelField;

/**
 * @Field({"conditionschecking"})
 */
class ConditionsCheckingField extends LabelField
{
    private $condition;

    protected const FIELD_CONDITION = 1;

    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);
        $this->condition = $values[self::FIELD_CONDITION] ?? '';
        $this->formText = $this->prepareFormText();
        $this->viewText = '';
    }

    protected function prepareFormText(): ?string
    {
        return $this->render('@bazar/inputs/conditions-checking.twig', [
            'condition' => $this->getCondition(),
        ]);
    }

    public function getCondition()
    {
        return $this->condition;
    }


    public function jsonSerialize()
    {
        return array_merge(
            parent::jsonSerialize(),
            [
                'condition' => $this->getCondition(),
            ]
        );
    }
}
