<?php

namespace YesWiki\Test\Federation;

use PHPUnit\Framework\TestCase;
use YesWiki\Federation\Service\ActivityPubInbox;
use YesWiki\Federation\Service\EntryOrigin;
use YesWiki\Kernel\Service\TripleStore;

require_once 'tests/YesWikiTestCase.php';

/** A mirrored entry is credited to the remote account as its profile names it, or as its address does when no profile was kept. */
class EntryOriginTest extends TestCase
{
    private const ACTOR = 'https://masto.example/ap/users/117474142124196539';

    /** @var array<string, array<string, string>> */
    private array $values = [];

    private function origin(): EntryOrigin
    {
        $tripleStore = $this->createStub(TripleStore::class);
        $tripleStore->method('getOne')->willReturnCallback(fn ($resource, $property) => $this->values[$resource][$property] ?? null);
        $tripleStore->method('getMatching')->willReturnCallback(function ($resource, $property) {
            $triples = [];
            foreach ($this->values as $subject => $properties) {
                if (isset($properties[$property])) {
                    $triples[] = ['resource' => $subject, 'property' => $property, 'value' => $properties[$property]];
                }
            }

            return $triples;
        });

        return new EntryOrigin($tripleStore);
    }

    public function testTheRemoteAuthorIsNamedFromTheKeptProfile(): void
    {
        $this->values['Pouet'][ActivityPubInbox::REMOTE_ACTOR_URI] = self::ACTOR;
        $this->values[self::ACTOR][ActivityPubInbox::REMOTE_ACTOR_PROFILE_URI] = (string)json_encode(['name' => 'Florian', 'username' => 'mrflos', 'url' => 'https://masto.example/@mrflos']);

        $author = $this->origin()->describe(['tag' => 'Pouet'])['author'];

        $this->assertSame(['name' => 'Florian', 'handle' => '@mrflos@masto.example', 'url' => 'https://masto.example/@mrflos'], $author);
        $this->assertSame($author, $this->origin()->describeAll([['tag' => 'Pouet']])[0]['origin']['author']);
    }

    public function testWithoutAProfileTheAddressNamesTheAuthor(): void
    {
        $this->values['Pouet'][ActivityPubInbox::REMOTE_ACTOR_URI] = 'https://mamot.fr/users/Khrys';

        $author = $this->origin()->describe(['tag' => 'Pouet'])['author'];

        $this->assertSame(['name' => 'Khrys', 'handle' => '@Khrys@mamot.fr', 'url' => 'https://mamot.fr/users/Khrys'], $author);
    }

    public function testALocalEntryIsCreditedToItsOwner(): void
    {
        $this->assertSame(['name' => 'mrflos', 'handle' => null, 'url' => null], $this->origin()->describe(['tag' => 'Billet', 'owner' => 'mrflos'])['author']);
    }
}
