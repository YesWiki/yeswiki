<?php

namespace YesWiki\Test\Actions;

use PHPUnit\Framework\Attributes\Depends;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiRuntime;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Service\TripleStore;
use YesWiki\Search\Service\TagsManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Regression tests for ticket 10's rebuilt page-tag editor: the old widget dumped every tag in the wiki into a client-side JS array (bootstrap-tagsinput's typeahead source) on every page edit; the new one is a live-search htmx widget that queries GET /api/tags instead, so it should never carry the full tag list.
 */
class TagsWidgetTest extends YesWikiTestCase
{
    private const PAGE_TAG = 'TagsWidgetRegressionPage';

    public function testWikiExisting(): YesWikiRuntime
    {
        $wiki = $this->getWiki();
        $this->assertTrue($wiki->services->has(YesWikiRuntime::class));

        return $wiki->services->get(YesWikiRuntime::class);
    }

    #[Depends('testWikiExisting')]
    public function testEditWidgetShowsExistingTagsWithoutDumpingWholeVocabulary(YesWikiRuntime $wiki): void
    {
        $pageManager = $wiki->services->get(PageManager::class);
        $tripleStore = $wiki->services->get(TripleStore::class);
        $authenticationService = $wiki->services->get(AuthenticationService::class);
        $userManager = $wiki->services->get(UserManager::class);

        $pageManager->save(self::PAGE_TAG, [PageBody::CONTENT => 'body content'], '', true);
        $wiki->services->get(TagsManager::class)->save(self::PAGE_TAG, 'widgettesttag');

        $tripleStore->create('TagsWidgetRegressionOtherPage', TagsManager::TAG_PROPERTY, 'unrelatedtag', '', '');

        $admin = current(array_filter($userManager->getAll(), fn ($u) => $wiki->services->get(\YesWiki\Identity\Service\AclService::class)->isAdmin($u['name'])));
        $this->assertNotFalse($admin, 'need an existing admin user to exercise write access');
        $authenticationService->login($admin);

        $wiki->services->get(\YesWiki\Kernel\Service\PageContext::class)->setTag(self::PAGE_TAG);
        $wiki->services->get(\YesWiki\Kernel\Service\PageContext::class)->setPage($pageManager->getOne(self::PAGE_TAG));
        $wiki->services->get(PageManager::class)->getOne(self::PAGE_TAG);

        $runtimeConfig = $wiki->services->get(\YesWiki\Kernel\Service\RuntimeConfig::class);
        $previousDebug = $runtimeConfig['debug'] ?? null;
        $runtimeConfig['debug'] = false;

        try {
            $output = $wiki->services->get(\YesWiki\Render\Service\Performer::class)->run('edit', 'handler', []);

            $this->assertStringContainsString('data-yw-tag-input', $output, 'the live-search widget root is missing');
            $this->assertStringContainsString('data-tag="widgettesttag"', $output, 'this page\'s own tag must render as a chip');
            $this->assertStringNotContainsString('unrelatedtag', $output, 'the widget must not dump unrelated pages\' tags');
            $this->assertStringNotContainsString('bootstrap-tagsinput', $output);
            $this->assertStringContainsString('yw-tags-input.js', $output);
            $this->assertMatchesRegularExpression('/hx-get="[^"]*api\/tags"/', $output, 'the search input must query GET /api/tags via htmx');
        } finally {
            $runtimeConfig['debug'] = $previousDebug;
            $tripleStore->delete(self::PAGE_TAG, TagsManager::TAG_PROPERTY, null, '', '');
            $tripleStore->delete('TagsWidgetRegressionOtherPage', TagsManager::TAG_PROPERTY, null, '', '');
            $pageManager->deleteOrphaned(self::PAGE_TAG);
            $authenticationService->logout();
        }
    }
}
