<?php

namespace YesWiki\Content\Entity;

/** Adds field types to the form builder: the module scripts that register them in `window.formBuilderFields`, and the data those scripts read. */
interface ExtendsFormBuilder
{
    /** @return list<string> module scripts loaded before the builder, each adding entries to `window.formBuilderFields` */
    public function formBuilderScripts(): array;

    /** @return array<string, mixed> data the scripts read from `data-extensions` on `#form-builder-container`, under a key of their own */
    public function formBuilderData(): array;
}
