<?php

namespace YesWiki\Test\Core\Service;

use PHPUnit\Framework\Attributes\Depends;
use Symfony\Component\HttpFoundation\Request;
use YesWiki\Content\Entity\ContentTypeSchema;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Exception\ExitException;
use YesWiki\Search\Service\TagsManager;
use YesWiki\Test\Core\YesWikiTestCase;
use YesWiki\YesWikiRuntime;

require_once 'tests/YesWikiTestCase.php';

/**
 * The page editor edits the Page form, not just the markup.
 *
 * A page is a form like any other since ticket 10, but its editor still only knew about
 * prose: there was no input for the page's own title, and keywords were hand-built HTML
 * in the handler rather than the `tags` field the form declares. So a page could not be
 * given a title at all -- which is why a bazar list of Pages showed a column of blanks --
 * and a webmaster who added a field to the Page form got nowhere to fill it in.
 */
class PageEditFormFieldsTest extends YesWikiTestCase
{
    private const PAGE_TAG = 'PageEditFormFieldsRegressionPage';

    public function testWikiExisting(): YesWikiRuntime
    {
        $wiki = $this->getWiki();
        $this->assertTrue($wiki->services->has(YesWikiRuntime::class));

        return $wiki->services->get(YesWikiRuntime::class);
    }

    #[Depends('testWikiExisting')]
    public function testTheEditorRendersAndSavesThePageFormsOwnFields(YesWikiRuntime $wiki): void
    {
        $pageManager = $wiki->services->get(PageManager::class);
        $authenticationService = $wiki->services->get(AuthenticationService::class);
        $performer = $wiki->services->get(\YesWiki\Kernel\Service\Performer::class);
        $currentRequest = $wiki->services->get(\YesWiki\Kernel\Service\CurrentRequest::class);
        $pageContext = $wiki->services->get(\YesWiki\Kernel\Service\PageContext::class);

        // the Page form must actually declare the fields this test is about
        $form = $wiki->services->get(FormManager::class)->getByContentType(ContentTypeSchema::TYPE_PAGE);
        $this->assertNotNull($form, 'the Page form should exist -- run ./yeswicli migrate');
        $this->assertContains(PageBody::TITLE, array_column($form['template'], 'name'));
        $this->assertContains(PageBody::KEYWORDS, array_column($form['template'], 'name'));

        $pageManager->save(self::PAGE_TAG, [
            PageBody::CONTENT => 'contenu initial',
            PageBody::TITLE => 'Titre initial',
            PageBody::KEYWORDS => ['premier'],
        ], '', true);
        $page = $pageManager->getOne(self::PAGE_TAG);
        $this->assertIsArray($page);

        $admin = current(array_filter(
            $wiki->services->get(UserManager::class)->getAll(),
            fn ($u) => $wiki->services->get(\YesWiki\Identity\Service\AclService::class)->isAdmin($u['name'])
        ));
        $this->assertNotFalse($admin, 'need an existing admin user to exercise write access');
        $authenticationService->login($admin);

        $pageContext->setTag(self::PAGE_TAG);
        $pageContext->setPage($page);
        // {{aceditor}} reaches for the global service container (see EditHandlerSaveTest)
        $GLOBALS['yeswikiServices'] = $wiki->services;

        try {
            $_POST = [];
            $currentRequest->replace(Request::createFromGlobals());
            $output = $performer->run('edit', 'handler', []);

            $this->assertMatchesRegularExpression(
                '/<input[^>]*value="Titre initial"[^>]*name="' . PageBody::TITLE . '"/s',
                $output,
                'the page\'s own title must be editable, filled with what is stored'
            );
            $this->assertStringContainsString('data-yw-tag-input', $output, 'keywords must render as the tags field');
            $this->assertMatchesRegularExpression(
                '/<input[^>]*name="' . PageBody::KEYWORDS . '"[^>]*value="premier"/',
                $output,
                'the tags field must post under the name the Page form gives it'
            );

            $_POST = [
                'submit' => 'Sauver',
                'previous' => $page['id'],
                'body' => 'contenu initial',
                PageBody::TITLE => 'Titre modifié',
                PageBody::KEYWORDS => 'alpha, beta',
            ];
            $currentRequest->replace(Request::createFromGlobals());
            $redirected = false;
            try {
                $performer->run('edit', 'handler', []);
            } catch (ExitException $e) {
                $redirected = true;
            }
            // retitling a page without touching its prose is a real change: the old
            // "nothing changed" test only compared the markup, so this save was dropped
            $this->assertTrue($redirected, 'a title-only change must save, not be dismissed as no change');

            $reloaded = $pageManager->getOne(self::PAGE_TAG);
            $this->assertIsArray($reloaded);
            $this->assertSame('Titre modifié', $reloaded['body'][PageBody::TITLE]);
            $this->assertSame('contenu initial', trim(PageBody::content($reloaded['body'])));
            $this->assertSame(['alpha', 'beta'], TagsManager::keywordsOf($reloaded), 'keywords are a list in the body');

            // ... and the derived reverse index follows the body it was saved with
            $indexed = array_column($wiki->services->get(TagsManager::class)->getAll(self::PAGE_TAG), 'value');
            sort($indexed);
            $this->assertSame(['alpha', 'beta'], $indexed);
        } finally {
            $pageManager->deleteOrphaned(self::PAGE_TAG);
            $wiki->services->get(TagsManager::class)->reindex(self::PAGE_TAG, []);
            $authenticationService->logout();
            $_POST = [];
        }
    }
}
