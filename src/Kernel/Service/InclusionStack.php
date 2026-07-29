<?php

namespace YesWiki\Kernel\Service;

/**
 * The stack of pages being included into one another ({{include}}, handlers rendering
 * other pages). Element 0 is the innermost inclusion, element 1 its parent, and so on.
 * Tags are stored lowercased, matching the case-insensitive wiki-name semantics.
 */
class InclusionStack
{
    /** @var list<string> */
    protected $inclusions = [];

    /**
     * Push the page whose inclusion begins.
     *
     * @return int the stack depth after pushing
     */
    public function register(string $pageTag): int
    {
        return array_unshift($this->inclusions, strtolower(trim($pageTag)));
    }

    /**
     * Pop the innermost inclusion.
     *
     * @return string|null the tag whose inclusion ends, null when the stack is empty
     */
    public function unregisterLast(): ?string
    {
        return array_shift($this->inclusions);
    }

    /** Whether $pageTag is anywhere in the current inclusion chain (case-insensitive). */
    public function isIncludedBy(string $pageTag): bool
    {
        return in_array(strtolower($pageTag), $this->inclusions);
    }

    /** @return list<string> */
    public function getAll(): array
    {
        return $this->inclusions;
    }

    /**
     * Replace the whole stack (e.g. to format a page outside the current inclusion
     * chain) and return the previous one so the caller can restore it.
     *
     * @param list<string> $stack
     *
     * @return list<string> the previous stack
     */
    public function replace(array $stack = []): array
    {
        $previous = $this->inclusions;
        $this->inclusions = $stack;

        return $previous;
    }
}
