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
        $profiles = $this->valuesByResource(ActivityPubInbox::REMOTE_ACTOR_PROFILE_URI);
        $sources = $this->valuesByResource(TripleStore::SOURCE_URL_URI);
        $generated = $this->valuesByResource(self::GENERATED_TITLE_URI);
        foreach ($entries as $key => $entry) {
            $tag = $entry['id_fiche'] ?? '';
            $actorUri = $actors[$tag] ?? null;
            $entries[$key]['origin'] = $this->origin(
                $entry,
                $actorUri,
                $actorUri ? ($profiles[$actorUri] ?? null) : null,
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
        $actorUri = $this->tripleStore->getOne($tag, ActivityPubInbox::REMOTE_ACTOR_URI, '', '');

        return $this->origin(
            $entry,
            $actorUri,
            $actorUri ? $this->tripleStore->getOne($actorUri, ActivityPubInbox::REMOTE_ACTOR_PROFILE_URI, '', '') : null,
            $this->tripleStore->getOne($tag, TripleStore::SOURCE_URL_URI, '', ''),
            $this->tripleStore->getOne($tag, self::GENERATED_TITLE_URI, '', '') !== null
        );
    }

    private function origin(array $entry, ?string $actorUri, ?string $profile, ?string $sourceUrl, bool $generatedTitle): array
    {
        return [
            'author' => $actorUri ? $this->remoteAuthor($actorUri, $profile) : ['name' => $entry['owner'] ?? '', 'handle' => null, 'url' => null],
            'sourceUrl' => $sourceUrl,
            'generatedTitle' => $generatedTitle,
        ];
    }

    /**
     * The remote author as their profile names them, or as their actor URI does when no profile was kept.
     */
    private function remoteAuthor(string $actorUri, ?string $profile): array
    {
        $profile = json_decode((string)$profile, true);
        $profile = is_array($profile) ? $profile : [];
        $host = (string)parse_url($actorUri, PHP_URL_HOST);
        $username = ($profile['username'] ?? '') ?: basename(rtrim((string)parse_url($actorUri, PHP_URL_PATH), '/'));

        return [
            'name' => ($profile['name'] ?? '') ?: $username,
            'handle' => "@{$username}@{$host}",
            'url' => ($profile['url'] ?? '') ?: $actorUri,
        ];
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
