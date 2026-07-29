<?php

namespace YesWiki\Test\Core\Controller;

use PHPUnit\Framework\Attributes\CoversMethod;
use Symfony\Component\HttpFoundation\Request;
use YesWiki\Admin\Controller\ApiController;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Test\Core\YesWikiTestCase;
use YesWiki\Wiki;

require_once 'tests/YesWikiTestCase.php';

/**
 * The form designer's cards preview a field by rendering its real entry-form input
 * (ApiController::previewFormTemplate), so the answer must be the actual Twig markup and
 * must stay positionally aligned with the posted template -- the designer maps
 * `previews[i]` back onto the card of the i-th field it sent.
 */
#[CoversMethod(ApiController::class, 'previewFormTemplate')]
class ApiControllerFormPreviewTest extends YesWikiTestCase
{
    private function preview(Wiki $wiki, array $template): array
    {
        $controller = $wiki->services->get(ApiController::class);
        $request = Request::create('/api/forms/preview', 'POST', [
            'template' => json_encode($template),
        ]);

        return json_decode($controller->previewFormTemplate($request)->getContent(), true);
    }

    public function testWikiExisting(): Wiki
    {
        $wiki = $this->getWiki();
        $this->assertTrue($wiki->services->has(ApiController::class));

        return $wiki;
    }

    #[\PHPUnit\Framework\Attributes\Depends('testWikiExisting')]
    public function testRendersTheFieldsRealInputMarkup(Wiki $wiki)
    {
        $answer = $this->preview($wiki, [[
            'type' => 'texte',
            'name' => 'bf_titre',
            'label' => 'Titre de la fiche',
            'sub_type' => 'text',
            'max_chars' => '42',
            'required' => '1',
        ]]);

        $this->assertCount(1, $answer['previews']);
        $html = $answer['previews'][0];
        // the whole @core/inputs/text.twig output, not just a bare input: label,
        // required marker and the attributes the field configures
        $this->assertStringContainsString('Titre de la fiche', $html);
        $this->assertStringContainsString('name="bf_titre"', $html);
        $this->assertStringContainsString('maxlength="42"', $html);
        $this->assertStringContainsString('required', $html);
    }

    #[\PHPUnit\Framework\Attributes\Depends('testWikiExisting')]
    public function testUnrenderableFieldsKeepTheAnswerAlignedWithThePostedTemplate(Wiki $wiki)
    {
        $answer = $this->preview($wiki, [
            ['name' => 'bf_typeless'], // dropped by prepareData()
            ['type' => 'nosuchfieldtype', 'name' => 'bf_unknown'],
            ['type' => 'texte', 'name' => 'bf_last', 'label' => 'Last', 'sub_type' => 'text'],
        ]);

        $this->assertCount(3, $answer['previews']);
        $this->assertSame('', $answer['previews'][0]);
        $this->assertSame('', $answer['previews'][1]);
        $this->assertStringContainsString('name="bf_last"', $answer['previews'][2]);
    }

    #[\PHPUnit\Framework\Attributes\Depends('testWikiExisting')]
    public function testReportsTheStylesheetsTheInputTemplatesRegistered(Wiki $wiki)
    {
        $answer = $this->preview($wiki, [[
            'type' => 'fichier',
            'name' => 'bf_fichier',
            'label' => 'Un fichier',
        ]]);

        // @core/inputs/file.twig include_css()es its own stylesheet; the designer page
        // only knows about it through this field
        $this->assertStringContainsString('styles/bazar/inputs/file.css', $answer['styles']);
    }

    /**
     * Regression: routed controllers are instantiated by YesWikiControllerResolver with
     * `new $class()`, so an endpoint declared on a constructor-injected controller dies
     * with an ArgumentCountError at dispatch time -- which calling the method directly,
     * as the tests above do, never shows. Exercise the whole api pipeline instead:
     * querystring -> route match -> ACL -> kernel -> argument resolution.
     */
    #[\PHPUnit\Framework\Attributes\Depends('testWikiExisting')]
    public function testTheRouteDispatchesThroughTheWholeApiPipeline(Wiki $wiki)
    {
        $userManager = $wiki->services->get(UserManager::class);
        $admin = current(array_filter($userManager->getAll(), fn ($u) => $wiki->services->get(\YesWiki\Identity\Service\AclService::class)->isAdmin($u['name'])));
        $this->assertNotFalse($admin, 'need an existing admin user: the route is @admins only');

        $authenticationService = $wiki->services->get(AuthenticationService::class);
        $previousRequest = $wiki->request;
        $previousWikiParam = $_GET['wiki'] ?? null;
        $previousMethod = $_SERVER['REQUEST_METHOD'] ?? null; // unset under CLI
        $runSpecialPages = new \ReflectionMethod($wiki, 'RunSpecialPages');

        try {
            $authenticationService->login($admin);
            $wiki->request = Request::create('/?api/forms/preview', 'POST', [
                'template' => json_encode([['type' => 'texte', 'name' => 'bf_titre', 'label' => 'Titre', 'sub_type' => 'text']]),
            ]);
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
            $wiki->request = $previousRequest;
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

        $answer = json_decode($sent, true);
        $this->assertIsArray($answer, "the endpoint answered with non-JSON: $sent");
        $this->assertArrayNotHasKey('rawOutput', $answer, 'the endpoint leaked output beside its JSON body');
        $this->assertStringContainsString('name="bf_titre"', $answer['previews'][0] ?? '');
    }

    #[\PHPUnit\Framework\Attributes\Depends('testWikiExisting')]
    public function testRejectsATemplateThatIsNotAJsonArray(Wiki $wiki)
    {
        $controller = $wiki->services->get(ApiController::class);
        $request = Request::create('/api/forms/preview', 'POST', ['template' => 'not json']);

        $this->assertSame(400, $controller->previewFormTemplate($request)->getStatusCode());
    }
}
