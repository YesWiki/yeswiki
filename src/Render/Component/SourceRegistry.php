<?php

namespace YesWiki\Render\Component;

use YesWiki\Content\Entity\SuppliesItems;
use YesWiki\Kernel\Performable\RegisteredPerformable;

/**
 * Every Source this wiki has, for the Presentations to offer.
 *
 * Discovered by DI tag like everything else here, so **adding a Source is a change to that
 * action alone**: it implements `SuppliesItems`, and it appears in the source picker of
 * every Presentation without a single one of them being edited.
 */
class SourceRegistry
{
    /** @var list<array{tag: string, label: string, settings: list<\YesWiki\Kernel\Component\Setting>}>|null */
    private ?array $sources = null;

    /** @param iterable<object> $sourceServices services tagged `yeswiki.item_source` */
    public function __construct(private readonly iterable $sourceServices)
    {
    }

    /**
     * @return list<array{tag: string, label: string, settings: list<\YesWiki\Kernel\Component\Setting>}>
     */
    public function all(): array
    {
        if ($this->sources !== null) {
            return $this->sources;
        }

        $sources = [];
        foreach ($this->sourceServices as $service) {
            // the tag it is written as. A Source is an action, and it keeps its own tag --
            // picking a Source is picking which tag the Presentation writes. A Source that
            // is not a registered action has no tag to write, so it cannot be offered.
            if (!$service instanceof SuppliesItems || !$service instanceof RegisteredPerformable) {
                continue;
            }
            $sources[] = [
                'tag' => $service::performableName(),
                'label' => $service::sourceLabel(),
                'settings' => $service::sourceSettings(),
            ];
        }

        return $this->sources = $sources;
    }

    /** @return list<string> */
    public function tags(): array
    {
        return array_map(static fn (array $source) => $source['tag'], $this->all());
    }
}
