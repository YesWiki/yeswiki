<?php

namespace YesWiki\Kernel\Component;

/**
 * Declares what the editor's palette can insert.
 *
 * Implemented by an action for its own Components -- the parameters an action reads and the
 * settings the palette offers for it are the same list, and keeping them in two files is
 * how they drifted into 51 actions with no palette entry and 16 palette entries with no
 * action. It is also implemented by a standalone provider for anything no single action
 * owns: a Presentation such as `Cards` writes `{{entrylist}}` *or* `{{syndication}}`, so
 * neither of them can claim it.
 *
 * Discovered by DI tag, exactly like `RegisteredAction` (`_instanceof` in services.yaml,
 * collected by ComponentRegistry). There is no registry to enrol in and no file to list.
 *
 * An **instance** method on purpose. A palette is not the same on two wikis: which forms
 * exist, which custom entry templates `custom/templates/bazar/` holds, and whether the
 * person asking can administer the wiki all change it. Those used to be special cases
 * inside the palette service; here they are the provider's own business, which is where
 * knowledge of them belongs.
 */
interface ProvidesComponents
{
    /**
     * @return list<Component> may be empty -- a provider that decides this wiki has nothing
     *                         to offer says so by returning nothing, not by being skipped
     */
    public function components(): array;
}
