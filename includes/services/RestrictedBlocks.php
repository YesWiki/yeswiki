<?php

namespace YesWiki\Core\Service;

/** Marks the blocks a visitor may not read so the formatter drops them before the page leaves the server. */
class RestrictedBlocks
{
    private string $nonce;
    private int $counter = 0;
    private array $stack = [];

    public function __construct()
    {
        $this->nonce = bin2hex(random_bytes(8));
    }

    /** Opens a block and returns its marker, empty when the block is visible. */
    public function enter(bool $visible): string
    {
        if ($visible) {
            $this->stack[] = null;

            return '';
        }
        $id = ++$this->counter;
        $this->stack[] = $id;

        return "<!--yw-restricted:{$this->nonce}:{$id}-->";
    }

    /** Closes the innermost block: its closing marker if it was restricted, null if it was visible or none is open. */
    public function leave(): ?string
    {
        $id = array_pop($this->stack);

        return $id === null ? null : "<!--/yw-restricted:{$this->nonce}:{$id}-->";
    }

    /** Removes every restricted block, markers included. */
    public function strip(string $html): string
    {
        if (!str_contains($html, "<!--yw-restricted:{$this->nonce}:")) {
            return $html;
        }
        $nonce = preg_quote($this->nonce, '/');

        return preg_replace("/<!--yw-restricted:{$nonce}:(\\d+)-->.*?<!--\\/yw-restricted:{$nonce}:\\1-->/s", '', $html);
    }
}
