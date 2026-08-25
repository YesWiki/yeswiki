<?php

namespace YesWiki\Test\Render;

use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Depends;
use YesWiki\Content\Controller\EntryController;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiRuntime;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Service\LanguageService;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Render\Service\Performer;
use YesWiki\Render\Service\TemplateEngine;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** `{{editbar}}`: the actions in the corner, the facts at the bottom. */
class EditBarTest extends YesWikiTestCase
{
    /**
     * One page per test, rather than one for the class: `AclService` remembers its answer for a tag, so a page one test closes to `@admins` is still closed for the next one that saves a fresh page under the same name -- which renders no bar at all, and an assertion that something is *absent* from an empty string passes for the wrong reason.
     */
    private const CLUSTER_PAGE = 'EditBarClusterPage';
    private const READER_PAGE = 'EditBarReaderPage';
    private const DATES_PAGE = 'EditBarDatesPage';
    private const FOOTER_PAGE = 'EditBarFooterPage';
    private const EDITING_PAGE = 'EditBarEditingPage';
    private const READER = 'EditBarReaderProbe';

    public function testWikiExisting(): YesWikiRuntime
    {
        $wiki = $this->getWiki();
        $GLOBALS['yeswikiServices'] = $wiki->services;
        $this->assertTrue($wiki->services->has(YesWikiRuntime::class));

        return $wiki->services->get(YesWikiRuntime::class);
    }

    #[Depends('testWikiExisting')]
    public function testTheClusterIsOfferedOnlyToSomeoneWhoCanWrite(YesWikiRuntime $wiki): void
    {
        $pageManager = $wiki->services->get(PageManager::class);
        $authentication = $wiki->services->get(AuthenticationService::class);

        $pageManager->save(self::CLUSTER_PAGE, [PageBody::CONTENT => 'contenu'], '', true);

        $wiki->services->get(AclService::class)->save(self::CLUSTER_PAGE, 'write', '*');

        try {
            $writable = $this->renderBar($wiki, self::CLUSTER_PAGE);
            $this->assertStringContainsString('yw-page-actions', $writable, 'an editor gets the cluster');
            $this->assertStringContainsString('yw-page-info', $writable, 'and the facts at the bottom');

            $this->assertStringContainsString('yw-page-actions__more', $writable);
            $this->assertStringNotContainsString('yw-page-info__actions', $writable);

            $wiki->services->get(AclService::class)->save(self::CLUSTER_PAGE, 'write', '@admins');
            $authentication->logout();

            $readable = $this->renderBar($wiki, self::CLUSTER_PAGE);
            $this->assertStringNotContainsString(
                'yw-page-actions',
                $readable,
                'a reader who cannot edit must not be offered the edit cluster'
            );
        } finally {
            $authentication->logout();
            $pageManager->deleteOrphaned(self::CLUSTER_PAGE);
        }
    }

    /** ...but a signed-in reader still gets the two things a reader wants. */
    #[Depends('testWikiExisting')]
    public function testASignedInReaderKeepsSharingAndTheStar(YesWikiRuntime $wiki): void
    {
        $pageManager = $wiki->services->get(PageManager::class);
        $userManager = $wiki->services->get(UserManager::class);
        $authentication = $wiki->services->get(AuthenticationService::class);

        $pageManager->save(self::READER_PAGE, [PageBody::CONTENT => 'contenu'], '', true);
        $wiki->services->get(AclService::class)->save(self::READER_PAGE, 'write', '@admins');

        $reader = $userManager->getOneByName(self::READER);
        if (empty($reader)) {
            $userManager->create(self::READER, 'editbar-reader@example.tld', 'Aa1!aaaaProbe');
            $reader = $userManager->getOneByName(self::READER);
        }
        $this->assertInstanceOf(\YesWiki\Identity\Entity\User::class, $reader);

        try {
            $authentication->login($reader);
            $rendered = $this->renderBar($wiki, self::READER_PAGE);

            $this->assertStringNotContainsString('yw-page-actions', $rendered, 'nothing to edit, nothing floating');
            $this->assertStringContainsString('link-share', $rendered, 'sharing is not an editor-only verb');
            $this->assertStringContainsString('yw-page-info__actions', $rendered);
        } finally {
            $authentication->logout();
            $pageManager->deleteOrphaned(self::READER_PAGE);
            $probe = $userManager->getOneByName(self::READER);
            if (!empty($probe)) {
                $userManager->delete($probe);
            }
        }
    }

    /** Neither bar says what the Content does not store. */
    #[Depends('testWikiExisting')]
    public function testNeitherBarInventsDatesTheContentDoesNotKeep(YesWikiRuntime $wiki): void
    {
        $pageManager = $wiki->services->get(PageManager::class);
        $pageContext = $wiki->services->get(PageContext::class);
        $previousTag = $pageContext->getTag();

        $pageManager->save(self::DATES_PAGE, [PageBody::CONTENT => 'contenu'], '', true);
        $this->assertArrayNotHasKey(
            'created_at',
            (array)($pageManager->getOne(self::DATES_PAGE)['body'] ?? []),
            'the premise: a page stores no creation date'
        );

        try {
            $rendered = $this->renderBar($wiki, self::DATES_PAGE);
            $this->assertNotSame('', $rendered, 'the premise: this visitor gets a bar at all');

            $createdAt = $pageManager->getCreateTime(self::DATES_PAGE);
            $this->assertNotNull($createdAt);
            $expected = Carbon::parse($createdAt);
            $expected->locale(LanguageService::getInstance()->preferredLanguage());
            $this->assertStringContainsString(
                $expected->isoFormat('LLL'),
                $rendered,
                "a page's creation date is the date of its first revision, written in the reader's language"
            );

            $pageContext->setTag('SomeOtherPage');
            $embedded = (string)$wiki->services->get(EntryController::class)->view(self::DATES_PAGE);
            $this->assertStringContainsString('BAZ_fiche_info', $embedded, 'the premise: this footer renders here');
            $this->assertStringNotContainsString('date_creation', $embedded);
            $this->assertStringNotContainsString('date_mise_a_jour', $embedded);
        } finally {
            $pageContext->setTag($previousTag);
            $pageManager->deleteOrphaned(self::DATES_PAGE);
        }
    }

    /**
     * On an edit screen the corner belongs to the editor: save, preview when this wiki asks for one, delete.
     */
    #[Depends('testWikiExisting')]
    public function testTheEditorOwnsTheCornerWhileYouAreEditing(YesWikiRuntime $wiki): void
    {
        $pageManager = $wiki->services->get(PageManager::class);
        $pageContext = $wiki->services->get(PageContext::class);
        $previousTag = $pageContext->getTag();
        $previousMethod = $pageContext->getRawMethod();

        $pageManager->save(self::EDITING_PAGE, [PageBody::CONTENT => 'contenu'], '', true);
        $wiki->services->get(AclService::class)->save(self::EDITING_PAGE, 'write', '*');

        try {
            $pageContext->setTag(self::EDITING_PAGE);
            $pageContext->setPage($pageManager->getOne(self::EDITING_PAGE));
            $pageContext->setMethod('edit');

            $bar = (string)$wiki->services->get(Performer::class)->run('editbar', 'action', []);
            $this->assertStringNotContainsString(
                'yw-page-actions',
                $bar,
                'the edit bar renders no cluster on an edit screen -- the editor renders its own'
            );
            $this->assertStringContainsString('yw-page-info', $bar, 'the facts still belong at the bottom');
        } finally {
            $pageContext->setMethod($previousMethod);
            $pageContext->setTag($previousTag);
            $pageManager->deleteOrphaned(self::EDITING_PAGE);
        }
    }

    /** ...and the preview button appears exactly when the wiki asks for a preview. */
    #[Depends('testWikiExisting')]
    public function testThePreviewButtonIsOfferedOnlyWhenTheWikiAsksForOne(YesWikiRuntime $wiki): void
    {
        $engine = $wiki->services->get(TemplateEngine::class);
        $config = $wiki->services->get(RuntimeConfig::class);

        $before = $config['preview_before_save'] ?? false;

        $editor = fn () => (string)$engine->render('@core/handlers/edit.twig', [
            'previous' => '1',
            'handler' => 'edit',
            'cancelUrl' => '/',
            'body' => '',
            'saveValue' => 'Sauver',
            'deleteUrl' => '/?SomePage/deletepage',
            'preview' => false,
            'hasContent' => false,
            'fieldsBeforeContent' => [],
            'fieldsAfterContent' => [],
        ]);

        try {
            $config['preview_before_save'] = true;
            $withPreview = $editor();
            $this->assertStringContainsString('name="submit" value="preview"', $withPreview);
            $this->assertStringContainsString('value="Sauver"', $withPreview, 'saving still means saving');

            $config['preview_before_save'] = false;
            $withoutPreview = $editor();
            $this->assertStringNotContainsString('name="submit" value="preview"', $withoutPreview);

            $this->assertMatchesRegularExpression(
                '/<a href="[^"]+"[^>]*class="[^"]*link-cancel/',
                $withoutPreview
            );

            foreach (['value="Sauver"', 'link-cancel'] as $action) {
                $this->assertSame(2, substr_count($withoutPreview, $action), "{$action} belongs in both places");
            }

            $this->assertSame(
                1,
                substr_count($withoutPreview, 'link-deletepage'),
                'delete belongs in the floating cluster only'
            );
            $this->assertStringContainsString('class="form-actions"', $withoutPreview);
        } finally {
            $config['preview_before_save'] = $before;
        }
    }

    /** The line of facts holds a dropdown, so it cannot be a paragraph. */
    #[Depends('testWikiExisting')]
    public function testTheLineOfFactsIsNotAParagraph(YesWikiRuntime $wiki): void
    {
        $rendered = (string)$wiki->services->get(TemplateEngine::class)->render(
            '@core/barreredaction_basic.twig',
            [
                'class' => 'footer',
                'page' => 'SomePage',
                'linkshare' => '/?SomePage/share',
                'linkduplicate' => '/?SomePage/duplicate',
                'userIsAdmin' => true,
                'userIsAdminOrOwner' => true,
                'author' => 'someone',
                'contentLabel' => 'Pages',

                'linkopencomments' => '/?SomePage/claim&action=opencomments',
                'wikigroups' => ['admins'],
            ]
        );

        $this->assertStringContainsString('dropdown-menu', $rendered, 'the premise: the dropdown is here');
        $this->assertStringNotContainsString(
            '<p',
            $rendered,
            'a paragraph cannot hold the comments dropdown: the parser would evict its <ul> from .dropup'
        );
    }

    /** One bar per page. */
    #[Depends('testWikiExisting')]
    public function testContentOnItsOwnPageHasOneActionBarNotTwo(YesWikiRuntime $wiki): void
    {
        $pageManager = $wiki->services->get(PageManager::class);
        $pageContext = $wiki->services->get(PageContext::class);
        $entryController = $wiki->services->get(EntryController::class);

        $pageManager->save(self::FOOTER_PAGE, [PageBody::CONTENT => 'contenu'], '', true);
        $previousTag = $pageContext->getTag();

        try {
            $pageContext->setTag(self::FOOTER_PAGE);
            $onItsOwnPage = (string)$entryController->view(self::FOOTER_PAGE);
            $this->assertStringNotContainsString(
                'BAZ_actions_fiche',
                $onItsOwnPage,
                'the edit bar is already showing these actions on this page'
            );

            $pageContext->setTag('SomeOtherPage');
            $embedded = (string)$entryController->view(self::FOOTER_PAGE);
            $this->assertStringContainsString(
                'BAZ_actions_fiche',
                $embedded,
                'embedded in another page, this footer is the only way to act on the entry'
            );
        } finally {
            $pageContext->setTag($previousTag);
            $pageManager->deleteOrphaned(self::FOOTER_PAGE);
        }
    }

    /** `{{editbar}}` for the test page, as a visitor would receive it. */
    private function renderBar(YesWikiRuntime $wiki, string $tag): string
    {
        $pageContext = $wiki->services->get(PageContext::class);
        $previousTag = $pageContext->getTag();
        $previousPage = $pageContext->getPage();
        $pageContext->setTag($tag);
        $pageContext->setPage($wiki->services->get(PageManager::class)->getOne($tag));

        try {
            return (string)$wiki->services->get(Performer::class)->run('editbar', 'action', []);
        } finally {
            $pageContext->setTag($previousTag);
            $pageContext->setPage($previousPage);
        }
    }
}
