<?php

namespace YesWiki\Test\Bazar\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use YesWiki\Bazar\Service\BazarListService;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Core\Controller\AuthController;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\TripleStore;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * The origin option keeps the entries written on the wiki, those brought from a remote URL, or both.
 */
class BazarListOriginTest extends YesWikiTestCase
{
    private $wiki;
    private $entryManager;
    private $formManager;
    private string $formId;
    private array $tags = [];

    protected function setUp(): void
    {
        $this->wiki = $this->getWiki();
        $GLOBALS['wiki'] = $this->wiki;
        $this->entryManager = $this->wiki->services->get(EntryManager::class);
        $this->formManager = $this->wiki->services->get(FormManager::class);
        $this->wiki->services->get(AuthController::class)->logout();

        $this->formId = $this->formManager->create([
            'bn_label_nature' => 'Origin filter test form',
            'bn_template' => 'texte***bf_titre***Titre***60***255*** *** ***text***1*** *** *** * *** * *** *** *** ***',
            'bn_condition' => '',
        ]);
        foreach (['Fiche locale une', 'Fiche locale deux'] as $title) {
            $this->tags[] = $this->entryManager->create($this->formId, ['antispam' => 1, 'bf_titre' => $title])['id_fiche'];
        }
        $this->tags[] = $this->entryManager->create(
            $this->formId,
            ['antispam' => 1, 'bf_titre' => 'Fiche distante'],
            false,
            'https://mamot.fr/users/Khrys/statuses/42'
        )['id_fiche'];
        foreach ($this->tags as $tag) {
            $this->wiki->services->get(AclService::class)->save($tag, 'read', '*');
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->tags as $tag) {
            if ($this->entryManager->isEntry($tag)) {
                $this->entryManager->delete($tag, true);
            }
            $this->wiki->services->get(PageManager::class)->deleteOrphaned($tag);
            $this->wiki->services->get(AclService::class)->delete($tag);
            $this->wiki->services->get(TripleStore::class)->deleteAll($tag, '');
        }
        $this->formManager->delete($this->formId);
    }

    private function titles(array $options): array
    {
        $entries = $this->wiki->services->get(BazarListService::class)->getEntries($options + ['idtypeannonce' => [$this->formId]]);
        $titles = array_column($entries, 'bf_titre');
        sort($titles);

        return $titles;
    }

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

    #[DataProvider('origins')]
    public function testTheOriginOptionFiltersTheEntries(?string $origin, array $expected)
    {
        $this->assertSame($expected, $this->titles($origin === null ? [] : ['origin' => $origin]));
    }

    public function testTheListActionHonoursTheOrigin()
    {
        $html = $this->wiki->Format('{{bazarliste id="' . $this->formId . '" template="liste_liens" origin="local"}}');

        $this->assertStringContainsString('Fiche locale une', $html);
        $this->assertStringNotContainsString('Fiche distante', $html);
    }
}
