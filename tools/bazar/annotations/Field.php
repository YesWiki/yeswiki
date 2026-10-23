<?php

/**
 * @Annotation
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class Field
{
    /** @array */
    public $keywords;
}
