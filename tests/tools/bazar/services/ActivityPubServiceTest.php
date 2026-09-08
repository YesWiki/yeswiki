<?php

namespace YesWiki\Test\Bazar\Service;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Bazar\Service\ActivityPubService;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\HttpSignatureService;
use YesWiki\Bazar\Service\SemanticTransformer;
use YesWiki\Bazar\Service\SsrfUrlValidator;
use YesWiki\Bazar\Service\WebfingerService;
use YesWiki\Core\Service\TripleStore;

require_once 'includes/autoload.inc.php';
require_once 'includes/constants.php';

class ActivityPubServiceTest extends TestCase
{
    private const FORM = ['bn_id_nature' => '1', 'bn_activitypub_enable' => '1'];
    private const THEM = 'https://them.example/actors/1';
    private const US = 'https://us.example/actors/1';

    /** @var array<int,array<string,string>> */
    private array $triples = [];
    /** @var array<string,string> */
    private array $owners = [];
    private array $updated = [];
    private array $deleted = [];
    private array $created = [];

    private function service(): ActivityPubService
    {
        $tripleStore = $this->createStub(TripleStore::class);
        $tripleStore->method('getMatching')->willReturnCallback(
            fn ($resource, $property, $value) => array_values(array_filter(
                $this->triples,
                fn ($t) => $t['property'] === $property && $t['value'] === $value
            ))
        );
        $tripleStore->method('getOne')->willReturnCallback(
            fn ($resource, $property) => $property === ActivityPubService::REMOTE_ACTOR_URI
                ? ($this->owners[$resource] ?? null)
                : null
        );
        $tripleStore->method('create')->willReturnCallback(function ($resource, $property, $value) {
            if ($property === ActivityPubService::REMOTE_ACTOR_URI) {
                $this->owners[$resource] = $value;
            }

            return 0;
        });

        $entryManager = $this->createStub(EntryManager::class);
        $entryManager->method('create')->willReturnCallback(function ($formId, $data, $semantic, $sourceUrl) {
            $tag = 'Fiche' . (count($this->created) + 1);
            $this->created[] = ['tag' => $tag, 'sourceUrl' => $sourceUrl];
            $this->triples[] = ['resource' => $tag, 'property' => TripleStore::SOURCE_URL_URI, 'value' => $sourceUrl];

            return ['id_fiche' => $tag] + $data;
        });
        $entryManager->method('update')->willReturnCallback(function ($tag) {
            $this->updated[] = $tag;

            return [];
        });
        $entryManager->method('delete')->willReturnCallback(function ($tag) {
            $this->deleted[] = $tag;
        });

        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')->willReturn($entryManager);

        $semanticTransformer = $this->createStub(SemanticTransformer::class);
        $semanticTransformer->method('convertFromSemanticData')->willReturn(['bf_titre' => 'Une fiche']);

        $params = $this->createStub(ParameterBagInterface::class);
        $params->method('get')->willReturn('https://us.example/');

        return new ActivityPubService(
            $params,
            $this->createStub(WebfingerService::class),
            $container,
            new HttpSignatureService($this->createStub(SsrfUrlValidator::class)),
            $semanticTransformer,
            $tripleStore,
            $this->createStub(SsrfUrlValidator::class)
        );
    }

    private function givenMirroredEntry(string $tag, string $objectId, ?string $owner = null): void
    {
        $this->triples[] = ['resource' => $tag, 'property' => TripleStore::SOURCE_URL_URI, 'value' => $objectId];
        if (!is_null($owner)) {
            $this->owners[$tag] = $owner;
        }
    }

    private function update(string $objectId): array
    {
        return ['type' => 'Update', 'actor' => self::THEM, 'object' => ['id' => $objectId]];
    }

    private function delete(string $objectId): array
    {
        return ['type' => 'Delete', 'actor' => self::THEM, 'object' => ['id' => $objectId]];
    }

    public function testAnActivityCannotClaimAnActorTheRequestWasNotSignedFor()
    {
        $this->givenMirroredEntry('FicheUne', 'https://them.example/entries/1', self::THEM);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('claims an actor');

        $this->service()->processActivity($this->update('https://them.example/entries/1'), self::FORM, 'https://attacker.example/actors/1');
    }

    public function testAnActorCannotUpdateAnEntryThatBelongsToAnother()
    {
        $this->givenMirroredEntry('FicheUne', 'https://them.example/entries/1', 'https://them.example/actors/2');
        $activity = $this->update('https://them.example/entries/1');

        try {
            $this->service()->processActivity($activity, self::FORM, self::THEM);
            $this->fail('the update should have been refused');
        } catch (\Exception $e) {
            $this->assertStringContainsString('belongs to another actor', $e->getMessage());
        }
        $this->assertSame([], $this->updated);
    }

    public function testAnActorCannotDeleteAnEntryThatBelongsToAnother()
    {
        $this->givenMirroredEntry('FicheUne', 'https://them.example/entries/1', 'https://them.example/actors/2');

        try {
            $this->service()->processActivity($this->delete('https://them.example/entries/1'), self::FORM, self::THEM);
            $this->fail('the delete should have been refused');
        } catch (\Exception $e) {
            $this->assertStringContainsString('belongs to another actor', $e->getMessage());
        }
        $this->assertSame([], $this->deleted);
    }

    public function testTheActorAnEntryCameFromCanUpdateAndDeleteIt()
    {
        $this->givenMirroredEntry('FicheUne', 'https://them.example/entries/1', self::THEM);
        $service = $this->service();

        $service->processActivity($this->update('https://them.example/entries/1'), self::FORM, self::THEM);
        $service->processActivity($this->delete('https://them.example/entries/1'), self::FORM, self::THEM);

        $this->assertSame(['FicheUne'], $this->updated);
        $this->assertSame(['FicheUne'], $this->deleted);
    }

    public function testAnEntryMirroredBeforeOwnersWereRecordedAnswersToItsOwnHost()
    {
        $this->givenMirroredEntry('FicheUne', 'https://them.example/entries/1');

        $this->service()->processActivity($this->update('https://them.example/entries/1'), self::FORM, self::THEM);

        $this->assertSame(['FicheUne'], $this->updated);
    }

    public function testAnotherHostCannotTouchAnEntryMirroredBeforeOwnersWereRecorded()
    {
        $this->givenMirroredEntry('FicheUne', 'https://them.example/entries/1');
        $activity = ['type' => 'Delete', 'actor' => 'https://attacker.example/actors/1', 'object' => ['id' => 'https://them.example/entries/1']];

        try {
            $this->service()->processActivity($activity, self::FORM, 'https://attacker.example/actors/1');
            $this->fail('the delete should have been refused');
        } catch (\Exception $e) {
            $this->assertStringContainsString('another host', $e->getMessage());
        }
        $this->assertSame([], $this->deleted);
    }

    public function testCreatingAnEntryWritesDownWhoItAnswersTo()
    {
        $activity = [
            'type' => 'Create',
            'actor' => self::THEM,
            'object' => ['id' => 'https://them.example/entries/7', 'type' => 'Note'],
        ];

        $this->service()->processActivity($activity, self::FORM, self::THEM);

        $this->assertSame([['tag' => 'Fiche1', 'sourceUrl' => 'https://them.example/entries/7']], $this->created);
        $this->assertSame([self::THEM], array_values($this->owners));
    }

    public function testAnActorCannotBringAnObjectFromAnotherHost()
    {
        $activity = [
            'type' => 'Create',
            'actor' => self::THEM,
            'object' => ['id' => 'https://us.example/entries/7', 'type' => 'Note'],
        ];

        try {
            $this->service()->processActivity($activity, self::FORM, self::THEM);
            $this->fail('the creation should have been refused');
        } catch (\Exception $e) {
            $this->assertStringContainsString('its own host', $e->getMessage());
        }
        $this->assertSame([], $this->created);
    }

    public function testAMalformedActivityIsRefused()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Malformed activity');

        $this->service()->processActivity(null, self::FORM, self::THEM);
    }

    public function testUnknownObjectsAreLeftAlone()
    {
        $this->givenMirroredEntry('FicheUne', 'https://them.example/entries/1', self::THEM);

        $this->service()->processActivity($this->update('https://them.example/entries/2'), self::FORM, self::THEM);

        $this->assertSame([], $this->updated);
    }
}
