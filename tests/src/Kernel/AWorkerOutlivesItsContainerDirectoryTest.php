<?php

namespace YesWiki\Test\Kernel;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use YesWiki\Admin\Scheduler\AdminSchedule;
use YesWiki\Core\YesWikiRuntime;
use YesWiki\Kernel\Service\ConfigurationSecrets;
use YesWiki\Kernel\Service\JournalSchema;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** A booted worker keeps building services after another process pruned or cleared the directory its container was dumped to (ADR-0024). */
class AWorkerOutlivesItsContainerDirectoryTest extends YesWikiTestCase
{
    public function testAContainerWhoseDirectoryWentStillBuildsTheServicesItHadNotBuiltYet(): void
    {
        $booted = self::getWiki()->services;
        $compiled = $booted::class;
        $pruned = new $compiled([], sys_get_temp_dir() . '/a-container-directory-another-process-pruned');
        foreach ([ParameterBagInterface::class, CsrfTokenManager::class, YesWikiRuntime::class, ConfigurationSecrets::class] as $synthetic) {
            $pruned->set($synthetic, $booted->get($synthetic));
        }

        $this->assertInstanceOf(AdminSchedule::class, $pruned->get(AdminSchedule::class));
        $this->assertInstanceOf(JournalSchema::class, $pruned->get(JournalSchema::class));
    }
}
