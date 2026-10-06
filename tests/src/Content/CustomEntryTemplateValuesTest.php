<?php

namespace YesWiki\Test\Content;

use PHPUnit\Framework\TestCase;
use YesWiki\Content\Controller\EntryController;

/** A custom entry template gets each field's value exactly, however much HTML the value holds. */
class CustomEntryTemplateValuesTest extends TestCase
{
    private function inner(string $html, string $class): ?string
    {
        return (new \ReflectionMethod(EntryController::class, 'innerHtmlOfClass'))->invoke(null, $html, $class);
    }

    public function testNestedBlocksInAValueKeepTheirOwnClosingTags(): void
    {
        $field = '<div class="BAZ_rubrique field-textelong" data-id="bf_description"><span class="BAZ_label">Description</span>'
            . '<div class="BAZ_texte"><p>Du <strong>gras</strong></p><div class="yw-callout">Été</div></div></div>';

        $this->assertSame('<p>Du <strong>gras</strong></p><div class="yw-callout">Été</div>', $this->inner($field, 'BAZ_texte'));
    }

    public function testTheTitleIsTheHeadingsText(): void
    {
        $this->assertSame('Mon titre', $this->inner('<h1 class="BAZ_fiche_titre">Mon titre</h1>', 'BAZ_fiche_titre'));
    }

    public function testAFragmentWithoutTheWrapperIsLeftToTheCaller(): void
    {
        $this->assertNull($this->inner('texte nu', 'BAZ_texte'));
    }
}
