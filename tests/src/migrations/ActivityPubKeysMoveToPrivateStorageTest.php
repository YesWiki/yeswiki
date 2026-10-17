<?php

namespace YesWiki\Test\Core\Migrations;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Federation\Service\ActivityPubKeyStore;
use YesWiki\Files\Service\Storage;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\KeyPairGenerator;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** A form's key leaves its page metadata for the protected key store, unchanged. */
class ActivityPubKeysMoveToPrivateStorageTest extends YesWikiTestCase
{
    private const FORM_ID = '999908';

    public static function setUpBeforeClass(): void
    {
        self::getWiki();
        require_once 'src/migrations/20261017120000_ActivityPubKeysMoveToPrivateStorage.php';
    }

    public function testTheKeyMovesAndTheMetadataForgetsIt(): void
    {
        $services = $this->getWiki()->services;
        $formManager = $services->get(FormManager::class);
        $pageManager = $services->get(PageManager::class);
        $storage = $services->get(Storage::class);
        $keyFile = ActivityPubKeyStore::DIRECTORY . '/' . self::FORM_ID . '.pem';
        [$privateKey, $publicKey] = (new KeyPairGenerator())->generate();

        try {
            $formManager->create(['id' => self::FORM_ID, 'label' => 'ActivityPub keys migration test', 'template' => '', 'activitypub_enable' => '1', 'activitypub_username' => 'keysmigration']);
            $form = $formManager->getOne(self::FORM_ID);
            $this->assertNotNull($form);
            $tag = $form['tag'];
            $page = $pageManager->getOne($tag, null, false, true);
            $this->assertNotNull($page);
            $pageManager->setMetadata($tag, ['activitypub' => $page['metadatas']['activitypub'] + ['private_key' => $privateKey, 'public_key' => $publicKey]]);

            $this->runMigration();
            $this->runMigration();

            $this->assertSame($privateKey, $storage->read($keyFile));
            $this->assertSame($publicKey, $services->get(ActivityPubKeyStore::class)->publicKey(self::FORM_ID));
            $page = $pageManager->getOne($tag, null, false, true);
            $this->assertNotNull($page);
            $metadata = $page['metadatas']['activitypub'];
            $this->assertArrayNotHasKey('private_key', $metadata);
            $this->assertArrayNotHasKey('public_key', $metadata);
            $this->assertSame('keysmigration', $metadata['username']);
        } finally {
            if ($formManager->getOne(self::FORM_ID)) {
                $formManager->delete(self::FORM_ID);
            }
            if ($storage->exists($keyFile)) {
                $storage->delete($keyFile);
            }
        }
    }

    private function runMigration(): void
    {
        $services = $this->getWiki()->services;
        $migration = new \ActivityPubKeysMoveToPrivateStorage();
        $migration->setServices($services);
        $migration->setDbService($services->get(DbService::class));
        $migration->setParams($services->get(ParameterBagInterface::class));
        $migration->run();
    }
}
