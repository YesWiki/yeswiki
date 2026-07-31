<?php

namespace YesWiki\Test\Core\Controller;

use PHPUnit\Framework\Attributes\CoversMethod;
use Symfony\Component\HttpFoundation\Request;
use YesWiki\Content\Api\FormApiController;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Test\Core\YesWikiTestCase;
use YesWiki\YesWikiRuntime;

require_once 'tests/YesWikiTestCase.php';

/**
 * The form designer's cards preview a field by rendering its real entry-form input, so the
 * answer must be the actual Twig markup.
 *
 * Ticket 14 changed the shape of that answer from JSON to markup: one htmx out-of-band swap
 * per field, addressed by the designer's own card id, plus one carrying the assets the input
 * templates declared while rendering. Addressing by id is what removed the old positional
 * coupling -- an unbuildable field used to shift every card after it.
 */
#[CoversMethod(FormApiController::class, 'previewFormTemplate')]
class ApiControllerFormPreviewTest extends YesWikiTestCase
{
    /**
     * @param list<array<string,mixed>> $template
     * @param list<string>|null         $ids
     */
    private function preview(YesWikiRuntime $wiki, array $template, ?array $ids = null): string
    {
        $controller = $wiki->services->get(FormApiController::class);
        $request = Request::create('/api/forms/preview', 'POST', [
            'template' => json_encode($template),
            'ids' => json_encode($ids ?? array_map(fn ($i) => "fbf$i", array_keys($template))),
        ]);

        return (string)$controller->previewFormTemplate($request)->getContent();
    }

    /** The markup htmx would swap into the card with this id. */
    private static function swapFor(string $html, string $id): string
    {
        $open = '<div hx-swap-oob="innerHTML:#yw-fb-preview-' . $id . '">';
        $start = strpos($html, $open);
        if ($start === false) {
            return '';
        }
        $start += strlen($open);

        return substr($html, $start, strpos($html, '</div>', $start) - $start);
    }

    public function testWikiExisting(): YesWikiRuntime
    {
        $wiki = $this->getWiki();
        $this->assertTrue($wiki->services->has(FormApiController::class));

        return $wiki;
    }

    #[\PHPUnit\Framework\Attributes\Depends('testWikiExisting')]
    public function testRendersTheFieldsRealInputMarkup(YesWikiRuntime $wiki): void
    {
        $answer = $this->preview($wiki, [[
            'type' => 'texte',
            'name' => 'bf_titre',
            'label' => 'Titre de la fiche',
            'sub_type' => 'text',
            'max_chars' => '42',
            'required' => '1',
        ]]);

        $html = self::swapFor($answer, 'fbf0');
        // the whole @core/inputs/text.twig output, not just a bare input: label,
        // required marker and the attributes the field configures
        $this->assertStringContainsString('Titre de la fiche', $html);
        $this->assertStringContainsString('name="bf_titre"', $html);
        $this->assertStringContainsString('maxlength="42"', $html);
        $this->assertStringContainsString('required', $html);
    }

    #[\PHPUnit\Framework\Attributes\Depends('testWikiExisting')]
    public function testAnUnrenderableFieldCannotDisturbTheOthers(YesWikiRuntime $wiki): void
    {
        $answer = $this->preview($wiki, [
            ['name' => 'bf_typeless'], // dropped by prepareData()
            ['type' => 'nosuchfieldtype', 'name' => 'bf_unknown'],
            ['type' => 'texte', 'name' => 'bf_last', 'label' => 'Last', 'sub_type' => 'text'],
        ]);

        // every card is addressed, and the two that cannot render simply clear theirs
        $this->assertSame('', self::swapFor($answer, 'fbf0'));
        $this->assertSame('', self::swapFor($answer, 'fbf1'));
        $this->assertStringContainsString('name="bf_last"', self::swapFor($answer, 'fbf2'));
    }

    #[\PHPUnit\Framework\Attributes\Depends('testWikiExisting')]
    public function testCardsAreAddressedByIdNotByPosition(YesWikiRuntime $wiki): void
    {
        $answer = $this->preview(
            $wiki,
            [['type' => 'texte', 'name' => 'bf_only', 'label' => 'Only', 'sub_type' => 'text']],
            ['card-XYZ']
        );

        $this->assertStringContainsString('name="bf_only"', self::swapFor($answer, 'card-XYZ'));
    }

    /** A client-supplied id lands in a CSS selector, so anything but [A-Za-z0-9_-] is refused. */
    #[\PHPUnit\Framework\Attributes\Depends('testWikiExisting')]
    public function testRefusesAnIdThatCouldEscapeTheSelector(YesWikiRuntime $wiki): void
    {
        $answer = $this->preview(
            $wiki,
            [['type' => 'texte', 'name' => 'bf_x', 'label' => 'X', 'sub_type' => 'text']],
            ['"><script>alert(1)</script>']
        );

        $this->assertStringNotContainsString('<script>alert(1)</script>', $answer);
        $this->assertStringNotContainsString('name="bf_x"', $answer);
    }

    #[\PHPUnit\Framework\Attributes\Depends('testWikiExisting')]
    public function testCarriesTheAssetsTheInputTemplatesDeclared(YesWikiRuntime $wiki): void
    {
        $answer = $this->preview($wiki, [[
            'type' => 'fichier',
            'name' => 'bf_fichier',
            'label' => 'Un fichier',
        ]]);

        // @core/inputs/file.twig declares its own stylesheet; the designer page only learns
        // about it through this field
        $this->assertStringContainsString('styles/bazar/inputs/file.css', $answer);
        // ...and it arrives as an out-of-band swap into <head>, so its lifetime is not the
        // preview card's: deleting the card must not unstyle the other previews
        $this->assertStringContainsString('hx-swap-oob="beforeend:head"', $answer);
    }

    /**
     * The reason this ticket exists. A map input declares four scripts; before ticket 14
     * they were appended to $GLOBALS['js'] and flushed by a page footer that an API response
     * never renders, so the preview arrived as markup with no leaflet behind it.
     */
    #[\PHPUnit\Framework\Attributes\Depends('testWikiExisting')]
    public function testAMapPreviewCarriesLeaflet(YesWikiRuntime $wiki): void
    {
        $answer = $this->preview($wiki, [[
            'type' => 'carte_google',
            'name' => 'bf_carte',
            'label' => 'Une carte',
        ]]);

        $this->assertStringContainsString('geocode-input', self::swapFor($answer, 'fbf0'), 'the map markup itself');
        $this->assertStringContainsString('javascripts/vendor/leaflet/leaflet.min.js', $answer);
        $this->assertStringContainsString('javascripts/inputs/map-leaflet.js', $answer);
        $this->assertStringContainsString('styles/vendor/leaflet/leaflet.css', $answer);
    }

    /**
     * Regression: routed controllers are instantiated by YesWikiControllerResolver with
     * `new $class()`, so an endpoint declared on a constructor-injected controller dies
     * with an ArgumentCountError at dispatch time -- which calling the method directly,
     * as the tests above do, never shows. Exercise the whole api pipeline instead:
     * querystring -> route match -> ACL -> kernel -> argument resolution.
     */
    #[\PHPUnit\Framework\Attributes\Depends('testWikiExisting')]
    public function testTheRouteDispatchesThroughTheWholeApiPipeline(YesWikiRuntime $wiki): void
    {
        $userManager = $wiki->services->get(UserManager::class);
        $admin = current(array_filter($userManager->getAll(), fn ($u) => $wiki->services->get(\YesWiki\Identity\Service\AclService::class)->isAdmin($u['name'])));
        $this->assertNotFalse($admin, 'need an existing admin user: the route is @admins only');

        $authenticationService = $wiki->services->get(AuthenticationService::class);
        $previousRequest = $wiki->services->get(\YesWiki\Kernel\Service\CurrentRequest::class)->get();
        $previousWikiParam = $_GET['wiki'] ?? null;
        $previousMethod = $_SERVER['REQUEST_METHOD'] ?? null; // unset under CLI
        $runSpecialPages = new \ReflectionMethod($wiki, 'RunSpecialPages');

        try {
            $authenticationService->login($admin);
            $wiki->services->get(\YesWiki\Kernel\Service\CurrentRequest::class)->replace(Request::create('/?api/forms/preview', 'POST', [
                'template' => json_encode([['type' => 'texte', 'name' => 'bf_titre', 'label' => 'Titre', 'sub_type' => 'text']]),
                'ids' => json_encode(['fbf0']),
            ]));
            $_GET['wiki'] = 'api/forms/preview';
            $_SERVER['REQUEST_METHOD'] = 'POST';

            ob_start();
            try {
                $runSpecialPages->invoke($wiki);
            } finally {
                $sent = ob_get_clean();
            }
        } finally {
            $authenticationService->logout();
            $wiki->services->get(\YesWiki\Kernel\Service\CurrentRequest::class)->replace($previousRequest);
            if ($previousWikiParam === null) {
                unset($_GET['wiki']);
            } else {
                $_GET['wiki'] = $previousWikiParam;
            }
            if ($previousMethod === null) {
                unset($_SERVER['REQUEST_METHOD']);
            } else {
                $_SERVER['REQUEST_METHOD'] = $previousMethod;
            }
        }

        $this->assertStringContainsString('name="bf_titre"', self::swapFor($sent, 'fbf0'), "endpoint answered: $sent");
    }

    #[\PHPUnit\Framework\Attributes\Depends('testWikiExisting')]
    public function testRejectsATemplateThatIsNotAJsonArray(YesWikiRuntime $wiki): void
    {
        $controller = $wiki->services->get(FormApiController::class);
        $request = Request::create('/api/forms/preview', 'POST', ['template' => 'not json', 'ids' => '[]']);

        $this->assertSame(400, $controller->previewFormTemplate($request)->getStatusCode());
    }

    /** Without an id per field nothing can be addressed, so the request is a client error. */
    #[\PHPUnit\Framework\Attributes\Depends('testWikiExisting')]
    public function testRejectsIdsThatDoNotMatchTheTemplateLength(YesWikiRuntime $wiki): void
    {
        $controller = $wiki->services->get(FormApiController::class);
        $request = Request::create('/api/forms/preview', 'POST', [
            'template' => json_encode([['type' => 'texte', 'name' => 'bf_a'], ['type' => 'texte', 'name' => 'bf_b']]),
            'ids' => json_encode(['only-one']),
        ]);

        $this->assertSame(400, $controller->previewFormTemplate($request)->getStatusCode());
    }
}
