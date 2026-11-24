<?php

namespace YesWiki\Content\Service;

use YesWiki\Render\Component\ComponentRegistry;

/** What an editor is told of the components a page may hold: their tags, to find them in the text; the rail asks the server for the rest. */
class ActionsBuilderService
{
    /** @var array<string, mixed>|null */
    private ?array $data = null;

    public function __construct(private readonly ComponentRegistry $registry)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        if ($this->data !== null) {
            return $this->data;
        }
        $components = [];
        foreach ($this->registry->byId() as $id => $component) {
            $components[$id] = array_intersect_key($component, array_flip(['id', 'tags', 'pins', 'addOnly', 'isWrapper', 'label']));
        }

        return $this->data = ['components' => $components];
    }
}
