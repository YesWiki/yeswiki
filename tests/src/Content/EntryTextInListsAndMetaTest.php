<?php

namespace YesWiki\Test\Content;

use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Render\Service\ActionRunner;
use YesWiki\Render\Service\TemplateHelperService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** An entry's long text reads the same in a list as on its own page, and its meta description is plain words. */
class EntryTextInListsAndMetaTest extends YesWikiTestCase
{
    private string $formId = '';

    /** @var list<string> */
    private array $entries = [];

    protected function setUp(): void
    {
        parent::setUp();
        $services = $this->getWiki()->services;
        $services->get(AuthenticationService::class)->connectFirstAdmin();
        $forms = $services->get(FormManager::class);
        $this->formId = (string)$forms->findNewId();
        $forms->create([
            'id' => $this->formId,
            'label' => 'Entry text in lists test',
            'template' => [
                ['type' => 'texte', 'name' => 'bf_titre', 'label' => 'Titre'],
                ['type' => 'texte', 'name' => 'bf_soustitre', 'label' => 'Sous-titre'],
                ['type' => 'textelong', 'name' => 'bf_description', 'label' => 'Description', 'syntax' => 'wiki'],
            ],
            'entry_title_template' => '{{bf_titre}}',
        ]);
    }

    protected function tearDown(): void
    {
        $services = $this->getWiki()->services;
        foreach ($this->entries as $tag) {
            $services->get(PageManager::class)->deleteOrphaned($tag);
        }
        $services->get(FormManager::class)->delete($this->formId);
        $services->get(AuthenticationService::class)->logout();
        parent::tearDown();
    }

    private function entry(string $title, string $subtitle, string $description): string
    {
        $entry = $this->getWiki()->services->get(EntryManager::class)->create($this->formId, [
            'bf_titre' => $title,
            'bf_soustitre' => $subtitle,
            'bf_description' => $description,
            'antispam' => 1,
        ], false);
        $this->entries[] = (string)$entry['tag'];

        return (string)$entry['tag'];
    }

    public function testALongTextIsRenderedInEveryPresentationThatShowsIt(): void
    {
        $this->entry('Texte riche', 'x', 'Du **gras** et un [lien](https://example.org)');
        $runner = $this->getWiki()->services->get(ActionRunner::class);

        foreach (['card', 'list', 'timeline'] as $template) {
            $html = $runner->action('entrylist', ['id' => $this->formId, 'template' => $template, 'displayfields' => 'title=bf_titre,description=bf_description']);
            $this->assertStringContainsString('<strong>gras</strong>', $html, "{$template} renders the Markdown");
            $this->assertStringNotContainsString('**gras**', $html, "{$template} shows no raw Markdown");
        }
    }

    public function testAShortTextChosenAsDescriptionIsEscaped(): void
    {
        $this->entry('Texte court', 'Si 5 < 6 & 7 > 2', 'rien');
        $html = $this->getWiki()->services->get(ActionRunner::class)->action('entrylist', ['id' => $this->formId, 'template' => 'card', 'displayfields' => 'title=bf_titre,description=bf_soustitre']);

        $this->assertStringContainsString('Si 5 &lt; 6 &amp; 7 &gt; 2', $html);
    }

    public function testTheMetaDescriptionComesFromTheDescriptionRoleWithoutMarkup(): void
    {
        $tag = $this->entry('Meta', 'ignored', 'Du **gras** et un [lien](https://example.org)');
        $page = $this->getWiki()->services->get(PageManager::class)->getOne($tag);
        $this->assertNotNull($page);

        $this->assertSame('Du gras et un lien', $this->getWiki()->services->get(TemplateHelperService::class)->getDescriptionFromBody($page, 'Meta'));
    }
}
