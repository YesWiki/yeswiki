<?php

namespace YesWiki\Test\Render;

use PHPUnit\Framework\Attributes\DataProvider;
use YesWiki\Content\Entity\Item;
use YesWiki\Render\Service\PresentationRenderer;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * The shared presentations (ticket 37): one list of Items, four shapes, and the same shape whatever supplied the list.
 */
class PresentationRendererTest extends YesWikiTestCase
{
    private static function renderer(): PresentationRenderer
    {
        return self::getWiki()->services->get(PresentationRenderer::class);
    }

    /**
     * @return list<Item>
     */
    private static function items(): array
    {
        return [
            new Item(
                id: 'https://example.test/1',
                title: 'Une première nouvelle',
                subtitle: 'Le journal',
                description: '<p>Le corps.</p>',
                image: 'https://example.test/img.jpg',
                url: 'https://example.test/1',
                date: '2026-08-12T09:00:00+00:00',
                badge: 'actualité',
                categories: ['actualite'],
            ),
            new Item(id: 'x2', title: 'Sans rien d’autre'),
        ];
    }

    /**
     * @return list<array{string}>
     */
    public static function presentations(): array
    {
        return array_map(static fn (string $p) => [$p], PresentationRenderer::PRESENTATIONS);
    }

    #[DataProvider('presentations')]
    public function testEveryPresentationRendersEveryItem(string $presentation): void
    {
        $html = self::renderer()->render($presentation, self::items());

        $this->assertStringContainsString('Une première nouvelle', $html);
        $this->assertStringContainsString('Sans rien d’autre', $html, 'an Item with only a title still renders');
        $this->assertStringContainsString("yw-items--{$presentation}", $html);
    }

    /**
     * An Item is mostly optional, and a Presentation must not fall over on the empty half of it: a page list has no images, a feed may have no categories, `listusers` has no dates.
     */
    public function testASparseItemRendersWithoutEmptyMarkup(): void
    {
        $html = self::renderer()->render('card', [new Item(id: 'x', title: 'Rien que le titre')]);

        $this->assertStringContainsString('Rien que le titre', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('yw-item__badge', $html);
        $this->assertStringNotContainsString('<time', $html);
    }

    public function testAnEmptyListSaysSoRatherThanRenderingNothing(): void
    {
        foreach (PresentationRenderer::PRESENTATIONS as $presentation) {
            $html = self::renderer()->render($presentation, []);
            $this->assertStringContainsString(
                _t('BAZ_NO_RESULT'),
                $html,
                "{$presentation} with no items"
            );
        }
    }

    /**
     * A date is written the way the reader's language writes dates, and drawn only when the Item carries one.
     */
    #[DataProvider('presentations')]
    public function testADateIsLocalisedAndOnlyDrawnWhenThereIsOne(string $presentation): void
    {
        $previous = $GLOBALS['prefered_language'] ?? null;

        try {
            $GLOBALS['prefered_language'] = 'fr';
            $french = self::renderer()->render($presentation, self::items());
            $GLOBALS['prefered_language'] = 'en';
            $english = self::renderer()->render($presentation, self::items());
        } finally {
            $GLOBALS['prefered_language'] = $previous;
        }

        $this->assertStringContainsString('12 août 2026', $french, "{$presentation} in French");
        $this->assertStringContainsString('August 12, 2026', $english, "{$presentation} in English");

        $this->assertStringContainsString('2026-08-12T09:00:00+00:00', $french);

        $this->assertSame(
            1,
            substr_count($french, '<time'),
            "{$presentation} draws a date for the item that has one, and only that one"
        );
    }

    /**
     * The same presentation is written `card` in page content and `card.twig` in a config, and `tableau.tpl.html` in bodies old enough to predate the Twig move.
     */
    public function testATemplateIsKnownWhateverItIsWrittenAs(): void
    {
        $this->assertTrue(PresentationRenderer::knows('card'));
        $this->assertTrue(PresentationRenderer::knows('card.twig'));
        $this->assertTrue(PresentationRenderer::knows('table.tpl.html'));
        $this->assertFalse(PresentationRenderer::knows('liste_description.twig'), 'a syndication template is not one');
        $this->assertFalse(PresentationRenderer::knows('../../../etc/passwd'));
    }

    /** A name that reaches a filesystem path must not be able to leave the directory. */
    public function testAnUnknownTemplateFallsBackRatherThanReachingForAFile(): void
    {
        $html = self::renderer()->render('../../../etc/passwd', self::items());

        $this->assertStringContainsString('yw-items--list', $html);
    }
}
