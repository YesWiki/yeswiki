<?php

namespace YesWiki\Test\Content;

use YesWiki\Content\Entity\ContentTypeSchema;
use YesWiki\Content\Field\BazarField;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** A listefiche, radiofiche or checkboxfiche shows its chosen entries through an entry list template, and says how a click opens them. */
class EntryFieldDisplayTest extends YesWikiTestCase
{
    private const FORM_ID = '999951';

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
            'label' => 'Entry field display test form',
            'template' => json_encode([['type' => 'texte', 'name' => 'bf_titre', 'label' => 'Titre']]),
            'condition' => '',
        ]);
        foreach (['Fiche liée une', 'Fiche liée deux', 'Fiche pas choisie'] as $title) {
            $entry = $services->get(EntryManager::class)->create(self::FORM_ID, ['antispam' => 1, 'bf_titre' => $title]);
            $this->tags[] = (string)$entry['tag'];
        }
        $services->get(AuthenticationService::class)->logout();
    }

    protected function tearDown(): void
    {
        $services = $this->getWiki()->services;
        $entryManager = $services->get(EntryManager::class);
        foreach ($this->tags as $tag) {
            if ($entryManager->isEntry($tag)) {
                $entryManager->delete($tag, true);
            }
        }
        $services->get(FormManager::class)->delete(self::FORM_ID);
        parent::tearDown();
    }

    /** @param array<string, string> $attributes */
    private function field(string $type, array $attributes = []): BazarField
    {
        $prepared = $this->getWiki()->services->get(FormManager::class)->prepareData(['template' => [
            ['type' => $type, 'linked_object' => self::FORM_ID, 'name' => 'bf_liees', 'label' => 'Liées'] + $attributes,
        ]]);
        $field = reset($prepared);
        $this->assertInstanceOf(BazarField::class, $field);

        return $field;
    }

    public function testTheNamedAttributesReachTheirPositions(): void
    {
        foreach (['checkboxfiche', 'listefiche', 'radiofiche'] as $type) {
            $template = $this->getWiki()->services->get(FormManager::class)->parseTemplate((string)json_encode([[
                'type' => $type, 'linked_object' => self::FORM_ID, 'name' => 'bf_liees',
                'entries_template' => 'card', 'entries_params' => 'nb="2"', 'entry_display' => 'newtab',
            ]]));

            $this->assertSame(['card', 'nb="2"', 'newtab'], [$template[0][20], $template[0][21], $template[0][22]], $type);
        }
    }

    public function testCheckedEntriesGoThroughTheTemplate(): void
    {
        $html = $this->field('checkboxfiche', ['entries_template' => 'liste_liens.twig'])
            ->renderStaticIfPermitted(['bf_liees' => $this->tags[0] . ',' . $this->tags[1]]);

        $this->assertStringContainsString('data-template="liste_liens.twig"', $html);
        $this->assertStringContainsString('data-tag="' . $this->tags[0] . '"', $html);
        $this->assertStringContainsString('data-tag="' . $this->tags[1] . '"', $html);
        $this->assertStringNotContainsString('data-tag="' . $this->tags[2] . '"', $html);
    }

    public function testTheSelectedEntryOpensTheWayTheFieldSays(): void
    {
        $html = $this->field('listefiche', ['entries_template' => 'card', 'entry_display' => 'newtab'])
            ->renderStaticIfPermitted(['bf_liees' => $this->tags[0]]);

        $this->assertStringContainsString('Fiche liée une', $html);
        $this->assertStringContainsString('target="_blank"', $html);
        $this->assertStringNotContainsString('Fiche liée deux', $html);
    }

    public function testTheRadioEntryGoesThroughTheTemplateToo(): void
    {
        $html = $this->field('radiofiche', ['entries_template' => 'liste_liens.twig'])
            ->renderStaticIfPermitted(['bf_liees' => $this->tags[1]]);

        $this->assertStringContainsString('data-tag="' . $this->tags[1] . '"', $html);
        $this->assertStringNotContainsString('data-tag="' . $this->tags[0] . '"', $html);
        $direct = $this->field('radiofiche', ['entry_display' => 'newtab'])->renderStaticIfPermitted(['bf_liees' => $this->tags[1]]);
        $this->assertStringContainsString('target="_blank"', $direct);
    }

    public function testATemplateOpensEntriesInAPopupUnlessToldOtherwise(): void
    {
        $entry = ['bf_liees' => $this->tags[0]];

        $this->assertStringContainsString('modalbox', $this->field('listefiche', ['entries_template' => 'card'])->renderStaticIfPermitted($entry));
        $direct = $this->field('listefiche', ['entries_template' => 'card', 'entries_params' => 'entrydisplay="direct"'])->renderStaticIfPermitted($entry);
        $this->assertStringNotContainsString('modalbox', $direct);
    }

    public function testThePlainLinksFollowTheDisplayChoice(): void
    {
        $entry = ['bf_liees' => $this->tags[0]];

        $this->assertStringContainsString('modalbox', $this->field('listefiche')->renderStaticIfPermitted($entry));
        $newTab = $this->field('checkboxfiche', ['entry_display' => 'newtab'])->renderStaticIfPermitted($entry);
        $this->assertStringContainsString('target="_blank"', $newTab);
        $this->assertStringNotContainsString('modalbox', $newTab);
        $direct = $this->field('listefiche', ['entry_display' => 'direct'])->renderStaticIfPermitted($entry);
        $this->assertStringNotContainsString('modalbox', $direct);
        $this->assertStringNotContainsString('target="_blank"', $direct);
    }

    public function testChosenAccountsGoThroughTheTemplateLikeEntries(): void
    {
        $services = $this->getWiki()->services;
        $userForm = $services->get(FormManager::class)->getByContentType(ContentTypeSchema::TYPE_USER);
        $this->assertNotNull($userForm, 'the User form should exist -- run ./yeswicli migrate');
        $userManager = $services->get(UserManager::class);
        $userManager->create('EntryFieldDisplayTestUser', 'efdt@example.tld', 'Aa1!aaaaRegression');
        try {
            $prepared = $services->get(FormManager::class)->prepareData(['template' => [[
                'type' => 'checkboxfiche', 'linked_object' => (string)$userForm['id'], 'name' => 'bf_participantes',
                'label' => 'Participantes', 'entries_template' => 'card',
            ]]]);
            $field = reset($prepared);
            $this->assertInstanceOf(BazarField::class, $field);

            $html = $field->renderStaticIfPermitted(['bf_participantes' => 'EntryFieldDisplayTestUser']);

            $this->assertStringContainsString('EntryFieldDisplayTestUser', $html);
            $this->assertStringNotContainsString('yw-items__empty', $html);
        } finally {
            $user = $userManager->getOneByName('EntryFieldDisplayTestUser');
            if ($user) {
                $userManager->delete($user);
            }
            $services->get(PageManager::class)->deleteOrphaned('EntryFieldDisplayTestUser');
        }
    }

    public function testAValueCannotAddActionParameters(): void
    {
        $html = $this->field('checkboxfiche', ['entries_template' => 'liste_liens.twig'])
            ->renderStaticIfPermitted(['bf_liees' => $this->tags[0] . '" template="tableau']);

        $this->assertStringNotContainsString('tableau', $html);
    }
}
