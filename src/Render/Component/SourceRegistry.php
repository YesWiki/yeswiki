<?php

namespace YesWiki\Render\Component;

use YesWiki\Content\Entity\SuppliesItems;
use YesWiki\Kernel\Performable\RegisteredPerformable;

/** Every Source this wiki has, for the Presentations to offer. */
class SourceRegistry
{
    /**
     * @var list<array{tag: string, label: string, settings: list<\YesWiki\Kernel\Component\Setting>, selection: list<\YesWiki\Kernel\Component\Setting>}>|null
     */
    private ?array $sources = null;

    /**
     * @param iterable<object> $sourceServices services tagged `yeswiki.item_source`
     */
    public function __construct(private readonly iterable $sourceServices)
    {
    }

    /**
     * @return list<array{tag: string, label: string, settings: list<\YesWiki\Kernel\Component\Setting>, selection: list<\YesWiki\Kernel\Component\Setting>}>
     */
    public function all(): array
    {
        if ($this->sources !== null) {
            return $this->sources;
        }

        $sources = [];
        foreach ($this->sourceServices as $service) {
            if (!$service instanceof SuppliesItems || !$service instanceof RegisteredPerformable) {
                continue;
            }
            $sources[] = [
                'tag' => $service::performableName(),
                'label' => $service::sourceLabel(),
                'settings' => $service::sourceSettings(),

                'selection' => $service::sourceSelectionSettings(),
            ];
        }

        return $this->sources = $sources;
    }

    /**
     * @return list<string>
     */
    public function tags(): array
    {
        return array_map(static fn (array $source) => $source['tag'], $this->all());
    }
}
