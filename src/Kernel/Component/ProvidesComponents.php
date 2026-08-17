<?php

namespace YesWiki\Kernel\Component;

/** Declares what the editor's palette can insert. */
interface ProvidesComponents
{
    /**
     * @return list<Component> may be empty -- a provider that decides this wiki has nothing
     *                         to offer says so by returning nothing, not by being skipped
     */
    public function components(): array;
}
