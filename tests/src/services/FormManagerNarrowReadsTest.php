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

/** FormManager's narrow reads must answer exactly what the wide one would. */
class FormManagerNarrowReadsTest extends YesWikiTestCase
{
    public function testItAgreesWithGetAllOnEveryFormAndItsLabel(): void
    {
        $formManager = $this->getWiki()->services->get(FormManager::class);

        $labels = $formManager->getAllLabels();

        $expected = [];
        foreach ($formManager->getAll() as $id => $form) {
            $expected[(int)$id] = (string)($form['label'] ?? '');
        }

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
     * `getByContentType()` is on the render path of **every page**, through ContentTypeResolver::formFor().
     */
    #[DataProvider('builtInContentTypes')]
    public function testGetByContentTypeFindsTheSameFormWithoutPreparingEveryForm(string $contentType): void
    {
        $formManager = $this->getWiki()->services->get(FormManager::class);

        $narrow = $this->coldFormManager($formManager)->getByContentType($contentType);

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

        $this->assertArrayHasKey('prepared', $narrow, 'a form is no use to its caller unprepared');
    }

    /**
     * @return array<string, list<string>>
     */
    public static function builtInContentTypes(): array
    {
        return [
            'page' => [ContentTypeSchema::TYPE_PAGE],
            'user' => [ContentTypeSchema::TYPE_USER],
            'file' => [ContentTypeSchema::TYPE_FILE],

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

        $this->assertLessThanOrEqual(
            2,
            $used,
            'getByContentType() must cost one form, not every form: it runs on every page render'
        );

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

        (new \ReflectionProperty($clone, 'cachedForms'))->setValue($clone, []);
        (new \ReflectionProperty($clone, 'cacheValidatedForAll'))->setValue($clone, false);
        (new \ReflectionProperty($clone, 'cachedContentTypeTags'))->setValue($clone, []);

        return $clone;
    }

    /** Counts statements by swapping a counting PDO into DbService. */
    private function countQueries(DbService $dbService): int
    {
        $count = CountingQueriesPdo::countFor($dbService);
        if ($count === null) {
            $this->markTestSkipped('the counting PDO is wired for the suite\'s sqlite database');
        }

        return $count;
    }
}
