<?php

namespace YesWiki\Test\Bazar\Service;

use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Core\Controller\AuthController;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * A field the user may not write keeps its stored value, whatever the request carries.
 */
class RestrictedFieldsTest extends YesWikiTestCase
{
    private string $formId;
    private const STORED_GEO = ['latitude' => '45.1', 'longitude' => '5.7', 'geometries' => ''];

    private $wiki;
    private $entryManager;
    private $formManager;
    private $pageManager;
    private $aclService;
    private $createdTags = [];

    protected function setUp(): void
    {
        $this->wiki = $this->getWiki();
        $GLOBALS['wiki'] = $this->wiki;
        $this->entryManager = $this->wiki->services->get(EntryManager::class);
        $this->formManager = $this->wiki->services->get(FormManager::class);
        $this->pageManager = $this->wiki->services->get(PageManager::class);
        $this->aclService = $this->wiki->services->get(AclService::class);

        $this->formId = $this->formManager->create([
            'bn_label_nature' => 'Restricted fields test form',
            'bn_template' => implode("\n", [
                self::fieldLine('texte', 'bf_titre'),
                self::fieldLine('texte', 'bf_admin_only', '', '', '@admins'),
                self::fieldLine('texte', 'bf_admin_default', 'fallback', '', '@admins'),
                self::fieldLine('texte', 'bf_write_only', '', '@admins', '*'),
                self::fieldLine('map', 'bf_geolocation', '', '', '@admins'),
            ]),
            'bn_condition' => '',
        ]);

        $this->wiki->services->get(AuthController::class)->logout();
    }

    protected function tearDown(): void
    {
        unset($_REQUEST['bf_admin_only']);
        foreach ($this->createdTags as $tag) {
            if ($this->entryManager->isEntry($tag)) {
                $this->entryManager->delete($tag, true);
            }
            $this->pageManager->deleteOrphaned($tag);
            $this->aclService->delete($tag);
        }
        $this->createdTags = [];
        $this->formManager->delete($this->formId);
    }

    private static function fieldLine(string $type, string $name, string $default = '', string $read = '', string $write = ''): string
    {
        $slots = array_fill(0, 16, ' ');
        $slots[0] = $type;
        $slots[1] = $name;
        $slots[2] = $name;
        $slots[5] = $default === '' ? ' ' : $default;
        $slots[11] = $read === '' ? ' ' : $read;
        $slots[12] = $write === '' ? ' ' : $write;

        return implode('***', $slots) . '***';
    }

    private function createEntry(string $title, array $data = []): string
    {
        $entry = $this->entryManager->create($this->formId, array_merge(['antispam' => 1, 'bf_titre' => $title], $data));
        $this->createdTags[] = $entry['id_fiche'];
        $this->aclService->save($entry['id_fiche'], 'write', '*');

        return $entry['id_fiche'];
    }

    private function storeRaw(string $tag, array $values): void
    {
        $stored = $this->entryManager->getOne($tag, false, null, false, true);
        $this->pageManager->save($tag, json_encode(array_merge($stored, $values)), '', true);
    }

    private function stored(string $tag): array
    {
        return json_decode($this->pageManager->getOne($tag, null, false, true)['body'], true);
    }

    public function testCreationIgnoresAPostedValueForAFieldTheUserCannotWrite()
    {
        $tag = $this->createEntry('Restricted posted on create', ['bf_admin_only' => 'injected']);

        $this->assertSame('', $this->stored($tag)['bf_admin_only'] ?? '');
    }

    public function testCreationIgnoresARequestValueForAFieldTheUserCannotWrite()
    {
        $_REQUEST['bf_admin_only'] = 'injected';
        $tag = $this->createEntry('Restricted request on create');

        $this->assertSame('', $this->stored($tag)['bf_admin_only'] ?? '');
    }

    public function testCreationGivesARestrictedFieldItsDefault()
    {
        $tag = $this->createEntry('Restricted default on create', ['bf_admin_default' => 'injected']);

        $this->assertSame('fallback', $this->stored($tag)['bf_admin_default']);
    }

    public function testUpdateKeepsTheStoredValueOfAFieldTheUserCannotWrite()
    {
        $tag = $this->createEntry('Restricted posted on update');
        $this->storeRaw($tag, ['bf_admin_only' => 'original']);

        $this->entryManager->update($tag, ['bf_titre' => 'Restricted posted on update', 'bf_admin_only' => 'injected']);

        $this->assertSame('original', $this->stored($tag)['bf_admin_only']);
    }

    public function testUpdateCannotFillARestrictedFieldThatWasEmpty()
    {
        $tag = $this->createEntry('Restricted empty on update');

        $this->entryManager->update($tag, ['bf_titre' => 'Restricted empty on update', 'bf_admin_only' => 'injected']);

        $this->assertSame('', $this->stored($tag)['bf_admin_only'] ?? '');
    }

    public function testAFieldTheUserCannotReadIsNotWipedByAnEdit()
    {
        $tag = $this->createEntry('Write only field');
        $this->storeRaw($tag, ['bf_write_only' => 'secret note']);

        $this->entryManager->update($tag, ['bf_titre' => 'Write only field', 'bf_write_only' => '']);

        $this->assertSame('secret note', $this->stored($tag)['bf_write_only']);
    }

    public function testAFieldTheUserCannotReadHasNoInputWhenEditing()
    {
        $tag = $this->createEntry('Write only input');
        $form = $this->formManager->getOne($this->formId);
        $field = current(array_filter($form['prepared'], function ($field) {
            return $field->getPropertyName() === 'bf_write_only';
        }));

        $this->assertSame('', $field->renderInputIfPermitted(['id_fiche' => $tag]));
        $this->assertNotSame('', $field->renderInputIfPermitted([]));
    }

    public function testARestrictedMapFieldKeepsItsStoredCoordinates()
    {
        $tag = $this->createEntry('Restricted map');
        $this->storeRaw($tag, ['bf_geolocation' => self::STORED_GEO]);

        $this->entryManager->update($tag, ['bf_titre' => 'Restricted map', 'bf_geolocation' => ['latitude' => '1', 'longitude' => '2']]);

        $this->assertSame(self::STORED_GEO, $this->stored($tag)['bf_geolocation']);
    }
}
