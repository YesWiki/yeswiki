<?php

namespace YesWiki\Test\Social;

use YesWiki\Social\Service\ReactionsFormatter;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** What each reaction is drawn with, from the comma-separated `images` an author wrote. */
class ReactionImagesTest extends YesWikiTestCase
{
    /** @return array<array-key, string> */
    private function images(string $written): array
    {
        $ids = array_map(fn ($i) => "r$i", array_keys(explode(',', $written)));

        return $this->getWiki()->services->get(ReactionsFormatter::class)->formatImages($ids, $written);
    }

    public function testAShortMikoneNamePointsAtTheShippedImage(): void
    {
        $this->assertStringEndsWith('/styles/images/mikone-j-aime.svg', $this->images('j-aime')[0]);
    }

    public function testAnEmojiAndASymbolPassThrough(): void
    {
        $this->assertSame(['🌿', 'heart'], $this->images('🌿,heart'));
    }

    public function testAPathIsRefused(): void
    {
        $this->assertSame('', $this->images('../secret.png')[0]);
    }
}
