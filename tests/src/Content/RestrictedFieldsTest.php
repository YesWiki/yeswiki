<?php

namespace YesWiki\Test\Content;

use YesWiki\Content\Field\BazarField;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Identity\Service\AclService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Restricted fields keep their stored value. */
class RestrictedFieldsTest extends YesWikiTestCase
{
    private const FORM_ID = '999971';
    private const STORED_GEO = ['latitude' => '45.1', 'longitude' => '5.7', 'geometries' => ''];

    private EntryManager $entryManager;
    private FormManager $formManager;
    private PageManager $pageManager;
    private AclService $aclService;
    /** @var list<string> */
    private array $createdTags = [];

    protected function setUp(): void
    {
        parent::setUp();
        $wiki = $this->getWiki();
        $this->entryManager = $wiki->services->get(EntryManager::class);
        $this->formManager = $wiki->services->get(FormManager::class);
        $this->pageManager = $wiki->services->get(PageManager::class);
        $this->aclService = $wiki->services->get(AclService::class);

        $this->formManager->create([
            'id' => self::FORM_ID,
            'label' => 'Restricted fields test form',
            'entry_title_template' => '{{bf_titre}}',
            'template' => implode("\n", [
                self::fieldLine('texte', 'bf_titre'),
                self::fieldLine('texte', 'bf_admin_only', '', '', '@admins'),
                self::fieldLine('texte', 'bf_admin_default', 'fallback', '', '@admins'),
                self::fieldLine('texte', 'bf_write_only', '', '@admins', '*'),
                self::fieldLine('map', 'bf_geolocation', '', '', '@admins'),
            ]),
        ]);

        unset($_SESSION['user']);
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
        $this->formManager->delete(self::FORM_ID);
        parent::tearDown();
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

    /**
     * @param array<string, mixed> $data
     */
    private function createEntry(string $title, array $data = []): string
    {
        $entry = $this->entryManager->create(self::FORM_ID, array_merge(['antispam' => 1, 'bf_titre' => $title], $data));
        $this->createdTags[] = $entry['tag'];
        $this->aclService->save($entry['tag'], 'write', '*');

        return $entry['tag'];
    }

    /**
     * @param array<string, mixed> $values
     */
    private function storeRaw(string $tag, array $values): void
    {
        $this->pageManager->save($tag, array_merge($this->stored($tag), $values), '', true);
    }

    /**
     * @return array<string, mixed>
     */
    private function stored(string $tag): array
    {
        $body = $this->pageManager->getOne($tag, null, false, true)['body'] ?? [];

        return is_array($body) ? $body : json_decode((string)$body, true);
    }

    public function testCreationIgnoresAPostedValueForAFieldTheUserCannotWrite(): void
    {
        $tag = $this->createEntry('Restricted posted on create', ['bf_admin_only' => 'injected']);

        $this->assertSame('', $this->stored($tag)['bf_admin_only'] ?? '');
    }

    public function testCreationIgnoresARequestValueForAFieldTheUserCannotWrite(): void
    {
        $_REQUEST['bf_admin_only'] = 'injected';
        $tag = $this->createEntry('Restricted request on create');

        $this->assertSame('', $this->stored($tag)['bf_admin_only'] ?? '');
    }

    public function testCreationGivesARestrictedFieldItsDefault(): void
    {
        $tag = $this->createEntry('Restricted default on create', ['bf_admin_default' => 'injected']);

        $this->assertSame('fallback', $this->stored($tag)['bf_admin_default']);
    }

    public function testUpdateKeepsTheStoredValueOfAFieldTheUserCannotWrite(): void
    {
        $tag = $this->createEntry('Restricted posted on update');
        $this->storeRaw($tag, ['bf_admin_only' => 'original']);

        $this->entryManager->update($tag, ['antispam' => 1, 'bf_titre' => 'Restricted posted on update', 'bf_admin_only' => 'injected']);

        $this->assertSame('original', $this->stored($tag)['bf_admin_only']);
    }

    public function testUpdateCannotFillARestrictedFieldThatWasEmpty(): void
    {
        $tag = $this->createEntry('Restricted empty on update');

        $this->entryManager->update($tag, ['antispam' => 1, 'bf_titre' => 'Restricted empty on update', 'bf_admin_only' => 'injected']);

        $this->assertSame('', $this->stored($tag)['bf_admin_only'] ?? '');
    }

    public function testAFieldTheUserCannotReadIsNotWipedByAnEdit(): void
    {
        $tag = $this->createEntry('Write only field');
        $this->storeRaw($tag, ['bf_write_only' => 'secret note']);

        $this->entryManager->update($tag, ['antispam' => 1, 'bf_titre' => 'Write only field', 'bf_write_only' => '']);

        $this->assertSame('secret note', $this->stored($tag)['bf_write_only']);
    }

    public function testAFieldTheUserCannotReadHasNoInputWhenEditing(): void
    {
        $tag = $this->createEntry('Write only input');
        $form = $this->formManager->getOne(self::FORM_ID);
        $this->assertNotNull($form);
        $field = current(array_filter($form['prepared'], function ($field) {
            return $field instanceof BazarField && $field->getPropertyName() === 'bf_write_only';
        }));

        $this->assertSame('', $field->renderInputIfPermitted(['tag' => $tag]));
        $this->assertNotSame('', $field->renderInputIfPermitted([]));
    }

    public function testARestrictedMapFieldKeepsItsStoredCoordinates(): void
    {
        $tag = $this->createEntry('Restricted map');
        $this->storeRaw($tag, ['bf_geolocation' => self::STORED_GEO]);

        $this->entryManager->update($tag, ['antispam' => 1, 'bf_titre' => 'Restricted map', 'bf_geolocation' => ['latitude' => '1', 'longitude' => '2']]);

        $this->assertSame(self::STORED_GEO, $this->stored($tag)['bf_geolocation']);
    }
}
