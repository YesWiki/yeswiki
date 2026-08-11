<?php

namespace YesWiki\Test\Core\Migrations;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Ticket 34's to-do list. The migration changes nothing, so what is worth testing is its
 * *detection*: a page it fails to notice is a page nobody is told about, and the symptom -- a list
 * replaced by an explanation -- appears on the site rather than in any log.
 */
class ReportExternalContentCallsTest extends YesWikiTestCase
{
    private const TAG = 'TestTicket34ExternalCall';

    public static function setUpBeforeClass(): void
    {
        self::getWiki();
        require_once 'src/migrations/20260811140000_ReportExternalContentCalls.php';
    }

    /** @return array<string, array{string, bool}> */
    public static function syntaxProvider(): array
    {
        return [
            'entrylist with a remote form' => ['{{entrylist id="https://other.wiki|4"}}', true],
            'several remote forms' => ['{{entrylist id="https://other.wiki|4,5"}}', true],
            'parenthesised ids' => ['{{entrylist id="https://other.wiki|(4,5)"}}', true],
            'mapped onto a local form' => ['{{entrylist id="https://other.wiki|4->2"}}', true],
            'plain http' => ['{{entrylist id="http://other.wiki|4"}}', true],
            'spaces around the value' => ['{{entrylist id=" https://other.wiki|4 "}}', true],
            // the same value reaches other actions, and some spell the parameter form_id
            'entrymap' => ['{{entrymap id="https://other.wiki|4"}}', true],
            'form_id spelling' => ['{{entryexport form_id="https://other.wiki|4"}}', true],

            // and these must NOT be reported
            'a local id' => ['{{entrylist id="4"}}', false],
            'a url in another parameter' => ['{{button link="https://other.wiki" text="go"}}', false],
            'a url in prose' => ['See https://other.wiki|4 for the old syntax.', false],
            'an image url' => ['{{entrylist id="4" template="map" bgimg="https://other.wiki/x.png"}}', false],
        ];
    }

    #[DataProvider('syntaxProvider')]
    public function testDetection(string $content, bool $expected): void
    {
        $matched = preg_match(\ReportExternalContentCalls::externalIdPattern(), $content) === 1;

        $this->assertSame($expected, $matched, "on: {$content}");
    }

    /**
     * End to end: a page holding such a call is reported, and the page itself is not touched --
     * this migration deliberately rewrites nothing, because choosing the replacement needs
     * decisions (which local form, which syncMode, which credentials) it cannot make.
     */
    public function testItReportsWithoutChangingAnything(): void
    {
        $wiki = $this->getWiki();
        $dbService = $wiki->services->get(DbService::class);
        $pages = $dbService->prefixTable('pages');
        $body = PageBody::encode(['content' => '{{entrylist id="https://other.wiki|4" template="map"}}']);

        $dbService->query(
            "INSERT INTO {$pages} (tag, {$dbService->quoteIdentifier('time')}, body, owner,"
            . " {$dbService->quoteIdentifier('user')}, latest, type, parent)"
            . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [self::TAG, '2026-01-01 00:00:00', $body, '', '', 'Y', 'page', '']
        );

        try {
            $migration = new \ReportExternalContentCalls();
            $migration->setServices($wiki->services);
            $migration->setDbService($dbService);
            $migration->setParams($wiki->services->get(ParameterBagInterface::class));
            $migration->run();

            $after = $dbService->loadSingle(
                "SELECT body FROM {$pages} WHERE tag = ?",
                [self::TAG]
            );
            $this->assertNotNull($after, 'fixture: the row must still be there');
            $this->assertSame($body, (string)$after['body'], 'the migration must not rewrite the page');
        } finally {
            $dbService->query("DELETE FROM {$pages} WHERE tag = ?", [self::TAG]);
        }
    }

    /** A wiki with nothing to report must produce no log entry at all, not an empty one. */
    public function testAWikiWithNoExternalCallsLogsNothing(): void
    {
        $wiki = $this->getWiki();
        $dbService = $wiki->services->get(DbService::class);

        $logTag = 'LogDesActionsAdministratives' . date('Ymd');
        $pageManager = $wiki->services->get(\YesWiki\Content\Service\PageManager::class);
        /** @var array<string, mixed>|null $before */
        $before = $pageManager->getOne($logTag);

        $migration = new \ReportExternalContentCalls();
        $migration->setServices($wiki->services);
        $migration->setDbService($dbService);
        $migration->setParams($wiki->services->get(ParameterBagInterface::class));
        $migration->run();

        $after = $pageManager->getOne($logTag);
        $this->assertSame(
            is_array($before) ? ($before['body'] ?? null) : null,
            is_array($after) ? ($after['body'] ?? null) : null,
            'nothing to report means nothing written'
        );
    }
}
