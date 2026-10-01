<?php

namespace YesWiki\Fieldfactoryfixture\Field;

/**
 * @Field({"annotationkeyword"})
 */
class AnnotationDeclaredField
{
    public function __construct(public array $values, public $services)
    {
    }
}
