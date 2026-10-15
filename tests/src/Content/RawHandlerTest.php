<?php

namespace YesWiki\Test\Content;

use PHPUnit\Framework\TestCase;
use YesWiki\Content\Handler\RawHandler;

/** `/raw` prints a page's wiki markup, and an entry's whole body as JSON for the QR code scanners. */
class RawHandlerTest extends TestCase
{
    public function testAPagePrintsItsMarkup(): void
    {
        $this->assertSame('# Titre', RawHandler::text(['content' => '# Titre', 'title' => 'Titre']));
    }

    public function testAnEntryPrintsItsBodyAsJson(): void
    {
        $this->assertSame(
            '{"bf_titre":"Sortie","id_typeannonce":"2","url":"https://x.test/"}',
            RawHandler::text(['bf_titre' => 'Sortie', 'id_typeannonce' => '2', 'url' => 'https://x.test/'])
        );
    }
}
