<?php

namespace YesWiki\Render\Component;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Core\YesWikiPerformable;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Component\Category;
use YesWiki\Kernel\Component\Component;
use YesWiki\Kernel\Component\ProvidesComponents;
use YesWiki\Render\Service\TemplateEngine;

/**
 * Every Component this wiki offers, asked of the services that declare them.
 *
 * Replaces `ActionsBuilderService`'s three globs -- core's own `docs/actions` YAML, each
 * extension's `actions/documentation.yaml` and the instance's `custom` one -- and with them
 * the `array_walk_recursive` pass that turned `_t(KEY)` marker strings into translations
 * afterwards -- a provider calls `_t()` itself, at the moment it declares a label, in the
 * language of the request that asked.
 *
 * In `Render` rather than beside the model in `Kernel`: it asks `AclService` whether the
 * person is an administrator, and Kernel may depend on no feature module (ADR-0013). The
 * palette is an editing surface, and editing surfaces are Render's.
 */
class ComponentRegistry
{
    /** @var list<Component>|null */
    private ?array $components = null;

    /**
     * @param iterable<ProvidesComponents> $providers tagged services, in no particular
     *                                                order -- Category decides the order
     */
    public function __construct(
        private readonly iterable $providers,
        private readonly ContainerInterface $container,
        private readonly ParameterBagInterface $params,
        private readonly TemplateEngine $twig,
        private readonly AclService $acl,
    ) {
    }

    /**
     * @return list<Component> everything this wiki has, palette-ordered, including the ones
     *                         the palette will not show (`notOffered()`) -- the rail still
     *                         has to recognise those in a page that already holds one
     */
    public function all(): array
    {
        if ($this->components !== null) {
            return $this->components;
        }

        $components = [];
        foreach ($this->providers as $provider) {
            // An action declares its own Components, and an action is prepared by setter
            // injection rather than by its constructor -- `$this->getService()` throws on
            // an unset property until something has called setServices(). Performer does
            // this before running one; the same has to happen before asking one what it
            // offers. Arguments and output are deliberately not set: declaring is not
            // running, and a provider that reached for them would be doing the wrong thing.
            if ($provider instanceof YesWikiPerformable) {
                $provider->setServices($this->container);
                $provider->setParams($this->params);
                $provider->setTwig($this->twig);
            }

            foreach ($provider->components() as $component) {
                $components[] = $component;
            }
        }

        $isAdmin = $this->acl->isAdmin();
        $components = array_values(array_filter(
            $components,
            static fn (Component $c) => $isAdmin || !$c->isAdminOnly(),
        ));

        usort(
            $components,
            static fn (Component $a, Component $b) => $a->categoryOf()->position() <=> $b->categoryOf()->position(),
        );

        return $this->components = $components;
    }

    /**
     * Which Component wrote this tag.
     *
     * Take every Component that lists the tag name; keep those whose pins all match what
     * the tag actually says; the one with the most pins wins, and a Component with no pins
     * is the fallback. So `{{entrylist template="card"}}` is a `Cards`, and
     * `{{entrylist template="something-nobody-declared"}}` is a plain entry list rather
     * than nothing at all.
     *
     * @param array<string, string> $arguments the tag's parameters, as parsed
     */
    public function match(string $tag, array $arguments): ?Component
    {
        $best = null;
        $bestPins = -1;

        foreach ($this->all() as $component) {
            if (!in_array($tag, $component->tags(), true)) {
                continue;
            }
            $pins = $component->pins();
            foreach ($pins as $name => $value) {
                if (($arguments[$name] ?? null) !== $value) {
                    continue 2;
                }
            }
            if (count($pins) > $bestPins) {
                $best = $component;
                $bestPins = count($pins);
            }
        }

        return $best;
    }

    /**
     * The palette, as the browser reads it: categories in core's order, each holding the
     * Components that named it and are offered.
     *
     * @return list<array<string, mixed>>
     */
    public function palette(): array
    {
        $byCategory = [];
        foreach ($this->all() as $component) {
            $array = $component->toArray();
            if (($array['offered'] ?? false) !== true) {
                continue;
            }
            $byCategory[$component->categoryOf()->value][] = $array;
        }

        $palette = [];
        foreach (Category::cases() as $category) {
            if (empty($byCategory[$category->value])) {
                continue;
            }
            $palette[] = [
                'id' => $category->value,
                'label' => $category->label(),
                'components' => $byCategory[$category->value],
            ];
        }

        return $palette;
    }

    /**
     * Every Component by id, palette-visible or not -- what the settings rail looks one up
     * in once recognition has named it.
     *
     * @return array<string, array<string, mixed>>
     */
    public function byId(): array
    {
        $map = [];
        foreach ($this->all() as $component) {
            $map[$component->id()] = $component->toArray();
        }

        return $map;
    }
}
