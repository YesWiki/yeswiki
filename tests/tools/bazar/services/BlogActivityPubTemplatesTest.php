<?php

namespace YesWiki\Test\Bazar\Service;

use YesWiki\Core\Service\TemplateEngine;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * The default Blog-actu templates publish an entry as a Note that Mastodon and GoToSocial show as a post, and read notes back into entries.
 */
class BlogActivityPubTemplatesTest extends YesWikiTestCase
{
    private const DIR = 'tools/bazar/templates/activitypub/';

    private TemplateEngine $templateEngine;
    private string $timezone;

    protected function setUp(): void
    {
        $wiki = $this->getWiki();
        $GLOBALS['wiki'] = $wiki;
        $this->templateEngine = $wiki->services->get(TemplateEngine::class);
        $this->timezone = date_default_timezone_get();
        date_default_timezone_set('Europe/Paris');
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->timezone);
    }

    private function render(string $file, array $data)
    {
        return json_decode($this->templateEngine->renderSandboxedFromStringNoEscape(file_get_contents(self::DIR . $file), $data), true, 512, JSON_THROW_ON_ERROR);
    }

    private function note(array $entry = []): array
    {
        return $this->render('blog-note.json.twig', $entry + [
            'id_fiche' => 'UnBeauLogo',
            'bf_titre' => 'Un beau logo <enfin> & le reste',
            'bf_chapeau' => "Il fallait le rafraîchir, c'est fait",
            'bf_description' => 'Un **grand** moment',
            'imagebf_image' => 'UnBeauLogo_logo.png',
            'date_creation_fiche' => '2024-03-01 10:00:00',
            'date_maj_fiche' => '2024-03-02 11:00:00',
        ]);
    }

    public function testTheTitleIsInTheTextNotInTheName()
    {
        $note = $this->note();

        $this->assertArrayNotHasKey('name', $note);
        $this->assertStringStartsWith('<p><strong>Un beau logo &lt;enfin&gt; &amp; le reste</strong></p><p><em>', $note['content']);
        $this->assertStringContainsString('<b>grand</b>', $note['content']);
    }

    public function testANoteHasNoContentWarningUnlessTheEntryAsksForOne()
    {
        $this->assertArrayNotHasKey('summary', $this->note());
        $this->assertArrayNotHasKey('sensitive', $this->note(['bf_avertissement' => '']));

        $note = $this->note(['bf_avertissement' => 'Spoilers de la saison 2']);

        $this->assertSame('Spoilers de la saison 2', $note['summary']);
        $this->assertTrue($note['sensitive']);
    }

    public function testDatesHaveAnOffset()
    {
        $note = $this->note();

        $this->assertSame('2024-03-01T10:00:00+01:00', $note['published']);
        $this->assertSame('2024-03-02T11:00:00+01:00', $note['updated']);
    }

    public function testANoteFromOneWikiIsReadBackByAnother()
    {
        $entry = $this->render('blog-note.reverse.json.twig', $this->note(['bf_avertissement' => 'Spoilers']));

        $this->assertSame('Un beau logo <enfin> & le reste', $entry['bf_titre']);
        $this->assertSame("Il fallait le rafraîchir, c'est fait", $entry['bf_chapeau']);
        $this->assertSame('Spoilers', $entry['bf_avertissement']);
        $this->assertStringNotContainsString('beau logo', $entry['bf_description']);
        $this->assertStringContainsString('**grand**', $entry['bf_description']);
        $this->assertStringEndsWith('UnBeauLogo_logo.png', $entry['imagebf_image']);
        $this->assertSame('2024-03-01 10:00:00', $entry['date_creation_fiche']);
    }

    public function testAMastodonNoteKeepsItsContentWarningAndLeavesTheTitleToTheInbox()
    {
        $entry = $this->render('blog-note.reverse.json.twig', [
            'type' => 'Note',
            'summary' => 'Politique',
            'content' => '<p>Un <strong>texte</strong> engagé</p>',
            'published' => '2026-10-01T08:00:00Z',
        ]);

        $this->assertSame('', $entry['bf_titre']);
        $this->assertSame('Politique', $entry['bf_avertissement']);
        $this->assertStringContainsString('**texte**', $entry['bf_description']);
    }

    public function testANoteThatStillHasANameKeepsIt()
    {
        $entry = $this->render('blog-note.reverse.json.twig', ['type' => 'Note', 'name' => 'Ancien titre', 'content' => '<p><strong>Ancien titre</strong></p>Le texte']);

        $this->assertSame('Ancien titre', $entry['bf_titre']);
        $this->assertStringNotContainsString('Ancien titre', $entry['bf_description']);
    }

    public function testTheInstallerShipsTheseTemplates()
    {
        $sql = file_get_contents('setup/sql/default-content.sql');

        foreach (['blog-note.json.twig', 'blog-note.reverse.json.twig'] as $file) {
            $this->assertStringContainsString(str_replace("\n", '\n', trim(file_get_contents(self::DIR . $file))), $sql);
        }
        $this->assertStringContainsString('texte***bf_avertissement***', $sql);
    }
}
