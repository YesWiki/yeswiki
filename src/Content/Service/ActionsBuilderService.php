<?php

namespace YesWiki\Content\Service;

use Psr\Container\ContainerInterface;
use YesWiki\Files\Service\LocalFiles;
use YesWiki\Files\Service\Storage;
use YesWiki\Kernel\Service\ExtensionRegistry;
use YesWiki\Render\Component\ComponentRegistry;
use YesWiki\Render\Service\TemplateEngine;

/** What the editor's component palette is given. */
class ActionsBuilderService
{
    /**
     * @var array<string, mixed>|null
     */
    protected $data;

    protected TemplateEngine $renderer;

    protected ContainerInterface $container;

    public function __construct(TemplateEngine $renderer, ContainerInterface $container)
    {
        $this->renderer = $renderer;
        $this->container = $container;
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        if ($this->data !== null) {
            return $this->data;
        }

        $registry = $this->container->get(ComponentRegistry::class);

        $data = $this->container->get(EntryDisplay::class)->formAndListNames();

        $data['palette'] = $registry->palette();
        $data['components'] = $registry->byId();

        $extraComponents = $this->extraInputComponents();
        if (!empty($extraComponents)) {
            $data['extraComponents'] = $extraComponents;
        }

        return $this->data = $data;
    }

    /**
     * Vue inputs an extension or the instance ships, for a setting type core has never heard of.
     *
     * @return array<string, string>
     */
    private function extraInputComponents(): array
    {
        $extra = [];
        foreach ($this->container->get(ExtensionRegistry::class)->all() as $pluginName => $pluginPath) {
            foreach ($this->container->get(LocalFiles::class)->matching($pluginPath . 'javascripts/components/actions-builder/*.js') as $filePath) {
                $filename = pathinfo($filePath)['filename'];
                $extra[$filename] = "../../../$pluginName/javascripts/components/actions-builder/$filename.js";
            }
        }
        foreach ($this->container->get(Storage::class)->glob('custom/javascripts/components/actions-builder/*.js') as $filePath) {
            $filename = pathinfo($filePath)['filename'];
            $extra[$filename] = "../../../../custom/javascripts/components/actions-builder/$filename.js";
        }

        return $extra;
    }
}
