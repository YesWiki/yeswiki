<?php

namespace YesWiki\Test\Core\Service;

use PHPUnit\Framework\Attributes\CoversMethod;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Core\YesWikiRuntime;
use YesWiki\Kernel\Service\ConfigurationService;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Render\Service\HibernationNotice;
use YesWiki\Render\Service\TemplateEngine;
use YesWiki\Test\Core\ForcedParameterBag;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';
require_once 'tests/ForcedParameterBag.php';

/**
 * Regression tests for ticket 15 (security-core-split): HibernationService is the new, standalone home for the hibernation check formerly bundled inside SecurityController (renamed to InputFilter by wave-two ticket 03).
 */
#[CoversMethod(HibernationService::class, 'isWikiHibernated')]
#[CoversMethod(HibernationService::class, 'isWikiHibernatedNow')]
#[CoversMethod(HibernationNotice::class, 'getMessageWhenHibernated')]
class HibernationServiceTest extends YesWikiTestCase
{
    private function buildService(YesWikiRuntime $wiki, string $wikiStatus): HibernationService
    {
        $realParams = $wiki->services->get(ParameterBagInterface::class);
        $forcedParams = new ForcedParameterBag($realParams, ['wiki_status' => $wikiStatus]);

        return new HibernationService($forcedParams);
    }

    public function testIsWikiHibernatedForEachStatus(): void
    {
        $wiki = $this->getWiki();

        foreach (['hibernate', 'archiving', 'updating'] as $status) {
            $this->assertTrue($this->buildService($wiki, $status)->isWikiHibernated(), "wiki_status '{$status}' must be considered hibernated");
        }
        foreach (['running', '', 'unknown'] as $status) {
            $this->assertFalse($this->buildService($wiki, $status)->isWikiHibernated(), "wiki_status '{$status}' must not be considered hibernated");
        }
    }

    public function testGetMessageWhenHibernatedRendersHibernationNotice(): void
    {
        $wiki = $this->getWiki();

        $templateEngine = $wiki->services->get(TemplateEngine::class);
        $this->assertInstanceOf(TemplateEngine::class, $templateEngine);

        $notice = new HibernationNotice($this->buildService($wiki, 'hibernate'), $templateEngine);

        $this->assertStringContainsString(_t('WIKI_IN_HIBERNATION'), $notice->getMessageWhenHibernated());
    }

    /** The queue consumer runs for minutes or hours: it has to see the status an archive or an update writes to the config file meanwhile, not the one the container was built with. */
    public function testTheLiveStatusIsReadFromTheConfigFile(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'yw-config-') . '.php';
        $previous = getenv('YESWIKI_CONFIG_FILE');
        putenv('YESWIKI_CONFIG_FILE=' . $file);

        try {
            $service = $this->buildService($this->getWiki(), 'running');
            $configuration = new ConfigurationService();
            $config = $configuration->getConfiguration($file);
            $config['wiki_status'] = 'archiving';
            $this->assertTrue($configuration->write($config));

            $this->assertFalse($service->isWikiHibernated(), 'the parameters are what the request started with');
            $this->assertTrue($service->isWikiHibernatedNow());

            unset($config['wiki_status']);
            $this->assertTrue($configuration->write($config));
            $this->assertFalse($service->isWikiHibernatedNow());
        } finally {
            putenv($previous === false ? 'YESWIKI_CONFIG_FILE' : 'YESWIKI_CONFIG_FILE=' . $previous);
            @unlink($file);
        }
    }

    public function testHibernationServiceDependsOnNothingButParameters(): void
    {
        $ctor = (new \ReflectionClass(HibernationService::class))->getConstructor();
        $this->assertNotNull($ctor);
        $types = array_map(
            static fn (\ReflectionParameter $p): string => (string)$p->getType(),
            $ctor->getParameters()
        );

        $this->assertSame([ParameterBagInterface::class], $types);
    }
}
