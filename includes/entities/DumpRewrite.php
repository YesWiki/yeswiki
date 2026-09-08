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
        public readonly array $skip = [],
    ) {
    }

    /**
     * Statements about tables that belong to another wiki sharing the database.
     */
    public function skips(string $statement): bool
    {
        foreach ($this->skip as $table) {
            if (strpos($statement, $table) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * The same rewrite, carrying the dump it was applied to.
     */
    public function withSql(string $sql): self
    {
        return new self($sql, $this->prefixFrom, $this->prefixTo, $this->urlFrom, $this->urlTo, $this->substitutions, $this->skip);
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
