<?php

namespace YesWiki\Test\Content;

use YesWiki\Content\Entity\ContributesEntryFields;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\EntryExtraFieldsService;
use YesWiki\Content\Service\FieldFactory;
use YesWiki\Content\Service\PageManager;
use YesWiki\Content\Service\PageViewAppendices;
use YesWiki\Identity\Service\AclService;
use YesWiki\Social\Field\ReactionsField;
use YesWiki\Social\Service\AppendsCommentsToPageView;
use YesWiki\Social\Service\ContributesSocialEntryFields;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * The three seams ticket 39 put between `Content` and `Social` (ADR-0019).
 *
 * All three fail *silently* when the wiring is wrong, which is why they are worth a test each:
 *
 * - an untagged field contributor makes `comments` and `reactions` read as `null`, and a
 *   template rendering nothing looks like an entry with no comments;
 * - an untagged page-view appendix removes the comment box from every page, and a page with no
 *   comment form looks like a page with comments turned off;
 * - a field type in a directory nothing scans simply is not offered, and a form that once had
 *   a reactions field renders it as an unknown type.
 *
 * Before the split each of these was a direct call, and a mistake was a fatal error.
 */
class SocialSeamsTest extends YesWikiTestCase
{
    public function testSocialContributesTheEntryFieldsContentNoLongerAnswers(): void
    {
        $contributor = self::getWiki()->services->get(ContributesSocialEntryFields::class);

        $this->assertInstanceOf(ContributesEntryFields::class, $contributor);
        $this->assertSame(
            ['comments', 'comments_count', 'reactions', 'reactions_count'],
            $contributor->contributedFieldNames()
        );
    }

    /** The contributor has to be reachable through the service that consumes it, not just exist. */
    public function testTheExtraFieldsServiceAsksTheContributors(): void
    {
        $service = self::getWiki()->services->get(EntryExtraFieldsService::class);
        $contributors = (new \ReflectionClass($service))->getProperty('contributors')->getValue($service);

        $classes = array_map(static fn (object $c) => $c::class, [...$contributors]);
        $this->assertContains(
            ContributesSocialEntryFields::class,
            $classes,
            'EntryExtraFieldsService got no social contributor: comments and reactions read as null'
        );
    }

    /**
     * The appendix does not merely exist -- it produces the comment box.
     *
     * Asserted here rather than in the browser suite because the box only appears once a page's
     * comment ACL is open, and a fresh wiki's `default_comment_acl` is `comments-closed`. From
     * PHP that is one `AclService::save()`; through the UI it is a drop-up whose first item I
     * could not make fire, and a test that fails for a reason unrelated to its subject is worse
     * than no test.
     */
    public function testTheAppendixRendersTheCommentBox(): void
    {
        $wiki = self::getWiki();
        $tag = 'SocialSeamsCommentPage';
        $wiki->services->get(PageManager::class)->save($tag, [PageBody::CONTENT => 'a page'], '', true);
        $wiki->services->get(AclService::class)->save($tag, 'comment', '@admins');

        $html = '';
        foreach ($wiki->services->get(PageViewAppendices::class)->all() as $appendix) {
            $html .= $appendix->appendToPageView($tag);
        }

        $this->assertStringContainsString(
            'yeswiki-page-comments',
            $html,
            'the page view gains no comment area, so ShowHandler renders none'
        );
    }

    public function testTheCommentBoxIsAPageViewAppendix(): void
    {
        $appendices = [...self::getWiki()->services->get(PageViewAppendices::class)->all()];

        $this->assertNotEmpty($appendices, 'nothing appends to a page view: the comment box is gone');
        $this->assertContains(
            AppendsCommentsToPageView::class,
            array_map(static fn (object $a) => $a::class, $appendices)
        );
    }

    /**
     * `ReactionsField` lives in `Social/Field` now, and is found only because `FieldFactory`
     * globs every module's `Field` directory rather than naming `Content`. Its own docblock
     * records that this scan once pointed at a directory that no longer existed and silently
     * found nothing, which is exactly the failure this asserts against.
     */
    public function testAFieldTypeIsFoundOutsideContent(): void
    {
        // asked of the registry rather than by building one: an unknown type yields an empty
        // attribute map, and a half-specified field emits deprecations that say nothing about
        // discovery
        $map = self::getWiki()->services->get(FieldFactory::class)->getAttributeIndexToKeyMap('reactions');

        $this->assertNotEmpty(
            $map,
            'the reactions field type is not discovered, so a form using it renders nothing'
        );
        $this->assertSame(
            ReactionsField::class,
            (new \ReflectionClass(ReactionsField::class))->getName(),
            'and it is Social\'s, not Content\'s'
        );
    }
}
