<?php

namespace YesWiki\Test\Content;

use YesWiki\Content\Entity\MenuNode;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\ListManager;
use YesWiki\Content\Service\MenuManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Content\Service\TranslatableContent;
use YesWiki\Content\Service\TranslationCoverage;
use YesWiki\Kernel\Service\LanguageService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** What the translations screen reports, and where each of its links leads. */
class TranslationCoverageTest extends YesWikiTestCase
{
    private const FORM_ID = '999915';
    private const LIST_ID = 'ListeCoverageTest';

    /** @var list<string> */
    private array $entries = [];

    private string $menu = '';

    protected function tearDown(): void
    {
        $wiki = $this->getWiki();
        foreach ($this->entries as $tag) {
            $wiki->services->get(PageManager::class)->deleteOrphaned($tag);
        }
        $this->entries = [];
        if ($this->menu !== '') {
            $wiki->services->get(MenuManager::class)->delete($this->menu);
            $this->menu = '';
        }
        $wiki->services->get(FormManager::class)->delete(self::FORM_ID);
        $wiki->services->get(PageManager::class)->deleteOrphaned(self::LIST_ID);

        parent::tearDown();
    }

    private function skipUnlessMultilingual(): void
    {
        if (!in_array('en', $this->getWiki()->services->get(LanguageService::class)->availableLanguages(), true)) {
            $this->markTestSkipped('this wiki does not offer English, so nothing is translated into it');
        }
    }

    private function makeForm(): void
    {
        $this->getWiki()->services->get(FormManager::class)->create([
            'id' => self::FORM_ID,
            'label' => 'Formulaire de couverture',
            'template' => json_encode([
                ['type' => 'texte', 'name' => 'bf_titre', 'label' => 'Titre'],
                ['type' => 'textelong', 'name' => 'bf_description', 'label' => 'Description'],
            ]),
        ]);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function makeEntry(array $values): string
    {
        $entry = $this->getWiki()->services->get(EntryManager::class)
            ->create(self::FORM_ID, ['antispam' => 1] + $values);
        $this->entries[] = (string)$entry['tag'];

        return (string)$entry['tag'];
    }

    /**
     * The group the fixture form's entries land on.
     *
     * @param array<string, mixed> $report
     *
     * @return array<string, mixed>
     */
    private function group(array $report, string $key): array
    {
        foreach ($report['groups'] as $group) {
            if ($group['key'] === $key) {
                return $group;
            }
        }

        $this->fail("no group {$key} in the report");
    }

    public function testEachFormIsCountedOnItsOwnLine(): void
    {
        $this->skipUnlessMultilingual();
        $this->makeForm();
        $translated = $this->makeEntry(['bf_titre' => 'Un titre', 'bf_description' => 'Une description']);
        $this->makeEntry(['bf_titre' => 'Un autre titre', 'bf_description' => 'Une autre description']);
        $started = $this->makeEntry(['bf_titre' => 'Un troisième titre', 'bf_description' => 'Encore une description']);

        $entryManager = $this->getWiki()->services->get(EntryManager::class);
        $entryManager->saveTranslations($translated, 'en', ['bf_titre' => 'A title', 'bf_description' => 'A description']);
        $entryManager->saveTranslations($started, 'en', ['bf_titre' => 'A third title']);

        $group = $this->group(
            $this->getWiki()->services->get(TranslationCoverage::class)->report(),
            'entry:' . self::FORM_ID
        );

        $this->assertSame('Formulaire de couverture', $group['label'], 'a form is named by its own label');
        $this->assertSame(3, $group['total']);
        $this->assertSame(1, $group['languages']['en']['full']);
        $this->assertSame(1, $group['languages']['en']['partial']);
        $this->assertSame(1, $group['languages']['en']['empty']);
        $this->assertSame(3, $group['languages']['en']['done'], 'two values for one entry, one for another');
        $this->assertSame(6, $group['languages']['en']['wanted'], 'two translatable fields on each of three entries');
    }

    public function testAValueListIsCountedTooAndLinksToTheListEditor(): void
    {
        $this->skipUnlessMultilingual();
        $this->getWiki()->services->get(ListManager::class)->create(
            'Couleurs',
            [['id' => 'rouge', 'label' => 'Rouge', 'children' => []]],
            self::LIST_ID
        );
        $this->getWiki()->services->get(ListManager::class)
            ->saveTranslations(self::LIST_ID, 'en', ['title' => 'Colours']);

        $report = $this->getWiki()->services->get(TranslationCoverage::class)
            ->report(['group' => 'list', 'language' => 'en', 'state' => 'partial']);

        $row = current(array_filter($report['rows'], fn ($row) => $row['tag'] === self::LIST_ID));
        $this->assertNotFalse($row, 'a half-translated list is a list with work left on it');
        $this->assertSame(1, $row['languages']['en']['done']);
        $this->assertSame(2, $row['languages']['en']['wanted'], 'the list title and its one value');
        $this->assertStringContainsString('admin/lists', $row['languages']['en']['href']);
        $this->assertStringContainsString('listid=' . self::LIST_ID, $row['languages']['en']['href']);
        $this->assertStringContainsString('editlang=en', $row['languages']['en']['href']);
    }

    public function testEveryOtherContentIsTranslatedInItsOwnEditor(): void
    {
        $this->skipUnlessMultilingual();
        $this->makeForm();
        $tag = $this->makeEntry(['bf_titre' => 'Un titre']);

        $report = $this->getWiki()->services->get(TranslationCoverage::class)
            ->report(['group' => 'entry:' . self::FORM_ID]);

        $row = current(array_filter($report['rows'], fn ($row) => $row['tag'] === $tag));
        $this->assertNotFalse($row);
        $this->assertStringContainsString($tag . '/edit', $row['languages']['en']['href']);
        $this->assertStringContainsString('editlang=en', $row['languages']['en']['href']);
    }

    public function testTheFilterNarrowsToWhatIsLeftToDo(): void
    {
        $this->skipUnlessMultilingual();
        $this->makeForm();
        $done = $this->makeEntry(['bf_titre' => 'Un titre']);
        $todo = $this->makeEntry(['bf_titre' => 'Un autre titre']);
        $this->getWiki()->services->get(EntryManager::class)
            ->saveTranslations($done, 'en', ['bf_titre' => 'A title']);

        $coverage = $this->getWiki()->services->get(TranslationCoverage::class);
        $tags = array_column(
            $coverage->report(['group' => 'entry:' . self::FORM_ID, 'language' => 'en', 'state' => 'empty'])['rows'],
            'tag'
        );

        $this->assertSame([$todo], $tags);
    }

    public function testTheScreenAndTheEditorCountTheSameThing(): void
    {
        $this->skipUnlessMultilingual();
        $this->makeForm();
        $tag = $this->makeEntry(['bf_titre' => 'Un titre', 'bf_description' => 'Une description']);
        $entryManager = $this->getWiki()->services->get(EntryManager::class);
        $entryManager->saveTranslations($tag, 'en', ['bf_titre' => 'A title']);

        $translatable = $this->getWiki()->services->get(TranslatableContent::class);
        $form = $this->getWiki()->services->get(FormManager::class)->getOne(self::FORM_ID);
        $fromEditor = $translatable->translationProgress(
            $entryManager->getUntranslated($tag) ?? [],
            'en',
            $translatable->entryPaths($form ?? [])
        );

        $report = $this->getWiki()->services->get(TranslationCoverage::class)
            ->report(['group' => 'entry:' . self::FORM_ID]);
        $row = current(array_filter($report['rows'], fn ($row) => $row['tag'] === $tag));

        $this->assertNotFalse($row);
        $this->assertSame($fromEditor['state'], $row['languages']['en']['state']);
        $this->assertSame($fromEditor['done'], $row['languages']['en']['done']);
        $this->assertSame($fromEditor['wanted'], $row['languages']['en']['wanted']);
    }

    public function testAMenuIsCountedAndLinksToTheMenuEditor(): void
    {
        $this->skipUnlessMultilingual();
        $menus = $this->getWiki()->services->get(MenuManager::class);
        $node = MenuNode::fromArray(['id' => 'accueil', 'label' => 'Accueil', 'link' => 'PagePrincipale']);
        $this->assertNotNull($node);
        $this->menu = $menus->create('Navigation', [$node]);
        $menus->saveTranslations($this->menu, 'en', ['nodes.accueil.label' => 'Home']);

        $report = $this->getWiki()->services->get(TranslationCoverage::class)
            ->report(['group' => 'menu']);

        $row = current(array_filter($report['rows'], fn ($row) => $row['tag'] === $this->menu));
        $this->assertNotFalse($row, 'a menu is a Content with labels, so it is counted');
        $this->assertSame(1, $row['languages']['en']['done']);
        $this->assertSame(2, $row['languages']['en']['wanted'], 'the menu name and its one entry');
        $this->assertStringContainsString('admin/menus', $row['languages']['en']['href']);
        $this->assertStringContainsString('menu=' . $this->menu, $row['languages']['en']['href']);
        $this->assertStringContainsString('editlang=en', $row['languages']['en']['href']);
    }

    public function testACommentHasNothingToTranslateAndIsLeftOut(): void
    {
        $this->assertNotContains('comment', TranslationCoverage::TYPES);
    }
}
