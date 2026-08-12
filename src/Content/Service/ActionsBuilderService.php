<?php

namespace YesWiki\Content\Service;

use Psr\Container\ContainerInterface;
use YesWiki\Kernel\Service\ExtensionRegistry;
use YesWiki\Render\Component\ComponentRegistry;
use YesWiki\Render\Service\TemplateEngine;

/**
 * What the editor's component palette is given.
 *
 * Until ticket 36 this class WAS the palette: it globbed `docs/actions/*.yaml` plus every
 * extension's `actions/documentation.yaml` plus the instance's `custom` one, sorted the
 * groups by a `position` integer, injected the custom bazar templates as extra entries of
 * the entrylist group, and finished by walking the whole structure to turn `_t(KEY)` marker
 * strings into translations. None of that is here any more. Components are declared in PHP
 * by the services that own them (`ProvidesComponents`), a provider calls `_t()` itself at
 * the moment it declares a label, and this is left holding the two things that are neither
 * a component nor a category: which forms exist, and which extra Vue inputs to load.
 */
class ActionsBuilderService
{
    /** @var array<string, mixed>|null */
    protected $data;

    protected TemplateEngine $renderer;

    protected ContainerInterface $container;

    public function __construct(TemplateEngine $renderer, ContainerInterface $container)
    {
        $this->renderer = $renderer;
        $this->container = $container;
    }

    /** @return array<string, mixed> */
    public function getData(): array
    {
        if ($this->data !== null) {
            return $this->data;
        }

        $registry = $this->container->get(ComponentRegistry::class);

        $data = formAndListIds();
        // the palette, in category order; and every component by id, palette-visible or not,
        // because the settings rail opens on components the palette does not offer
        $data['palette'] = $registry->palette();
        $data['components'] = $registry->byId();

        $extraComponents = $this->extraInputComponents();
        if (!empty($extraComponents)) {
            $data['extraComponents'] = $extraComponents;
        }

        return $this->data = $data;
    }

    /**
     * Vue inputs an extension or the instance ships, for a setting type core has never
     * heard of. Unrelated to Components -- these are the widgets a setting is drawn with.
     *
     * @return array<string, string>
     */
    private function extraInputComponents(): array
    {
        $extra = [];
        foreach ($this->container->get(ExtensionRegistry::class)->all() as $pluginName => $pluginPath) {
            foreach (glob($pluginPath . 'javascripts/components/actions-builder/*.js') ?: [] as $filePath) {
                $filename = pathinfo($filePath)['filename'];
                $extra[$filename] = "../../../$pluginName/javascripts/components/actions-builder/$filename.js";
            }
        }
        foreach (glob('custom/javascripts/components/actions-builder/*.js') ?: [] as $filePath) {
            $filename = pathinfo($filePath)['filename'];
            $extra[$filename] = "../../../../custom/javascripts/components/actions-builder/$filename.js";
        }

        return $extra;
    }
}
