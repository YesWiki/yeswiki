<?php

namespace YesWiki\Test\Kernel;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Kernel\Service\WikiSettings;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Replicas 04: a setting another replica saved shows on this worker's next request, not on the one after its restart. */
class AWorkerFollowsStoredSettingsTest extends YesWikiTestCase
{
    private const SETTING = 'meta_description';

    public function testAnotherReplicasSaveReachesTheNextRequest(): void
    {
        $wiki = self::getWiki();
        $settings = $wiki->services->get(WikiSettings::class);
        $stored = $settings->all();
        $was = $wiki->services->get(ParameterBagInterface::class)->get(self::SETTING);

        try {
            $settings->store([self::SETTING => 'Saved on another replica']);
            $settings->startNewRequest();
            $wiki->followStoredSettings();

            $this->assertSame('Saved on another replica', $wiki->services->get(ParameterBagInterface::class)->get(self::SETTING));
            $this->assertSame('Saved on another replica', $wiki->services->get(RuntimeConfig::class)->getValue(self::SETTING));
            $this->assertTrue($wiki->configurationChanged(), 'the worker still restarts, for what the container compiled');
        } finally {
            array_key_exists(self::SETTING, $stored)
                ? $settings->store([self::SETTING => $stored[self::SETTING]])
                : $settings->store([], [self::SETTING]);
            $settings->startNewRequest();
            $wiki->followStoredSettings();
        }

        $this->assertSame($was, $wiki->services->get(ParameterBagInterface::class)->get(self::SETTING));
    }
}
