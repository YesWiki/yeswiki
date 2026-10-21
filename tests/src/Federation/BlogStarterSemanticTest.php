<?php

namespace YesWiki\Test\Federation;

use YesWiki\Content\Service\SemanticTransformer;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** The Blog starter's templates send a post as a Note Mastodon can show, and read one back as wiki text. */
class BlogStarterSemanticTest extends YesWikiTestCase
{
    /** @return array<string, mixed> */
    private function form(): array
    {
        return json_decode((string)file_get_contents('src/Admin/starters/blog.json'), true)['form'];
    }

    private function transformer(): SemanticTransformer
    {
        return $this->getWiki()->services->get(SemanticTransformer::class);
    }

    public function testAPostLeadsItsNoteWithTitleAndSummary(): void
    {
        $note = $this->transformer()->convertToSemanticData($this->form(), [
            'tag' => 'UnBillet',
            'bf_titre' => 'Un titre',
            'bf_chapeau' => 'Le chapeau',
            'bf_description' => 'Du **gras**',
            'created_at' => '2026-10-19 10:00:00',
            'updated_at' => '2026-10-19 11:00:00',
        ]);

        $this->assertSame('Note', $note['type']);
        $this->assertStringStartsWith('<p><strong>Un titre</strong></p><p><em>Le chapeau</em></p>', $note['content']);
        $this->assertStringContainsString('<strong>gras</strong>', $note['content']);
        $this->assertSame(date('c', (int)strtotime('2026-10-19 10:00:00')), $note['published']);
        $this->assertSame(date('c', (int)strtotime('2026-10-19 11:00:00')), $note['updated']);
    }

    public function testALinkedImageIsAttachedAsItIs(): void
    {
        $note = $this->transformer()->convertToSemanticData($this->form(), [
            'tag' => 'UnBillet',
            'bf_titre' => 'Un titre',
            'imagebf_image' => 'https://files.mastodon.example/photo.jpg',
        ]);

        $this->assertSame('https://files.mastodon.example/photo.jpg', $note['attachment'][0]['url']);
    }

    public function testANoteComesBackAsMarkdownWithoutItsLead(): void
    {
        $entry = $this->transformer()->convertFromSemanticData($this->form(), [
            'name' => 'Un titre',
            'preview' => 'Le chapeau',
            'content' => '<p><strong>Un titre</strong></p><p><em>Le chapeau</em></p><p>Du <strong>gras</strong> {{include page="X"}}</p>',
        ]);

        $this->assertNotNull($entry);
        $this->assertSame('Un titre', $entry['bf_titre']);
        $this->assertSame('Le chapeau', $entry['bf_chapeau']);
        $this->assertStringStartsWith('Du **gras** &#123;&#123;include', $entry['bf_description']);
    }

    public function testAMastodonNoteWithoutNameKeepsItsWholeText(): void
    {
        $entry = $this->transformer()->convertFromSemanticData($this->form(), [
            'content' => '<p>Un pouet</p>',
        ]);

        $this->assertNotNull($entry);
        $this->assertSame('', $entry['bf_titre']);
        $this->assertSame('Un pouet', $entry['bf_description']);
    }
}
