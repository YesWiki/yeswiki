<?php

namespace YesWiki\Test\Core\Service;

use PHPUnit\Framework\Attributes\Depends;
use Symfony\Component\HttpFoundation\Request;
use YesWiki\Content\Entity\ContentTypeSchema;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiRuntime;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Exception\ExitException;
use YesWiki\Search\Service\SearchIndexer;
use YesWiki\Search\Service\TagsManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** The page editor edits the Page form, not just the markup. */
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
        $performer = $wiki->services->get(\YesWiki\Render\Service\Performer::class);
        $currentRequest = $wiki->services->get(\YesWiki\Kernel\Service\CurrentRequest::class);
        $previousRequest = $currentRequest->get();
        $pageContext = $wiki->services->get(\YesWiki\Kernel\Service\PageContext::class);

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

            $this->assertTrue($redirected, 'a title-only change must save, not be dismissed as no change');

            $reloaded = $pageManager->getOne(self::PAGE_TAG);
            $this->assertIsArray($reloaded);
            $this->assertSame('Titre modifié', $reloaded['body'][PageBody::TITLE]);
            $this->assertSame('contenu initial', trim(PageBody::content($reloaded['body'])));
            $this->assertSame(['alpha', 'beta'], TagsManager::keywordsOf($reloaded), 'keywords are a list in the body');

            $wiki->services->get(SearchIndexer::class)->index(self::PAGE_TAG);
            $indexed = array_column(
                $wiki->services->get(TagsManager::class)->pairs(['alpha', 'beta']),
                'keyword',
                'keyword'
            );
            ksort($indexed);
            $this->assertSame(['alpha' => 'alpha', 'beta' => 'beta'], $indexed);
        } finally {
            $pageManager->deleteOrphaned(self::PAGE_TAG);
            $wiki->services->get(SearchIndexer::class)->delete(self::PAGE_TAG);
            $authenticationService->logout();
            // Shared with every spec after this one: a leftover `submit` makes the next edit
            // screen think it is handling a save.
            $_POST = [];
            $currentRequest->replace($previousRequest);
        }
    }

    /** ... */
    #[Depends('testWikiExisting')]
    public function testANewPageIsOfferedTheSameFieldsBeforeItExists(YesWikiRuntime $wiki): void
    {
        $pageManager = $wiki->services->get(PageManager::class);
        $authenticationService = $wiki->services->get(AuthenticationService::class);
        $performer = $wiki->services->get(\YesWiki\Render\Service\Performer::class);
        $currentRequest = $wiki->services->get(\YesWiki\Kernel\Service\CurrentRequest::class);
        $previousRequest = $currentRequest->get();
        $pageContext = $wiki->services->get(\YesWiki\Kernel\Service\PageContext::class);

        $tag = self::PAGE_TAG . 'ThatDoesNotExistYet';

        $this->assertNull($pageManager->typeOf($tag), 'the fixture must start with no such row');

        $admin = current(array_filter(
            $wiki->services->get(UserManager::class)->getAll(),
            fn ($u) => $wiki->services->get(\YesWiki\Identity\Service\AclService::class)->isAdmin($u['name'])
        ));
        $this->assertNotFalse($admin, 'need an existing admin user to exercise write access');
        $authenticationService->login($admin);

        $pageContext->setTag($tag);
        $pageContext->setPage(null);
        $GLOBALS['yeswikiServices'] = $wiki->services;

        try {
            $_POST = [];
            $currentRequest->replace(Request::createFromGlobals());
            $output = $performer->run('edit', 'handler', []);

            $this->assertMatchesRegularExpression(
                '/<input[^>]*name="' . PageBody::TITLE . '"/s',
                $output,
                'a page being created must be offered its title, not only once it exists'
            );
            $this->assertStringContainsString(
                'data-yw-tag-input',
                $output,
                'a page being created must be offered its keywords too'
            );

            $_POST = [
                'submit' => 'Sauver',
                'body' => 'tout premier contenu',
                PageBody::TITLE => 'Titre a la creation',
                PageBody::KEYWORDS => 'neuf, page',
            ];
            $currentRequest->replace(Request::createFromGlobals());
            try {
                $performer->run('edit', 'handler', []);
            } catch (ExitException $e) {
            }

            $created = $pageManager->getOne($tag);
            $this->assertIsArray($created, 'the first save must create the page');
            $this->assertSame('Titre a la creation', $created['body'][PageBody::TITLE]);
            $this->assertSame('tout premier contenu', trim(PageBody::content($created['body'])));
            $this->assertSame(['neuf', 'page'], TagsManager::keywordsOf($created));
        } finally {
            $pageManager->deleteOrphaned($tag);
            $wiki->services->get(SearchIndexer::class)->delete($tag);
            $authenticationService->logout();
            // Shared with every spec after this one: a leftover `submit` makes the next edit
            // screen think it is handling a save.
            $_POST = [];
            $currentRequest->replace($previousRequest);
        }
    }
}
