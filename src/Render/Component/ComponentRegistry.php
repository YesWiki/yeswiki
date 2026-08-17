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

/** Every Component this wiki offers, asked of the services that declare them. */
class ComponentRegistry
{
    /**
     * @var list<Component>|null
     */
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
     * The palette, as the browser reads it: categories in core's order, each holding the Components that named it and are offered.
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
     * Every Component by id, palette-visible or not -- what the settings rail looks one up in once recognition has named it.
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
