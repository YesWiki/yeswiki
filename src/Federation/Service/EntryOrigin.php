<?php

namespace YesWiki\Federation\Service;

use YesWiki\Kernel\Service\TripleStore;

/** Who wrote an entry and where it came from: the ActivityPub account and post it mirrors, or else its owner on the wiki. */
class EntryOrigin
{
    public const GENERATED_TITLE_URI = 'http://outils-reseaux.org/_vocabulary/generatedTitle';

    public function __construct(private readonly TripleStore $tripleStore)
    {
    }

    /**
     * Adds an 'origin' to each entry, reading each kind of fact once for the whole list.
     *
     * @param array<array-key, array<string, mixed>> $entries
     *
     * @return array<array-key, array<string, mixed>>
     */
    public function describeAll(array $entries): array
    {
        $actors = $this->valuesByResource(ActivityPubInbox::REMOTE_ACTOR_URI);
        $sources = $this->valuesByResource(TripleStore::SOURCE_URL_URI);
        $generated = $this->valuesByResource(self::GENERATED_TITLE_URI);
        foreach ($entries as $key => $entry) {
            $tag = (string)($entry['tag'] ?? '');
            $entries[$key]['origin'] = $this->origin(
                $entry,
                $actors[$tag] ?? null,
                $sources[$tag] ?? null,
                isset($generated[$tag])
            );
        }

        return $entries;
    }

    /**
     * The origin of a single entry.
     *
     * @param array<string, mixed> $entry
     *
     * @return array{author: array{name: string, handle: ?string, url: ?string}, sourceUrl: ?string, generatedTitle: bool}
     */
    public function describe(array $entry): array
    {
        $tag = (string)($entry['tag'] ?? '');

        return $this->origin(
            $entry,
            $this->tripleStore->getOne($tag, ActivityPubInbox::REMOTE_ACTOR_URI, '', ''),
            $this->tripleStore->getOne($tag, TripleStore::SOURCE_URL_URI, '', ''),
            $this->tripleStore->getOne($tag, self::GENERATED_TITLE_URI, '', '') !== null
        );
    }

    /**
     * @param array<string, mixed> $entry
     *
     * @return array{author: array{name: string, handle: ?string, url: ?string}, sourceUrl: ?string, generatedTitle: bool}
     */
    private function origin(array $entry, ?string $actorUri, ?string $sourceUrl, bool $generatedTitle): array
    {
        return [
            'author' => $actorUri
                ? $this->remoteAuthor($actorUri)
                : ['name' => (string)($entry['owner'] ?? ''), 'handle' => null, 'url' => null],
            'sourceUrl' => $sourceUrl,
            'generatedTitle' => $generatedTitle,
        ];
    }

    /** @return array{name: string, handle: string, url: string} */
    private function remoteAuthor(string $actorUri): array
    {
        $host = (string)parse_url($actorUri, PHP_URL_HOST);
        $name = basename(rtrim((string)parse_url($actorUri, PHP_URL_PATH), '/'));

        return ['name' => $name, 'handle' => "@{$name}@{$host}", 'url' => $actorUri];
    }

    /** @return array<string, string> */
    private function valuesByResource(string $property): array
    {
        $values = [];
        foreach ($this->tripleStore->getMatching(null, $property, null, '=', '=', '=') as $triple) {
            $values[(string)$triple['resource']] = (string)$triple['value'];
        }

        return $values;
    }
}
