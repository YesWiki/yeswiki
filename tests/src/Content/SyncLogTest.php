<?php

namespace YesWiki\Test\Content;

use PHPUnit\Framework\Attributes\Depends;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Core\YesWikiRuntime;
use YesWiki\Files\Service\Storage;
use YesWiki\Import\Service\SyncLog;
use YesWiki\Test\Core\ForcedParameterBag;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';
require_once 'tests/ForcedParameterBag.php';

/** What the last sync of a data source left behind, for the importers page; the schedule does not read it. */
class SyncLogTest extends YesWikiTestCase
{
    private const SOURCES = [
        'every_time' => ['importer' => 'Rss', 'syncOnMaintenance' => true],
        'once_a_day' => ['importer' => 'Rss', 'syncOnMaintenance' => true, 'syncIntervalInMin' => 1440],
        'on_demand' => ['importer' => 'Rss'],
    ];

    public function testWikiExisting(): YesWikiRuntime
    {
        $wiki = $this->getWiki();
        $this->assertTrue($wiki->services->has(YesWikiRuntime::class));

        return $wiki->services->get(YesWikiRuntime::class);
    }

    #[Depends('testWikiExisting')]
    public function testARunIsRecordedAndReadBack(YesWikiRuntime $wiki): void
    {
        $this->forgetPreviousRuns($wiki);
        $log = $this->log($wiki);

        $this->assertNull($log->getLastSync('every_time'), 'a source that never ran has no last sync');

        $log->recordRun('every_time', "Entrée \"Fête\" mise à jour : bf_description.\n2 entrée(s) déjà à jour.");
        $last = $log->getLastSync('every_time');

        $this->assertNotNull($last);
        $this->assertStringContainsString('bf_description', $last['output']);
        $this->assertLessThanOrEqual(5, abs($last['time'] - time()), 'the recorded time is when it ran');
    }

    #[Depends('testWikiExisting')]
    public function testASourceKeyCannotEscapeTheStateDirectory(YesWikiRuntime $wiki): void
    {
        $log = $this->logFor($wiki, ['../../evil' => ['importer' => 'Rss', 'syncOnMaintenance' => true]]);
        $log->recordRun('../../evil', 'nope');

        $this->assertFileDoesNotExist('evil.log');
        $this->assertFileExists($this->stateDir($wiki) . '/.._.._evil.log');
        @unlink($this->stateDir($wiki) . '/.._.._evil.log');
    }

    private function log(YesWikiRuntime $wiki): SyncLog
    {
        return $this->logFor($wiki, self::SOURCES);
    }

    /**
     * @param array<string, array<string, mixed>> $dataSources
     */
    private function logFor(YesWikiRuntime $wiki, array $dataSources): SyncLog
    {
        return new SyncLog(
            new ForcedParameterBag($wiki->services->get(ParameterBagInterface::class), ['dataSources' => $dataSources]),
            $wiki->services->get(Storage::class)
        );
    }

    private function stateDir(YesWikiRuntime $wiki): string
    {
        $attachConfig = $wiki->services->get(ParameterBagInterface::class)->get('attach_config');
        $cachePath = is_array($attachConfig) ? ($attachConfig['cache_path'] ?? 'cache') : 'cache';

        return rtrim((string)$cachePath, '/') . '/importer';
    }

    private function forgetPreviousRuns(YesWikiRuntime $wiki): void
    {
        foreach (array_keys(self::SOURCES) as $id) {
            @unlink($this->stateDir($wiki) . '/' . $id . '.log');
        }
    }
}
