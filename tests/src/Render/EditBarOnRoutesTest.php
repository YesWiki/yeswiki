<?php

namespace YesWiki\Test\Render;

use YesWiki\Kernel\Routing\ReservedTags;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Render\Service\ActionRunner;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * `{{editbar}}` offers nothing on a routed name.
 *
 * A reserved tag has no Content behind it at all -- that is what CONTEXT's "Reserved tag"
 * entry says and what ticket 20 enforced everywhere a tag is chosen. The edit bar had not
 * been told: `/search` and `/doc` rendered "edit this page", a file manager, duplicate and
 * share for a tag `PageManager::save()` refuses to write. Following the edit link opened an
 * editor whose save can only ever throw ReservedTagException.
 */
class EditBarOnRoutesTest extends YesWikiTestCase
{
    private string $previousTag = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousTag = (string)$this->getWiki()->services->get(PageContext::class)->getTag();
    }

    protected function tearDown(): void
    {
        $this->getWiki()->services->get(PageContext::class)->setTag($this->previousTag);
        parent::tearDown();
    }

    /** @param array<string, string> $arguments */
    private function editBarFor(string $tag, array $arguments = []): string
    {
        $wiki = $this->getWiki();
        $wiki->services->get(PageContext::class)->setTag($tag);

        return trim($wiki->services->get(ActionRunner::class)->action('editbar', $arguments));
    }

    public function testARoutedNameGetsNoPageActions(): void
    {
        foreach (ReservedTags::NAMES as $reserved) {
            $this->assertSame(
                '',
                $this->editBarFor($reserved),
                "'{$reserved}' is a route, not a page: offering to edit, duplicate or share it "
                . 'points at a tag no Content may occupy'
            );
        }
    }

    public function testAnOrdinaryPageStillGetsItsEditBar(): void
    {
        $bar = $this->editBarFor('PagePrincipale');

        $this->assertNotSame('', $bar, 'the guard must be about routed names, not about the bar itself');
    }

    /**
     * Naming a page explicitly is asking for *that* page's bar, so a routed surface stays
     * free to show one for something else.
     */
    public function testAnExplicitPageParameterIsStillHonouredOnARoute(): void
    {
        $bar = $this->editBarFor(ReservedTags::NAMES[0], ['page' => 'PagePrincipale']);

        $this->assertNotSame('', $bar);
    }
}
