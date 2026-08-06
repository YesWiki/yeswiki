<?php

namespace YesWiki\Test\Admin;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Request;
use YesWiki\Admin\Controller\AdminController;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * The Preset screen's component gallery (ticket 30).
 *
 * A colour preset is judged against components, not against a paragraph: a contrast that
 * reads well in body text can fail on a button, and a heading scale that works alone can
 * collide inside a panel. So the screen renders every graphical component the wiki has --
 * and this asserts that it really does, because the failure mode is silent. An action that
 * stops existing, or is renamed, leaves *nothing* on the page; the gallery would go on
 * looking fine while quietly previewing less and less.
 *
 * Asserted on what each component actually emits, taken from the renderer rather than
 * guessed: `{{section}}` produces a `<section>` carrying its classes, and `icon="star"`
 * produces an SVG sprite reference, not a font class.
 */
class PresetPreviewTest extends YesWikiTestCase
{
    private static ?string $rendered = null;

    /**
     * component => a string only that component can have produced.
     *
     * @return array<string, list<string>>
     */
    public static function components(): array
    {
        return [
            // Markers the *gallery* is the only possible source of. Half of an earlier set
            // proved nothing: `<strong>`, `<em>`, `<code>`, `<table` and `yw-progress` all
            // appear on the screen with the gallery emptied -- the debug SQL panel emits
            // bold and italic, and htmx's loading indicator is a `yw-progress`. Each of
            // these was checked by rendering the screen with an empty gallery and
            // confirming it disappears.
            'heading level 6' => ['<h6'],
            'bold' => ['<strong>gras</strong>'],
            'italic' => ['<em>italique</em>'],
            'strikethrough' => ['<del>texte'],
            'inline code' => ['<code>code en'],
            'table' => ['<th>Colonne</th>'],
            'blockquote' => ['<blockquote>'],
            'nested list' => ['un point imbriqué'],
            'ordered list' => ['<ol>'],
            'internal link' => ['data-tag="PagePrincipale"'],
            'button' => ['yw-btn--danger'],
            'button with an icon' => ['icons.svg#star'],
            'block button' => ['yw-btn--block'],
            'label' => ['yw-label--warning'],
            // `[35%]`, the wiki syntax that replaced {{progressbar}} (ticket 30)
            'progress bar' => ['<progress class="yw-progressbar" value="35"'],
            'grid and columns' => ['yw-col'],
            'panel' => ['yw-panel--primary'],
            'accordion' => ['yw-accordion'],
            // the tab's own label, not the id it is given: ids are made unique against
            // process-global state, so the same gallery yields `premier-onglet` alone and
            // something else in a suite where tabs have already been rendered
            'tabs' => ['Premier onglet'],
            'section' => ['shape-rounded'],
        ];
    }

    #[DataProvider('components')]
    public function testTheGalleryRendersEveryComponent(string $marker): void
    {
        $this->assertStringContainsString(
            $marker,
            $this->screen(),
            'the gallery must actually render this component -- an action that renders nothing leaves no trace'
        );
    }

    /**
     * The gallery titles each block with the markup that draws it, inside a code span, so
     * the preview doubles as a syntax reference. Those are the only literal `{{…}}` allowed:
     * one anywhere else is a component that failed to run and was printed instead.
     */
    public function testNoComponentIsPrintedInsteadOfRendered(): void
    {
        $withoutCodeSpans = preg_replace('~<code>[^<]*</code>~', '', $this->screen());

        $this->assertSame(
            0,
            preg_match_all('/\{\{\s*(button|label|panel|accordion|tabs|grid|col|section)\b/', (string)$withoutCodeSpans),
            'a component printed as literal markup did not run'
        );
    }

    /**
     * Every component the gallery opens, it closes.
     *
     * The markers above all assert on *opening* tags, and every one of them passed while
     * the six `{{label}}`s were emitting unclosed spans that nested the rest of the page
     * inside themselves (see ActionTagsOnOneLineTest). Counting is what would have caught
     * it: an element that opens six times and closes five is a broken document however
     * good its first six inches look.
     */
    public function testTheGalleryClosesEveryComponentItOpens(): void
    {
        $screen = $this->screen();

        $this->assertSame(
            6,
            substr_count($screen, 'start of label'),
            'six labels -- if this changes, so must the count below'
        );
        $this->assertGreaterThanOrEqual(
            6,
            substr_count($screen, '</span>'),
            'a label that never closes swallows the rest of the gallery'
        );
        foreach (['col', 'grid', 'panel', 'accordion'] as $element) {
            $this->assertSame(
                substr_count($screen, 'start of ' . $element),
                substr_count($screen, 'end of ' . $element),
                "every {{{$element}}} the gallery opens must close"
            );
        }
    }

    /** Around the gallery: the list of presets, and the rail that edits one. */
    public function testTheScreenCarriesThePresetListAndTheRail(): void
    {
        $screen = $this->screen();

        $this->assertStringContainsString('yw-preset-cards', $screen, 'the list of presets');
        $this->assertStringContainsString('yw-preset-card__swatch', $screen, 'a preset is shown as its colours');
        $this->assertStringContainsString('data-yw-preset-new', $screen, 'the button that creates one');
        $this->assertStringContainsString('id="yw-preset-rail"', $screen, 'the rail that edits one');
        // the rail is the same docked panel the actions builder and file picker use
        $this->assertStringContainsString('yw-designer__sidebar', $screen);
        // one input per variable, named as the CSS custom property it writes
        $this->assertStringContainsString('name="primary-color"', $screen);
        $this->assertStringContainsString('name="main-title-fontfamily"', $screen);
    }

    /**
     * What left the screen, asserted so it cannot drift back.
     *
     * The theme/squelette/style selector is going to the configuration file and per-page
     * themes are a property of the content -- both are somebody else's screen now, and
     * either one reappearing here is the reorganisation quietly coming undone.
     */
    public function testTheScreenNoLongerCarriesTheThemeSelectors(): void
    {
        $screen = $this->screen();

        $this->assertStringNotContainsString('preset-sidenav', $screen, 'the old theme selector');
        $this->assertStringNotContainsString('adminthemes', $screen, 'the per-page theme table');
        $this->assertStringNotContainsString('setwikidefaulttheme', $screen, 'the default-theme form');
    }

    /** Every preset the wiki ships is offered, plus the way back to no preset at all. */
    public function testEveryShippedPresetIsListed(): void
    {
        $screen = $this->screen();

        foreach (['default', 'fun', 'landes', 'red', 'yellow'] as $preset) {
            $this->assertStringContainsString('value="' . $preset . '.css"', $screen, "the $preset preset");
        }
        $this->assertStringContainsString(_t('ADMIN_PRESET_NONE'), $screen, 'the way back to the theme\'s own colours');
    }

    /**
     * Which preset the wiki wears is said by a filled star, and by exactly one of them.
     *
     * The star is on every card -- outline on the others -- because a state read off a mark
     * present on one card and absent on the rest is a state nobody sees. Two filled stars
     * would mean the screen is claiming the wiki wears two presets at once.
     */
    public function testExactlyOneStarIsFilled(): void
    {
        $screen = $this->screen();

        $this->assertSame(
            1,
            substr_count($screen, 'yw-preset-card__star--on'),
            'one filled star: the preset the wiki wears, or the "no preset" card when it wears none'
        );
        $this->assertStringContainsString(
            'icons.svg#star-filled',
            $screen,
            'filled and outline are two different symbols, not the same one tinted'
        );
    }

    public function testAnActionThatNoLongerExistsWouldBeNoticed(): void
    {
        // how Performer reports an unknown action -- the gallery must contain no such report
        $this->assertStringNotContainsString('does not exist', $this->screen());
        $this->assertStringNotContainsString('INVALID_ACTION', $this->screen());
    }

    private function screen(): string
    {
        if (self::$rendered !== null) {
            return self::$rendered;
        }

        $wiki = $this->getWiki();
        $GLOBALS['yeswikiServices'] = $wiki->services;
        $acl = $wiki->services->get(AclService::class);
        $admin = current(array_filter(
            $wiki->services->get(UserManager::class)->getAll(),
            fn ($user) => $acl->isAdmin($user['name'])
        ));
        if ($admin === false) {
            $this->markTestSkipped('the preset screen needs an admin to render for');
        }

        $authentication = $wiki->services->get(AuthenticationService::class);
        $authentication->login($admin);

        try {
            $wiki->services->get(CurrentRequest::class)->replace(Request::create('/?admin/preset'));

            return self::$rendered = (string)$wiki->services->get(AdminController::class)->preset()->getContent();
        } finally {
            $authentication->logout();
        }
    }
}
