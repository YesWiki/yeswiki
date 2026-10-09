<?php

namespace YesWiki\Test\Content;

use YesWiki\Content\Exception\EntryValidationException;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Identity\Service\AclService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Required fields are enforced on save without locking entries stored before they were filled. */
class RequiredFieldsTest extends YesWikiTestCase
{
    private const FORM_ID = '999972';

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
            'label' => 'Required fields test form',
            'entry_title_template' => '{{bf_titre}}',
            'template' => implode("\n", [
                self::fieldLine('texte', 'bf_titre'),
                self::fieldLine('champs_mail', 'bf_mail', true),
            ]),
        ]);

        unset($_SESSION['user']);
    }

    protected function tearDown(): void
    {
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

    private static function fieldLine(string $type, string $name, bool $required = false): string
    {
        $slots = array_fill(0, 16, ' ');
        $slots[0] = $type;
        $slots[1] = $name;
        $slots[2] = $name;
        $slots[8] = $required ? '1' : '0';

        return implode('***', $slots) . '***';
    }

    private function createEntry(string $title, string $mail): string
    {
        $entry = $this->entryManager->create(self::FORM_ID, ['bf_titre' => $title, 'bf_mail' => $mail]);
        $this->createdTags[] = $entry['tag'];
        $this->aclService->save($entry['tag'], 'write', '*');

        return $entry['tag'];
    }

    /** @return array<string, mixed> */
    private function stored(string $tag): array
    {
        $body = $this->pageManager->getOne($tag, null, false, true)['body'] ?? [];

        return is_array($body) ? $body : json_decode((string)$body, true);
    }

    public function testCreationRefusesAnEmptyRequiredField(): void
    {
        $this->expectException(EntryValidationException::class);

        $this->entryManager->create(self::FORM_ID, ['bf_titre' => 'Required missing on create', 'bf_mail' => '']);
    }

    public function testUpdateRefusesEmptyingARequiredField(): void
    {
        $tag = $this->createEntry('Required emptied', 'someone@example.org');

        $this->expectException(EntryValidationException::class);

        $this->entryManager->update($tag, ['bf_titre' => 'Required emptied', 'bf_mail' => '']);
    }

    public function testUpdateAcceptsARequiredFieldThatWasAlreadyEmpty(): void
    {
        $tag = $this->createEntry('Required already empty', 'someone@example.org');
        $this->pageManager->save($tag, array_merge($this->stored($tag), ['bf_mail' => '']), '', true);

        $this->entryManager->update($tag, ['bf_titre' => 'Required already empty, edited', 'bf_mail' => '']);

        $this->assertSame('Required already empty, edited', $this->stored($tag)['bf_titre']);
    }
}
