<?php

namespace YesWiki\Bazar\Service;

use YesWiki\Core\Service\TripleStore;

/**
 * Who wrote an entry and where it came from: the ActivityPub account and post it mirrors, or else its owner on the wiki.
 */
class EntryOrigin
{
    public const GENERATED_TITLE_URI = 'http://outils-reseaux.org/_vocabulary/generatedTitle';

    protected $tripleStore;

    public function __construct(TripleStore $tripleStore)
    {
        $this->tripleStore = $tripleStore;
    }

    /**
     * Adds an 'origin' to each entry, reading each kind of fact once for the whole list.
     */
    public function describeAll(array $entries): array
    {
        $actors = $this->valuesByResource(ActivityPubInbox::REMOTE_ACTOR_URI);
        $sources = $this->valuesByResource(TripleStore::SOURCE_URL_URI);
        $generated = $this->valuesByResource(self::GENERATED_TITLE_URI);
        foreach ($entries as $key => $entry) {
            $tag = $entry['id_fiche'] ?? '';
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
     */
    public function describe(array $entry): array
    {
        $tag = $entry['id_fiche'] ?? '';

        return $this->origin(
            $entry,
            $this->tripleStore->getOne($tag, ActivityPubInbox::REMOTE_ACTOR_URI, '', ''),
            $this->tripleStore->getOne($tag, TripleStore::SOURCE_URL_URI, '', ''),
            $this->tripleStore->getOne($tag, self::GENERATED_TITLE_URI, '', '') !== null
        );
    }

    private function origin(array $entry, ?string $actorUri, ?string $sourceUrl, bool $generatedTitle): array
    {
        return [
            'author' => $actorUri ? $this->remoteAuthor($actorUri) : ['name' => $entry['owner'] ?? '', 'handle' => null, 'url' => null],
            'sourceUrl' => $sourceUrl,
            'generatedTitle' => $generatedTitle,
        ];
    }

    private function remoteAuthor(string $actorUri): array
    {
        $host = (string)parse_url($actorUri, PHP_URL_HOST);
        $name = basename(rtrim((string)parse_url($actorUri, PHP_URL_PATH), '/'));

        return ['name' => $name, 'handle' => "@{$name}@{$host}", 'url' => $actorUri];
    }

    private function valuesByResource(string $property): array
    {
        $values = [];
        foreach ($this->tripleStore->getMatching(null, $property, null, '=', '=', '=') as $triple) {
            $values[$triple['resource']] = $triple['value'];
        }

        return $values;
    }
}
