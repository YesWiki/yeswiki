<?php

namespace YesWiki\Test\Core\Service;

use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Identity\Service\AclService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Ticket 27 (ADR-0010): form-level behavior lives in form properties -- the entry pipeline computes `title` from entry_title_template, generates slugged tags, and stamps entry ACLs from entry_*_access.
 */
class FormPropertiesTest extends YesWikiTestCase
{
    private const FORM_ID = '999909';

    /**
     * @param array<string, mixed> $extra
     */
    private function makeForm(FormManager $formManager, array $extra = []): void
    {
        $formManager->create($extra + [
            'id' => self::FORM_ID,
            'label' => 'FormPropertiesTest form',
            'template' => '[{"type": "texte", "name": "bf_titre", "label": "Titre", "required": "1"}]',
        ]);
    }

    /**
     * @param list<string> $entryTags
     */
    private function cleanup(FormManager $formManager, EntryManager $entryManager, array $entryTags = []): void
    {
        foreach ($entryTags as $tag) {
            if ($entryManager->isEntry($tag)) {
                $entryManager->delete($tag, true);
            }
        }
        if ($formManager->getOne(self::FORM_ID)) {
            $formManager->delete(self::FORM_ID);
        }
    }

    public function testEntryGetsComputedTitleAndSluggedTag(): void
    {
        $wiki = $this->getWiki();
        $formManager = $wiki->services->get(FormManager::class);
        $entryManager = $wiki->services->get(EntryManager::class);
        $created = [];

        try {
            $this->makeForm($formManager, ['entry_title_template' => '{{bf_titre}}']);

            $entry = $entryManager->create(self::FORM_ID, [
                'antispam' => 1,
                'bf_titre' => "L'Été à Nantes",
            ]);
            $created[] = $entry['tag'];

            $this->assertSame("L'Été à Nantes", $entry['title']);
            $this->assertSame('l-ete-a-nantes', $entry['tag']);

            $second = $entryManager->create(self::FORM_ID, [
                'antispam' => 1,
                'bf_titre' => "L'Été à Nantes",
            ]);
            $created[] = $second['tag'];
            $this->assertSame('l-ete-a-nantes-2', $second['tag']);

            $stored = $entryManager->getOne($entry['tag']);
            $this->assertIsArray($stored, 'the entry just created must be readable back');
            $this->assertArrayNotHasKey('antispam', $stored);
            $this->assertSame(self::FORM_ID, $stored['form_id']);
            $this->assertArrayNotHasKey('id_fiche', $stored);
        } finally {
            $this->cleanup($formManager, $entryManager, $created);
        }
    }

    public function testCompositeTitleTemplate(): void
    {
        $wiki = $this->getWiki();
        $formManager = $wiki->services->get(FormManager::class);
        $entryManager = $wiki->services->get(EntryManager::class);
        $created = [];

        try {
            $formManager->create([
                'id' => self::FORM_ID,
                'label' => 'FormPropertiesTest composite',
                'template' => '[{"type": "texte", "name": "bf_nom", "label": "Nom", "required": "1"},'
                    . '{"type": "texte", "name": "bf_prenom", "label": "Prénom"}]',
                'entry_title_template' => '{{bf_prenom}} {{bf_nom}}',
            ]);

            $entry = $entryManager->create(self::FORM_ID, [
                'antispam' => 1,
                'bf_nom' => 'Dupont',
                'bf_prenom' => 'Jean',
            ]);
            $created[] = $entry['tag'];

            $this->assertSame('Jean Dupont', $entry['title']);
            $this->assertSame('jean-dupont', $entry['tag']);
        } finally {
            $this->cleanup($formManager, $entryManager, $created);
        }
    }

    public function testEntryAclsStampedFromFormProperties(): void
    {
        $wiki = $this->getWiki();
        $formManager = $wiki->services->get(FormManager::class);
        $entryManager = $wiki->services->get(EntryManager::class);
        $aclService = $wiki->services->get(AclService::class);
        $created = [];

        try {
            $this->makeForm($formManager, [
                'entry_title_template' => '{{bf_titre}}',
                'entry_read_access' => '*',
                'entry_write_access' => '@admins',
                'entry_comment_access' => 'comments-closed',
            ]);

            $entry = $entryManager->create(self::FORM_ID, [
                'antispam' => 1,
                'bf_titre' => 'Acl stamped entry',
            ]);
            $created[] = $entry['tag'];

            $this->assertSame('*', $aclService->load($entry['tag'], 'read', false)['list'] ?? null);
            $this->assertSame('@admins', $aclService->load($entry['tag'], 'write', false)['list'] ?? null);
            $this->assertSame('comments-closed', $aclService->load($entry['tag'], 'comment', false)['list'] ?? null);
        } finally {
            $this->cleanup($formManager, $entryManager, $created);
        }
    }

    public function testLegacyEntryBodyKeysAreAliasedOnRead(): void
    {
        $wiki = $this->getWiki();
        $entryManager = $wiki->services->get(EntryManager::class);

        $data = $entryManager->decode([
            'id_fiche' => 'OldEntry',
            'id_typeannonce' => '42',
            'bf_titre' => 'Old title',
            'date_creation_fiche' => '2020-01-01 00:00:00',
            'statut_fiche' => '1',
        ]);

        $this->assertIsArray($data);
        $this->assertSame('OldEntry', $data['tag']);
        $this->assertSame('42', $data['form_id']);
        $this->assertSame('Old title', $data['title']);
        $this->assertSame('Old title', $data['bf_titre']);
        $this->assertSame('2020-01-01 00:00:00', $data['created_at']);
        $this->assertSame('1', $data['status']);
        $this->assertArrayNotHasKey('id_fiche', $data);
    }
}
