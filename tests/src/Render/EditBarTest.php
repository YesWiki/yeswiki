<?php

namespace YesWiki\Test\Render;

use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Depends;
use YesWiki\Content\Controller\EntryController;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\PageManager;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\Performer;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Render\Service\TemplateEngine;
use YesWiki\Test\Core\YesWikiTestCase;
use YesWiki\YesWikiRuntime;

require_once 'tests/YesWikiTestCase.php';

/**
 * `{{editbar}}`: the actions in the corner, the facts at the bottom.
 *
 * Three things here are only visible on a rendered page, which is why they went unnoticed.
 * Ticket 10 made pages render through the Page form, so every page grew the bazar entry
 * footer *on top of* its edit bar -- two strips of buttons saying overlapping things. That
 * footer prints `entry.created_at|date(…)`, and Twig's date filter on a key the Content
 * does not have is **now**, so every page claimed to have been created and updated the
 * second you looked at it. And the cluster is an offer to edit: showing it to someone who
 * cannot is an offer that fails on the click.
 */
class EditBarTest extends YesWikiTestCase
{
    /**
     * One page per test, rather than one for the class: `AclService` remembers its answer
     * for a tag, so a page one test closes to `@admins` is still closed for the next one
     * that saves a fresh page under the same name -- which renders no bar at all, and an
     * assertion that something is *absent* from an empty string passes for the wrong
     * reason. (It did: the dates below were being checked against nothing.).
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
        // spelled out rather than left to the wiki's default_write_acl, which a test
        // should not have to guess at
        $wiki->services->get(AclService::class)->save(self::CLUSTER_PAGE, 'write', '*');

        try {
            $writable = $this->renderBar($wiki, self::CLUSTER_PAGE);
            $this->assertStringContainsString('yw-page-actions', $writable, 'an editor gets the cluster');
            $this->assertStringContainsString('yw-page-info', $writable, 'and the facts at the bottom');
            // sharing and the star are verbs, so they are in the cluster with the others --
            // not left behind in the line of facts
            $this->assertStringContainsString('yw-page-actions__more', $writable);
            $this->assertStringNotContainsString('yw-page-info__actions', $writable);

            // the same page, closed to everyone but the admins
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

    /**
     * ...but a signed-in reader still gets the two things a reader wants.
     *
     * They have no cluster to put them in, so sharing and the star stay in the line at the
     * bottom for them -- the one case where that line carries a verb.
     */
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
        $this->assertNotEmpty($reader);

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

    /**
     * Neither bar says what the Content does not store.
     *
     * A page keeps no `created_at`. The honest answer is to say nothing about when it was
     * created -- not to print today, which is what `null|date(…)` renders to and what a
     * page five years old was therefore claiming about itself.
     */
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

            // a page still has a creation date -- the date of its first revision, which the
            // `pages` table has always held. Derived, not invented.
            $createdAt = $pageManager->getCreateTime(self::DATES_PAGE);
            $this->assertNotNull($createdAt);
            $expected = Carbon::parse($createdAt);
            $expected->locale((string)($GLOBALS['prefered_language'] ?? 'en'));
            $this->assertStringContainsString(
                $expected->isoFormat('LLL'),
                $rendered,
                "a page's creation date is the date of its first revision, written in the reader's language"
            );

            // and the entry footer, which is where the dates were being made up, for the
            // one case that still renders it
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
     * On an edit screen the corner belongs to the editor: save, preview when this wiki
     * asks for one, delete. Not "edit this page", which is where you already are.
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
        // the setting itself, not a stand-in: render() re-reads RuntimeConfig on every
        // call and overwrites any `config` a caller passes, so this is the only way in
        $before = $config['preview_before_save'] ?? false;

        $editor = fn () => (string)$engine->render('@core/handlers/edit.twig', [
            'previous' => '1',
            'handler' => 'edit',
            'cancelUrl' => '/',
            'body' => '',
            'saveValue' => 'Sauver',
            'deleteUrl' => null,
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

            // leaving is in the cluster too, and as a real link: it was an <a> with no
            // href driving `document.location` from an onclick, which the keyboard cannot
            // reach -- and the way out of an editor has to be reachable
            $this->assertMatchesRegularExpression(
                '/<a href="[^"]+"[^>]*class="[^"]*link-cancel/',
                $withoutPreview
            );
        } finally {
            $config['preview_before_save'] = $before;
        }
    }

    /**
     * The line of facts holds a dropdown, so it cannot be a paragraph.
     *
     * A `<ul>` inside a `<p>` is not a thing an HTML parser will build: it closes the
     * paragraph first, which put the comments menu outside the `.dropup` its toggle looks
     * in -- clicking "open the comments" did nothing -- and left a stray empty `<p>`
     * behind. Nothing in PHP notices: the template's own output is well formed, and
     * libxml's parser (unlike a browser's) keeps the `<ul>` where it was written. So this
     * pins the markup rule, and tests/e2e/tests/editbar.spec.ts pins the behaviour in a
     * browser that really does reparent it.
     */
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
                // the dropdown this is all about
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

    /**
     * One bar per page. The entry footer keeps its buttons for the case it is the only
     * thing there -- an entry rendered inside some *other* page.
     */
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
