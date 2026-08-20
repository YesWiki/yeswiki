<?php

namespace YesWiki\Core\Entity;

/**
 * A SQL dump made restorable here, and what had to be changed to make it so.
 */
class DumpRewrite
{
    public function __construct(
        public readonly string $sql,
        public readonly string $prefixFrom,
        public readonly string $prefixTo,
        public readonly string $urlFrom,
        public readonly string $urlTo,
        public readonly array $substitutions = [],
    ) {
    }

    /**
     * The same rewrite, carrying the dump it was applied to.
     */
    public function withSql(string $sql): self
    {
        return new self($sql, $this->prefixFrom, $this->prefixTo, $this->urlFrom, $this->urlTo, $this->substitutions);
    }

    public function apply(string $statement): string
    {
        return empty($this->substitutions) ? $statement : strtr($statement, $this->substitutions);
    }

    public function renamedTables(): bool
    {
        return $this->prefixFrom !== $this->prefixTo;
    }

    public function rewroteUrls(): bool
    {
        return $this->urlFrom !== '';
    }
}
