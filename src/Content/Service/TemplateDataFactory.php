<?php

namespace YesWiki\Content\Service;

use Psr\Container\ContainerInterface;
use YesWiki\Content\TemplateData\PrepareData;
use YesWiki\Kernel\Service\ClassDirectoryScanner;

/** Finds the `PrepareData…` class for a list template, the way FieldFactory finds a field (ticket 49). */
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
        $bare = (string)preg_replace('/\.(twig|tpl\.html)$/', '', $template);

        return $this->preparers[strtolower($bare)] ?? [];
    }

    private function load(): void
    {
        require_once YESWIKI_PROGRAM_DIR . '/src/annotations/PreparesTemplate.php';

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
