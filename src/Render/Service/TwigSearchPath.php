<?php

namespace YesWiki\Render\Service;

/** The directories Twig's loader looks in. */
class TwigSearchPath
{
    /**
     * Keep the directories that are actually there, in the order given.
     *
     * @param list<string> $candidates
     *
     * @return list<string>
     */
    public function existing(array $candidates): array
    {
        return array_values(array_filter($candidates, static fn (string $path) => file_exists($path)));
    }

    public function exists(string $path): bool
    {
        return file_exists($path);
    }
}
