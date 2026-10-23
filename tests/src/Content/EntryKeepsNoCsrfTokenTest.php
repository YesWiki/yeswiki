<?php

namespace YesWiki\Test\Content;

use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** The anti-CSRF field every POST form carries is not saved into the entry, where `/raw` would publish it. */
class EntryKeepsNoCsrfTokenTest extends YesWikiTestCase
{
    private const FORM_ID = '999961';

    private string $tag = '';

    protected function setUp(): void
    {
        parent::setUp();
        $services = $this->getWiki()->services;
        if ($services->get(AuthenticationService::class)->connectFirstAdmin() === null) {
            $this->markTestSkipped('no admin account in the test wiki');
        }
        $services->get(FormManager::class)->create([
            'id' => self::FORM_ID,
            'label' => 'Entry keeps no csrf token test form',
            'template' => json_encode([['type' => 'texte', 'name' => 'bf_titre', 'label' => 'Titre']]),
            'condition' => '',
        ]);
    }

    protected function tearDown(): void
    {
        $services = $this->getWiki()->services;
        if ($this->tag !== '' && $services->get(EntryManager::class)->isEntry($this->tag)) {
            $services->get(EntryManager::class)->delete($this->tag, true);
        }
        $services->get(FormManager::class)->delete(self::FORM_ID);
        $services->get(AuthenticationService::class)->logout();
        parent::tearDown();
    }

    public function testTheTokenIsLeftOut(): void
    {
        $services = $this->getWiki()->services;
        $entry = $services->get(EntryManager::class)->create(self::FORM_ID, [
            'antispam' => 1,
            'bf_titre' => 'Fiche sans jeton',
            'csrf-token' => 'a-secret-session-token',
        ]);
        $this->tag = (string)$entry['tag'];

        $stored = $services->get(PageManager::class)->getOne($this->tag);
        $this->assertIsArray($stored);
        $this->assertArrayNotHasKey('csrf-token', $stored['body']);
    }
}
