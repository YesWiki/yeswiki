<?php

namespace YesWiki\Render\Service;

/**
 * The directories Twig's own loader is told to look in.
 *
 * These are the one place in the wiki where a bare `file_exists` is the right question rather than
 * a leftover. `Twig\Loader\FilesystemLoader` opens paths itself, with PHP's filesystem functions
 * and nothing else, so asking `Storage` whether a template directory exists would be asking about
 * a filesystem Twig is not going to read -- and on an instance whose Public tier is a bucket, the
 * two would disagree and the loader would be handed a path it cannot open.
 *
 * ADR-0022 records the same reasoning for `ZipArchive`, `Zebra_Image`, `HTMLPurifier::cleanFile`
 * and `getimagesize`: a library that wants a real path gets a real path. This is that list's fifth
 * entry, and it is here rather than inline so the exemption is a class with a reason instead of a
 * line in a list nobody reads.
 */
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
