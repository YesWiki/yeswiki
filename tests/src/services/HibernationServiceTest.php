<?php

namespace YesWiki\Test\Core\Service;

use PHPUnit\Framework\Attributes\CoversMethod;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
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
#[CoversMethod(HibernationNotice::class, 'getMessageWhenHibernated')]
class HibernationServiceTest extends YesWikiTestCase
{
    private function buildService($wiki, string $wikiStatus): HibernationService
    {
        $realParams = $wiki->services->get(ParameterBagInterface::class);
        $forcedParams = new ForcedParameterBag($realParams, ['wiki_status' => $wikiStatus]);

        return new HibernationService($forcedParams);
    }

    public function testIsWikiHibernatedForEachStatus()
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
