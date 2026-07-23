<?php

namespace YesWiki\Test\Bazar\Service;

use YesWiki\Bazar\Service\ActivityPubService;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\TripleStore;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Regression/acceptance tests for ticket 05 (forms become Content): forms are now `pages`
 * rows typed via a TYPE_URI='form' triple instead of standalone `nature` rows, keep a
 * stable numeric id (bn_id_nature, embedded in `body`) distinct from their renameable
 * `tag`, and get ACLs via `metadata.acls` (ticket 03's mechanism).
 */
class FormManagerContentTest extends YesWikiTestCase
{
    private const FORM_ID = '999906';
    private const OTHER_FORM_ID = '999907';
    private const ENTRY_TAG = 'FormManagerContentTestEntry';

    private function cleanupForm(FormManager $formManager, EntryManager $entryManager, string $formId, ?string $entryTag = null): void
    {
        if ($entryTag && $entryManager->isEntry($entryTag)) {
            $entryManager->delete($entryTag, true);
        }
        if ($formManager->getOne($formId)) {
            $formManager->delete($formId);
        }
    }

    public function testCreateStoresFormAsAPageTypedViaTripleNotANatureRow()
    {
        $wiki = $this->getWiki();
        $formManager = $wiki->services->get(FormManager::class);
        $pageManager = $wiki->services->get(PageManager::class);
        $tripleStore = $wiki->services->get(TripleStore::class);
        $entryManager = $wiki->services->get(EntryManager::class);

        try {
            $formManager->create([
                'bn_id_nature' => self::FORM_ID,
                'bn_label_nature' => 'FormManagerContentTest form',
                'bn_template' => '',
                'bn_condition' => '',
            ]);

            $form = $formManager->getOne(self::FORM_ID);
            $this->assertIsArray($form);
            $tag = $form['tag'];

            // it's a genuine `pages` row ...
            $page = $pageManager->getOne($tag, null, true, true);
            $this->assertIsArray($page);

            // ... typed via the same TYPE_URI-triple convention EntryManager uses for entries
            $typeTriple = $tripleStore->getOne($tag, TripleStore::TYPE_URI, '', '');
            $this->assertSame(FormManager::TRIPLES_FORM_TYPE, $typeTriple);
        } finally {
            $this->cleanupForm($formManager, $entryManager, self::FORM_ID);
        }
    }

    public function testGetOneResolvesByNumericIdOrByTag()
    {
        $wiki = $this->getWiki();
        $formManager = $wiki->services->get(FormManager::class);
        $entryManager = $wiki->services->get(EntryManager::class);

        try {
            $formManager->create([
                'bn_id_nature' => self::FORM_ID,
                'bn_label_nature' => 'FormManagerContentTest form',
                'bn_template' => '',
                'bn_condition' => '',
            ]);

            $byId = $formManager->getOne(self::FORM_ID);
            $this->assertIsArray($byId);

            $byTag = $formManager->getOne($byId['tag']);
            $this->assertIsArray($byTag);
            $this->assertSame(self::FORM_ID, $byTag['bn_id_nature']);
        } finally {
            $this->cleanupForm($formManager, $entryManager, self::FORM_ID);
        }
    }

    public function testCreatingWithACollidingDesiredTagUsesSuggestFreeTag()
    {
        $wiki = $this->getWiki();
        $formManager = $wiki->services->get(FormManager::class);
        $entryManager = $wiki->services->get(EntryManager::class);

        try {
            $formManager->create([
                'bn_id_nature' => self::FORM_ID,
                'bn_label_nature' => 'SameLabel',
                'bn_template' => '',
                'bn_condition' => '',
            ]);
            $formManager->create([
                'bn_id_nature' => self::OTHER_FORM_ID,
                'bn_label_nature' => 'SameLabel',
                'bn_template' => '',
                'bn_condition' => '',
            ]);

            $first = $formManager->getOne(self::FORM_ID);
            $second = $formManager->getOne(self::OTHER_FORM_ID);

            $this->assertNotSame($first['tag'], $second['tag']);
            $this->assertSame('SameLabel', $first['tag']);
            $this->assertSame('SameLabel2', $second['tag']);
        } finally {
            $this->cleanupForm($formManager, $entryManager, self::FORM_ID);
            $this->cleanupForm($formManager, $entryManager, self::OTHER_FORM_ID);
        }
    }

    public function testFormsGetAclsViaMetadataAcls()
    {
        $wiki = $this->getWiki();
        $formManager = $wiki->services->get(FormManager::class);
        $pageManager = $wiki->services->get(PageManager::class);
        $entryManager = $wiki->services->get(EntryManager::class);

        try {
            $formManager->create([
                'bn_id_nature' => self::FORM_ID,
                'bn_label_nature' => 'FormManagerContentTest acl form',
                'bn_template' => '',
                'bn_condition' => '',
            ]);

            $form = $formManager->getOne(self::FORM_ID);
            $metadata = $pageManager->getMetadata($form['tag']);

            $this->assertIsArray($metadata['acls'] ?? null);
            $this->assertSame('@admins', $metadata['acls']['write']);
        } finally {
            $this->cleanupForm($formManager, $entryManager, self::FORM_ID);
        }
    }

    public function testRenamingATagKeepsOldTagResolvableAndDoesNotBreakEntries()
    {
        $wiki = $this->getWiki();
        $formManager = $wiki->services->get(FormManager::class);
        $entryManager = $wiki->services->get(EntryManager::class);

        try {
            $formManager->create([
                'bn_id_nature' => self::FORM_ID,
                'bn_label_nature' => 'OriginalTag',
                'bn_template' => '',
                'bn_condition' => '',
            ]);
            $oldTag = $formManager->getOne(self::FORM_ID)['tag'];

            $entry = $entryManager->create(self::FORM_ID, [
                'antispam' => 1,
                'bf_titre' => 'Test entry',
                'id_fiche' => self::ENTRY_TAG,
            ]);
            $this->assertIsArray($entry);

            $newTag = $formManager->renameTag(self::FORM_ID, 'RenamedTag');
            $this->assertSame('RenamedTag', $newTag);

            // stable numeric id resolves to the new tag
            $byId = $formManager->getOne(self::FORM_ID);
            $this->assertSame($newTag, $byId['tag']);

            // the OLD tag still resolves (via the former-tag alias triple)
            $byOldTag = $formManager->getOne($oldTag);
            $this->assertIsArray($byOldTag);
            $this->assertSame($newTag, $byOldTag['tag']);

            // the entry (keyed off the stable numeric id, not the tag) is unaffected
            $this->assertTrue($entryManager->isEntry(self::ENTRY_TAG));
            $fetchedEntry = $entryManager->getOne(self::ENTRY_TAG);
            $this->assertSame(self::FORM_ID, $fetchedEntry['id_typeannonce']);
        } finally {
            $this->cleanupForm($formManager, $entryManager, self::FORM_ID, self::ENTRY_TAG);
        }
    }

    public function testUpdatePreservesFieldsNotPresentInTheSubmission()
    {
        $wiki = $this->getWiki();
        $formManager = $wiki->services->get(FormManager::class);
        $entryManager = $wiki->services->get(EntryManager::class);

        try {
            $formManager->create([
                'bn_id_nature' => self::FORM_ID,
                'bn_label_nature' => 'Original label',
                'bn_template' => '',
                'bn_condition' => '',
                'bn_sem_context' => 'https://www.w3.org/ns/activitystreams',
                'bn_sem_type' => 'Event',
            ]);

            // simulate the admin edit form's submission, which doesn't carry
            // bn_sem_context/bn_sem_type (they aren't exposed in that UI)
            $formManager->update([
                'bn_id_nature' => self::FORM_ID,
                'bn_label_nature' => 'Updated label',
                'bn_template' => '',
                'bn_condition' => '',
            ]);

            $form = $formManager->getOne(self::FORM_ID);
            $this->assertSame('Updated label', $form['bn_label_nature']);
            $this->assertSame('https://www.w3.org/ns/activitystreams', $form['bn_sem_context']);
            $this->assertSame('Event', $form['bn_sem_type']);
        } finally {
            $this->cleanupForm($formManager, $entryManager, self::FORM_ID);
        }
    }

    public function testActivityPubCredentialsSurviveTheRoundTripAndActorUriUsesTheStableId()
    {
        $wiki = $this->getWiki();
        $formManager = $wiki->services->get(FormManager::class);
        $activityPubService = $wiki->services->get(ActivityPubService::class);
        $entryManager = $wiki->services->get(EntryManager::class);

        try {
            $formManager->create([
                'bn_id_nature' => self::FORM_ID,
                'bn_label_nature' => 'FormManagerContentTest activitypub form',
                'bn_template' => '',
                'bn_condition' => '',
                'bn_activitypub_enable' => '1',
                'bn_activitypub_username' => 'someactor',
            ]);

            $form = $formManager->getOne(self::FORM_ID);
            $this->assertTrue($activityPubService->isEnabled($form));
            $this->assertSame('someactor', $form['bn_activitypub_username']);
            $this->assertNotEmpty($form['bn_activitypub_private_key']);
            $this->assertNotEmpty($form['bn_activitypub_public_key']);

            // actor URIs are keyed off the stable numeric id -- unaffected by any future rename
            $this->assertStringEndsWith('/actors/' . self::FORM_ID, $activityPubService->getFormActorUri($form));

            // editing the form again (without touching activitypub fields) keeps the same keypair
            $formManager->update([
                'bn_id_nature' => self::FORM_ID,
                'bn_label_nature' => 'FormManagerContentTest activitypub form updated',
                'bn_template' => '',
                'bn_condition' => '',
                'bn_activitypub_enable' => '1',
                'bn_activitypub_username' => 'someactor',
            ]);
            $updatedForm = $formManager->getOne(self::FORM_ID);
            $this->assertSame($form['bn_activitypub_private_key'], $updatedForm['bn_activitypub_private_key']);
        } finally {
            $this->cleanupForm($formManager, $entryManager, self::FORM_ID);
        }
    }

    public function testDeleteRemovesTheFormAndItsEntries()
    {
        $wiki = $this->getWiki();
        $formManager = $wiki->services->get(FormManager::class);
        $entryManager = $wiki->services->get(EntryManager::class);

        $formManager->create([
            'bn_id_nature' => self::FORM_ID,
            'bn_label_nature' => 'FormManagerContentTest delete form',
            'bn_template' => '',
            'bn_condition' => '',
        ]);
        $entryManager->create(self::FORM_ID, [
            'antispam' => 1,
            'bf_titre' => 'Test entry',
            'id_fiche' => self::ENTRY_TAG,
        ]);

        $formManager->delete(self::FORM_ID);

        $this->assertNull($formManager->getOne(self::FORM_ID));
        $this->assertFalse($entryManager->isEntry(self::ENTRY_TAG));
    }
}
