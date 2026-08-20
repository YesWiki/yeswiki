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
    ) {
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
