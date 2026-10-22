<?php

namespace YesWiki\Content\Service;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use YesWiki\Content\Entity\ExtendsFormBuilder;

/** Every field type an extension adds to the form builder, as the scripts and data the builder page loads. */
class FormBuilderExtensions
{
    /** @param iterable<ExtendsFormBuilder> $extensions tagged `yeswiki.form_builder_extension` */
    public function __construct(#[AutowireIterator('yeswiki.form_builder_extension')] private readonly iterable $extensions = [])
    {
    }

    /** @return array{scripts: list<string>, data: array<string, mixed>} what every extension adds to the builder page */
    public function forDesigner(): array
    {
        $scripts = [];
        $data = [];
        foreach ($this->extensions as $extension) {
            $scripts = array_merge($scripts, $extension->formBuilderScripts());
            $data = array_replace($data, $extension->formBuilderData());
        }

        return ['scripts' => array_values(array_unique($scripts)), 'data' => $data];
    }
}
