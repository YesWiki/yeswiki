<?php

namespace YesWiki\Content\Service;

use Psr\Container\ContainerInterface;
use YesWiki\Content\TemplateData\PrepareData;
use YesWiki\Kernel\Service\ClassDirectoryScanner;

/**
 * Finds the `PrepareData…` class for a list template, the way FieldFactory finds a field (ticket 49).
 *
 * Scanned rather than tagged in the container, and for FieldFactory's reason: an extension is
 * installed at runtime and cannot be in the compiled container, so a convention that only
 * core can follow is not a convention. A template's preparer therefore ships next to the
 * template that needs it.
 */
class TemplateDataFactory
{
    /** @var array<string, list<class-string<PrepareData>>> template name => the classes that prepare it, in scan order */
    private array $preparers = [];

    public function __construct(private readonly ContainerInterface $container)
    {
        $this->load();
    }

    /**
     * Run every preparer this template has, over one argument array.
     *
     * Composed rather than dispatched: `map-and-table` is prepared by the map's and the
     * table's, in that order. Each sees what the one before it returned, so the later one can
     * read a key the earlier one wrote.
     *
     * @param array<string, mixed> $arguments
     *
     * @return array<string, mixed>
     */
    public function prepare(string $template, array $arguments): array
    {
        foreach ($this->preparersFor($template) as $className) {
            /** @var PrepareData $preparer */
            $preparer = new $className($this->container);
            $arguments = array_merge($arguments, $preparer->prepare($arguments));
        }

        return $arguments;
    }

    public function knows(string $template): bool
    {
        return $this->preparersFor($template) !== [];
    }

    /**
     * @return list<class-string<PrepareData>>
     */
    private function preparersFor(string $template): array
    {
        // the same template is written `map` in page content and `map.twig` in a config value
        $bare = (string)preg_replace('/\.(twig|tpl\.html)$/', '', $template);

        return $this->preparers[strtolower($bare)] ?? [];
    }

    private function load(): void
    {
        require_once YESWIKI_SOURCE_DIR . '/src/annotations/PreparesTemplate.php';

        $scanner = $this->container->get(ClassDirectoryScanner::class);
        foreach ($scanner->directories('TemplateData', 'templatedata') as $namespace => $dir) {
            $this->scan($scanner->filesIn($dir), $namespace);
        }
    }

    /**
     * @param list<string> $files
     */
    private function scan(array $files, string $namespace): void
    {
        foreach ($files as $file) {
            if (!preg_match('/^PrepareData([A-Za-z0-9]+)\.php$/', $file, $matches)) {
                continue;
            }
            $class = $namespace . 'PrepareData' . $matches[1];
            if (!class_exists($class) || !is_subclass_of($class, PrepareData::class)) {
                continue;
            }

            // The class name names one template; the attribute names any whose spelling a
            // class name cannot hold, `map-and-table` being the one that forced it.
            $templates = [strtolower($matches[1])];
            foreach ((new \ReflectionClass($class))->getAttributes(\PreparesTemplate::class) as $attribute) {
                $templates = array_merge($templates, $attribute->newInstance()->templates);
            }

            foreach ($templates as $template) {
                $this->preparers[strtolower($template)][] = $class;
            }
        }
    }
}
