<?php

namespace YesWiki\Test\Content;

use PHPUnit\Framework\Attributes\DataProvider;
use YesWiki\Content\Service\BazarListService;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Kernel\Service\TripleStore;
use YesWiki\Render\Service\Performer;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** The origin option keeps the entries written on the wiki, those brought from a remote URL, or both. */
class EntryListOriginTest extends YesWikiTestCase
{
    private const FORM_ID = '999943';

    /** @var list<string> */
    private array $tags = [];

    protected function setUp(): void
    {
        parent::setUp();
        $services = $this->getWiki()->services;
        if ($services->get(AuthenticationService::class)->connectFirstAdmin() === null) {
            $this->markTestSkipped('no admin account in the test wiki');
        }
        $services->get(FormManager::class)->create([
            'id' => self::FORM_ID,
            'label' => 'Origin filter test form',
            'template' => json_encode([['type' => 'texte', 'name' => 'bf_titre', 'label' => 'Titre']]),
            'condition' => '',
        ]);
        $entryManager = $services->get(EntryManager::class);
        foreach (['Fiche locale une', 'Fiche locale deux'] as $title) {
            $this->tags[] = (string)$entryManager->create(self::FORM_ID, ['antispam' => 1, 'bf_titre' => $title])['tag'];
        }
        $this->tags[] = (string)$entryManager->create(
            self::FORM_ID,
            ['antispam' => 1, 'bf_titre' => 'Fiche distante'],
            false,
            'https://mamot.fr/users/Khrys/statuses/42'
        )['tag'];
        $services->get(AuthenticationService::class)->logout();
    }

    protected function tearDown(): void
    {
        $services = $this->getWiki()->services;
        $services->get(AuthenticationService::class)->logout();
        $entryManager = $services->get(EntryManager::class);
        foreach ($this->tags as $tag) {
            $services->get(TripleStore::class)->deleteAll($tag, '');
            if ($entryManager->isEntry($tag)) {
                $entryManager->delete($tag, true);
            }
        }
        $services->get(FormManager::class)->delete(self::FORM_ID);
        parent::tearDown();
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return list<string>
     */
    private function titles(array $options): array
    {
        $entries = $this->getWiki()->services->get(BazarListService::class)->getEntries($options + ['id' => self::FORM_ID]);
        $titles = array_column($entries, 'bf_titre');
        sort($titles);

        return $titles;
    }

    /** @return array<string, array{?string, list<string>}> */
    public static function origins(): array
    {
        return [
            'local only' => ['local', ['Fiche locale deux', 'Fiche locale une']],
            'remote only' => ['remote', ['Fiche distante']],
            'both, said so' => ['all', ['Fiche distante', 'Fiche locale deux', 'Fiche locale une']],
            'both, by default' => [null, ['Fiche distante', 'Fiche locale deux', 'Fiche locale une']],
            'unknown value means both' => ['partout', ['Fiche distante', 'Fiche locale deux', 'Fiche locale une']],
            'case does not matter' => ['REMOTE', ['Fiche distante']],
        ];
    }

    /** @param list<string> $expected */
    #[DataProvider('origins')]
    public function testTheOriginOptionFiltersTheEntries(?string $origin, array $expected): void
    {
        $this->assertSame($expected, $this->titles($origin === null ? [] : ['origin' => $origin]));
    }

    public function testTheListActionHonoursTheOrigin(): void
    {
        $html = (string)$this->getWiki()->services->get(Performer::class)->run('entrylist', 'action', [
            'id' => self::FORM_ID,
            'template' => 'liste_liens',
            'origin' => 'local',
        ]);

        $this->assertStringContainsString('Fiche locale une', $html);
        $this->assertStringNotContainsString('Fiche distante', $html);
    }
}
