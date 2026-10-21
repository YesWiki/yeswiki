<?php

namespace YesWiki\Test\Federation;

use YesWiki\Render\Service\TemplateEngine;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** The default Blog-actu templates publish an entry as a Note that Mastodon and GoToSocial show as a post, and read notes back into entries. */
class BlogActivityPubTemplatesTest extends YesWikiTestCase
{
    private TemplateEngine $templateEngine;
    private string $timezone;

    protected function setUp(): void
    {
        parent::setUp();
        $wiki = $this->getWiki();
        $GLOBALS['wiki'] = $wiki;
        $this->templateEngine = $wiki->services->get(TemplateEngine::class);
        $this->timezone = date_default_timezone_get();
        date_default_timezone_set('Europe/Paris');
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->timezone);
        parent::tearDown();
    }

    /** @param array<string, mixed> $data */
    private function render(string $key, array $data): mixed
    {
        $form = json_decode((string)file_get_contents('src/Admin/starters/blog.json'), true)['form'];

        return json_decode($this->templateEngine->renderSandboxedFromStringNoEscape($form[$key], $data), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, mixed> $entry
     *
     * @return array<string, mixed>
     */
    private function note(array $entry = []): array
    {
        return $this->render('sem_template', $entry + [
            'tag' => 'UnBeauLogo',
            'bf_titre' => 'Un beau logo <enfin> & le reste',
            'bf_chapeau' => "Il fallait le rafraîchir, c'est fait",
            'bf_description' => 'Un **grand** moment',
            'imagebf_image' => 'UnBeauLogo_logo.png',
            'created_at' => '2024-03-01 10:00:00',
            'updated_at' => '2024-03-02 11:00:00',
        ]);
    }

    public function testTheTitleIsInTheTextNotInTheName(): void
    {
        $note = $this->note();

        $this->assertArrayNotHasKey('name', $note);
        $this->assertStringStartsWith('<p><strong>Un beau logo &lt;enfin&gt; &amp; le reste</strong></p><p><em>', $note['content']);
        $this->assertStringContainsString('<strong>grand</strong>', $note['content']);
    }

    public function testANoteHasNoContentWarningUnlessTheEntryAsksForOne(): void
    {
        $this->assertArrayNotHasKey('summary', $this->note());
        $this->assertArrayNotHasKey('sensitive', $this->note(['bf_avertissement' => '']));

        $note = $this->note(['bf_avertissement' => 'Spoilers de la saison 2']);

        $this->assertSame('Spoilers de la saison 2', $note['summary']);
        $this->assertTrue($note['sensitive']);
    }

    public function testDatesHaveAnOffset(): void
    {
        $note = $this->note();

        $this->assertSame('2024-03-01T10:00:00+01:00', $note['published']);
        $this->assertSame('2024-03-02T11:00:00+01:00', $note['updated']);
    }

    public function testANoteFromOneWikiIsReadBackByAnother(): void
    {
        $entry = $this->render('sem_reverse_template', $this->note(['bf_avertissement' => 'Spoilers']));

        $this->assertSame('Un beau logo <enfin> & le reste', $entry['bf_titre']);
        $this->assertSame("Il fallait le rafraîchir, c'est fait", $entry['bf_chapeau']);
        $this->assertSame('Spoilers', $entry['bf_avertissement']);
        $this->assertStringNotContainsString('beau logo', $entry['bf_description']);
        $this->assertStringContainsString('**grand**', $entry['bf_description']);
        $this->assertStringEndsWith('UnBeauLogo_logo.png', $entry['imagebf_image']);
        $this->assertSame('2024-03-01 10:00:00', $entry['created_at']);
    }

    public function testAMastodonNoteKeepsItsContentWarningAndLeavesTheTitleToTheInbox(): void
    {
        $entry = $this->render('sem_reverse_template', [
            'type' => 'Note',
            'summary' => 'Politique',
            'content' => '<p>Un <strong>texte</strong> engagé</p>',
            'published' => '2026-10-01T08:00:00Z',
        ]);

        $this->assertSame('', $entry['bf_titre']);
        $this->assertSame('Politique', $entry['bf_avertissement']);
        $this->assertStringContainsString('**texte**', $entry['bf_description']);
    }

    public function testANoteThatStillHasANameKeepsIt(): void
    {
        $entry = $this->render('sem_reverse_template', ['type' => 'Note', 'name' => 'Ancien titre', 'content' => '<p><strong>Ancien titre</strong></p>Le texte']);

        $this->assertSame('Ancien titre', $entry['bf_titre']);
        $this->assertStringNotContainsString('Ancien titre', $entry['bf_description']);
    }

    public function testTheStarterHasAContentWarningFieldAfterTheSummary(): void
    {
        $names = array_column(json_decode((string)file_get_contents('src/Admin/starters/blog.json'), true)['form']['template'], 'name');

        $this->assertSame(array_search('bf_chapeau', $names, true) + 1, array_search('bf_avertissement', $names, true));
    }
}
