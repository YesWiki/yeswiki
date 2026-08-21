<?php

#[Attribute(Attribute::TARGET_CLASS)]
final class Field
{
    /** @var list<string> the keywords this field class is registered under */
    public array $keywords;

    /**
     * @param list<string> $keywords
     */
    public function __construct(array $keywords = [])
    {
        $this->keywords = $keywords;
    }
}
