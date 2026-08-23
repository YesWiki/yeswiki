<?php

/** The templates a `PrepareData…` class prepares, beyond the one its filename names (ticket 49). */
#[Attribute(Attribute::TARGET_CLASS)]
final class PreparesTemplate
{
    /** @var list<string> */
    public array $templates;

    /**
     * @param list<string> $templates
     */
    public function __construct(array $templates = [])
    {
        $this->templates = $templates;
    }
}
