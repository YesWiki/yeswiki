<?php

/**
 * The templates a `PrepareData…` class prepares, beyond the one its filename names (ticket 49).
 *
 * `PrepareDataMap.php` prepares `map` because of what it is called. A template whose name is
 * not a class name -- `map-and-table` -- can only be stated, and stating it is the convention
 * every `performableName()` in this codebase follows for the same reason.
 */
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
