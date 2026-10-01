<?php

namespace YesWiki\Fieldfactoryfixture\Field;

#[\Field(['attributekeyword'])]
class AttributeDeclaredField
{
    public function __construct(public array $values, public $services)
    {
    }
}
