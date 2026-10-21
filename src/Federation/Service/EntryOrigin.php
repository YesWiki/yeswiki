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
        $profiles = $this->valuesByResource(ActivityPubInbox::REMOTE_ACTOR_PROFILE_URI);
        $sources = $this->valuesByResource(TripleStore::SOURCE_URL_URI);
        $generated = $this->valuesByResource(self::GENERATED_TITLE_URI);
        foreach ($entries as $key => $entry) {
            $tag = (string)($entry['tag'] ?? '');
            $actorUri = $actors[$tag] ?? null;
            $entries[$key]['origin'] = $this->origin(
                $entry,
                $actorUri,
                $actorUri === null ? null : ($profiles[$actorUri] ?? null),
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
        $actorUri = $this->tripleStore->getOne($tag, ActivityPubInbox::REMOTE_ACTOR_URI, '', '');

        return $this->origin(
            $entry,
            $actorUri,
            $actorUri ? $this->tripleStore->getOne($actorUri, ActivityPubInbox::REMOTE_ACTOR_PROFILE_URI, '', '') : null,
            $this->tripleStore->getOne($tag, TripleStore::SOURCE_URL_URI, '', ''),
            $this->tripleStore->getOne($tag, self::GENERATED_TITLE_URI, '', '') !== null
        );
    }

    /**
     * @param array<string, mixed> $entry
     *
     * @return array{author: array{name: string, handle: ?string, url: ?string}, sourceUrl: ?string, generatedTitle: bool}
     */
    private function origin(array $entry, ?string $actorUri, ?string $profile, ?string $sourceUrl, bool $generatedTitle): array
    {
        return [
            'author' => $actorUri
                ? $this->remoteAuthor($actorUri, $profile)
                : ['name' => (string)($entry['owner'] ?? ''), 'handle' => null, 'url' => null],
            'sourceUrl' => $sourceUrl,
            'generatedTitle' => $generatedTitle,
        ];
    }

    /**
     * The remote author as their profile names them, or as their actor URI does when no profile was kept.
     *
     * @return array{name: string, handle: string, url: string}
     */
    private function remoteAuthor(string $actorUri, ?string $profile): array
    {
        $profile = json_decode((string)$profile, true);
        $profile = is_array($profile) ? $profile : [];
        $text = fn (string $key): string => is_string($profile[$key] ?? null) ? $profile[$key] : '';
        $host = (string)parse_url($actorUri, PHP_URL_HOST);
        $username = $text('username') ?: basename(rtrim((string)parse_url($actorUri, PHP_URL_PATH), '/'));

        return [
            'name' => $text('name') ?: $username,
            'handle' => "@{$username}@{$host}",
            'url' => $text('url') ?: $actorUri,
        ];
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
