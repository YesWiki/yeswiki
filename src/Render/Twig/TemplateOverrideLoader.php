<?php

/* Hands Twig the override the database holds for a template name, else asks the files on disk; the key carries the revision, so a new one compiles without a restart (ticket 18). */

namespace YesWiki\Render\Twig;

use Twig\Loader\LoaderInterface;
use Twig\Source;
use YesWiki\Render\Service\TemplateOverrides;

final class TemplateOverrideLoader implements LoaderInterface
{
    private const SHIPPED = 'shipped';

    private ?TemplateOverrides $overrides = null;

    /** @param \Closure(): TemplateOverrides $resolve asked for at the first template, since the service needs what TemplateEngine is built for */
    public function __construct(private readonly \Closure $resolve, private readonly LoaderInterface $files)
    {
    }

    private function overrides(): TemplateOverrides
    {
        return $this->overrides ??= ($this->resolve)();
    }

    public function getSourceContext(string $name): Source
    {
        $path = $this->find($name);

        return $path === null ? $this->files->getSourceContext($name) : new Source((string)$this->overrides()->read($path), $name, 'custom/templates/' . $path);
    }

    public function getCacheKey(string $name): string
    {
        $path = $this->find($name);

        return $path === null ? $this->files->getCacheKey($name) : 'template-override:' . $path . '#' . $this->overrides()->revisionOf($path);
    }

    public function isFresh(string $name, int $time): bool
    {
        return $this->find($name) !== null || $this->files->isFresh($name, $time);
    }

    public function exists(string $name): bool
    {
        return $this->find($name) !== null || $this->files->exists($name);
    }

    /** The override path the database holds for a namespaced name, the way TemplateEngine maps namespaces to `custom/templates/` folders. */
    private function find(string $name): ?string
    {
        if (preg_match('~^@([A-Za-z0-9_-]+)/(.+)$~', $name, $match) !== 1 || $match[1] === self::SHIPPED) {
            return null;
        }
        [, $namespace, $template] = $match;
        $candidates = match ($namespace) {
            'core' => ["core/$template"],
            'custom' => [$template],
            default => ["$namespace/$template", "$namespace/templates/$template"],
        };
        foreach ($candidates as $path) {
            try {
                if ($this->overrides()->revisionOf($path) !== null) {
                    return $path;
                }
            } catch (\InvalidArgumentException) {
                continue;
            }
        }

        return null;
    }
}
