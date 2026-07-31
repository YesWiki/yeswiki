<?php

namespace YesWiki\Test\Core\Service;

use YesWiki\Content\Service\ActivityPubService;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Content\Service\TripleStore;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Regression/acceptance tests for ticket 05 (forms become Content): forms are now `pages`
 * rows typed via a TYPE_URI='form' triple instead of standalone `nature` rows, keep a
 * stable numeric id (id, embedded in `body`) distinct from their renameable
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
                'id' => self::FORM_ID,
                'label' => 'FormManagerContentTest form',
                'template' => '',
                'condition' => '',
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
                'id' => self::FORM_ID,
                'label' => 'FormManagerContentTest form',
                'template' => '',
                'condition' => '',
            ]);

            $byId = $formManager->getOne(self::FORM_ID);
            $this->assertIsArray($byId);

            $byTag = $formManager->getOne($byId['tag']);
            $this->assertIsArray($byTag);
            $this->assertSame(self::FORM_ID, $byTag['id']);
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
                'id' => self::FORM_ID,
                'label' => 'SameLabel',
                'template' => '',
                'condition' => '',
            ]);
            $formManager->create([
                'id' => self::OTHER_FORM_ID,
                'label' => 'SameLabel',
                'template' => '',
                'condition' => '',
            ]);

            $first = $formManager->getOne(self::FORM_ID);
            $second = $formManager->getOne(self::OTHER_FORM_ID);

            // new form tags are lowercase slugs, collisions suffixed -2 (ADR-0010)
            $this->assertNotSame($first['tag'], $second['tag']);
            $this->assertSame('samelabel', $first['tag']);
            $this->assertSame('samelabel-2', $second['tag']);
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
                'id' => self::FORM_ID,
                'label' => 'FormManagerContentTest acl form',
                'template' => '',
                'condition' => '',
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
                'id' => self::FORM_ID,
                'label' => 'OriginalTag',
                'template' => '',
                'condition' => '',
            ]);
            $oldTag = $formManager->getOne(self::FORM_ID)['tag'];

            $entry = $entryManager->create(self::FORM_ID, [
                'antispam' => 1,
                'bf_titre' => 'Test entry',
                'tag' => self::ENTRY_TAG,
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
            $this->assertSame(self::FORM_ID, $fetchedEntry['form_id']);
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
                'id' => self::FORM_ID,
                'label' => 'Original label',
                'template' => '',
                'condition' => '',
                'sem_context' => 'https://www.w3.org/ns/activitystreams',
                'sem_type' => 'Event',
            ]);

            // simulate the admin edit form's submission, which doesn't carry
            // sem_context/sem_type (they aren't exposed in that UI)
            $formManager->update([
                'id' => self::FORM_ID,
                'label' => 'Updated label',
                'template' => '',
                'condition' => '',
            ]);

            $form = $formManager->getOne(self::FORM_ID);
            $this->assertSame('Updated label', $form['label']);
            $this->assertSame('https://www.w3.org/ns/activitystreams', $form['sem_context']);
            $this->assertSame('Event', $form['sem_type']);
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
                'id' => self::FORM_ID,
                'label' => 'FormManagerContentTest activitypub form',
                'template' => '',
                'condition' => '',
                'activitypub_enable' => '1',
                'activitypub_username' => 'someactor',
            ]);

            $form = $formManager->getOne(self::FORM_ID);
            $this->assertTrue($activityPubService->isEnabled($form));
            $this->assertSame('someactor', $form['activitypub_username']);
            $this->assertNotEmpty($form['activitypub_private_key']);
            $this->assertNotEmpty($form['activitypub_public_key']);

            // actor URIs are keyed off the stable numeric id -- unaffected by any future rename
            $this->assertStringEndsWith('/actors/' . self::FORM_ID, $activityPubService->getFormActorUri($form));

            // editing the form again (without touching activitypub fields) keeps the same keypair
            $formManager->update([
                'id' => self::FORM_ID,
                'label' => 'FormManagerContentTest activitypub form updated',
                'template' => '',
                'condition' => '',
                'activitypub_enable' => '1',
                'activitypub_username' => 'someactor',
            ]);
            $updatedForm = $formManager->getOne(self::FORM_ID);
            $this->assertSame($form['activitypub_private_key'], $updatedForm['activitypub_private_key']);
        } finally {
            $this->cleanupForm($formManager, $entryManager, self::FORM_ID);
        }
    }

    public function testSetActivitypubKeypairRestoresAPreviouslyPublishedKeyAfterCreate()
    {
        // regression test for MigrateNatureToPages: create() has no way to distinguish "a
        // brand-new form" from "an existing, already-federating form being migrated" and
        // always generates a fresh keypair when ActivityPub is enabled -- the migration
        // must stash the real keypair and restore it via setActivitypubKeypair() afterwards,
        // or an upgrading install silently breaks federation for that form (the actor URL
        // still resolves, but the key behind it changes, so existing followers can no
        // longer verify signed activity).
        $wiki = $this->getWiki();
        $formManager = $wiki->services->get(FormManager::class);
        $entryManager = $wiki->services->get(EntryManager::class);

        try {
            $formManager->create([
                'id' => self::FORM_ID,
                'label' => 'FormManagerContentTest activitypub restore form',
                'template' => '',
                'condition' => '',
                'activitypub_enable' => '1',
                'activitypub_username' => 'preexisting',
            ]);
            $freshlyGeneratedKey = $formManager->getOne(self::FORM_ID)['activitypub_private_key'];
            $this->assertNotSame('PREVIOUSLY-PUBLISHED-PRIVATE-KEY', $freshlyGeneratedKey);

            $formManager->setActivitypubKeypair(self::FORM_ID, 'PREVIOUSLY-PUBLISHED-PRIVATE-KEY', 'PREVIOUSLY-PUBLISHED-PUBLIC-KEY');

            $form = $formManager->getOne(self::FORM_ID);
            $this->assertSame('PREVIOUSLY-PUBLISHED-PRIVATE-KEY', $form['activitypub_private_key']);
            $this->assertSame('PREVIOUSLY-PUBLISHED-PUBLIC-KEY', $form['activitypub_public_key']);
            // enabled/username, set by create() and untouched by setActivitypubKeypair(), survive
            $this->assertSame('1', $form['activitypub_enable']);
            $this->assertSame('preexisting', $form['activitypub_username']);
        } finally {
            $this->cleanupForm($formManager, $entryManager, self::FORM_ID);
        }
    }

    public function testBazForFormsAndListsIdsNoLongerQueriesTheDroppedNatureTable()
    {
        // regression test: formAndListIds() (src/bazar.functions.php, relocated
        // from tools/bazar/libs/bazar.fonct.php by ticket 24; used by FormController and
        // aceditor's ActionsBuilderService) used to run raw SQL
        // against the `nature` table directly, bypassing FormManager -- now dropped, that
        // would throw. It must go through FormManager::getAll() like everything else.
        $wiki = $this->getWiki();
        $formManager = $wiki->services->get(FormManager::class);
        $entryManager = $wiki->services->get(EntryManager::class);

        // formAndListIds() reads $GLOBALS['wiki'], only populated by the
        // production HTTP bootstrap outside a real request -- see EntryManagerTest's
        // docblock for the same pre-existing characteristic elsewhere in this tool
        $GLOBALS['yeswikiServices'] = $wiki->services;

        try {
            $formManager->create([
                'id' => self::FORM_ID,
                'label' => 'FormManagerContentTest formAndListIds form',
                'template' => '',
                'condition' => '',
            ]);

            $result = formAndListIds();

            $this->assertArrayHasKey('forms', $result);
            $this->assertArrayHasKey('lists', $result);
            $this->assertSame('FormManagerContentTest formAndListIds form', $result['forms'][self::FORM_ID] ?? null);
        } finally {
            unset($GLOBALS['wiki']);
            $this->cleanupForm($formManager, $entryManager, self::FORM_ID);
        }
    }

    public function testDeleteRemovesTheFormAndItsEntries()
    {
        $wiki = $this->getWiki();
        $formManager = $wiki->services->get(FormManager::class);
        $entryManager = $wiki->services->get(EntryManager::class);

        $formManager->create([
            'id' => self::FORM_ID,
            'label' => 'FormManagerContentTest delete form',
            'template' => '',
            'condition' => '',
        ]);
        $entryManager->create(self::FORM_ID, [
            'antispam' => 1,
            'bf_titre' => 'Test entry',
            'tag' => self::ENTRY_TAG,
        ]);

        $formManager->delete(self::FORM_ID);

        $this->assertNull($formManager->getOne(self::FORM_ID));
        $this->assertFalse($entryManager->isEntry(self::ENTRY_TAG));
    }
}
