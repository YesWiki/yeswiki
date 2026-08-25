<?php

namespace YesWiki\Test\Content;

use PHPUnit\Framework\Attributes\Depends;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Core\YesWikiRuntime;
use YesWiki\Files\Service\Storage;
use YesWiki\Import\Service\SyncScheduler;
use YesWiki\Test\Core\ForcedParameterBag;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';
require_once 'tests/ForcedParameterBag.php';

/** Which data sources the wiki's housekeeping imports, and when. */
class SyncSchedulerTest extends YesWikiTestCase
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
    public function testOnlyFlaggedSourcesAreDue(YesWikiRuntime $wiki): void
    {
        $this->forgetPreviousRuns($wiki);
        $due = $this->scheduler($wiki)->claimDueSources();

        $this->assertSame(['every_time', 'once_a_day'], array_keys($due));

        $this->assertSame('Rss', $due['every_time']['importer']);
    }

    #[Depends('testWikiExisting')]
    public function testAClaimedSourceIsNotClaimedTwice(YesWikiRuntime $wiki): void
    {
        $this->forgetPreviousRuns($wiki);
        $startedAt = time();
        $this->scheduler($wiki)->claimDueSources($startedAt);

        $this->assertSame([], $this->scheduler($wiki)->claimDueSources($startedAt));
    }

    #[Depends('testWikiExisting')]
    public function testAPerSourceIntervalHoldsASourceBack(YesWikiRuntime $wiki): void
    {
        $this->forgetPreviousRuns($wiki);
        $scheduler = $this->scheduler($wiki);
        $scheduler->claimDueSources();

        $this->ageStateFile($wiki, 'every_time', 3600);
        $this->ageStateFile($wiki, 'once_a_day', 3600);

        $this->assertSame(['every_time'], array_keys($this->scheduler($wiki)->claimDueSources()));
    }

    #[Depends('testWikiExisting')]
    public function testARunIsRecordedAndReadBack(YesWikiRuntime $wiki): void
    {
        $this->forgetPreviousRuns($wiki);
        $scheduler = $this->scheduler($wiki);

        $this->assertNull($scheduler->getLastSync('every_time'), 'a source that never ran has no last sync');

        $scheduler->recordRun('every_time', "Entrée \"Fête\" mise à jour : bf_description.\n2 entrée(s) déjà à jour.");
        $last = $scheduler->getLastSync('every_time');

        $this->assertNotNull($last);
        $this->assertStringContainsString('bf_description', $last['output']);
        $this->assertLessThanOrEqual(5, abs($last['time'] - time()), 'the recorded time is when it ran');
    }

    #[Depends('testWikiExisting')]
    public function testASourceKeyCannotEscapeTheStateDirectory(YesWikiRuntime $wiki): void
    {
        $scheduler = $this->schedulerFor($wiki, ['../../evil' => ['importer' => 'Rss', 'syncOnMaintenance' => true]]);
        $scheduler->recordRun('../../evil', 'nope');

        $this->assertFileDoesNotExist('evil.log');
        $this->assertFileExists($this->stateDir($wiki) . '/.._.._evil.log');
        @unlink($this->stateDir($wiki) . '/.._.._evil.log');
    }

    private function scheduler(YesWikiRuntime $wiki): SyncScheduler
    {
        return $this->schedulerFor($wiki, self::SOURCES);
    }

    /**
     * @param array<string, array<string, mixed>> $dataSources
     */
    private function schedulerFor(YesWikiRuntime $wiki, array $dataSources): SyncScheduler
    {
        return new SyncScheduler(
            new ForcedParameterBag($wiki->services->get(ParameterBagInterface::class), ['dataSources' => $dataSources]),
            $wiki->services,
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

    /** Pretend this source's last run happened $seconds ago. */
    private function ageStateFile(YesWikiRuntime $wiki, string $id, int $seconds): void
    {
        $file = $this->stateDir($wiki) . '/' . $id . '.log';
        if (is_file($file)) {
            touch($file, time() - $seconds);
        }
    }
}
