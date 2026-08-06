<?php

namespace YesWiki\Test\Core\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use YesWiki\Content\Entity\ContentTypeSchema;
use YesWiki\Content\Service\FormManager;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Test\Core\YesWikiTestCase;
use YesWiki\Test\CountingQueriesPdo;

require_once 'tests/YesWikiTestCase.php';
require_once 'tests/CountingQueriesPdo.php';

/**
 * FormManager's narrow reads must answer exactly what the wide one would.
 *
 * `getAll()` does not read forms, it *prepares* them: per form it loads the page row,
 * normalises legacy body keys, enforces the template, re-reads every image field's default
 * off disk to base64-encode it, and instantiates every field object -- loading the list
 * behind any `liste` field as it goes. Two callers on hot paths only ever wanted a sliver
 * of that, and this fixes them in place; the risk is that a narrow query drifts from the
 * real thing, so every test here asserts the two agree on whatever this wiki holds rather
 * than on a hand-written expectation that would rot.
 *
 * `{{linkrss}}` prints a `<link rel="alternate">` per form into the document head of every
 * page, and it was calling `getAll()` to do it -- which loads each form's page row,
 * normalises its body, enforces its template, re-reads every image field's default off disk
 * to base64-encode it, and instantiates every field object (loading the list behind any
 * `liste` field as it goes). On a 14-form wiki: 29 queries and ~20 ms, on every page load,
 * to print a title attribute.
 *
 * The risk in a narrow query is that it drifts from the real thing -- a form that `getAll()`
 * filters out but this one keeps, a label read from the wrong body key. So the test does not
 * assert a hand-written expectation: it asserts the two agree, whatever this wiki holds.
 */
class FormManagerNarrowReadsTest extends YesWikiTestCase
{
    public function testItAgreesWithGetAllOnEveryFormAndItsLabel(): void
    {
        $formManager = $this->getWiki()->services->get(FormManager::class);

        // getAllLabels() first, on a cold cache: that is the path {{linkrss}} takes, and the
        // path that reads SQL rather than the shared cache
        $labels = $formManager->getAllLabels();

        $expected = [];
        foreach ($formManager->getAll() as $id => $form) {
            $expected[(int)$id] = (string)($form['label'] ?? '');
        }

        // compared as maps, not as ordered lists, and deliberately: `getAll()` is keyed by id
        // in whatever order its cache happened to fill, so anything already loaded this
        // request -- a form fetched by id, the Page form resolved for a render -- comes out
        // first. It has no order to match. `getAllLabels()` does: it is always tag order,
        // which is the one the ORDER BY names.
        ksort($expected);
        $actual = $labels;
        ksort($actual);

        $this->assertSame(
            $expected,
            $actual,
            'getAllLabels() must return the same ids and the same labels as getAll()'
        );
    }

    public function testItCostsOneQueryWhereGetAllCostsOnePerForm(): void
    {
        $wiki = $this->getWiki();
        $formManager = $wiki->services->get(FormManager::class);
        $dbService = $wiki->services->get(DbService::class);

        $formCount = count($formManager->getAllLabels());
        if ($formCount < 2) {
            $this->markTestSkipped('needs at least two forms for the difference to mean anything');
        }

        // a fresh FormManager, so the shared cache does not answer for it
        $cold = $this->coldFormManager($formManager);

        $before = $this->countQueries($dbService);
        $cold->getAllLabels();
        $labelQueries = $this->countQueries($dbService) - $before;

        $this->assertSame(
            1,
            $labelQueries,
            'getAllLabels() must be one query whatever the wiki holds -- it is in every page head'
        );
    }

    public function testItReadsTheSharedCacheWhenGetAllHasAlreadyRun(): void
    {
        $wiki = $this->getWiki();
        $formManager = $wiki->services->get(FormManager::class);
        $dbService = $wiki->services->get(DbService::class);

        // a screen that genuinely needs whole forms must not pay for a second trip
        $formManager->getAll();

        $before = $this->countQueries($dbService);
        $formManager->getAllLabels();

        $this->assertSame(
            0,
            $this->countQueries($dbService) - $before,
            'after getAll(), the labels are already in memory'
        );
    }

    public function testItsOrderIsTheWikisTagOrderWhateverWasCachedBefore(): void
    {
        $wiki = $this->getWiki();
        $formManager = $wiki->services->get(FormManager::class);
        $db = $wiki->services->get(DbService::class);

        $tagOrder = array_column(
            $db->loadAll(
                'SELECT ' . $db->jsonExtract('body', '$.id') . ' AS id FROM ' . $db->prefixTable('pages')
                . " WHERE latest = 'Y' AND " . $db->quoteIdentifier('type') . " = 'form' ORDER BY tag"
            ),
            'id'
        );
        $tagOrder = array_values(array_filter(array_map('intval', array_filter($tagOrder, 'is_numeric'))));

        $this->assertSame(
            $tagOrder,
            array_values(array_filter(array_keys($this->coldFormManager($formManager)->getAllLabels()))),
            'the labels come out in tag order, which is the one thing getAll() could not promise'
        );
    }

    /**
     * `getByContentType()` is on the render path of **every page**, through
     * ContentTypeResolver::formFor(). It used to scan a fully-prepared `getAll()` to find
     * one form.
     */
    #[DataProvider('builtInContentTypes')]
    public function testGetByContentTypeFindsTheSameFormWithoutPreparingEveryForm(string $contentType): void
    {
        $formManager = $this->getWiki()->services->get(FormManager::class);

        // the narrow branch: a manager that has never called getAll()
        $narrow = $this->coldFormManager($formManager)->getByContentType($contentType);

        // the getAll() branch, on a manager whose cache is populated
        $formManager->getAll();
        $wide = $formManager->getByContentType($contentType);

        if ($wide === null) {
            $this->assertNull($narrow, "no form describes '{$contentType}', so neither branch may invent one");

            return;
        }

        $this->assertNotNull($narrow, "the narrow read lost the '{$contentType}' form");
        $this->assertSame($wide['tag'], $narrow['tag'], 'both branches must name the same form page');
        $this->assertSame($wide['id'], $narrow['id']);
        $this->assertSame(
            $wide[ContentTypeSchema::CONTENT_TYPE] ?? null,
            $narrow[ContentTypeSchema::CONTENT_TYPE] ?? null
        );
        // the form still has to arrive prepared -- the caller renders its fields
        $this->assertArrayHasKey('prepared', $narrow, 'a form is no use to its caller unprepared');
    }

    /** @return array<string, list<string>> */
    public static function builtInContentTypes(): array
    {
        return [
            'page' => [ContentTypeSchema::TYPE_PAGE],
            'user' => [ContentTypeSchema::TYPE_USER],
            'file' => [ContentTypeSchema::TYPE_FILE],
            // an entry names its own form in body.form_id, so this one has no answer
            'entry' => [ContentTypeSchema::TYPE_ENTRY],
        ];
    }

    public function testGetByContentTypeDoesNotPrepareEveryFormInTheWiki(): void
    {
        $wiki = $this->getWiki();
        $formManager = $wiki->services->get(FormManager::class);
        $dbService = $wiki->services->get(DbService::class);

        if (count($formManager->getAllLabels()) < 3) {
            $this->markTestSkipped('needs a few forms for "one form" and "every form" to differ');
        }

        $cold = $this->coldFormManager($formManager);

        $before = $this->countQueries($dbService);
        $cold->getByContentType(ContentTypeSchema::TYPE_PAGE);
        $used = $this->countQueries($dbService) - $before;
        $statements = CountingQueriesPdo::statementsSince($before);

        // At most two -- find which page holds the form, then read that page -- and often
        // fewer, because PageManager may already hold the row. A total is the wrong thing to
        // pin: it would measure how warm the request is rather than this method.
        $this->assertLessThanOrEqual(
            2,
            $used,
            'getByContentType() must cost one form, not every form: it runs on every page render'
        );

        // and the specific waste it used to carry: routing the tag through getOne() put it
        // through resolveTag(), whose non-numeric branch asks whether the page exists --
        // about a page just selected out of `pages`, and read in full on the very next line
        foreach ($statements as $sql) {
            $this->assertDoesNotMatchRegularExpression(
                '/^SELECT 1 FROM \S*pages WHERE tag/',
                $sql,
                'a form found by querying `pages` must not then be asked whether it exists'
            );
        }
    }

    /** A FormManager with an empty form cache, sharing the wiki's services. */
    private function coldFormManager(FormManager $formManager): FormManager
    {
        $clone = clone $formManager;
        // every cache, not just the two obvious ones: `clone` copies them all, so a memo
        // left behind by an earlier test made the clone answer without querying and the
        // counts below measured nothing
        (new \ReflectionProperty($clone, 'cachedForms'))->setValue($clone, []);
        (new \ReflectionProperty($clone, 'cacheValidatedForAll'))->setValue($clone, false);
        (new \ReflectionProperty($clone, 'cachedContentTypeTags'))->setValue($clone, []);

        return $clone;
    }

    /**
     * Counts statements by swapping a counting PDO into DbService. Its own read cache is
     * cleared first, so a repeated query is really re-issued rather than answered from
     * memory -- otherwise this measures the cache, not the query count.
     */
    private function countQueries(DbService $dbService): int
    {
        $count = CountingQueriesPdo::countFor($dbService);
        if ($count === null) {
            $this->markTestSkipped('the counting PDO is wired for the suite\'s sqlite database');
        }

        return $count;
    }
}
