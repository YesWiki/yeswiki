<?php

namespace YesWiki\Test\Search;

use PHPUnit\Framework\Attributes\DataProvider;
use YesWiki\Content\Entity\ContentTypeSchema;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Search\Service\SearchManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** A query on an entry's own properties finds an account, a page or a file the way it finds a bazar entry. */
class BuiltInContentQueryTest extends YesWikiTestCase
{
    private const NAME = 'BuiltInContentQueryTestUser';

    protected function setUp(): void
    {
        parent::setUp();
        $this->getWiki()->services->get(UserManager::class)->create(self::NAME, 'bicqt@example.tld', 'Aa1!aaaaRegression');
    }

    protected function tearDown(): void
    {
        $services = $this->getWiki()->services;
        $userManager = $services->get(UserManager::class);
        $user = $userManager->getOneByName(self::NAME);
        if ($user) {
            $userManager->delete($user);
        }
        $services->get(PageManager::class)->deleteOrphaned(self::NAME);
        parent::tearDown();
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function typeAndProperty(): array
    {
        $cases = [];
        foreach ([ContentTypeSchema::TYPE_USER, ContentTypeSchema::TYPE_PAGE, ContentTypeSchema::TYPE_FILE] as $type) {
            foreach (['tag', 'form_id', 'title', 'owner', 'status', 'created_at', 'updated_at'] as $property) {
                $cases["$type $property"] = [$type, $property];
            }
        }
        $cases['user username'] = [ContentTypeSchema::TYPE_USER, 'username'];

        return $cases;
    }

    #[DataProvider('typeAndProperty')]
    public function testAPropertyFindsTheRowItDescribes(string $type, string $property): void
    {
        $services = $this->getWiki()->services;
        $form = $services->get(FormManager::class)->getByContentType($type);
        $this->assertNotNull($form, "the $type form should exist -- run ./yeswicli migrate");
        $searchManager = $services->get(SearchManager::class);

        $rows = $searchManager->search(['formsIds' => [$form['id']]]);
        if ($rows === []) {
            $this->markTestSkipped("no $type in the test wiki");
        }
        if (in_array($property, ['tag', 'form_id', 'title', 'status', 'created_at', 'updated_at'], true)) {
            foreach ($rows as $row) {
                $this->assertNotSame('', (string)($row[$property] ?? ''), "every $type has a $property, as an entry does");
            }
        }
        $withValue = array_filter($rows, fn (array $row) => (string)($row[$property] ?? '') !== '');
        if ($withValue === []) {
            $this->markTestSkipped("no $type with a $property in the test wiki");
        }
        $row = $withValue[self::NAME] ?? reset($withValue);
        $value = (string)$row[$property];

        $found = $searchManager->search([
            'formsIds' => [$form['id']],
            'queries' => [$property => $value],
        ]);

        $this->assertArrayHasKey($row['tag'], $found, "$property=$value finds the $type it was read from");
    }
}
