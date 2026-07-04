<?php

#[Attribute(Attribute::TARGET_CLASS)]
final class Field
{
    public array $keywords;

    public function __construct(array $keywords = [])
    {
        $this->keywords = $keywords;
    }
}
